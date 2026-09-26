<?php
/**
 * Login page. Also invites new visitors to register (Login/Register component).
 *
 * Variables from AuthController:
 * @var string                $emailValue       Email to show in the field.
 * @var array<string, string> $validationErrors Field name => message; key "form" for a general error.
 * @var string|null           $lastLoginText    Last login time from the cookie, or null.
 * @var string                $csrfToken        Token for the hidden form field.
 * @var string                $basePath         URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
$emailError = $validationErrors['email'] ?? '';
$passwordError = $validationErrors['password'] ?? '';
?>
<section class="page-section">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-6">
                <div class="auth-card">
                    <h1 class="page-title">
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Log in
                    </h1>

                    <?php if ($lastLoginText !== null): ?>
                        <p class="last-login-note">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            Last login from this computer was
                            <strong><?= htmlspecialchars($lastLoginText, ENT_QUOTES, 'UTF-8') ?></strong>.
                        </p>
                    <?php endif; ?>

                    <?php if (isset($validationErrors['form'])): ?>
                        <div class="alert alert-danger site-alert" role="alert">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <?= htmlspecialchars($validationErrors['form'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= $safeBasePath ?>/login" data-validate>
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email"
                                   class="form-control form-control-lg<?= $emailError !== '' ? ' is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
                                   required maxlength="100" autocomplete="email"
                                   data-label="Email" aria-describedby="emailError"
                                   <?= $emailError !== '' ? 'aria-invalid="true"' : '' ?>>
                            <div class="invalid-feedback" id="emailError"><?= htmlspecialchars($emailError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password"
                                   class="form-control form-control-lg<?= $passwordError !== '' ? ' is-invalid' : '' ?>"
                                   required autocomplete="current-password"
                                   data-label="Password" aria-describedby="passwordError"
                                   <?= $passwordError !== '' ? 'aria-invalid="true"' : '' ?>>
                            <div class="invalid-feedback" id="passwordError"><?= htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <button type="submit" class="btn btn-accent btn-lg-touch w-100 justify-content-center">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Log in
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="auth-side">
                    <h2><i class="fa-solid fa-user-plus" aria-hidden="true"></i> New to Alzikrayat?</h2>
                    <p>Create a free account to upload photos and comment on memories.</p>
                    <ul class="auth-side-list">
                        <li><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload your own photos</li>
                        <li><i class="fa-solid fa-comment" aria-hidden="true"></i> Comment on any photo</li>
                        <li><i class="fa-solid fa-lock" aria-hidden="true"></i> Your password is stored as a secure hash</li>
                    </ul>
                    <a class="btn btn-accent btn-lg-touch w-100 justify-content-center" href="<?= $safeBasePath ?>/register">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create an account
                    </a>
                </aside>
            </div>
        </div>
    </div>
</section>
