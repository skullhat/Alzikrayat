<?php
declare(strict_types=1);

/**
 * Class PhotoController
 *
 * Handles the public pages (home, about, not found) and the photo
 * actions: gallery, upload form, saving an upload, detail page, delete,
 * and member tags (novelty): tagged-photos page and removing a tag.
 */
class PhotoController extends Controller
{
    /** Number of recent photos shown in the home page hero. */
    private const HOME_PREVIEW_COUNT = 3;

    /** Photos on one gallery page. 12 divides evenly into 3 and 4 columns. */
    private const PHOTOS_PER_PAGE = 12;

    /**
     * Shows the landing page with recent photos and site statistics.
     *
     * Route: GET /
     *
     * @return void
     * @throws RuntimeException When the database or the view cannot be loaded.
     */
    public function home(): void
    {
        $photoModel = new Photo();
        $userModel = new User();
        $commentModel = new Comment();

        $this->render('photos/home', [
            'latestPhotos'  => $photoModel->findLatest(self::HOME_PREVIEW_COUNT),
            'totalPhotos'   => $photoModel->countAll(),
            'totalUsers'    => $userModel->countAll(),
            'totalComments' => $commentModel->countAll(),
            'isLoggedIn'    => $this->isLoggedIn(),
        ], 'Alzikrayat - Share your memories');
    }

    /**
     * Shows the static About Us page.
     *
     * Route: GET /about
     *
     * @return void
     * @throws RuntimeException When the view cannot be loaded.
     */
    public function about(): void
    {
        $this->render('photos/about', [], 'About Alzikrayat');
    }

    /**
     * Shows one page of the photo gallery.
     *
     * Route: GET /photos (optional query: ?page=2)
     * A page number after the last page shows the 404 page.
     *
     * @return void
     * @throws PDOException When a query fails.
     */
    public function index(): void
    {
        $pageNumber = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($pageNumber === false) {
            $pageNumber = 1;
        }

        $photoModel = new Photo();
        $totalPhotos = $photoModel->countAll();
        $totalPages = max(1, (int) ceil($totalPhotos / self::PHOTOS_PER_PAGE));

        if ($pageNumber > $totalPages) {
            $this->notFound();

            return;
        }

        $this->render('photos/index', [
            'galleryPhotos'  => $photoModel->findPage($pageNumber, self::PHOTOS_PER_PAGE),
            'totalPhotos'    => $totalPhotos,
            'pageNumber'     => $pageNumber,
            'totalPages'     => $totalPages,
            'isLoggedIn'     => $this->isLoggedIn(),
            'galleryHeading' => 'Photo gallery',
            'galleryIntro'   => 'shared by our members.',
            'galleryPath'    => '/photos',
        ], 'Photo gallery - Alzikrayat');
    }

    /**
     * Shows the photos in which one member is tagged (novelty feature).
     *
     * Route: GET /photos/tagged/{userId} (optional query: ?page=2)
     * Uses the same gallery view and layouts as /photos.
     *
     * @param int $userId Member id from the URL.
     * @return void
     * @throws PDOException When a query fails.
     */
    public function tagged(int $userId): void
    {
        $memberProfile = (new User())->findPublicProfile($userId);
        if ($memberProfile === null) {
            $this->notFound();

            return;
        }

        $pageNumber = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pageNumber = $pageNumber === false ? 1 : $pageNumber;

        $tagModel = new PhotoTag();
        $totalPhotos = $tagModel->countPhotosOfUser($userId);
        $totalPages = max(1, (int) ceil($totalPhotos / self::PHOTOS_PER_PAGE));

        if ($pageNumber > $totalPages) {
            $this->notFound();

            return;
        }

        $memberName = $memberProfile['first_name'] . ' ' . $memberProfile['last_name'];
        $isOwnPage = $this->getCurrentUserId() === $userId;

        $this->render('photos/index', [
            'galleryPhotos'  => $tagModel->findPhotosOfUser($userId, $pageNumber, self::PHOTOS_PER_PAGE),
            'totalPhotos'    => $totalPhotos,
            'pageNumber'     => $pageNumber,
            'totalPages'     => $totalPages,
            'isLoggedIn'     => $this->isLoggedIn(),
            'galleryHeading' => $isOwnPage ? 'Photos of me' : 'Photos with ' . $memberName,
            'galleryIntro'   => $isOwnPage ? 'in which other members tagged you.' : 'in which ' . $memberName . ' is tagged.',
            'galleryPath'    => '/photos/tagged/' . $userId,
        ], ($isOwnPage ? 'Photos of me' : 'Photos with ' . $memberName) . ' - Alzikrayat');
    }

