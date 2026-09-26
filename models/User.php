<?php
declare(strict_types=1);

/**
 * Class User
 *
 * Data access and validation for the "users" table.
 *
 * Validation rules (course specification, Section 5.1):
 * - first_name, last_name: required, letters only, maximum 50 characters.
 * - email: required, valid format, maximum 100 characters, unique.
 * - password: stored only as a password_hash() result.
 * - location, occupation: optional, maximum 100 characters.
 * - description: optional text.
 */
class User extends Model
{
    public const NAME_MAX_LENGTH = 50;
    public const EMAIL_MAX_LENGTH = 100;
    public const PASSWORD_MIN_LENGTH = 8;

    /**
     * bcrypt (the algorithm behind PASSWORD_DEFAULT) uses only the first
     * 72 bytes of a password. Longer passwords are refused instead of
     * being cut silently.
     */
    public const PASSWORD_MAX_BYTES = 72;

    public const LOCATION_MAX_LENGTH = 100;
    public const OCCUPATION_MAX_LENGTH = 100;
    public const DESCRIPTION_MAX_LENGTH = 1000;

    /**
     * Letters only, in any language. \p{L} is any letter; \p{M} allows
     * marks that belong to letters, such as Arabic short vowels.
     */
    private const NAME_PATTERN = '/^[\p{L}\p{M}]+$/u';

    /**
     * Checks all registration fields and returns the problems found.
     *
     * @param array<string, string> $registrationInput Trimmed form values with keys firstName,
     *        lastName, email, password, passwordConfirm, location, occupation, description.
     * @return array<string, string> Field name => error message. Empty array means valid.
     * @throws PDOException When the unique email check fails.
     */
    public function validateRegistration(array $registrationInput): array
    {
        $validationErrors = [];

        foreach (['firstName' => 'First name', 'lastName' => 'Last name'] as $fieldName => $fieldLabel) {
            $nameError = $this->validateName($registrationInput[$fieldName] ?? '', $fieldLabel);
            if ($nameError !== null) {
                $validationErrors[$fieldName] = $nameError;
            }
        }

        $emailError = $this->validateEmail($registrationInput['email'] ?? '');
        if ($emailError !== null) {
            $validationErrors['email'] = $emailError;
        }

        $passwordError = $this->validatePassword($registrationInput['password'] ?? '');
        if ($passwordError !== null) {
            $validationErrors['password'] = $passwordError;
        } elseif (($registrationInput['passwordConfirm'] ?? '') !== $registrationInput['password']) {
            $validationErrors['passwordConfirm'] = 'The two passwords do not match.';
        }

        $optionalLimits = [
            'location'    => ['Location', self::LOCATION_MAX_LENGTH],
            'occupation'  => ['Occupation', self::OCCUPATION_MAX_LENGTH],
            'description' => ['Description', self::DESCRIPTION_MAX_LENGTH],
        ];
        foreach ($optionalLimits as $fieldName => [$fieldLabel, $maximumLength]) {
            if (mb_strlen($registrationInput[$fieldName] ?? '', 'UTF-8') > $maximumLength) {
                $validationErrors[$fieldName] = $fieldLabel . ' must be ' . $maximumLength . ' characters or less.';
            }
        }

        return $validationErrors;
    }

