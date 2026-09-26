<?php
declare(strict_types=1);

/**
 * Class CommentController
 *
 * Saves comments on photos. Any logged-in user may comment on any photo.
 *
 * One action answers two kinds of request:
 * - A normal form post (JavaScript off): redirect-after-post. On success the
 *   browser opens the photo page at the new comment; on error the message
 *   and the typed text are kept for one page view. Refresh never re-sends.
 * - A fetch() request with "Accept: application/json" (JavaScript on): a JSON
 *   answer with the new comment's HTML, so the page updates without reloading.
 *   The HTML comes from the same partial view the photo page uses.
 */
class CommentController extends Controller
{
    /**
     * Validates and saves a comment.
     *
     * Route: POST /photo/{id}/comments
     *
     * JSON answers: 200 {ok, commentHtml, commentCount}, 401 not logged in,
     * 403 bad CSRF token, 404 photo not found, 422 {ok: false, error}.
     *
     * @param int $photoId Photo id from the URL.
     * @return void
     * @throws PDOException When the database fails for a reason other than a deleted photo.
     */
    public function store(int $photoId): void
    {
        $photoPath = '/photo/' . $photoId;
        $wantsJson = $this->isJsonRequest();

        if ($wantsJson) {
            // A fetch() request cannot follow a redirect to a login page, so it gets a clear answer.
            if (!$this->isLoggedIn()) {
                $this->sendJson(['ok' => false, 'error' => 'Please log in again to comment.'], 401);
            }
            if (!$this->hasValidCsrfToken()) {
                $this->sendJson(['ok' => false, 'error' => 'Your form has expired. Please reload the page.'], 403);
            }
        } else {
            $this->requireLogin($photoPath);
            $this->requireValidCsrfToken($photoPath);
        }

        if ((new Photo())->findById($photoId) === null) {
            if ($wantsJson) {
                $this->sendJson(['ok' => false, 'error' => 'This photo was deleted.'], 404);
            }
            (new PhotoController())->notFound();

            return;
        }

        $rawText = is_string($_POST['commentText'] ?? null) ? $_POST['commentText'] : '';
        $commentText = Comment::normalizeText($rawText);

        $commentModel = new Comment();
        $validationError = $commentModel->validateText($commentText);

        if ($validationError !== null) {
            if ($wantsJson) {
                $this->sendJson(['ok' => false, 'error' => $validationError], 422);
            }
            $this->setFormState('comment', [
                'photoId' => $photoId,
                'error'   => $validationError,
                'oldText' => $commentText,
            ]);
            $this->redirect($photoPath . '#commentForm');
        }

        try {
            $newCommentId = $commentModel->create($photoId, (int) $this->getCurrentUserId(), $commentText);
        } catch (PDOException $insertError) {
            // 23000 = foreign key error: the photo was deleted a moment ago.
            if ($insertError->getCode() !== '23000') {
                throw $insertError;
            }
            if ($wantsJson) {
                $this->sendJson(['ok' => false, 'error' => 'This photo was deleted, so the comment was not saved.'], 404);
            }
            $this->setFlashMessage('This photo was deleted, so the comment was not saved.', 'warning');
            $this->redirect('/photos');
        }

        if ($wantsJson) {
            $this->sendJson([
                'ok'           => true,
                'commentHtml'  => $this->renderPartial('photos/comment_item', [
                    'photoComment' => $commentModel->findById($newCommentId),
                    'isNewComment' => true,
                ]),
                'commentCount' => $commentModel->countByPhotoId($photoId),
            ]);
        }

        $this->setFlashMessage('Your comment was added.', 'success');
        $this->redirect($photoPath . '#comment-' . $newCommentId);
    }
}
