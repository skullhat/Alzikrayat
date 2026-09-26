<?php
declare(strict_types=1);

/**
 * Class Photo
 *
 * Data access for the "photos" table and the image files on disk.
 *
 * The model keeps the database and the disk in sync:
 * - A file is saved first, then its row. If the row fails, the file is removed.
 * - On delete, the row is removed inside a transaction, then the file.
 *   If the file cannot be removed, the transaction is rolled back.
 *
 * Upload rules: JPEG, PNG, or WebP only; 5 MB maximum; the real file type
 * is read from the file content with finfo, not taken from the browser.
 */
class Photo extends Model
{
    /** Largest allowed upload: 5 MB. */
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    public const TITLE_MAX_LENGTH = 200;
    public const DESCRIPTION_MAX_LENGTH = 2000;

    /** Largest width or height accepted, in pixels. */
    private const MAX_IMAGE_SIDE = 10000;

    /** Allowed real file types (MIME) and the file extensions that may go with each. */
    private const ALLOWED_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /** Extension used when saving each type. */
    private const SAVED_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Pattern of every file name this model creates, for example photo_3f9a...c2.jpg */
    private const FILE_NAME_PATTERN = '/^photo_[a-f0-9]{32}\.(jpg|png|webp)$/';

    /** Largest number of photos one preview query may return. */
    private const MAXIMUM_PREVIEW_LIMIT = 12;

    /**
     * Returns the folder that holds uploaded photos.
     *
     * @return string Full path of public/images/uploads (no trailing slash).
     */
    public static function getUploadFolder(): string
    {
        return ROOT_PATH . '/public/images/uploads';
    }

    /**
     * Returns the real upload limit in bytes.
     *
     * The limit is the smallest of: our own 5 MB rule, the PHP setting
     * upload_max_filesize, and the PHP setting post_max_size. On XAMPP both
     * PHP settings are 40 MB, so the result is 5 MB. On a server with lower
     * settings, the form and messages show the lower, true limit.
     *
     * @return int Largest file size in bytes that this server will accept.
     */
    public static function getMaxUploadBytes(): int
    {
        $serverLimits = [
            self::MAX_FILE_BYTES,
            self::parseIniSize((string) ini_get('upload_max_filesize')),
            self::parseIniSize((string) ini_get('post_max_size')),
        ];

        // A value of 0 means "no limit" for these PHP settings, so it is ignored.
        return min(array_filter($serverLimits, static function (int $limitBytes): bool {
            return $limitBytes > 0;
        }));
    }

    /**
     * Formats the upload limit for messages, for example "5 MB" or "1.5 MB".
     *
     * @return string The limit in megabytes.
     */
    public static function getMaxUploadText(): string
    {
        $limitInMegabytes = round(self::getMaxUploadBytes() / (1024 * 1024), 1);

        return rtrim(rtrim(number_format($limitInMegabytes, 1, '.', ''), '0'), '.') . ' MB';
    }

    /**
     * Checks the title and description of a new photo.
     *
     * @param string $title       Trimmed title.
     * @param string $description Trimmed description (may be empty).
     * @return array<string, string> Field name => error message. Empty array means valid.
     */
    public function validateMetadata(string $title, string $description): array
    {
        $validationErrors = [];

        if ($title === '') {
            $validationErrors['title'] = 'Title is required.';
        } elseif (mb_strlen($title, 'UTF-8') > self::TITLE_MAX_LENGTH) {
            $validationErrors['title'] = 'Title must be ' . self::TITLE_MAX_LENGTH . ' characters or less.';
        }

        if (mb_strlen($description, 'UTF-8') > self::DESCRIPTION_MAX_LENGTH) {
            $validationErrors['description'] = 'Description must be ' . self::DESCRIPTION_MAX_LENGTH . ' characters or less.';
        }

        return $validationErrors;
    }

