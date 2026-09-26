<?php
/**
 * Layout footer: closes the main area, shows site links, and loads JavaScript.
 *
 * @var string $basePath URL prefix of the app.
 */
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row gy-3 align-items-center">
            <div class="col-md-6">
                <p class="site-footer-brand">
                    <i class="fa-solid fa-camera-retro" aria-hidden="true"></i> Alzikrayat
                </p>
                <p class="site-footer-note">
                    A photo sharing project for the Advanced Web Technologies course,
                    Sudan University of Science and Technology.
                </p>
            </div>
            <div class="col-md-6">
                <ul class="site-footer-links">
                    <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/">Home</a></li>
                    <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/photos">Photos</a></li>
                    <li><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/about">About</a></li>
                </ul>
                <p class="site-footer-note text-md-end">&copy; <?= date('Y') ?> Alzikrayat</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/js/validation.js"></script>
<script src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/js/photos.js"></script>
</body>
</html>
