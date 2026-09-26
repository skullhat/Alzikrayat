<?php
declare(strict_types=1);

/**
 * Class Controller
 *
 * Base class for all controllers (Auth, Photo, Comment).
 * It gives shared tools: rendering views inside the layout, safe
 * redirects, login checks, CSRF tokens, flash messages, and reading
 * form fields. Controllers never contain SQL; they call models.
 */
abstract class Controller
{
    /** Flash message types that match Bootstrap alert classes. */
    private const FLASH_TYPES = ['success', 'danger', 'warning', 'info'];

    /**
     * Shows a view inside the common layout (header, page, footer).
     *
     * The keys of $viewVariables become normal variables inside the view.
     * The layout always receives: $pageTitle, $currentUserFirstName,
     * $currentUserId, $csrfToken, $flashMessage, $basePath, and $currentPath.
     *
     * @param string               $viewName      View path without ".php", for example "photos/index".
     * @param array<string, mixed> $viewVariables Values the view needs.
     * @param string               $pageTitle     Text for the browser tab.
     * @return void
     * @throws InvalidArgumentException When the view name has unsafe characters.
     * @throws RuntimeException         When the view file does not exist.
     */
    protected function render(string $viewName, array $viewVariables = [], string $pageTitle = 'Alzikrayat'): void
    {
        // Only lowercase folder/file names are allowed. This blocks "../" tricks.
        if (preg_match('#^[a-z]+(/[a-z_]+)+$#', $viewName) !== 1) {
            throw new InvalidArgumentException('Invalid view name: ' . $viewName);
        }

        $viewFilePath = ROOT_PATH . '/views/' . $viewName . '.php';
        if (!is_file($viewFilePath)) {
            throw new RuntimeException('View file was not found: ' . $viewName);
        }

        $layoutVariables = [
            'pageTitle'            => $pageTitle,
            'currentUserFirstName' => $this->getCurrentUserFirstName(),
            'currentUserId'        => $this->getCurrentUserId(),
            'csrfToken'            => $this->getCsrfToken(),
            'flashMessage'         => $this->takeFlashMessage(),
            'basePath'             => APP_BASE_PATH,
            'currentPath'          => $this->getCurrentPath(),
        ];

        $this->includeViewFiles($viewFilePath, array_merge($viewVariables, $layoutVariables));
    }

    /**
     * Includes the header, the page view, and the footer.
     *
     * Output is buffered first. If a view fails in the middle, no broken
     * half page is sent to the browser.
     *
     * @param string               $viewFilePath      Full path of the page view.
     * @param array<string, mixed> $templateVariables Variables for all three files.
     * @return void
     * @throws Throwable Any error raised inside a view is passed on after cleanup.
     */
    private function includeViewFiles(string $viewFilePath, array $templateVariables): void
    {
        extract($templateVariables, EXTR_SKIP);

        ob_start();
        try {
            require ROOT_PATH . '/views/layout/header.php';
            require $viewFilePath;
            require ROOT_PATH . '/views/layout/footer.php';
        } catch (Throwable $viewError) {
            ob_end_clean();
            throw $viewError;
        }
        echo ob_get_clean();
    }

    /**
     * Renders one view WITHOUT the layout and returns the HTML as text.
     *
     * Used for small pieces of a page, for example one comment. The same
     * partial is used by the full page and by the JSON answer, so the
     * markup (and its escaping) is written only once.
     *
     * @param string               $viewName      View path without ".php", for example "photos/comment_item".
     * @param array<string, mixed> $viewVariables Values the partial needs.
     * @return string The rendered HTML.
     * @throws InvalidArgumentException When the view name has unsafe characters.
     * @throws RuntimeException         When the view file does not exist.
     */
    protected function renderPartial(string $viewName, array $viewVariables): string
    {
        if (preg_match('#^[a-z]+(/[a-z_]+)+$#', $viewName) !== 1) {
            throw new InvalidArgumentException('Invalid view name: ' . $viewName);
        }

        $partialFilePath = ROOT_PATH . '/views/' . $viewName . '.php';
        if (!is_file($partialFilePath)) {
            throw new RuntimeException('View file was not found: ' . $viewName);
        }

        return self::capturePartial($partialFilePath, $viewVariables + ['basePath' => APP_BASE_PATH]);
    }

