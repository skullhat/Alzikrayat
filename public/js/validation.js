/**
 * Alzikrayat - client-side form validation (validation layer 2 of 3).
 *
 * Layer 1 is HTML5: required, maxlength, minlength, type="email", pattern.
 * Layer 2 is this file: it reads the same HTML5 rules, adds rules that HTML
 * cannot express, and shows clear messages under each field.
 * Layer 3 is PHP on the server. It always runs, because this file can be
 * turned off or skipped by the user.
 *
 * How to use: add the attribute data-validate to a <form>. Each field needs
 * an id, a data-label (name used in messages), and an element with the id
 * "<fieldId>Error" for the message. Extra rules:
 *   data-rule="name"     letters only (any language)
 *   data-rule="password" letter + number, maximum 72 bytes
 *   data-match="otherId" value must equal another field
 *   data-rule="image"    file must be JPG, PNG, or WebP and not larger
 *                        than data-max-bytes (value comes from PHP)
 */
(function () {
    'use strict';

    /** Letters in any language, plus marks such as Arabic short vowels. */
    var namePattern = /^[\p{L}\p{M}]+$/u;

    /**
     * Practical email check: text@text.domain with no spaces.
     * The server uses PHP filter_var() for the full check.
     */
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    /** bcrypt uses only 72 bytes of a password. The server has the same limit. */
    var passwordMaxBytes = 72;

    /** Image types the server accepts. The server checks the real content again. */
    var allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    var allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Counts characters the same way PHP mb_strlen() does (code points),
     * so an emoji counts as one character, not two.
     *
     * @param {string} textValue Any text.
     * @returns {number} Number of characters.
     */
    function countCharacters(textValue) {
        return Array.from(textValue).length;
    }

    /**
     * Counts UTF-8 bytes, the same way PHP strlen() does.
     *
     * @param {string} textValue Any text.
     * @returns {number} Number of bytes.
     */
    function countBytes(textValue) {
        return new TextEncoder().encode(textValue).length;
    }

    /**
     * Finds the first problem with a file field.
     *
     * The browser only knows the file name and the type it guesses from
     * the name, so this is a quick early check. PHP reads the real type
     * from the file content with finfo.
     *
     * @param {HTMLInputElement} fileField A field with type="file".
     * @returns {string} The error message, or an empty string when the file is acceptable.
     */
    function findFileError(fileField) {
        var chosenFile = fileField.files && fileField.files[0];

        if (!chosenFile) {
            return fileField.required ? 'Please choose a photo to upload.' : '';
        }

        var fileExtension = chosenFile.name.split('.').pop().toLowerCase();
        if (allowedImageTypes.indexOf(chosenFile.type) === -1 || allowedImageExtensions.indexOf(fileExtension) === -1) {
            return 'Only JPG, PNG, and WebP images are allowed.';
        }

        var maximumBytes = parseInt(fileField.dataset.maxBytes, 10);
        if (maximumBytes > 0 && chosenFile.size > maximumBytes) {
            return 'The photo is too large. The maximum size is ' + (fileField.dataset.maxText || '5 MB') + '.';
        }
        if (chosenFile.size === 0) {
            return 'The selected file is empty.';
        }

        return '';
    }

    /**
     * Finds the first problem with one field.
     *
     * @param {HTMLInputElement|HTMLTextAreaElement} formField The field to check.
     * @param {HTMLFormElement} parentForm The form that holds the field.
     * @returns {string} The error message, or an empty string when the field is valid.
     */
    function findFieldError(formField, parentForm) {
        if (formField.type === 'file') {
            return findFileError(formField);
        }

        var fieldLabel = formField.dataset.label || 'This field';
        // Passwords are checked exactly as typed; other fields without outer spaces.
        var fieldValue = formField.type === 'password' ? formField.value : formField.value.trim();

        if (fieldValue === '') {
            return formField.required ? fieldLabel + ' is required.' : '';
        }

        var maximumLength = parseInt(formField.getAttribute('maxlength'), 10);
        if (formField.dataset.rule !== 'password' && maximumLength > 0 && countCharacters(fieldValue) > maximumLength) {
            return fieldLabel + ' must be ' + maximumLength + ' characters or less.';
        }

        var minimumLength = parseInt(formField.getAttribute('minlength'), 10);
        if (minimumLength > 0 && countCharacters(fieldValue) < minimumLength) {
            return fieldLabel + ' must be at least ' + minimumLength + ' characters.';
        }

        if (formField.type === 'email' && (formField.validity.typeMismatch || !emailPattern.test(fieldValue))) {
            return 'Please enter a valid email address, for example name@example.com.';
        }

        if (formField.dataset.rule === 'name' && !namePattern.test(fieldValue)) {
            return fieldLabel + ' can contain letters only (no spaces, numbers, or symbols).';
        }

        if (formField.dataset.rule === 'password') {
            if (countBytes(fieldValue) > passwordMaxBytes) {
                return 'Password is too long. Please use 72 characters or less.';
            }
            if (!/\p{L}/u.test(fieldValue) || !/\d/.test(fieldValue)) {
                return 'Password must contain at least one letter and one number.';
            }
        }

        if (formField.dataset.match) {
            var otherField = parentForm.querySelector('#' + formField.dataset.match);
            if (otherField && otherField.value !== formField.value) {
                return 'The two passwords do not match.';
            }
        }

        return '';
    }

    /**
     * Shows or clears the message of one field, using Bootstrap classes
     * and ARIA attributes so screen readers also hear the error.
     *
     * @param {HTMLElement} formField The field.
     * @param {string} errorMessage Message to show; empty string clears it.
     * @returns {void}
     */
    function showFieldError(formField, errorMessage) {
        var messageElement = document.getElementById(formField.id + 'Error');
        var hasError = errorMessage !== '';

        formField.classList.toggle('is-invalid', hasError);
        if (hasError) {
            formField.setAttribute('aria-invalid', 'true');
        } else {
            formField.removeAttribute('aria-invalid');
        }
        if (messageElement) {
            messageElement.textContent = errorMessage;
        }
    }

    /**
     * Checks one field and updates its message.
     *
     * @param {HTMLElement} formField The field.
     * @param {HTMLFormElement} parentForm The form.
     * @returns {boolean} True when the field is valid.
     */
    function validateField(formField, parentForm) {
        var errorMessage = findFieldError(formField, parentForm);
        showFieldError(formField, errorMessage);
        return errorMessage === '';
    }

    /**
     * Returns the fields of a form that should be checked.
     *
     * @param {HTMLFormElement} parentForm The form.
     * @returns {HTMLElement[]} Visible input, file, and textarea fields with an id.
     */
    function getCheckedFields(parentForm) {
        return Array.from(parentForm.querySelectorAll('input[id], textarea[id]')).filter(function (formField) {
            return formField.type !== 'hidden';
        });
    }

    /**
     * Connects validation to one form.
     *
     * The form gets "novalidate" so the browser does not show its own
     * bubbles; this script shows the same rules as page messages instead.
     * Without JavaScript, the HTML5 rules still work as layer 1.
     *
     * @param {HTMLFormElement} parentForm A form with the data-validate attribute.
     * @returns {void}
     */
    function setupForm(parentForm) {
        parentForm.setAttribute('novalidate', '');
        var checkedFields = getCheckedFields(parentForm);

        parentForm.addEventListener('submit', function (submitEvent) {
            var firstInvalidField = null;

            checkedFields.forEach(function (formField) {
                if (!validateField(formField, parentForm) && firstInvalidField === null) {
                    firstInvalidField = formField;
                }
            });

            if (firstInvalidField !== null) {
                submitEvent.preventDefault();
                firstInvalidField.focus();
                return;
            }

            // Stop double submission while the server works.
            var submitButton = parentForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
            }
        });

        // After the user corrects a field, its message updates right away.
        checkedFields.forEach(function (formField) {
            formField.addEventListener('input', function () {
                if (formField.classList.contains('is-invalid')) {
                    validateField(formField, parentForm);
                }
                // Changing the password also re-checks the "repeat password" field.
                checkedFields.forEach(function (otherField) {
                    if (otherField.dataset.match === formField.id && otherField.value !== '') {
                        validateField(otherField, parentForm);
                    }
                });
            });
            formField.addEventListener('blur', function () {
                if (formField.value !== '' && formField.type !== 'file') {
                    validateField(formField, parentForm);
                }
            });
            // A file field is checked as soon as a file is chosen.
            if (formField.type === 'file') {
                formField.addEventListener('change', function () {
                    validateField(formField, parentForm);
                });
            }
        });
    }

    document.querySelectorAll('form[data-validate]').forEach(setupForm);
}());
