<?php
/**
 * 404 page. Shown for unknown addresses and for records that do not exist.
 *
 * @var string $basePath URL prefix of the app.
 */
?>
<section class="container page-section text-center not-found">
    <div class="not-found-frame" aria-hidden="true">
        <i class="fa-regular fa-image"></i>
    </div>
    <h1>This page is not in our album</h1>
    <p class="text-muted-custom">The address may be wrong, or the photo was deleted.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
        <a class="btn btn-accent btn-lg-touch" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/photos">
            <i class="fa-solid fa-images" aria-hidden="true"></i> Browse photos
        </a>
        <a class="btn btn-outline-primary-custom btn-lg-touch" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/">
            <i class="fa-solid fa-house" aria-hidden="true"></i> Go to home page
        </a>
    </div>
</section>
