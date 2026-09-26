<?php
/**
 * One comment in the comment list (partial view, no layout).
 *
 * Used by views/photos/show.php for every comment, and by
 * CommentController for the JSON answer after a comment is posted
 * without a page reload. One template means one place for the markup
 * and its escaping.
 *
 * @var array<string, mixed> $photoComment Row with id, comment, date_time, first_name, last_name.
 * @var bool                 $isNewComment Optional. True adds the short highlight.
 */
$commentTime = strtotime((string) $photoComment['date_time']);
?>
<li class="comment-item<?= !empty($isNewComment) ? ' comment-new' : '' ?>" id="comment-<?= (int) $photoComment['id'] ?>">
    <p class="comment-text"><?= nl2br(htmlspecialchars((string) $photoComment['comment'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p class="comment-meta">
        <i class="fa-solid fa-user" aria-hidden="true"></i>
        <?= htmlspecialchars($photoComment['first_name'] . ' ' . $photoComment['last_name'], ENT_QUOTES, 'UTF-8') ?>
        <span class="comment-time">
            <i class="fa-regular fa-clock" aria-hidden="true"></i>
            <time datetime="<?= htmlspecialchars(date('c', $commentTime), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(date('j M Y, g:i A', $commentTime), ENT_QUOTES, 'UTF-8') ?>
            </time>
        </span>
    </p>
</li>