    /**
     * Saves a new user. The password is hashed here and never stored as text.
     *
     * Empty optional fields are saved as NULL.
     *
     * @param array<string, string> $registrationInput Values that passed validateRegistration().
     * @return int The id of the new user.
     * @throws PDOException When the insert fails. Error code 23000 means the email already exists.
     */
    public function create(array $registrationInput): int
    {
        $sqlQuery = 'INSERT INTO users (first_name, last_name, email, password, location, description, occupation)
                     VALUES (:firstName, :lastName, :email, :passwordHash, :location, :description, :occupation)';

        $this->runQuery($sqlQuery, [
            'firstName'    => $registrationInput['firstName'],
            'lastName'     => $registrationInput['lastName'],
            'email'        => self::normalizeEmail($registrationInput['email']),
            'passwordHash' => password_hash($registrationInput['password'], PASSWORD_DEFAULT),
            'location'     => $this->emptyToNull($registrationInput['location'] ?? ''),
            'description'  => $this->emptyToNull($registrationInput['description'] ?? ''),
            'occupation'   => $this->emptyToNull($registrationInput['occupation'] ?? ''),
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Checks an email and a password. Used by the login action.
     *
     * If the stored hash uses old settings, it is updated to the current
     * PASSWORD_DEFAULT settings (password_needs_rehash).
     *
     * @param string $email         Email typed by the user.
     * @param string $plainPassword Password typed by the user.
     * @return array<string, mixed>|null The user row (without the password) when
     *         the details are correct, otherwise null.
     * @throws PDOException When a query fails.
     */
    public function findByCredentials(string $email, string $plainPassword): ?array
    {
        $userRow = $this->fetchOneRow(
            'SELECT id, first_name, last_name, email, password FROM users WHERE email = :email LIMIT 1',
            ['email' => self::normalizeEmail($email)]
        );

        if ($userRow === null) {
            // Unknown email: do one hash with the current settings anyway.
            // It costs the same time as password_verify() on a real account,
            // so attackers cannot find registered emails by measuring time.
            password_hash($plainPassword, PASSWORD_DEFAULT);

            return null;
        }

        if (!password_verify($plainPassword, (string) $userRow['password'])) {
            return null;
        }

        if (password_needs_rehash((string) $userRow['password'], PASSWORD_DEFAULT)) {
            $this->runQuery('UPDATE users SET password = :passwordHash WHERE id = :userId', [
                'passwordHash' => password_hash($plainPassword, PASSWORD_DEFAULT),
                'userId'       => (int) $userRow['id'],
            ]);
        }

        unset($userRow['password']);

        return $userRow;
    }

    /**
     * Checks if an email is already registered.
     *
     * @param string $email Email to look for.
     * @return bool True when a user already has this email.
     * @throws PDOException When the query fails.
     */
    public function emailExists(string $email): bool
    {
        $foundRow = $this->fetchOneRow(
            'SELECT id FROM users WHERE email = :email LIMIT 1',
            ['email' => self::normalizeEmail($email)]
        );

        return $foundRow !== null;
    }

    /**
     * Returns the members that can be tagged by a user (everyone except that user).
     *
     * @param int $currentUserId Id of the member who is uploading.
     * @return array<int, array<string, mixed>> Rows with id, first_name, last_name, sorted by name.
     * @throws PDOException When the query fails.
     */
    public function findTaggableUsers(int $currentUserId): array
    {
        return $this->fetchAllRows(
            'SELECT id, first_name, last_name FROM users WHERE id <> :currentUserId
             ORDER BY first_name, last_name',
            ['currentUserId' => $currentUserId]
        );
    }

    /**
     * Finds a member's public name by id.
     *
     * @param int $userId Member id.
     * @return array<string, mixed>|null Row with id, first_name, last_name, or null.
     * @throws PDOException When the query fails.
     */
    public function findPublicProfile(int $userId): ?array
    {
        return $this->fetchOneRow(
            'SELECT id, first_name, last_name FROM users WHERE id = :userId LIMIT 1',
            ['userId' => $userId]
        );
    }

    /**
     * Counts all registered users. Used for the home page statistics.
     *
     * @return int Number of rows in the users table.
     * @throws PDOException When the query fails.
     */
    public function countAll(): int
    {
        $countRow = $this->fetchOneRow('SELECT COUNT(*) AS totalUsers FROM users');

        return (int) ($countRow['totalUsers'] ?? 0);
    }

    /**
     * Checks one email field for the login form (format only, no database).
     *
     * @param string $email Email typed by the user.
     * @return bool True when the format is valid and the length is allowed.
     */
    public static function isValidEmailFormat(string $email): bool
    {
        return $email !== ''
            && mb_strlen($email, 'UTF-8') <= self::EMAIL_MAX_LENGTH
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Makes emails uniform before saving or searching: trimmed and lowercase.
     *
     * @param string $email Any email text.
     * @return string The normalized email.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Checks a first or last name.
     *
     * @param string $nameValue  The name to check.
     * @param string $fieldLabel Label used in the message, for example "First name".
     * @return string|null Error message, or null when the name is valid.
     */
    private function validateName(string $nameValue, string $fieldLabel): ?string
    {
        if ($nameValue === '') {
            return $fieldLabel . ' is required.';
        }
        if (mb_strlen($nameValue, 'UTF-8') > self::NAME_MAX_LENGTH) {
            return $fieldLabel . ' must be ' . self::NAME_MAX_LENGTH . ' characters or less.';
        }
        if (preg_match(self::NAME_PATTERN, $nameValue) !== 1) {
            return $fieldLabel . ' can contain letters only (no spaces, numbers, or symbols).';
        }

        return null;
    }

    /**
     * Checks the email format, length, and that it is not already used.
     *
     * @param string $email Email typed by the user.
     * @return string|null Error message, or null when the email is valid and free.
     * @throws PDOException When the database check fails.
     */
    private function validateEmail(string $email): ?string
    {
        if ($email === '') {
            return 'Email is required.';
        }
        if (!self::isValidEmailFormat($email)) {
            return 'Please enter a valid email address, for example name@example.com.';
        }
        if ($this->emailExists($email)) {
            return 'This email is already registered. Please log in or use another email.';
        }

        return null;
    }

    /**
     * Checks the password strength rules.
     *
     * Rules: 8 characters or more, at most 72 bytes, at least one letter
     * and at least one number.
     *
     * @param string $plainPassword Password typed by the user.
     * @return string|null Error message, or null when the password is valid.
     */
    private function validatePassword(string $plainPassword): ?string
    {
        if ($plainPassword === '') {
            return 'Password is required.';
        }
        if (mb_strlen($plainPassword, 'UTF-8') < self::PASSWORD_MIN_LENGTH) {
            return 'Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters.';
        }
        if (strlen($plainPassword) > self::PASSWORD_MAX_BYTES) {
            return 'Password is too long. Please use ' . self::PASSWORD_MAX_BYTES . ' characters or less.';
        }
        if (preg_match('/\p{L}/u', $plainPassword) !== 1 || preg_match('/\d/', $plainPassword) !== 1) {
            return 'Password must contain at least one letter and one number.';
        }

        return null;
    }

    /**
     * Turns an empty string into null, so optional fields are stored as NULL.
     *
     * @param string $fieldValue A trimmed form value.
     * @return string|null The value, or null when it is empty.
     */
    private function emptyToNull(string $fieldValue): ?string
    {
        return $fieldValue === '' ? null : $fieldValue;
    }
}
