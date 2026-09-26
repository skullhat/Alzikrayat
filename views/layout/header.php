<?php
/**
 * Layout header: HTML head, skip link, navbar, and flash message.
 *
 * Variables from Controller::render():
 * @var string      $pageTitle            Text for the browser tab.
 * @var string|null $currentUserFirstName First name of the logged-in user, or null.
 * @var int|null    $currentUserId        Id of the logged-in user, or null.
 * @var string      $csrfToken            Token for the logout form.
 * @var array|null  $flashMessage         One-time message: ['text' => ..., 'type' => ...].
 * @var string      $basePath             URL prefix of the app.
 * @var string      $currentPath          Path of this page, used to mark the active link.
 */
$navigationLinks = [
    ['path' => '/',       'label' => 'Home',   'icon' => 'fa-house'],
    ['path' => '/photos', 'label' => 'Photos', 'icon' => 'fa-images'],
    ['path' => '/about',  'label' => 'About',  'icon' => 'fa-circle-info'],
];
if ($currentUserId !== null) {
    // Members also get a link to the photos in which they are tagged.
    array_splice($navigationLinks, 2, 0, [
        ['path' => '/photos/tagged/' . (int) $currentUserId, 'label' => 'Photos of me', 'icon' => 'fa-user-tag'],
    ]);
}
$flashIcons = [
    'success' => 'fa-circle-check',
    'danger'  => 'fa-circle-exclamation',
    'warning' => 'fa-triangle-exclamation',
    'info'    => 'fa-circle-info',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/images/favicon.svg">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/css/style.css">
</head>
<body>
<a class="skip-link" href="#mainContent">Skip to main content</a>

<nav class="navbar navbar-expand-lg site-navbar" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand site-brand" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/">
            <i class="fa-solid fa-camera-retro" aria-hidden="true"></i>
            <span>Alzikrayat</span>
        </a>

        <button class="navbar-toggler site-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavigation" aria-controls="mainNavigation"
                aria-expanded="false" aria-label="Open menu">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($navigationLinks as $navigationLink): ?>
                    <?php $isActiveLink = $currentPath === $navigationLink['path']; ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $isActiveLink ? ' active' : '' ?>"
                           href="<?= htmlspecialchars($basePath . $navigationLink['path'], ENT_QUOTES, 'UTF-8') ?>"
                           <?= $isActiveLink ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid <?= $navigationLink['icon'] ?>" aria-hidden="true"></i>
                            <?= htmlspecialchars($navigationLink['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="navbar-account">
                <?php if ($currentUserFirstName !== null): ?>
                    <a class="btn btn-accent btn-sm-touch"
                       href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/photos/create">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload
                    </a>
                    <span class="navbar-greeting">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        Hi, <?= htmlspecialchars($currentUserFirstName, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <form method="post" action="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/logout" class="d-inline">
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-outline-light btn-sm-touch">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout
                        </button>
                    </form>
                <?php else: ?>
                    <span class="navbar-greeting">
                        <i class="fa-solid fa-user-lock" aria-hidden="true"></i> Please Login
                    </span>
                    <a class="btn btn-accent btn-sm-touch<?= $currentPath === '/login' ? ' active' : '' ?>"
                       href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/login">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Login
                    </a>
                    <a class="btn btn-outline-light btn-sm-touch"
                       href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/register">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main id="mainContent" tabindex="-1">
    <?php if ($flashMessage !== null): ?>
        <div class="container pt-3">
            <div class="alert alert-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?> site-alert" role="alert">
                <i class="fa-solid <?= $flashIcons[$flashMessage['type']] ?? 'fa-circle-info' ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($flashMessage['text'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    <?php endif; ?>
