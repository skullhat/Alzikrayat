<?php
/**
 * Upload form for a new photo.
 *
 * Variables from PhotoController:
 * @var array<string, string> $validationErrors Field name => message.
 * @var array<string, mixed>  $oldInput         Previous title, description, and tagged member ids.
 * @var array<int, array<string, mixed>> $taggableUsers Members who can be tagged (everyone except the uploader).
 * @var int                   $maxTags          Largest number of tags per photo.
 * @var int                   $maxFileBytes     Largest allowed file size in bytes.
 * @var string                $maxFileText      The same limit as text, for example "5 MB".
 * @var string                $csrfToken        Token for the hidden form field.
 * @var string                $basePath         URL prefix of the app.
 */
$safeBasePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
$fileError = $validationErrors['photoFile'] ?? '';
$titleError = $validationErrors['title'] ?? '';
$descriptionError = $validationErrors['description'] ?? '';
$tagsError = $validationErrors['taggedUserIds'] ?? '';
$selectedTagIds = $oldInput['taggedUserIds'] ?? [];
?>
<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="auth-card">
                    <h1 class="page-title">
                        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload a photo
                    </h1>

                    <?php if ($validationErrors !== []): ?>
                        <div class="alert alert-danger site-alert" role="alert">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            Please fix the fields marked in red.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= $safeBasePath ?>/photos" enctype="multipart/form-data" data-validate>
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-4">
                            <label for="photoFile" class="form-label">Photo <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="upload-drop">
                                <img id="photoPreview" class="upload-preview" alt="Preview of the selected photo" hidden>
                                <input type="file" id="photoFile" name="photoFile"
                                       class="form-control form-control-lg<?= $fileError !== '' ? ' is-invalid' : '' ?>"
                                       accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required
                                       data-label="Photo" data-rule="image" data-max-bytes="<?= (int) $maxFileBytes ?>"
                                       data-max-text="<?= htmlspecialchars($maxFileText, ENT_QUOTES, 'UTF-8') ?>"
                                       aria-describedby="photoFileHint photoFileError"
                                       <?= $fileError !== '' ? 'aria-invalid="true"' : '' ?>>
                                <div class="invalid-feedback" id="photoFileError"><?= htmlspecialchars($fileError, ENT_QUOTES, 'UTF-8') ?></div>
                                <p class="form-text mb-0" id="photoFileHint">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                    JPG, PNG, or WebP. Maximum size <?= htmlspecialchars($maxFileText, ENT_QUOTES, 'UTF-8') ?>.
                                </p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="required-mark" aria-hidden="true">*</span></label>
                            <input type="text" id="title" name="title"
                                   class="form-control form-control-lg<?= $titleError !== '' ? ' is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($oldInput['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   required maxlength="200" placeholder="For example: Sunset on the Nile"
                                   data-label="Title" aria-describedby="titleError"
                                   <?= $titleError !== '' ? 'aria-invalid="true"' : '' ?>>
                            <div class="invalid-feedback" id="titleError"><?= htmlspecialchars($titleError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">The story behind it <span class="text-muted-custom fw-normal">(optional)</span></label>
                            <textarea id="description" name="description" rows="4"
                                      class="form-control<?= $descriptionError !== '' ? ' is-invalid' : '' ?>"
                                      maxlength="2000" data-label="Description" aria-describedby="descriptionError"
                                      <?= $descriptionError !== '' ? 'aria-invalid="true"' : '' ?>><?= htmlspecialchars($oldInput['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="invalid-feedback" id="descriptionError"><?= htmlspecialchars($descriptionError, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <?php if ($taggableUsers !== []): ?>
                            <fieldset class="tag-picker mb-4" id="tagPicker" data-max-tags="<?= (int) $maxTags ?>"
                                      aria-describedby="tagPickerHint taggedUserIdsError">
                                <legend class="form-label">
                                    <i class="fa-solid fa-user-tag" aria-hidden="true"></i> Who is in this photo?
                                    <span class="text-muted-custom fw-normal">(optional)</span>
                                </legend>
                                <p class="form-text mt-0" id="tagPickerHint">
                                    Tag up to <?= (int) $maxTags ?> members. Tagged members can remove their tag at any time.
                                </p>
                                <!-- Search box: shown by photos.js; without JavaScript the full list is shown. -->
                                <label for="tagSearch" class="visually-hidden">Search members</label>
                                <input type="search" id="tagSearch" class="form-control mb-2" placeholder="Search by name" hidden>
                                <ul class="tag-options" id="tagOptions">
                                    <?php foreach ($taggableUsers as $taggableUser): ?>
                                        <?php $optionId = 'tagUser' . (int) $taggableUser['id']; ?>
                                        <li class="tag-option">
                                            <input type="checkbox" class="form-check-input" id="<?= $optionId ?>"
                                                   name="taggedUserIds[]" value="<?= (int) $taggableUser['id'] ?>"
                                                   <?= in_array((int) $taggableUser['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
                                            <label for="<?= $optionId ?>">
                                                <?= htmlspecialchars($taggableUser['first_name'] . ' ' . $taggableUser['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="tag-count form-text" id="tagCount" aria-live="polite"></p>
                                <div class="invalid-feedback<?= $tagsError !== '' ? ' d-block' : '' ?>" id="taggedUserIdsError"><?= htmlspecialchars($tagsError, ENT_QUOTES, 'UTF-8') ?></div>
                            </fieldset>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-accent btn-lg-touch">
                                <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload photo
                            </button>
                            <a class="btn btn-outline-primary-custom btn-lg-touch" href="<?= $safeBasePath ?>/photos">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