    /**
     * Shows the upload form. Logged-in users only.
     *
     * Route: GET /photos/create
     *
     * @return void
     */
    public function create(): void
    {
        $this->requireLogin();

        $this->renderUploadForm([], ['title' => '', 'description' => '', 'taggedUserIds' => []]);
    }

    /**
     * Validates and saves an uploaded photo, then opens its detail page.
     *
     * Route: POST /photos
     *
     * @return void
     * @throws PDOException When the database insert fails.
     */
    public function store(): void
    {
        $this->requireLogin();

        // When a request is bigger than PHP post_max_size, PHP drops ALL form
        // data, including the CSRF token. Detect it first to show the real reason.
        if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $tooLargeMessage = 'The photo is too large. The maximum size is ' . Photo::getMaxUploadText() . '.';

            // With display_errors on, PHP has already printed its own warning,
            // so a redirect header can no longer be sent. Show the form directly.
            if (headers_sent()) {
                $this->renderUploadForm(['photoFile' => $tooLargeMessage], ['title' => '', 'description' => '']);

                return;
            }

            $this->setFlashMessage($tooLargeMessage, 'danger');
            $this->redirect('/photos/create');
        }

        $this->requireValidCsrfToken('/photos/create');

        $title = $this->getPostString('title');
        $description = $this->getPostString('description');
        $uploadedFile = $_FILES['photoFile'] ?? null;

        $currentUserId = (int) $this->getCurrentUserId();
        $photoModel = new Photo();
        $tagModel = new PhotoTag();

        $validationErrors = $photoModel->validateMetadata($title, $description);
        $fileError = $photoModel->validateImageFile($uploadedFile);
        if ($fileError !== null) {
            $validationErrors['photoFile'] = $fileError;
        }
        $tagCheck = $tagModel->validateTaggedUsers($_POST['taggedUserIds'] ?? null, $currentUserId);
        if ($tagCheck['error'] !== null) {
            $validationErrors['taggedUserIds'] = $tagCheck['error'];
        }

        if ($validationErrors === []) {
            try {
                $newPhotoId = $photoModel->createFromUpload($uploadedFile, $currentUserId, $title, $description);
            } catch (RuntimeException $saveError) {
                // RuntimeException messages from the model are written for users.
                $validationErrors['photoFile'] = $saveError->getMessage();
            }
        }

        if (isset($newPhotoId)) {
            try {
                $tagModel->addTags($newPhotoId, $tagCheck['userIds']);
                $this->setFlashMessage('Your photo was uploaded.', 'success');
            } catch (PDOException $tagError) {
                // The photo is saved; only the tags failed (for example, a member was deleted a moment ago).
                error_log('[Alzikrayat] Saving tags failed for photo ' . $newPhotoId . ': ' . $tagError->getMessage());
                $this->setFlashMessage('Your photo was uploaded, but the tags could not be saved.', 'warning');
            }
            $this->redirect('/photo/' . $newPhotoId);
        }