    /**
     * Includes a partial in its own clean scope and returns its output.
     *
     * @param string               $partialFilePath   Full path of the partial.
     * @param array<string, mixed> $templateVariables Variables for the partial.
     * @return string The HTML the partial printed.
     * @throws Throwable Any error raised inside the partial, after cleanup.
     */
    private static function capturePartial(string $partialFilePath, array $templateVariables): string
    {
        extract($templateVariables, EXTR_SKIP);

        ob_start();
        try {
            require $partialFilePath;
        } catch (Throwable $partialError) {
            ob_end_clean();
            throw $partialError;
        }

        return (string) ob_get_clean();
    }

    /**
     * Checks if the browser asked for a JSON answer (a fetch() request
     * sent with the header "Accept: application/json").
     *
     * @return bool True when the request wants JSON instead of an HTML page.
     */
    protected function isJsonRequest(): bool
    {
        return strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
    }

    /**
     * Sends a JSON answer with a status code and stops.
     *
     * JSON_HEX_* flags turn <, >, &, ' and " into \u codes, so the text can
     * never be read as HTML even if it is placed in a page by mistake.
     *
     * @param array<string, mixed> $responseData Data to send.
     * @param int                  $statusCode   HTTP status code, for example 200 or 422.
     * @return void This method does not return. It always ends the script with exit.
     */
    protected function sendJson(array $responseData, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(
            $responseData,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        exit;
    }

    /**
     * Sends the browser to another page of this application and stops.
     *
     * Uses status 303 (See Other), which is the correct code for the
     * redirect-after-post pattern.
     *
     * @param string $routePath Internal path that starts with "/", for example "/photos".
     * @return void This method does not return. It always ends the script with exit.
     * @throws InvalidArgumentException When the path is not an internal path.
     */
    protected function redirect(string $routePath): void
    {
        // A path like "//evil.com" would leave the site, so it is refused.
        if ($routePath === '' || $routePath[0] !== '/' || strpos($routePath, '//') === 0) {
            throw new InvalidArgumentException('Redirect path must be an internal path.');
        }

        header('Location: ' . APP_BASE_PATH . $routePath, true, 303);
        exit;
    }

    /**
     * Returns the path of the current page without the base path.
     * The navbar uses it to highlight the active link.
     *
     * @return string Path such as "/photos". "/" for the home page.
     */
    private function getCurrentPath(): string
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

        if (APP_BASE_PATH !== '' && strpos($requestPath, APP_BASE_PATH) === 0) {
            $requestPath = substr($requestPath, strlen(APP_BASE_PATH));
        }

        return '/' . trim($requestPath, '/');
    }

