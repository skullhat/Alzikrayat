<?php
/**
 * About Us page (static content).
 *
 * @var string $basePath URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
?>
<section class="page-section">
    <div class="container about-page">
        <h1 class="page-title">About Alzikrayat</h1>
        <p class="home-lead">
            "Alzikrayat" (<span lang="ar" dir="rtl">الذكريات</span>) means "the memories" in Arabic. It is a small photo
            sharing website where people save photos and talk about the moments behind them.
        </p>

        <div class="row gy-4 mt-2">
            <div class="col-md-6">
                <div class="info-panel">
                    <h2><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Project background</h2>
                    <p>
                        Alzikrayat is Course Project 1 of the Advanced Web Technologies course,
                        College of Computer Science and Information Technology, Sudan University
                        of Science and Technology.
                    </p>
                    <p>
                        The goal of the project is to understand how a web application works
                        inside, so no ready-made framework was used.
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-panel">
                    <h2><i class="fa-solid fa-camera" aria-hidden="true"></i> What you can do</h2>
                    <ul class="info-list">
                        <li><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload JPG, PNG, and WebP photos.</li>
                        <li><i class="fa-solid fa-table-cells" aria-hidden="true"></i> View the gallery in different layouts.</li>
                        <li><i class="fa-solid fa-comment" aria-hidden="true"></i> Comment on any photo.</li>
                        <li><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete your own photos at any time.</li>
                    </ul>
                </div>
            </div>
            <div class="col-12">
                <div class="info-panel">
                    <h2><i class="fa-solid fa-code" aria-hidden="true"></i> How it is built</h2>
                    <ul class="info-list info-list-columns">
                        <li><i class="fa-brands fa-php" aria-hidden="true"></i> Pure PHP with a custom MVC structure</li>
                        <li><i class="fa-solid fa-route" aria-hidden="true"></i> A hand-written router based on regular expressions</li>
                        <li><i class="fa-solid fa-database" aria-hidden="true"></i> MySQL with PDO prepared statements</li>
                        <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Hashed passwords, CSRF tokens, and safe output</li>
                        <li><i class="fa-brands fa-bootstrap" aria-hidden="true"></i> Bootstrap 5 for a responsive layout</li>
                        <li><i class="fa-solid fa-icons" aria-hidden="true"></i> Font Awesome icons</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <a class="btn btn-accent btn-lg-touch" href="<?= $safeBasePath ?>/photos">
                <i class="fa-solid fa-images" aria-hidden="true"></i> Browse photos
            </a>
        </div>
    </div>
</section>