        // Keep the chosen tags only when they were valid, so the form shows them again.
        http_response_code(422);
        $this->renderUploadForm($validationErrors, [
            'title'         => $title,
            'description'   => $description,
            'taggedUserIds' => $tagCheck['userIds'],
        ]);
    }

    /**
     * Shows one photo with its details and comments.
     *
     * Route: GET /photo/{id}
     *
     * @param int $photoId Photo id from the URL.
     * @return void
     * @throws PDOException When a query fails.
     */
    public function show(int $photoId): void
    {
        $photoRow = (new Photo())->findById($photoId);

        if ($photoRow === null) {
            $this->notFound();

            return;
        }

        // A failed comment (from CommentController) is shown once, and only on its own photo.
        $commentFormState = $this->takeFormState('comment');
        if (($commentFormState['photoId'] ?? null) !== $photoId) {
            $commentFormState = [];
        }

        $this->render('photos/show', [
            'photo'            => $photoRow,
            'photoComments'    => (new Comment())->findByPhotoId($photoId),
            'isOwner'          => $this->getCurrentUserId() === (int) $photoRow['user_id'],
            'isLoggedIn'       => $this->isLoggedIn(),
            'commentError'     => (string) ($commentFormState['error'] ?? ''),
            'commentOldText'   => (string) ($commentFormState['oldText'] ?? ''),
            'commentMaxLength' => Comment::TEXT_MAX_LENGTH,
            'photoTags'        => (new PhotoTag())->findByPhotoId($photoId),
            'currentUserId'    => $this->getCurrentUserId(),
        ], $photoRow['title'] . ' - Alzikrayat');
    }

    /**
     * Deletes a photo after checking that the logged-in user owns it.
     *
     * Route: POST /photo/{id}/delete
     *
     * @param int $photoId Photo id from the URL.
     * @return void
     * @throws PDOException When a query fails.
     */
    public function delete(int $photoId): void
    {
        $this->requireLogin();
        $this->requireValidCsrfToken('/photo/' . $photoId);

        $photoModel = new Photo();
        $photoRow = $photoModel->findById($photoId);

        if ($photoRow === null) {
            $this->notFound();

            return;
        }

        $currentUserId = (int) $this->getCurrentUserId();

        if ((int) $photoRow['user_id'] !== $currentUserId) {
            error_log(sprintf('[Alzikrayat] User %d tried to delete photo %d owned by user %d.',
                $currentUserId, $photoId, (int) $photoRow['user_id']));
            $this->setFlashMessage('You can only delete your own photos.', 'danger');
            $this->redirect('/photo/' . $photoId);
        }

        try {
            $wasDeleted = $photoModel->deleteOwnedPhoto($photoId, $currentUserId);
        } catch (RuntimeException $deleteError) {
            error_log('[Alzikrayat] Photo delete failed: ' . $deleteError->getMessage());
            $this->setFlashMessage('The photo could not be deleted. Please try again.', 'danger');
            $this->redirect('/photo/' . $photoId);
        }

        $this->setFlashMessage(
            $wasDeleted ? 'Your photo and its comments were deleted.' : 'This photo was already deleted.',
            $wasDeleted ? 'success' : 'info'
        );
        $this->redirect('/photos');
    }

    /**
     * Removes a member tag from a photo (novelty feature).
     *
     * Allowed for the photo owner and for the tagged member (so people can
     * remove themselves from photos they do not want to be tagged in).
     *
     * Route: POST /photo/{id}/tags/{userId}/delete
     *
     * @param int $photoId Photo id from the URL.
     * @param int $userId  Tagged member id from the URL.
     * @return void
     * @throws PDOException When a query fails.
     */
    public function removeTag(int $photoId, int $userId): void
    {
        $photoPath = '/photo/' . $photoId;
        $this->requireLogin($photoPath);
        $this->requireValidCsrfToken($photoPath);

        $photoRow = (new Photo())->findById($photoId);
        if ($photoRow === null) {
            $this->notFound();

            return;
        }

        $currentUserId = (int) $this->getCurrentUserId();
        $isOwner = (int) $photoRow['user_id'] === $currentUserId;
        $isTaggedMember = $userId === $currentUserId;

        if (!$isOwner && !$isTaggedMember) {
            error_log(sprintf('[Alzikrayat] User %d tried to remove the tag of user %d from photo %d.', $currentUserId, $userId, $photoId));
            $this->setFlashMessage('Only the photo owner or the tagged member can remove a tag.', 'danger');
            $this->redirect($photoPath);
        }

        $wasRemoved = (new PhotoTag())->removeTag($photoId, $userId);
        $this->setFlashMessage(
            $wasRemoved ? ($isTaggedMember ? 'You are no longer tagged in this photo.' : 'The tag was removed.') : 'This tag was already removed.',
            $wasRemoved ? 'success' : 'info'
        );
        $this->redirect($photoPath . '#photoTags');
    }

    /**
     * Shows the styled 404 page inside the normal layout.
     *
     * The Router calls this for unknown paths (see public/index.php).
     * Actions also call it when a record is not found.
     *
     * @return void
     * @throws RuntimeException When the view cannot be loaded.
     */
    public function notFound(): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }

        $this->render('layout/not_found', [], 'Page not found - Alzikrayat');
    }

    /**
     * Shows the upload form with errors and the values typed before.
     *
     * @param array<string, string> $validationErrors Field name => message.
     * @param array<string, mixed>  $oldInput         Previous title, description, and tagged member ids.
     * @return void
     */
    private function renderUploadForm(array $validationErrors, array $oldInput): void
    {
        $this->render('photos/create', [
            'validationErrors' => $validationErrors,
            'oldInput'         => $oldInput,
            'taggableUsers'    => (new User())->findTaggableUsers((int) $this->getCurrentUserId()),
            'maxTags'          => PhotoTag::MAX_TAGS_PER_PHOTO,
            'maxFileBytes'     => Photo::getMaxUploadBytes(),
            'maxFileText'      => Photo::getMaxUploadText(),
        ], 'Upload a photo - Alzikrayat');
    }
}
