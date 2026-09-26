<?php
declare(strict_types=1);

/**
 * Class PhotoTag
 *
 * Data access and validation for the "photo_tags" table (novelty feature).
 * A tag links one photo to one registered member who appears in it.
 *
 * Rules:
 * - Only existing members can be tagged, at most 10 per photo.
 * - The owner does not tag themselves (they are already shown as the owner).
 * - The same member is tagged only once per photo (UNIQUE key in the database).
 * - The photo owner or the tagged member may remove a tag.
 */
class PhotoTag extends Model
{
    /** Largest number of members that can be tagged in one photo. */
    public const MAX_TAGS_PER_PHOTO = 10;

    /**
     * Checks the list of member ids chosen in the upload form.
     *
     * Values arrive as text from checkboxes, so each one must be a positive
     * whole number. Repeated ids are counted once.
     *
     * @param mixed $submittedUserIds Value of $_POST['taggedUserIds'] (array or missing).
     * @param int   $ownerId          Id of the member uploading the photo.
     * @return array{userIds: array<int, int>, error: string|null} Clean ids and an error message (or null).
     * @throws PDOException When the database check fails.
     */
    public function validateTaggedUsers($submittedUserIds, int $ownerId): array
    {
        if ($submittedUserIds === null || $submittedUserIds === '') {
            return ['userIds' => [], 'error' => null];
        }
        if (!is_array($submittedUserIds)) {
            return ['userIds' => [], 'error' => 'The list of tagged members is not valid.'];
        }

        $cleanUserIds = [];
        foreach ($submittedUserIds as $submittedUserId) {
            $userId = filter_var($submittedUserId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($userId === false) {
                return ['userIds' => [], 'error' => 'The list of tagged members is not valid.'];
            }
            $cleanUserIds[$userId] = $userId;
        }
        $cleanUserIds = array_values($cleanUserIds);

        if (in_array($ownerId, $cleanUserIds, true)) {
            return ['userIds' => [], 'error' => 'You do not need to tag yourself. You are shown as the owner.'];
        }
        if (count($cleanUserIds) > self::MAX_TAGS_PER_PHOTO) {
            return ['userIds' => [], 'error' => 'You can tag up to ' . self::MAX_TAGS_PER_PHOTO . ' members in one photo.'];
        }
        if ($cleanUserIds !== [] && $this->countExistingUsers($cleanUserIds) !== count($cleanUserIds)) {
            return ['userIds' => [], 'error' => 'One of the tagged members does not exist.'];
        }

        return ['userIds' => $cleanUserIds, 'error' => null];
    }

    /**
     * Saves tags for a photo. Ids must come from validateTaggedUsers().
     *
     * All rows are saved in one transaction: either every tag is saved or none.
     *
     * @param int             $photoId Photo id.
     * @param array<int, int> $userIds Member ids to tag.
     * @return void
     * @throws PDOException When an insert fails (nothing is saved).
     */
    public function addTags(int $photoId, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $this->database->beginTransaction();
        try {
            foreach ($userIds as $userId) {
                $this->runQuery(
                    'INSERT INTO photo_tags (photo_id, user_id) VALUES (:photoId, :userId)',
                    ['photoId' => $photoId, 'userId' => $userId]
                );
            }
            $this->database->commit();
        } catch (Throwable $insertError) {
            $this->database->rollBack();
            throw $insertError;
        }
    }

    /**
     * Returns the members tagged in one photo, sorted by name.
     *
     * @param int $photoId Photo id.
     * @return array<int, array<string, mixed>> Rows with user_id, first_name, last_name.
     * @throws PDOException When the query fails.
     */
    public function findByPhotoId(int $photoId): array
    {
        return $this->fetchAllRows(
            'SELECT photo_tags.user_id, users.first_name, users.last_name
             FROM photo_tags
             INNER JOIN users ON users.id = photo_tags.user_id
             WHERE photo_tags.photo_id = :photoId
             ORDER BY users.first_name, users.last_name',
            ['photoId' => $photoId]
        );
    }

    /**
     * Returns one page of photos in which a member is tagged, newest first.
     *
     * @param int $userId        Tagged member.
     * @param int $pageNumber    Page number, starting at 1.
     * @param int $photosPerPage Photos on each page.
     * @return array<int, array<string, mixed>> Photo rows in the same shape as Photo::findPage().
     * @throws PDOException When the query fails.
     */
    public function findPhotosOfUser(int $userId, int $pageNumber, int $photosPerPage): array
    {
        return $this->fetchAllRows(
            'SELECT photos.id, photos.file_name, photos.title, photos.description, photos.date_time,
                    users.first_name, users.last_name,
                    (SELECT COUNT(*) FROM comments WHERE comments.photo_id = photos.id) AS comment_count
             FROM photo_tags
             INNER JOIN photos ON photos.id = photo_tags.photo_id
             INNER JOIN users ON users.id = photos.user_id
             WHERE photo_tags.user_id = :userId
             ORDER BY photos.date_time DESC, photos.id DESC
             LIMIT :pageLimit OFFSET :pageOffset',
            [
                'userId'     => $userId,
                'pageLimit'  => max(1, $photosPerPage),
                'pageOffset' => (max(1, $pageNumber) - 1) * max(1, $photosPerPage),
            ]
        );
    }

    /**
     * Counts the photos in which a member is tagged.
     *
     * @param int $userId Tagged member.
     * @return int Number of photos.
     * @throws PDOException When the query fails.
     */
    public function countPhotosOfUser(int $userId): int
    {
        $countRow = $this->fetchOneRow(
            'SELECT COUNT(*) AS taggedPhotos FROM photo_tags WHERE user_id = :userId',
            ['userId' => $userId]
        );

        return (int) ($countRow['taggedPhotos'] ?? 0);
    }

    /**
     * Removes one tag.
     *
     * @param int $photoId Photo id.
     * @param int $userId  Tagged member.
     * @return bool True when a tag was removed; false when it did not exist.
     * @throws PDOException When the query fails.
     */
    public function removeTag(int $photoId, int $userId): bool
    {
        $statement = $this->runQuery(
            'DELETE FROM photo_tags WHERE photo_id = :photoId AND user_id = :userId',
            ['photoId' => $photoId, 'userId' => $userId]
        );

        return $statement->rowCount() > 0;
    }

    /**
     * Counts how many of the given ids belong to registered members.
     *
     * Named placeholders are built for each id (:userId0, :userId1, ...), so
     * the IN (...) list is still a prepared statement with bound values.
     *
     * @param array<int, int> $userIds Member ids (not empty).
     * @return int Number of ids that exist in the users table.
     * @throws PDOException When the query fails.
     */
    private function countExistingUsers(array $userIds): int
    {
        $placeholderNames = [];
        $queryParameters = [];
        foreach (array_values($userIds) as $position => $userId) {
            $placeholderNames[] = ':userId' . $position;
            $queryParameters['userId' . $position] = $userId;
        }

        $countRow = $this->fetchOneRow(
            'SELECT COUNT(*) AS existingUsers FROM users WHERE id IN (' . implode(', ', $placeholderNames) . ')',
            $queryParameters
        );

        return (int) ($countRow['existingUsers'] ?? 0);
    }
}
