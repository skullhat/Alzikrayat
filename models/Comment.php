<?php
declare(strict_types=1);

/**
 * Class Comment
 *
 * Data access and validation for the "comments" table.
 *
 * Rules: a comment is required (not only spaces) and is at most
 * 1000 characters. Any logged-in user may comment on any photo.
 */
class Comment extends Model
{
    public const TEXT_MAX_LENGTH = 1000;

    /**
     * Cleans comment text before it is checked and saved.
     *
     * Windows line endings (\r\n) become \n, and spaces at the start
     * and end are removed.
     *
     * @param string $commentText Raw text from the form.
     * @return string Cleaned text.
     */
    public static function normalizeText(string $commentText): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $commentText));
    }

    /**
     * Checks one comment.
     *
     * @param string $commentText Text after normalizeText().
     * @return string|null Error message, or null when the comment is valid.
     */
    public function validateText(string $commentText): ?string
    {
        if ($commentText === '') {
            return 'Comment is required.';
        }
        if (mb_strlen($commentText, 'UTF-8') > self::TEXT_MAX_LENGTH) {
            return 'Comment must be ' . self::TEXT_MAX_LENGTH . ' characters or less.';
        }

        return null;
    }

    /**
     * Saves a new comment. The time is set by MySQL (DEFAULT CURRENT_TIMESTAMP).
     *
     * @param int    $photoId     Photo that receives the comment.
     * @param int    $userId      Author of the comment.
     * @param string $commentText Valid comment text.
     * @return int Id of the new comment.
     * @throws PDOException When the insert fails (for example, the photo was just deleted).
     */
    public function create(int $photoId, int $userId, string $commentText): int
    {
        $this->runQuery(
            'INSERT INTO comments (photo_id, user_id, comment) VALUES (:photoId, :userId, :commentText)',
            ['photoId' => $photoId, 'userId' => $userId, 'commentText' => $commentText]
        );

        return $this->getLastInsertId();
    }

    /**
     * Finds one comment with the commenter's name.
     *
     * @param int $commentId Comment id.
     * @return array<string, mixed>|null The row, or null when it does not exist.
     * @throws PDOException When the query fails.
     */
    public function findById(int $commentId): ?array
    {
        return $this->fetchOneRow(
            'SELECT comments.id, comments.photo_id, comments.comment, comments.date_time, comments.user_id,
                    users.first_name, users.last_name
             FROM comments
             INNER JOIN users ON users.id = comments.user_id
             WHERE comments.id = :commentId
             LIMIT 1',
            ['commentId' => $commentId]
        );
    }

    /**
     * Counts the comments of one photo.
     *
     * @param int $photoId Photo id.
     * @return int Number of comments on the photo.
     * @throws PDOException When the query fails.
     */
    public function countByPhotoId(int $photoId): int
    {
        $countRow = $this->fetchOneRow(
            'SELECT COUNT(*) AS photoComments FROM comments WHERE photo_id = :photoId',
            ['photoId' => $photoId]
        );

        return (int) ($countRow['photoComments'] ?? 0);
    }

    /**
     * Returns all comments of one photo, oldest first, with the commenter's name.
     *
     * Uses the index (photo_id, date_time) created in database.sql.
     *
     * @param int $photoId Photo id.
     * @return array<int, array<string, mixed>> Rows with id, comment, date_time, user_id,
     *         first_name, and last_name.
     * @throws PDOException When the query fails.
     */
    public function findByPhotoId(int $photoId): array
    {
        return $this->fetchAllRows(
            'SELECT comments.id, comments.comment, comments.date_time, comments.user_id,
                    users.first_name, users.last_name
             FROM comments
             INNER JOIN users ON users.id = comments.user_id
             WHERE comments.photo_id = :photoId
             ORDER BY comments.date_time ASC, comments.id ASC',
            ['photoId' => $photoId]
        );
    }

    /**
     * Counts all comments. Used for the home page statistics.
     *
     * @return int Number of rows in the comments table.
     * @throws PDOException When the query fails.
     */
    public function countAll(): int
    {
        $countRow = $this->fetchOneRow('SELECT COUNT(*) AS totalComments FROM comments');

        return (int) ($countRow['totalComments'] ?? 0);
    }
}
