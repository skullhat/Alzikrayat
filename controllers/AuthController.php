<?php
declare(strict_types=1);

/**
 * Class AuthController
 *
 * Handles registration, login, and logout.
 *
 * Security steps in this controller:
 * - CSRF token check on every POST form.
 * - Session id is regenerated after login (stops session fixation).
 * - Logout clears and destroys the session and its cookie.
 * - The "lastLoginDate" cookie records the time of the last successful
 *   login from this browser for 7 days. It is independent of the session,
 *   so logout does not remove it.
 */
class AuthController extends Controller
{
    /** Name of the cookie that stores the last login time. */
    private const LAST_LOGIN_COOKIE = 'lastLoginDate';

    /** Cookie lifetime: 7 days in seconds. */
    private const LAST_LOGIN_LIFETIME = 7 * 24 * 60 * 60;

    /** Registration fields read from the form. */
    private const REGISTRATION_FIELDS = [
        'firstName', 'lastName', 'email', 'password', 'passwordConfirm',
        'location', 'occupation', 'description',
    ];

    /**
     * Shows the login form and the last login time from the cookie.
     *
     * Route: GET /login (optional query: ?returnTo=/photo/5)
     *
     * @return void
     */
    public function showLogin(): void
    {
        $this->redirectIfLoggedIn();

        // Links such as "/login?returnTo=/photo/5" bring the user back after login.
        $requestedReturnPath = $_GET['returnTo'] ?? '';
        if (is_string($requestedReturnPath) && $this->isSafeInternalPath($requestedReturnPath)) {
            $_SESSION['returnPath'] = $requestedReturnPath;
        }

        $prefilledEmail = $_SESSION['prefillEmail'] ?? '';
        unset($_SESSION['prefillEmail']);

        $this->renderLoginForm(is_string($prefilledEmail) ? $prefilledEmail : '', []);
    }

    /**
     * Checks the login form. On success: starts the user session, sets the
     * lastLoginDate cookie, and redirects.
     *
     * Route: POST /login
     *
     * @return void
     * @throws PDOException When the database query fails.
     */
    public function login(): void
    {
        $this->redirectIfLoggedIn();
        $this->requireValidCsrfToken('/login');

        $email = $this->getPostString('email');
        // Passwords are not trimmed: spaces can be part of a password.
        $plainPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $validationErrors = [];
        if (!User::isValidEmailFormat($email)) {
            $validationErrors['email'] = 'Please enter a valid email address.';
        }
        if ($plainPassword === '') {
            $validationErrors['password'] = 'Password is required.';
        }

        if ($validationErrors === []) {
            $userRow = (new User())->findByCredentials($email, $plainPassword);

            if ($userRow !== null) {
                $this->startUserSession((int) $userRow['id'], (string) $userRow['first_name']);
                $this->saveLastLoginCookie();
                $this->setFlashMessage('Welcome back, ' . $userRow['first_name'] . '!', 'success');
                $this->redirect($this->takeReturnPath('/'));
            }

            // Same message for a wrong email and a wrong password.
            $validationErrors['form'] = 'Email or password is incorrect.';
        }

        http_response_code(422);
        $this->renderLoginForm($email, $validationErrors);
    }

    /**
     * Shows the registration form.
     *
     * Route: GET /register
     *
     * @return void
     */
    public function showRegister(): void
    {
        $this->redirectIfLoggedIn();

        $this->render('auth/register', [
            'oldInput'         => [],
            'validationErrors' => [],
        ], 'Create an account - Alzikrayat');
    }

