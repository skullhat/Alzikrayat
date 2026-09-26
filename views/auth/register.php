<?php
/**
 * Registration page.
 *
 * Variables from AuthController:
 * @var array<string, string> $oldInput         Values typed before (never the passwords).
 * @var array<string, string> $validationErrors Field name => message.
 * @var string                $csrfToken        Token for the hidden form field.
 * @var string                $basePath         URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');

/*
 * Small display values for each field: the old value (escaped), the error
 * text (escaped), and the extra CSS class. Kept here so the markup below
 * stays short. No business logic happens in this view.
 */
$fieldState = [];
foreach (['firstName', 'lastName', 'email', 'password', 'passwordConfirm', 'location', 'occupation', 'description'] as $fieldName) {
    $errorText = $validationErrors[$fieldName] ?? '';
    $fieldState[$fieldName] = [
        'value'     => htmlspecialchars($oldInput[$fieldName] ?? '', ENT_QUOTES, 'UTF-8'),
        'error'     => htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8'),
        'class'     => $errorText !== '' ? ' is-invalid' : '',
        'ariaValid' => $errorText !== '' ? ' aria-invalid="true"' : '',
    ];
}
?>
<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="auth-card">
                    <h1 class="page-title">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create an account
                    </h1>
                    <p class="text-muted-custom">
                        Already have an account? <a href="<?= $safeBasePath ?>/login">Log in here</a>.
                    </p>

                    <?php if ($validationErrors !== []): ?>
                        <div class="alert alert-danger site-alert" role="alert">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            Please fix the fields marked in red.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= $safeBasePath ?>/register" data-validate>
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="firstName" class="form-label">First name <span class="required-mark" aria-hidden="true">*</span></label>
                                <input type="text" id="firstName" name="firstName"
                                       class="form-control form-control-lg<?= $fieldState['firstName']['class'] ?>"
                                       value="<?= $fieldState['firstName']['value'] ?>"
                                       required maxlength="50" pattern="[\p{L}\p{M}]+" autocomplete="given-name"
                                       data-label="First name" data-rule="name"
                                       aria-describedby="nameHint firstNameError"<?= $fieldState['firstName']['ariaValid'] ?>>
                                <div class="invalid-feedback" id="firstNameError"><?= $fieldState['firstName']['error'] ?></div>
                            </div>
                            <div class="col-md-6">
                                <label for="lastName" class="form-label">Last name <span class="required-mark" aria-hidden="true">*</span></label>
                                <input type="text" id="lastName" name="lastName"
                                       class="form-control form-control-lg<?= $fieldState['lastName']['class'] ?>"
                                       value="<?= $fieldState['lastName']['value'] ?>"
                                       required maxlength="50" pattern="[\p{L}\p{M}]+" autocomplete="family-name"
                                       data-label="Last name" data-rule="name"
                                       aria-describedby="nameHint lastNameError"<?= $fieldState['lastName']['ariaValid'] ?>>
                                <div class="invalid-feedback" id="lastNameError"><?= $fieldState['lastName']['error'] ?></div>
                            </div>
                            <div class="col-12">
                                <p class="form-text mt-0" id="nameHint">Letters only, up to 50 characters. Arabic and English letters are both fine.</p>
                            </div>

                            <div class="col-12">
                                <label for="email" class="form-label">Email <span class="required-mark" aria-hidden="true">*</span></label>
                                <input type="email" id="email" name="email"
                                       class="form-control form-control-lg<?= $fieldState['email']['class'] ?>"
                                       value="<?= $fieldState['email']['value'] ?>"
                                       required maxlength="100" autocomplete="email"
                                       data-label="Email" aria-describedby="emailError"<?= $fieldState['email']['ariaValid'] ?>>
                                <div class="invalid-feedback" id="emailError"><?= $fieldState['email']['error'] ?></div>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="required-mark" aria-hidden="true">*</span></label>
                                <input type="password" id="password" name="password"
                                       class="form-control form-control-lg<?= $fieldState['password']['class'] ?>"
                                       required minlength="8" maxlength="72" autocomplete="new-password"
                                       data-label="Password" data-rule="password"
                                       aria-describedby="passwordHint passwordError"<?= $fieldState['password']['ariaValid'] ?>>
                                <div class="invalid-feedback" id="passwordError"><?= $fieldState['password']['error'] ?></div>
                                <p class="form-text" id="passwordHint">At least 8 characters, with a letter and a number.</p>
                            </div>
                            <div class="col-md-6">
                                <label for="passwordConfirm" class="form-label">Repeat password <span class="required-mark" aria-hidden="true">*</span></label>
                                <input type="password" id="passwordConfirm" name="passwordConfirm"
                                       class="form-control form-control-lg<?= $fieldState['passwordConfirm']['class'] ?>"
                                       required autocomplete="new-password"
                                       data-label="Repeat password" data-match="password"
                                       aria-describedby="passwordConfirmError"<?= $fieldState['passwordConfirm']['ariaValid'] ?>>
                                <div class="invalid-feedback" id="passwordConfirmError"><?= $fieldState['passwordConfirm']['error'] ?></div>
                            </div>
                        </div>

                        <fieldset class="optional-fields">
                            <legend>About you <span class="text-muted-custom">(optional)</span></legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="location" class="form-label">
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i> Location
                                    </label>
                                    <input type="text" id="location" name="location"
                                           class="form-control<?= $fieldState['location']['class'] ?>"
                                           value="<?= $fieldState['location']['value'] ?>"
                                           maxlength="100" autocomplete="address-level2" placeholder="For example: Khartoum"
                                           data-label="Location" aria-describedby="locationError"<?= $fieldState['location']['ariaValid'] ?>>
                                    <div class="invalid-feedback" id="locationError"><?= $fieldState['location']['error'] ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="occupation" class="form-label">
                                        <i class="fa-solid fa-briefcase" aria-hidden="true"></i> Occupation
                                    </label>
                                    <input type="text" id="occupation" name="occupation"
                                           class="form-control<?= $fieldState['occupation']['class'] ?>"
                                           value="<?= $fieldState['occupation']['value'] ?>"
                                           maxlength="100" autocomplete="organization-title" placeholder="For example: Student"
                                           data-label="Occupation" aria-describedby="occupationError"<?= $fieldState['occupation']['ariaValid'] ?>>
                                    <div class="invalid-feedback" id="occupationError"><?= $fieldState['occupation']['error'] ?></div>
                                </div>
                                <div class="col-12">
                                    <label for="description" class="form-label">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i> A few words about you
                                    </label>
                                    <textarea id="description" name="description" rows="3"
                                              class="form-control<?= $fieldState['description']['class'] ?>"
                                              maxlength="1000" data-label="Description"
                                              aria-describedby="descriptionError"<?= $fieldState['description']['ariaValid'] ?>><?= $fieldState['description']['value'] ?></textarea>
                                    <div class="invalid-feedback" id="descriptionError"><?= $fieldState['description']['error'] ?></div>
                                </div>
                            </div>
                        </fieldset>

                        <button type="submit" class="btn btn-accent btn-lg-touch w-100 justify-content-center mt-4">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create my account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