    /**
     * Checks an uploaded file from $_FILES.
     *
     * Checks, in order: PHP upload error code, that the file really came
     * from an HTTP upload, size, real type from the file content (finfo),
     * the extension matches the real type, and that the image can be read.
     *
     * @param mixed $uploadedFile One entry of $_FILES (or null when missing).
     * @return string|null Error message, or null when the file is acceptable.
     */
    public function validateImageFile($uploadedFile): ?string
    {
        // A normal single upload has these keys with simple values. Arrays mean
        // someone sent "photoFile[]" instead, which is not allowed.
        if (!is_array($uploadedFile) || !isset($uploadedFile['error'], $uploadedFile['tmp_name'], $uploadedFile['name'], $uploadedFile['size'])
            || !is_int($uploadedFile['error'])) {
            return 'Please choose a photo to upload.';
        }

        $uploadErrorMessage = $this->describeUploadError($uploadedFile['error']);
        if ($uploadErrorMessage !== null) {
            return $uploadErrorMessage;
        }

        if (!is_uploaded_file($uploadedFile['tmp_name'])) {
            return 'The upload was not valid. Please try again.';
        }

        $fileSize = (int) $uploadedFile['size'];
        if ($fileSize <= 0) {
            return 'The selected file is empty.';
        }
        if ($fileSize > self::getMaxUploadBytes()) {
            return 'The photo is too large. The maximum size is ' . self::getMaxUploadText() . '.';
        }

        $realMimeType = $this->detectMimeType($uploadedFile['tmp_name']);
        if ($realMimeType === null) {
            return 'Only JPG, PNG, and WebP images are allowed.';
        }

        $fileExtension = strtolower(pathinfo((string) $uploadedFile['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, self::ALLOWED_TYPES[$realMimeType], true)) {
            return 'The file extension does not match the image type. Please use a .jpg, .png, or .webp file.';
        }

        // getimagesize() reads the image header. A damaged or fake image fails here.
        $imageInfo = @getimagesize($uploadedFile['tmp_name']);
        if ($imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
            return 'The image file seems to be damaged. Please choose another photo.';
        }
        if ($imageInfo[0] > self::MAX_IMAGE_SIDE || $imageInfo[1] > self::MAX_IMAGE_SIDE) {
            return 'The image is too big. Width and height must be ' . self::MAX_IMAGE_SIDE . ' pixels or less.';
        }

        return null;
    }

    /**
     * Saves the uploaded file with a new random name, then saves the row.
     *
     * The original file name is never used on disk. If the database insert
     * fails, the saved file is deleted, so disk and database stay in sync.
     *
     * @param array<string, mixed> $uploadedFile An entry of $_FILES that passed validateImageFile().
     * @param int                  $userId       Owner of the photo.
     * @param string               $title        Photo title.
     * @param string               $description  Photo description (empty string is saved as NULL).
     * @return int Id of the new photo.
     * @throws RuntimeException When the folder is not writable or the file cannot be moved.
     * @throws PDOException     When the database insert fails.
     */
    public function createFromUpload(array $uploadedFile, int $userId, string $title, string $description): int
    {
        $uploadFolder = self::getUploadFolder();
        if (!is_dir($uploadFolder) || !is_writable($uploadFolder)) {
            error_log('[Alzikrayat] Upload folder is missing or not writable: ' . $uploadFolder);
            throw new RuntimeException('The server cannot save photos right now. Please tell the site administrator.');
        }

        $realMimeType = (string) $this->detectMimeType($uploadedFile['tmp_name']);
        // 16 random bytes = 32 hex characters. Guessing a name is not practical.
        $newFileName = 'photo_' . bin2hex(random_bytes(16)) . '.' . self::SAVED_EXTENSIONS[$realMimeType];
        $targetPath = $uploadFolder . '/' . $newFileName;

        if (!@move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            $systemError = error_get_last();
            error_log('[Alzikrayat] move_uploaded_file failed: ' . ($systemError['message'] ?? 'unknown reason'));
            throw new RuntimeException('The photo could not be saved. Please try again.');
        }
        @chmod($targetPath, 0644);

        try {
            $this->runQuery(
                'INSERT INTO photos (user_id, file_name, title, description)
                 VALUES (:userId, :fileName, :title, :description)',
                [
                    'userId'      => $userId,
                    'fileName'    => $newFileName,
                    'title'       => $title,
                    'description' => $description === '' ? null : $description,
                ]
            );
        } catch (PDOException $insertError) {
            @unlink($targetPath);
            throw $insertError;
        }

        return $this->getLastInsertId();
    }

    /**
     * Finds one photo with its owner's name.
     *
     * @param int $photoId Photo id.
     * @return array<string, mixed>|null The photo row, or null when it does not exist.
     * @throws PDOException When the query fails.
     */
    public function findById(int $photoId): ?array
    {
        return $this->fetchOneRow(
            'SELECT photos.id, photos.user_id, photos.file_name, photos.title, photos.description,
                    photos.date_time, users.first_name, users.last_name
             FROM photos
             INNER JOIN users ON users.id = photos.user_id
             WHERE photos.id = :photoId
             LIMIT 1',
            ['photoId' => $photoId]
        );
    }

    /**
     * Returns one page of the gallery, newest first, with owner names and
     * the number of comments on each photo.
     *
     * @param int $pageNumber   Page number, starting at 1.
     * @param int $photosPerPage Photos on each page.
     * @return array<int, array<string, mixed>> Photo rows for this page.
     * @throws PDOException When the query fails.
     */
    public function findPage(int $pageNumber, int $photosPerPage): array
    {
        $pageNumber = max(1, $pageNumber);
        $photosPerPage = max(1, $photosPerPage);

        return $this->fetchAllRows(
            'SELECT photos.id, photos.file_name, photos.title, photos.description, photos.date_time,
                    users.first_name, users.last_name,
                    (SELECT COUNT(*) FROM comments WHERE comments.photo_id = photos.id) AS comment_count
             FROM photos
             INNER JOIN users ON users.id = photos.user_id
             ORDER BY photos.date_time DESC, photos.id DESC
             LIMIT :pageLimit OFFSET :pageOffset',
            [
                'pageLimit'  => $photosPerPage,
                'pageOffset' => ($pageNumber - 1) * $photosPerPage,
            ]
        );
    }

    /**
     * Deletes a photo row and its file, only when the user owns the photo.
     *
     * Ownership is checked again inside the SQL (WHERE user_id = ...), so
     * even a mistake in the controller cannot delete another user's photo.
     * Comments are removed by the database (ON DELETE CASCADE).
     *
     * @param int $photoId Photo to delete.
     * @param int $ownerId Id of the logged-in user.
     * @return bool True when the photo was deleted; false when it does not
     *              exist or belongs to another user.
     * @throws RuntimeException When the file exists but cannot be removed (nothing is deleted).
     * @throws PDOException     When a query fails (nothing is deleted).
     */
    public function deleteOwnedPhoto(int $photoId, int $ownerId): bool
    {
        $this->database->beginTransaction();

        try {
            // FOR UPDATE locks the row until commit, so two delete requests cannot race.
            $photoRow = $this->fetchOneRow(
                'SELECT file_name FROM photos WHERE id = :photoId AND user_id = :ownerId FOR UPDATE',
                ['photoId' => $photoId, 'ownerId' => $ownerId]
            );

            if ($photoRow === null) {
                $this->database->rollBack();

                return false;
            }

            $this->runQuery(
                'DELETE FROM photos WHERE id = :photoId AND user_id = :ownerId',
                ['photoId' => $photoId, 'ownerId' => $ownerId]
            );

            $this->deleteImageFile((string) $photoRow['file_name']);

            $this->database->commit();

            return true;
        } catch (Throwable $deleteError) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $deleteError;
        }
    }

    /**
     * Counts all uploaded photos.
     *
     * @return int Number of rows in the photos table.
     * @throws PDOException When the query fails.
     */
    public function countAll(): int
    {
        $countRow = $this->fetchOneRow('SELECT COUNT(*) AS totalPhotos FROM photos');

        return (int) ($countRow['totalPhotos'] ?? 0);
    }

    /**
     * Returns the newest photos with the owner's name (home page).
     *
     * @param int $photoLimit How many photos to return (clamped to 1..12).
     * @return array<int, array<string, mixed>> Newest photos first.
     * @throws PDOException When the query fails.
     */
    public function findLatest(int $photoLimit): array
    {
        $photoLimit = max(1, min($photoLimit, self::MAXIMUM_PREVIEW_LIMIT));

        return $this->fetchAllRows(
            'SELECT photos.id, photos.file_name, photos.title, photos.date_time,
                    users.first_name, users.last_name
             FROM photos
             INNER JOIN users ON users.id = photos.user_id
             ORDER BY photos.date_time DESC, photos.id DESC
             LIMIT :photoLimit',
            ['photoLimit' => $photoLimit]
        );
    }

    /**
     * Removes one image file from the upload folder.
     *
     * Safety checks: the name must match the pattern this model creates, and
     * the real path must be inside the upload folder. A missing file is not
     * an error (the goal, "file is gone", is already true).
     *
     * @param string $fileName File name from the database.
     * @return void
     * @throws RuntimeException When the name is unsafe or the file cannot be deleted.
     */
    private function deleteImageFile(string $fileName): void
    {
        if (preg_match(self::FILE_NAME_PATTERN, $fileName) !== 1) {
            throw new RuntimeException('Refused to delete a file with an unexpected name.');
        }

        $uploadFolder = realpath(self::getUploadFolder());
        $filePath = self::getUploadFolder() . '/' . $fileName;

        if (!is_file($filePath)) {
            error_log('[Alzikrayat] Photo file was already missing: ' . $fileName);

            return;
        }

        $realFilePath = realpath($filePath);
        if ($uploadFolder === false || $realFilePath === false || dirname($realFilePath) !== $uploadFolder) {
            throw new RuntimeException('Refused to delete a file outside the upload folder.');
        }

        // The PHP warning is silenced here and the real reason is logged instead,
        // so the visitor sees only the friendly message from the controller.
        if (!@unlink($realFilePath)) {
            $systemError = error_get_last();
            error_log('[Alzikrayat] unlink failed for ' . $fileName . ': ' . ($systemError['message'] ?? 'unknown reason'));
            throw new RuntimeException('The photo file could not be deleted. Please try again.');
        }
    }

    /**
     * Turns a php.ini size such as "40M", "512K", or "2G" into bytes.
     *
     * @param string $iniValue Value from ini_get().
     * @return int Number of bytes. 0 when the value is empty or 0.
     */
    private static function parseIniSize(string $iniValue): int
    {
        $iniValue = trim($iniValue);
        if ($iniValue === '' || !preg_match('/^(\d+)\s*([KMG]?)/i', $iniValue, $sizeParts)) {
            return 0;
        }

        $sizeMultipliers = ['' => 1, 'K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3];

        return (int) $sizeParts[1] * $sizeMultipliers[strtoupper($sizeParts[2])];
    }

    /**
     * Reads the real type of a file from its content (magic bytes).
     *
     * @param string $filePath Path of the file to check.
     * @return string|null An allowed MIME type, or null when the type is not allowed.
     * @throws RuntimeException When the fileinfo extension is not enabled.
     */
    private function detectMimeType(string $filePath): ?string
    {
        if (!class_exists('finfo')) {
            error_log('[Alzikrayat] The PHP fileinfo extension is off. Enable "extension=fileinfo" in php.ini.');
            throw new RuntimeException('The server cannot check photo files right now. Please tell the site administrator.');
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($filePath);

        return is_string($mimeType) && isset(self::ALLOWED_TYPES[$mimeType]) ? $mimeType : null;
    }

    /**
     * Turns a PHP upload error code into a message for the user.
     *
     * @param int $uploadErrorCode Value of $_FILES[...]['error'].
     * @return string|null Message, or null for UPLOAD_ERR_OK.
     */
    private function describeUploadError(int $uploadErrorCode): ?string
    {
        switch ($uploadErrorCode) {
            case UPLOAD_ERR_OK:
                return null;
            case UPLOAD_ERR_NO_FILE:
                return 'Please choose a photo to upload.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The photo is too large. The maximum size is ' . self::getMaxUploadText() . '.';
            case UPLOAD_ERR_PARTIAL:
                return 'The upload stopped before it finished. Please try again.';
            default:
                // Server problems (no temp folder, disk write error, blocked by an extension).
                error_log('[Alzikrayat] File upload failed with PHP error code ' . $uploadErrorCode);

                return 'The server could not receive the photo. Please try again later.';
        }
    }
}