    /**
     * Validates and saves a new account, then sends the user to the login page.
     *
     * Route: POST /register
     *
     * @return void
     * @throws PDOException When the database fails for a reason other than a duplicate email.
     */
    public function register(): void
    {
        $this->redirectIfLoggedIn();
        $this->requireValidCsrfToken('/register');

        $registrationInput = [];
        foreach (self::REGISTRATION_FIELDS as $fieldName) {
            $isPasswordField = $fieldName === 'password' || $fieldName === 'passwordConfirm';
            $rawValue = $_POST[$fieldName] ?? '';
            // Passwords are kept exactly as typed; other fields are trimmed.
            $registrationInput[$fieldName] = is_string($rawValue) ? ($isPasswordField ? $rawValue : trim($rawValue)) : '';
        }

        $userModel = new User();
        $validationErrors = $userModel->validateRegistration($registrationInput);

        if ($validationErrors === []) {
            try {
                $userModel->create($registrationInput);
                $_SESSION['prefillEmail'] = User::normalizeEmail($registrationInput['email']);
                $this->setFlashMessage('Your account is ready. Please log in.', 'success');
                $this->redirect('/login');
            } catch (PDOException $insertError) {
                // 23000 = unique key error: someone saved this email a moment ago.
                if ($insertError->getCode() !== '23000') {
                    throw $insertError;
                }
                $validationErrors['email'] = 'This email is already registered. Please log in or use another email.';
            }
        }

        // Never send passwords back to the browser.
        unset($registrationInput['password'], $registrationInput['passwordConfirm']);

        http_response_code(422);
        $this->render('auth/register', [
            'oldInput'         => $registrationInput,
            'validationErrors' => $validationErrors,
        ], 'Create an account - Alzikrayat');
    }

    /**
     * Logs the user out: clears the session data, deletes the session
     * cookie, and destroys the session. The lastLoginDate cookie stays.
     *
     * Route: POST /logout
     *
     * @return void
     */
    public function logout(): void
    {
        $this->requireValidCsrfToken('/');

        $_SESSION = [];

        $sessionCookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $sessionCookie['path'],
            'domain'   => $sessionCookie['domain'],
            'secure'   => $sessionCookie['secure'],
            'httponly' => $sessionCookie['httponly'],
            'samesite' => $sessionCookie['samesite'] ?? 'Lax',
        ]);
        session_destroy();

        // A new, empty session carries the goodbye message to the next page.
        session_start();
        session_regenerate_id(true);
        $this->setFlashMessage('You have logged out. See you soon.', 'info');
        $this->redirect('/');
    }

    /**
     * Sends logged-in users to the home page. Login and register pages are for guests.
     *
     * @return void
     */
    private function redirectIfLoggedIn(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }
    }

    /**
     * Shows the login view with the last login text.
     *
     * @param string                $emailValue       Email to show in the field.
     * @param array<string, string> $validationErrors Field name => message.
     * @return void
     */
    private function renderLoginForm(string $emailValue, array $validationErrors): void
    {
        $this->render('auth/login', [
            'emailValue'       => $emailValue,
            'validationErrors' => $validationErrors,
            'lastLoginText'    => $this->readLastLoginText(),
        ], 'Login - Alzikrayat');
    }

    /**
     * Starts the logged-in session safely.
     *
     * The session id is changed (session fixation protection), and the CSRF
     * token is removed so a new one is made for the logged-in session.
     *
     * @param int    $userId        Id of the user.
     * @param string $userFirstName First name for the navbar greeting.
     * @return void
     */
    private function startUserSession(int $userId, string $userFirstName): void
    {
        session_regenerate_id(true);
        unset($_SESSION['csrfToken']);

        $_SESSION['userId'] = $userId;
        $_SESSION['userFirstName'] = $userFirstName;
    }

    /**
     * Saves the current time in the lastLoginDate cookie for 7 days.
     *
     * The cookie holds a Unix timestamp. It is HttpOnly (JavaScript cannot
     * read it), SameSite=Lax, and Secure when the site uses HTTPS.
     *
     * @return void
     */
    private function saveLastLoginCookie(): void
    {
        setcookie(self::LAST_LOGIN_COOKIE, (string) time(), [
            'expires'  => time() + self::LAST_LOGIN_LIFETIME,
            'path'     => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/',
            'secure'   => APP_IS_HTTPS,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Reads the lastLoginDate cookie and turns it into readable text.
     *
     * The value comes from the browser, so it is checked: digits only, and
     * a time between the year 2000 and now.
     *
     * @return string|null Text such as "Thursday, 24 September 2026 at 7:10 PM", or null.
     */
    private function readLastLoginText(): ?string
    {
        $cookieValue = $_COOKIE[self::LAST_LOGIN_COOKIE] ?? '';

        if (!is_string($cookieValue) || !ctype_digit($cookieValue)) {
            return null;
        }

        $loginTimestamp = (int) $cookieValue;
        if ($loginTimestamp < 946684800 || $loginTimestamp > time() + 60) {
            return null;
        }

        return date('l, j F Y \a\t g:i A', $loginTimestamp);
    }
}
