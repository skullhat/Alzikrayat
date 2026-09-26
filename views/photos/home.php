<?php
/**
 * Home page (landing page).
 *
 * Variables from PhotoController::home():
 * @var array<int, array<string, mixed>> $latestPhotos  Newest photos with owner names (up to 3).
 * @var int                              $totalPhotos   Number of photos.
 * @var int                              $totalUsers    Number of members.
 * @var int                              $totalComments Number of comments.
 * @var bool                             $isLoggedIn    True when a user is logged in.
 * @var string                           $basePath      URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
$emptyFrameCount = 3 - count($latestPhotos);
?>
<section class="home-hero">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <h1 class="home-title">Every photo keeps a memory.</h1>
                <p class="home-lead">
                    Alzikrayat is a place to save your photos, tell the story behind each one,
                    and talk about them with other people.
                </p>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <?php if ($isLoggedIn): ?>
                        <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/photos/create">
                            <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload a photo
                        </a>
                    <?php else: ?>
                        <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/register">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create an account
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary-custom btn-lg-touch" href="<?= $safeBasePath ?>/photos">
                        <i class="fa-solid fa-images" aria-hidden="true"></i> Browse photos
                    </a>
                </div>

                <dl class="home-stats" aria-label="Site statistics">
                    <div class="home-stat">
                        <dt><i class="fa-solid fa-image" aria-hidden="true"></i> Photos shared</dt>
                        <dd><?= number_format($totalPhotos) ?></dd>
                    </div>
                    <div class="home-stat">
                        <dt><i class="fa-solid fa-users" aria-hidden="true"></i> Members</dt>
                        <dd><?= number_format($totalUsers) ?></dd>
                    </div>
                    <div class="home-stat">
                        <dt><i class="fa-solid fa-comments" aria-hidden="true"></i> Comments</dt>
                        <dd><?= number_format($totalComments) ?></dd>
                    </div>
                </dl>
            </div>

            <div class="col-lg-6">
                <div class="memory-stack">
                    <?php foreach ($latestPhotos as $latestPhoto): ?>
                        <a class="memory-frame" href="<?= $safeBasePath ?>/photo/<?= (int) $latestPhoto['id'] ?>">
                            <img src="<?= $safeBasePath ?>/images/uploads/<?= htmlspecialchars(rawurlencode((string) $latestPhoto['file_name']), ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars((string) $latestPhoto['title'], ENT_QUOTES, 'UTF-8') ?>"
                                 loading="lazy">
                            <span class="memory-caption">
                                <?= htmlspecialchars((string) $latestPhoto['title'], ENT_QUOTES, 'UTF-8') ?>
                                <small>
                                    by <?= htmlspecialchars($latestPhoto['first_name'] . ' ' . $latestPhoto['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </span>
                        </a>
                    <?php endforeach; ?>

                    <?php for ($frameNumber = 0; $frameNumber < $emptyFrameCount; $frameNumber++): ?>
                        <div class="memory-frame memory-frame-empty" aria-hidden="true">
                            <span class="memory-placeholder"><i class="fa-solid fa-camera"></i></span>
                            <span class="memory-caption">A new memory</span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="page-section">
    <div class="container">
        <h2 class="section-title">How it works</h2>
        <ol class="step-list">
            <li class="step-item">
                <i class="fa-solid fa-user-plus step-icon" aria-hidden="true"></i>
                <h3>Create an account</h3>
                <p>Register with your name and email. It takes one minute.</p>
            </li>
            <li class="step-item">
                <i class="fa-solid fa-cloud-arrow-up step-icon" aria-hidden="true"></i>
                <h3>Upload a photo</h3>
                <p>Add a title and the story behind it. JPG, PNG, and WebP are supported.</p>
            </li>
            <li class="step-item">
                <i class="fa-solid fa-comment-dots step-icon" aria-hidden="true"></i>
                <h3>Share and comment</h3>
                <p>Everyone can see your photo. Members can leave comments on any photo.</p>
            </li>
        </ol>
    </div>
</section>

<section class="page-section about-teaser" id="about">
    <div class="container">
        <div class="row gy-3 align-items-center">
            <div class="col-lg-8">
                <h2 class="section-title">About us</h2>
                <p>
                    "Alzikrayat" means "the memories" in Arabic. The project was built for the
                    Advanced Web Technologies course at Sudan University of Science and Technology.
                    It is written from scratch with pure PHP, a custom MVC structure, and MySQL.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a class="btn btn-outline-primary-custom btn-lg-touch" href="<?= $safeBasePath ?>/about">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i> Read about the project
                </a>
            </div>
        </div>
    </div>
</section>