    /**
     * Checks if a user is logged in.
     *
     * @return bool True when the session holds a user id.
     */
    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['userId']) && is_int($_SESSION['userId']);
    }

    /**
     * Returns the id of the logged-in user.
     *
     * @return int|null The user id, or null when nobody is logged in.
     */
    protected function getCurrentUserId(): ?int
    {
        return $this->isLoggedIn() ? $_SESSION['userId'] : null;
    }

    /**
     * Returns the first name of the logged-in user for the navbar.
     *
     * @return string|null The first name, or null when nobody is logged in.
     */
    protected function getCurrentUserFirstName(): ?string
    {
        if (!$this->isLoggedIn() || !isset($_SESSION['userFirstName'])) {
            return null;
        }

        return (string) $_SESSION['userFirstName'];
    }

    /**
     * Stops guests. If nobody is logged in, shows a message and goes to /login.
     *
     * The user comes back to a page after a successful login:
     * - the page given in $returnPathAfterLogin, when it is not empty;
     * - otherwise the current page, for GET requests.
     *
     * @param string $returnPathAfterLogin Optional internal path, for example "/photo/5".
     *                                     Useful for POST actions, which cannot be repeated.
     * @return void
     */
    protected function requireLogin(string $returnPathAfterLogin = ''): void
    {
        if ($this->isLoggedIn()) {
            return;
        }

        if ($returnPathAfterLogin !== '') {
            $_SESSION['returnPath'] = $returnPathAfterLogin;
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['returnPath'] = $this->getCurrentPath();
        }

        $this->setFlashMessage('Please log in first.', 'warning');
        $this->redirect('/login');
    }

    /**
     * Reads and removes the page saved by requireLogin().
     *
     * Only simple internal paths are accepted, so a changed session value
     * cannot send the user to another website.
     *
     * @param string $defaultPath Path to use when nothing valid was saved.
     * @return string An internal path such as "/photos/create".
     */
    protected function takeReturnPath(string $defaultPath = '/'): string
    {
        $savedPath = $_SESSION['returnPath'] ?? '';
        unset($_SESSION['returnPath']);

        return is_string($savedPath) && $this->isSafeInternalPath($savedPath) ? $savedPath : $defaultPath;
    }

    /**
     * Checks that a path is a simple page of this site.
     *
     * Allowed: "/", letters, digits, "/", "_", "-". Not allowed: "//", "..",
     * a scheme like "https:", or query text. So the path can never lead
     * to another website (open redirect protection).
     *
     * @param string $candidatePath Path to check.
     * @return bool True when the path is safe to redirect to.
     */
    protected function isSafeInternalPath(string $candidatePath): bool
    {
        return preg_match('#^/[A-Za-z0-9/_-]*$#', $candidatePath) === 1
            && strpos($candidatePath, '//') === false;
    }

    /**
     * Returns the CSRF token of this session. Creates it when missing.
     *
     * @return string A 64-character random hex token.
     * @throws Exception When the system cannot make random bytes (very rare).
     */
    protected function getCsrfToken(): string
    {
        if (empty($_SESSION['csrfToken']) || !is_string($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    /**
     * Checks the CSRF token sent in the "csrfToken" POST field.
     *
     * hash_equals() is used so the check takes the same time for every
     * wrong token (protection against timing attacks).
     *
     * @return bool True when the sent token matches the session token.
     */
    protected function hasValidCsrfToken(): bool
    {
        $submittedToken = $_POST['csrfToken'] ?? '';
        $sessionToken = $_SESSION['csrfToken'] ?? '';

        return is_string($submittedToken)
            && is_string($sessionToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Stops the request when the CSRF token is missing or wrong.
     *
     * @param string $returnPath Internal path to go back to, for example "/login".
     * @return void
     */
    protected function requireValidCsrfToken(string $returnPath): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->setFlashMessage('Your form has expired. Please try again.', 'danger');
            $this->redirect($returnPath);
        }
    }

    /**
     * Saves a one-time message. It is shown on the next page, then removed.
     *
     * @param string $messageText The text to show.
     * @param string $messageType One of: success, danger, warning, info.
     * @return void
     */
    protected function setFlashMessage(string $messageText, string $messageType = 'success'): void
    {
        if (!in_array($messageType, self::FLASH_TYPES, true)) {
            $messageType = 'info';
        }

        $_SESSION['flashMessage'] = ['text' => $messageText, 'type' => $messageType];
    }

    /**
     * Reads the saved one-time message and removes it from the session.
     *
     * @return array{text: string, type: string}|null The message, or null when there is none.
     */
    private function takeFlashMessage(): ?array
    {
        if (!isset($_SESSION['flashMessage']) || !is_array($_SESSION['flashMessage'])) {
            return null;
        }

        $flashMessage = $_SESSION['flashMessage'];
        unset($_SESSION['flashMessage']);

        return $flashMessage;
    }

    /**
     * Keeps the state of a form (errors and typed values) for the next page.
     *
     * Used with redirect-after-post: the POST action saves the state and
     * redirects; the GET page reads it once with takeFormState().
     *
     * @param string               $formKey   Name of the form, for example "comment".
     * @param array<string, mixed> $formState Values to keep, for example errors and old text.
     * @return void
     */
    protected function setFormState(string $formKey, array $formState): void
    {
        $_SESSION['formState'][$formKey] = $formState;
    }

    /**
     * Reads and removes the saved state of a form.
     *
     * @param string $formKey Name of the form.
     * @return array<string, mixed> The saved state, or an empty array.
     */
    protected function takeFormState(string $formKey): array
    {
        $formState = $_SESSION['formState'][$formKey] ?? [];
        unset($_SESSION['formState'][$formKey]);

        return is_array($formState) ? $formState : [];
    }

    /**
     * Reads one text field from the submitted form and trims spaces.
     *
     * If the field is missing, or someone sends an array instead of text,
     * an empty string is returned. This keeps validation code simple.
     *
     * @param string $fieldName Name of the form field.
     * @return string The trimmed value, or an empty string.
     */
    protected function getPostString(string $fieldName): string
    {
        $fieldValue = $_POST[$fieldName] ?? '';

        return is_string($fieldValue) ? trim($fieldValue) : '';
    }
}
