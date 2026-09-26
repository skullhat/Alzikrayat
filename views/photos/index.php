<?php
/**
 * Photo gallery with a layout switcher (3 columns, 4 columns, list, slideshow).
 * Also used for "Photos of me" / "Photos with <name>" (member tags).
 *
 * Variables from PhotoController::index():
 * @var array<int, array<string, mixed>> $galleryPhotos Photos on this page.
 * @var int                              $totalPhotos   Number of photos on all pages.
 * @var int                              $pageNumber    Current page (starts at 1).
 * @var int                              $totalPages    Number of pages.
 * @var bool                             $isLoggedIn    True when a user is logged in.
 * @var string                           $galleryHeading Page heading, for example "Photo gallery" or "Photos of me".
 * @var string                           $galleryIntro   Text after the photo count, for example "shared by our members."
 * @var string                           $galleryPath    Path of this gallery, used for page links.
 * @var string                           $basePath      URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
$layoutOptions = [
    ['name' => 'grid3', 'label' => '3 columns', 'icon' => 'fa-table-cells-large'],
    ['name' => 'grid4', 'label' => '4 columns', 'icon' => 'fa-table-cells'],
    ['name' => 'list',  'label' => 'List',      'icon' => 'fa-list'],
    ['name' => 'slideshow', 'label' => 'Slideshow', 'icon' => 'fa-film'],
];
?>
<section class="page-section">
    <div class="container">
        <div class="gallery-header">
            <div>
                <h1 class="page-title mb-1">
                    <i class="fa-solid <?= $galleryPath === '/photos' ? 'fa-images' : 'fa-user-tag' ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($galleryHeading, ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <p class="text-muted-custom mb-0">
                    <?= number_format($totalPhotos) ?> <?= $totalPhotos === 1 ? 'photo' : 'photos' ?>
                    <?= htmlspecialchars($galleryIntro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div class="gallery-tools">
                <!-- Hidden until photos.js runs, so it never shows as a dead control. -->
                <div class="layout-switcher" role="group" aria-label="Choose gallery layout" hidden>
                    <?php foreach ($layoutOptions as $layoutOption): ?>
                        <button type="button" class="layout-button" data-layout="<?= $layoutOption['name'] ?>"
                                aria-pressed="<?= $layoutOption['name'] === 'grid3' ? 'true' : 'false' ?>"
                                title="<?= $layoutOption['label'] ?>">
                            <i class="fa-solid <?= $layoutOption['icon'] ?>" aria-hidden="true"></i>
                            <span class="layout-button-text"><?= $layoutOption['label'] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if ($isLoggedIn): ?>
                    <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/photos/create">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload a photo
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($galleryPhotos === []): ?>
            <div class="empty-state">
                <i class="fa-regular fa-images" aria-hidden="true"></i>
                <h2>No photos yet</h2>
                <?php if ($galleryPath !== '/photos'): ?>
                    <p>Nobody has been tagged here yet.</p>
                    <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/photos">
                        <i class="fa-solid fa-images" aria-hidden="true"></i> Browse all photos
                    </a>
                <?php elseif ($isLoggedIn): ?>
                    <p>Be the first to share a memory.</p>
                    <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/photos/create">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload the first photo
                    </a>
                <?php else: ?>
                    <p>Log in to share the first memory.</p>
                    <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/login">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Log in
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="photo-gallery layout-grid3" id="photoGallery">
                <?php foreach ($galleryPhotos as $galleryPhoto): ?>
                    <?php
                    $photoUrl = $safeBasePath . '/photo/' . (int) $galleryPhoto['id'];
                    $commentCount = (int) $galleryPhoto['comment_count'];
                    ?>
                    <li class="photo-card">
                        <a class="photo-card-image" href="<?= $photoUrl ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= $safeBasePath ?>/images/uploads/<?= htmlspecialchars(rawurlencode((string) $galleryPhoto['file_name']), ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" loading="lazy">
                        </a>
                        <div class="photo-card-body">
                            <h2 class="photo-card-title">
                                <a href="<?= $photoUrl ?>"><?= htmlspecialchars((string) $galleryPhoto['title'], ENT_QUOTES, 'UTF-8') ?></a>
                            </h2>
                            <?php if ((string) $galleryPhoto['description'] !== ''): ?>
                                <p class="photo-card-text"><?= htmlspecialchars((string) $galleryPhoto['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <p class="photo-card-meta">
                                <span><i class="fa-solid fa-user" aria-hidden="true"></i>
                                    <?= htmlspecialchars($galleryPhoto['first_name'] . ' ' . $galleryPhoto['last_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span><i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <time datetime="<?= htmlspecialchars(date('c', strtotime((string) $galleryPhoto['date_time'])), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(date('j M Y', strtotime((string) $galleryPhoto['date_time'])), ENT_QUOTES, 'UTF-8') ?>
                                    </time></span>
                                <span><i class="fa-regular fa-comment" aria-hidden="true"></i>
                                    <?= $commentCount ?> <?= $commentCount === 1 ? 'comment' : 'comments' ?></span>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Shown by photos.js only in the slideshow layout. -->
            <div class="slideshow-controls" id="slideshowControls" hidden>
                <button type="button" class="btn btn-outline-primary-custom btn-sm-touch" id="previousSlide" aria-controls="photoGallery">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Previous
                </button>
                <span class="slide-counter" id="slideCounter" aria-live="polite">1 / <?= count($galleryPhotos) ?></span>
                <button type="button" class="btn btn-outline-primary-custom btn-sm-touch" id="nextSlide" aria-controls="photoGallery">
                    Next <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="gallery-pages" aria-label="Gallery pages">
                    <?php if ($pageNumber > 1): ?>
                        <a class="btn btn-outline-primary-custom btn-sm-touch" href="<?= $safeBasePath . htmlspecialchars($galleryPath, ENT_QUOTES, 'UTF-8') ?>?page=<?= $pageNumber - 1 ?>">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Newer photos
                        </a>
                    <?php endif; ?>
                    <span class="gallery-page-info">Page <?= $pageNumber ?> of <?= $totalPages ?></span>
                    <?php if ($pageNumber < $totalPages): ?>
                        <a class="btn btn-outline-primary-custom btn-sm-touch" href="<?= $safeBasePath . htmlspecialchars($galleryPath, ENT_QUOTES, 'UTF-8') ?>?page=<?= $pageNumber + 1 ?>">
                            Older photos <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
