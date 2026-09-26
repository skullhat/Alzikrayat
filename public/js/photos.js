/**
 * Alzikrayat - photo page helpers.
 *
 * 1. Gallery layout switcher: 3 columns, 4 columns, list, or slideshow.
 *    The choice is saved in localStorage and used again on the next visit.
 *    The slideshow has previous/next buttons, arrow keys, swipe, and a counter.
 * 2. Upload preview: shows the chosen image before it is uploaded.
 * 3. Delete confirmation: forms with data-confirm ask before sending.
 * 4. Character counter: a textarea with data-counter="elementId" shows
 *    how many characters are left in that element.
 * 5. Comments without a page reload (form with data-ajax-comment). If
 *    JavaScript is off, the same form still works with a normal page reload.
 * 6. Tag picker on the upload page: search by name, and a limit of 10 tags
 *    (at the limit, the other boxes are disabled). PHP checks the limit again.
 *
 * Every part checks that its elements exist, so this one file can be
 * loaded on every page.
 */
(function () {
    'use strict';

    /** Key used in localStorage for the saved gallery layout. */
    var layoutStorageKey = 'alzikrayatGalleryLayout';

    /** Layout names and the CSS class each one uses on the gallery list. */
    var layoutClasses = {
        grid3: 'layout-grid3',
        grid4: 'layout-grid4',
        list: 'layout-list',
        slideshow: 'layout-slideshow'
    };

    /**
     * Reads the saved layout. localStorage can be blocked (private mode),
     * so errors are ignored and the default layout is used.
     *
     * @returns {string} A known layout name.
     */
    function readSavedLayout() {
        try {
            var savedLayout = window.localStorage.getItem(layoutStorageKey);
            return layoutClasses.hasOwnProperty(savedLayout) ? savedLayout : 'grid3';
        } catch (storageError) {
            return 'grid3';
        }
    }

    /**
     * Saves the chosen layout. Errors are ignored for the same reason as above.
     *
     * @param {string} layoutName A known layout name.
     * @returns {void}
     */
    function saveLayout(layoutName) {
        try {
            window.localStorage.setItem(layoutStorageKey, layoutName);
        } catch (storageError) {
            // Saving is optional; the layout still changes for this visit.
        }
    }

    /**
     * Applies a layout to the gallery and updates the buttons.
     *
     * @param {HTMLElement} galleryList The gallery <ul>.
     * @param {NodeList} layoutButtons The switcher buttons.
     * @param {string} layoutName A known layout name.
     * @returns {void}
     */
    function applyLayout(galleryList, layoutButtons, layoutName) {
        Object.keys(layoutClasses).forEach(function (knownLayout) {
            galleryList.classList.toggle(layoutClasses[knownLayout], knownLayout === layoutName);
        });
        layoutButtons.forEach(function (layoutButton) {
            layoutButton.setAttribute('aria-pressed', layoutButton.dataset.layout === layoutName ? 'true' : 'false');
        });

        var isSlideshow = layoutName === 'slideshow';
        var slideshowControls = document.getElementById('slideshowControls');
        if (slideshowControls) {
            slideshowControls.hidden = !isSlideshow;
        }
        // In the slideshow the list can take keyboard focus, so arrow keys work.
        if (isSlideshow) {
            galleryList.setAttribute('tabindex', '0');
            galleryList.setAttribute('aria-roledescription', 'slideshow');
        } else {
            galleryList.removeAttribute('tabindex');
            galleryList.removeAttribute('aria-roledescription');
        }
        galleryList.scrollLeft = 0;
        updateSlideCounter(galleryList);
    }

    /**
     * Returns the number of the slide that is visible now (1-based).
     *
     * @param {HTMLElement} galleryList The gallery <ul>.
     * @returns {number} The current slide number.
     */
    function getCurrentSlide(galleryList) {
        var slideWidth = galleryList.clientWidth || 1;
        return Math.round(galleryList.scrollLeft / slideWidth) + 1;
    }

    /**
     * Writes "3 / 12" into the slideshow counter.
     *
     * @param {HTMLElement} galleryList The gallery <ul>.
     * @returns {void}
     */
    function updateSlideCounter(galleryList) {
        var slideCounter = document.getElementById('slideCounter');
        if (slideCounter) {
            slideCounter.textContent = getCurrentSlide(galleryList) + ' / ' + galleryList.children.length;
        }
    }

    /**
     * Moves the slideshow one slide back (-1) or forward (+1).
     * At the ends it wraps around, so "next" on the last slide shows the first.
     *
     * @param {HTMLElement} galleryList The gallery <ul>.
     * @param {number} slideStep -1 for previous, +1 for next.
     * @returns {void}
     */
    function moveSlide(galleryList, slideStep) {
        var slideCount = galleryList.children.length;
        var targetSlide = ((getCurrentSlide(galleryList) - 1 + slideStep + slideCount) % slideCount);
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        galleryList.scrollTo({ left: targetSlide * galleryList.clientWidth, behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    /**
     * Connects the previous/next buttons, arrow keys, and the counter.
     * Swiping works by itself, because the slideshow is a scroll area with snap points.
     *
     * @param {HTMLElement} galleryList The gallery <ul>.
     * @returns {void}
     */
    function setupSlideshowControls(galleryList) {
        var previousButton = document.getElementById('previousSlide');
        var nextButton = document.getElementById('nextSlide');
        var counterFrame = null;

        if (!previousButton || !nextButton) {
            return;
        }

        previousButton.addEventListener('click', function () { moveSlide(galleryList, -1); });
        nextButton.addEventListener('click', function () { moveSlide(galleryList, 1); });

        galleryList.addEventListener('keydown', function (keyEvent) {
            if (!galleryList.classList.contains('layout-slideshow')) {
                return;
            }
            if (keyEvent.key === 'ArrowLeft' || keyEvent.key === 'ArrowRight') {
                keyEvent.preventDefault();
                moveSlide(galleryList, keyEvent.key === 'ArrowLeft' ? -1 : 1);
            }
        });

        // Update the counter at most once per frame while scrolling or swiping.
        galleryList.addEventListener('scroll', function () {
            if (counterFrame === null) {
                counterFrame = window.requestAnimationFrame(function () {
                    counterFrame = null;
                    updateSlideCounter(galleryList);
                });
            }
        });
    }

    /**
     * Connects the layout switcher, when the gallery page is open.
     *
     * @returns {void}
     */
    function setupLayoutSwitcher() {
        var galleryList = document.getElementById('photoGallery');
        var layoutSwitcher = document.querySelector('.layout-switcher');

        if (!galleryList || !layoutSwitcher) {
            return;
        }

        var layoutButtons = layoutSwitcher.querySelectorAll('[data-layout]');
        layoutSwitcher.hidden = false;
        setupSlideshowControls(galleryList);
        applyLayout(galleryList, layoutButtons, readSavedLayout());

        layoutButtons.forEach(function (layoutButton) {
            layoutButton.addEventListener('click', function () {
                applyLayout(galleryList, layoutButtons, layoutButton.dataset.layout);
                saveLayout(layoutButton.dataset.layout);
            });
        });
    }

    /**
     * Shows a preview of the chosen image on the upload page.
     * The preview uses a temporary local URL; nothing is sent to the server.
     *
     * @returns {void}
     */
    function setupUploadPreview() {
        var fileField = document.getElementById('photoFile');
        var previewImage = document.getElementById('photoPreview');

        if (!fileField || !previewImage) {
            return;
        }

        var previewUrl = null;

        fileField.addEventListener('change', function () {
            var chosenFile = fileField.files && fileField.files[0];

            if (previewUrl !== null) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }

            // Only preview files that passed the client-side check.
            if (!chosenFile || fileField.classList.contains('is-invalid')) {
                previewImage.hidden = true;
                previewImage.removeAttribute('src');
                return;
            }

            previewUrl = URL.createObjectURL(chosenFile);
            previewImage.src = previewUrl;
            previewImage.hidden = false;
        });
    }

    /**
     * Asks for confirmation before sending forms that have data-confirm.
     *
     * @returns {void}
     */
    function setupConfirmForms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (confirmForm) {
            confirmForm.addEventListener('submit', function (submitEvent) {
                if (!window.confirm(confirmForm.dataset.confirm)) {
                    submitEvent.preventDefault();
                }
            });
        });
    }

    /**
     * Shows "N characters left" under text areas that have data-counter.
     * Characters are counted like PHP mb_strlen() (an emoji counts as one).
     *
     * @returns {void}
     */
    function setupCharacterCounters() {
        document.querySelectorAll('textarea[data-counter]').forEach(function (textArea) {
            var counterElement = document.getElementById(textArea.dataset.counter);
            var maximumLength = parseInt(textArea.getAttribute('maxlength'), 10);

            if (!counterElement || !(maximumLength > 0)) {
                return;
            }

            var updateCounter = function () {
                var charactersLeft = maximumLength - Array.from(textArea.value).length;
                counterElement.textContent = charactersLeft + (charactersLeft === 1 ? ' character left' : ' characters left');
                counterElement.classList.toggle('counter-low', charactersLeft <= 50);
            };

            textArea.addEventListener('input', updateCounter);
            updateCounter();
        });
    }

    /**
     * Writes a message for screen readers into the polite live region.
     *
     * @param {string} messageText The message.
     * @returns {void}
     */
    function announce(messageText) {
        var statusElement = document.getElementById('commentStatus');
        if (statusElement) {
            statusElement.textContent = '';
            // A short delay makes screen readers notice the same text again.
            window.setTimeout(function () { statusElement.textContent = messageText; }, 50);
        }
    }

    /**
     * Shows an error under the comment box (the same place validation.js uses).
     *
     * @param {HTMLTextAreaElement} commentField The comment textarea.
     * @param {string} errorMessage The message to show.
     * @returns {void}
     */
    function showCommentError(commentField, errorMessage) {
        var errorElement = document.getElementById(commentField.id + 'Error');
        commentField.classList.add('is-invalid');
        commentField.setAttribute('aria-invalid', 'true');
        if (errorElement) {
            errorElement.textContent = errorMessage;
        }
        announce(errorMessage);
    }

    /**
     * Sends comments with fetch() and adds the new comment without reloading.
     *
     * validation.js runs first. If it blocked the form, this handler does
     * nothing. The server answers with the comment's HTML, made by the same
     * PHP partial the page uses, so the markup and escaping are identical.
     *
     * @returns {void}
     */
    function setupAjaxComments() {
        var commentForm = document.querySelector('form[data-ajax-comment]');
        if (!commentForm || !window.fetch) {
            return;
        }

        var commentField = commentForm.querySelector('textarea');
        var submitButton = commentForm.querySelector('button[type="submit"]');
        var commentList = document.getElementById('commentList');
        var noCommentsText = document.getElementById('noCommentsText');
        var commentCount = document.getElementById('photoCommentCount');

        commentForm.addEventListener('submit', function (submitEvent) {
            if (submitEvent.defaultPrevented) {
                return;
            }
            submitEvent.preventDefault();

            fetch(commentForm.action, {
                method: 'POST',
                body: new FormData(commentForm),
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (serverResponse) {
                return serverResponse.json().then(function (responseData) {
                    return { statusCode: serverResponse.status, responseData: responseData };
                });
            }).then(function (answer) {
                if (!answer.responseData.ok) {
                    showCommentError(commentField, answer.responseData.error || 'The comment could not be saved.');
                    return;
                }

                commentList.insertAdjacentHTML('beforeend', answer.responseData.commentHtml);
                commentList.hidden = false;
                if (noCommentsText) {
                    noCommentsText.hidden = true;
                }
                if (commentCount) {
                    var totalComments = answer.responseData.commentCount;
                    commentCount.textContent = totalComments + (totalComments === 1 ? ' comment' : ' comments');
                }

                commentField.value = '';
                // Tell the character counter that the text changed.
                commentField.dispatchEvent(new Event('input'));

                var newComment = commentList.lastElementChild;
                var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                newComment.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
                announce('Your comment was added.');
            }).catch(function () {
                showCommentError(commentField, 'The comment could not be sent. Please try again.');
            }).then(function () {
                // validation.js disabled the button to stop double posting; enable it again.
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
        });
    }

    /**
     * Makes the member tag picker easier to use.
     *
     * - A search box hides members whose name does not match.
     * - A counter shows "3 of 10 chosen".
     * - At the limit, unchecked boxes are disabled, so the limit cannot be passed.
     *
     * @returns {void}
     */
    function setupTagPicker() {
        var tagPicker = document.getElementById('tagPicker');
        if (!tagPicker) {
            return;
        }

        var searchField = document.getElementById('tagSearch');
        var tagCount = document.getElementById('tagCount');
        var tagError = document.getElementById('taggedUserIdsError');
        var maximumTags = parseInt(tagPicker.dataset.maxTags, 10) || 10;
        var tagOptions = Array.from(tagPicker.querySelectorAll('.tag-option'));
        var tagBoxes = Array.from(tagPicker.querySelectorAll('input[type="checkbox"]'));

        var updateLimit = function () {
            var chosenCount = tagBoxes.filter(function (tagBox) { return tagBox.checked; }).length;
            var limitReached = chosenCount >= maximumTags;

            tagBoxes.forEach(function (tagBox) {
                tagBox.disabled = limitReached && !tagBox.checked;
            });
            tagCount.textContent = chosenCount + ' of ' + maximumTags + ' chosen' +
                (limitReached ? '. This is the limit.' : '.');
            if (!limitReached && tagError) {
                tagError.textContent = '';
                tagError.classList.remove('d-block');
            }
        };

        searchField.hidden = false;
        searchField.addEventListener('input', function () {
            var searchText = searchField.value.trim().toLowerCase();
            tagOptions.forEach(function (tagOption) {
                var memberName = tagOption.textContent.trim().toLowerCase();
                tagOption.hidden = searchText !== '' && memberName.indexOf(searchText) === -1;
            });
        });

        tagBoxes.forEach(function (tagBox) {
            tagBox.addEventListener('change', updateLimit);
        });
        updateLimit();
    }

    setupLayoutSwitcher();
    setupUploadPreview();
    setupConfirmForms();
    setupCharacterCounters();
    setupAjaxComments();
    setupTagPicker();
}());
