<?php
/**
 * Photo detail page: full image, details, owner actions, and comments.
 *
 * Variables from PhotoController::show():
 * @var array<string, mixed>             $photo         The photo row with owner names.
 * @var array<int, array<string, mixed>> $photoComments Comments, oldest first.
 * @var bool                             $isOwner       True when the logged-in user owns the photo.
 * @var bool                             $isLoggedIn    True when a user is logged in.
 * @var string                           $commentError     Error from the last comment attempt, or empty.
 * @var string                           $commentOldText   Text typed in the last failed attempt, or empty.
 * @var int                              $commentMaxLength Largest allowed comment length.
 * @var string|null                      $currentUserFirstName First name of the logged-in user.
 * @var array<int, array<string, mixed>> $photoTags     Tagged members (user_id, first_name, last_name).
 * @var int|null                         $currentUserId Id of the logged-in user, or null.
 * @var string                           $csrfToken     Token for the delete, tag, and comment forms.
 * @var string                           $basePath      URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
$photoId = (int) $photo['id'];
$imageUrl = $safeBasePath . '/images/uploads/' . htmlspecialchars(rawurlencode((string) $photo['file_name']), ENT_QUOTES, 'UTF-8');
$uploadTime = strtotime((string) $photo['date_time']);
$commentCount = count($photoComments);
?>
<section class="page-section photo-detail">
    <div class="container">
        <p class="mb-3">
            <a href="<?= $safeBasePath ?>/photos"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to the gallery</a>
        </p>

        <div class="row g-4">
            <div class="col-lg-8">
                <figure class="photo-detail-frame">
                    <a href="<?= $imageUrl ?>" target="_blank" rel="noopener" title="Open the full-size photo in a new tab">
                        <img src="<?= $imageUrl ?>" alt="<?= htmlspecialchars((string) $photo['title'], ENT_QUOTES, 'UTF-8') ?>">
                    </a>
                </figure>
            </div>

            <div class="col-lg-4">
                <div class="info-panel">
                    <h1 class="photo-detail-title"><?= htmlspecialchars((string) $photo['title'], ENT_QUOTES, 'UTF-8') ?></h1>

                    <ul class="info-list photo-detail-meta">
                        <li><i class="fa-solid fa-user" aria-hidden="true"></i>
                            <?= htmlspecialchars($photo['first_name'] . ' ' . $photo['last_name'], ENT_QUOTES, 'UTF-8') ?></li>
                        <li><i class="fa-regular fa-clock" aria-hidden="true"></i>
                            <time datetime="<?= htmlspecialchars(date('c', $uploadTime), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(date('j F Y, g:i A', $uploadTime), ENT_QUOTES, 'UTF-8') ?>
                            </time></li>
                        <li><i class="fa-regular fa-comment" aria-hidden="true"></i>
                            <span id="photoCommentCount"><?= $commentCount ?> <?= $commentCount === 1 ? 'comment' : 'comments' ?></span></li>
                    </ul>

                    <?php if ($photoTags !== []): ?>
                        <div class="photo-tags" id="photoTags">
                            <p class="photo-tags-label">
                                <i class="fa-solid fa-user-tag" aria-hidden="true"></i> In this photo:
                            </p>
                            <ul class="tag-chips">
                                <?php foreach ($photoTags as $photoTag): ?>
                                    <?php
                                    $taggedUserId = (int) $photoTag['user_id'];
                                    $taggedName = htmlspecialchars($photoTag['first_name'] . ' ' . $photoTag['last_name'], ENT_QUOTES, 'UTF-8');
                                    $canRemoveTag = $isOwner || $currentUserId === $taggedUserId;
                                    ?>
                                    <li class="tag-chip">
                                        <a href="<?= $safeBasePath ?>/photos/tagged/<?= $taggedUserId ?>"
                                           title="See all photos with <?= $taggedName ?>"><?= $taggedName ?></a>
                                        <?php if ($canRemoveTag): ?>
                                            <form method="post" action="<?= $safeBasePath ?>/photo/<?= $photoId ?>/tags/<?= $taggedUserId ?>/delete">
                                                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="tag-chip-remove"
                                                        aria-label="Remove the tag of <?= $taggedName ?>"
                                                        title="<?= $currentUserId === $taggedUserId ? 'Remove my tag' : 'Remove this tag' ?>">
                                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ((string) $photo['description'] !== ''): ?>
                        <p class="photo-detail-description"><?= nl2br(htmlspecialchars((string) $photo['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php endif; ?>

                    <a class="btn btn-outline-primary-custom btn-sm-touch" href="<?= $imageUrl ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Open full size
                    </a>

                    <?php if ($isOwner): ?>
                        <form method="post" action="<?= $safeBasePath ?>/photo/<?= $photoId ?>/delete" class="owner-actions"
                              data-confirm="Delete this photo and all its comments? This cannot be undone.">
                            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-delete btn-lg-touch">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete photo
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-8">
                <h2 class="section-title" id="comments">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i> Comments
                </h2>

                <p class="text-muted-custom" id="noCommentsText"<?= $photoComments === [] ? '' : ' hidden' ?>>No comments yet.</p>
                <!-- The list always exists, so photos.js can add a new comment to it. -->
                <ol class="comment-list" id="commentList"<?= $photoComments === [] ? ' hidden' : '' ?>>
                    <?php foreach ($photoComments as $photoComment): ?>
                        <?php require __DIR__ . '/comment_item.php'; ?>
                    <?php endforeach; ?>
                </ol>
                <!-- Screen readers announce messages written here by photos.js. -->
                <p class="visually-hidden" id="commentStatus" role="status" aria-live="polite"></p>

                <?php if ($isLoggedIn): ?>
                    <form method="post" action="<?= $safeBasePath ?>/photo/<?= $photoId ?>/comments"
                          class="comment-form" id="commentForm" data-validate data-ajax-comment>
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="commentText" class="form-label">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            Write a comment as <?= htmlspecialchars((string) $currentUserFirstName, ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <textarea id="commentText" name="commentText" rows="3"
                                  class="form-control<?= $commentError !== '' ? ' is-invalid' : '' ?>"
                                  required maxlength="<?= (int) $commentMaxLength ?>"
                                  data-label="Comment" data-counter="commentCounter"
                                  aria-describedby="commentTextError commentCounter"
                                  <?= $commentError !== '' ? 'aria-invalid="true"' : '' ?>><?= htmlspecialchars($commentOldText, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="invalid-feedback" id="commentTextError"><?= htmlspecialchars($commentError, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="comment-form-footer">
                            <span class="form-text" id="commentCounter">Up to <?= (int) $commentMaxLength ?> characters.</span>
                            <button type="submit" class="btn btn-accent btn-lg-touch">
                                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Post comment
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-muted-custom">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <a href="<?= $safeBasePath ?>/login?returnTo=<?= rawurlencode('/photo/' . $photoId) ?>">Log in</a> to write a comment.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
