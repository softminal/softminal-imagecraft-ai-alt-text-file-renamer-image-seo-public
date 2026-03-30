/**
 * ImageCraft - Media Modal Button
 *
 * Injects a "Generate with AI" button below the Alternative Text field
 * in WordPress media modal attachment details using MutationObserver.
 *
 * Standalone vanilla JS - no Vue, no Vite bundling.
 */
(function () {
    'use strict';

    var config = window.icaisMediaButton || {};
    var injectedSpans = new WeakSet();
    var injectedRenameSpans = new WeakSet();

    /**
     * Get the current attachment ID from the media modal.
     *
     * DOM-based methods are tried first because they always reflect the
     * currently *viewed* attachment.  The Backbone selection model is a
     * fallback — in the Media Library grid view, `selection.first()` returns
     * whichever image has a checked checkbox, NOT the one being viewed,
     * which caused it to reuse the first image's ID for subsequent images.
     */
    function getAttachmentId() {
        // Method 1: URL hash — Media Library grid uses #wp-media-grid/attachment/ID
        try {
            var hashMatch = window.location.hash.match(/attachment\/(\d+)/);
            if (hashMatch) {
                return parseInt(hashMatch[1], 10);
            }
        } catch (e) {}

        // Method 2: data-id on the currently viewed/selected grid thumbnail
        try {
            var selected = document.querySelector('.attachment.details, .attachment.selected');
            if (selected && selected.dataset.id) {
                return parseInt(selected.dataset.id, 10);
            }
        } catch (e) {}

        // Method 3: Parse the attachment's own edit link from the details panel.
        // Use the LAST a[href*="post="] link — the first may be an "Uploaded to" link
        // pointing to the parent post (e.g., a WooCommerce product), not the attachment.
        try {
            var details = document.querySelector('.attachment-details');
            if (details) {
                var editLinks = details.querySelectorAll('a[href*="post="]');
                if (editLinks.length > 0) {
                    var lastLink = editLinks[editLinks.length - 1];
                    var match = lastLink.href.match(/post=(\d+)/);
                    if (match) {
                        return parseInt(match[1], 10);
                    }
                }
            }
        } catch (e) {}

        // Method 4: Two-column edit view (attachment details in sidebar)
        try {
            var frame = wp.media.frame;
            if (frame && frame.model && frame.model.get) {
                return frame.model.get('id');
            }
        } catch (e) {}

        // Method 5: Standard media modal selection (fallback for selection-based modals)
        try {
            var frame = wp.media.frame;
            if (frame && frame.state) {
                var selection = frame.state().get('selection');
                if (selection && selection.first) {
                    var model = selection.first();
                    if (model && model.get) {
                        return model.get('id');
                    }
                }
            }
        } catch (e) {}

        return null;
    }

    /**
     * Create the Generate with AI button element
     */
    function createButton() {
        var wrapper = document.createElement('div');
        wrapper.className = 'icais-media-btn-wrapper';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'icais-media-btn';
        var iconSpan = document.createElement('span');
        iconSpan.className = 'icais-btn-icon';
        iconSpan.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        var textSpan = document.createElement('span');
        textSpan.className = 'icais-btn-text';
        textSpan.textContent = config.i18n?.generate || 'Generate with AI';
        btn.appendChild(iconSpan);
        btn.appendChild(textSpan);

        var status = document.createElement('div');
        status.className = 'icais-media-status';
        status.style.display = 'none';

        wrapper.appendChild(btn);
        wrapper.appendChild(status);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            handleGenerate(btn, status);
        });

        return wrapper;
    }

    /**
     * Handle the generate button click
     */
    function handleGenerate(btn, status) {
        var attachmentId = getAttachmentId();
        if (!attachmentId) {
            showStatus(status, 'error', config.i18n?.noAttachment || 'Could not detect image. Please select an image first.');
            return;
        }

        // Set loading state
        btn.disabled = true;
        btn.classList.add('icais-loading');
        btn.querySelector('.icais-btn-text').textContent = config.i18n?.generating || 'Generating...';
        status.style.display = 'none';

        var url = config.apiUrl + '/generator/generate';

        // Build request body — don't send tone so the backend can auto-select
        // the WooCommerce tone (woo_seo) when product context is detected
        var requestBody = {
            attachment_id: attachmentId
        };

        // Send the current post ID so the backend can look up SEO focus keyphrase
        var postIdInput = document.getElementById('post_ID');
        var postTypeInput = document.getElementById('post_type');
        if (postIdInput) {
            requestBody.post_id = parseInt(postIdInput.value, 10);

            // Detect if we're on a WooCommerce product edit page and pass product_id
            if (postTypeInput && postTypeInput.value === 'product') {
                requestBody.product_id = requestBody.post_id;
            }
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify(requestBody)
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                // Always query the DOM fresh — the alt textarea may have been
                // replaced by WordPress when the user switched images
                var textarea = document.querySelector('.setting[data-setting="alt"] textarea, [data-setting="alt"] textarea');

                if (textarea) {
                    textarea.value = data.alt_text;
                    // Dispatch events so WordPress Backbone model picks up the change
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true }));
                    // Also trigger keyup which some WP handlers listen for
                    textarea.dispatchEvent(new Event('keyup', { bubbles: true }));
                }

                showStatus(status, 'success', config.i18n?.success || 'Alt text generated successfully!');
            } else {
                showStatus(status, 'error', data.message || config.i18n?.error || 'Failed to generate alt text.');
            }
        })
        .catch(function () {
            showStatus(status, 'error', config.i18n?.error || 'Failed to generate alt text. Please try again.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.classList.remove('icais-loading');
            btn.querySelector('.icais-btn-text').textContent = config.i18n?.generate || 'Generate with AI';
        });
    }

    /**
     * Show status message below button
     */
    function showStatus(el, type, message) {
        el.textContent = message;
        el.className = 'icais-media-status icais-status-' + type;
        el.style.display = 'block';

        // Auto-hide success messages after 4 seconds
        if (type === 'success') {
            setTimeout(function () {
                el.style.display = 'none';
            }, 4000);
        }
    }

    /**
     * Inject button after the alt text setting field (as a sibling)
     */
    function injectButton(settingSpan) {
        if (injectedSpans.has(settingSpan)) {
            return;
        }
        injectedSpans.add(settingSpan);

        var buttonEl = createButton();

        // Insert the button wrapper as a sibling AFTER the alt setting span
        settingSpan.parentNode.insertBefore(buttonEl, settingSpan.nextSibling);
    }

    /**
     * Create a button element with given label and handler.
     */
    function createSecondaryButton(label, iconSvg, handler) {
        var wrapper = document.createElement('div');
        wrapper.className = 'icais-media-btn-wrapper icais-rename-btn-wrapper';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'icais-media-btn icais-rename-btn';
        var iconSpan = document.createElement('span');
        iconSpan.className = 'icais-btn-icon';
        iconSpan.innerHTML = iconSvg;
        var textSpan = document.createElement('span');
        textSpan.className = 'icais-btn-text';
        textSpan.textContent = label;
        btn.appendChild(iconSpan);
        btn.appendChild(textSpan);

        var status = document.createElement('div');
        status.className = 'icais-media-status';
        status.style.display = 'none';

        wrapper.appendChild(btn);
        wrapper.appendChild(status);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            handler(btn, status);
        });

        return wrapper;
    }

    var editIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
    var starIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';

    /**
     * Handle the rename button click
     */
    function handleRename(btn, status) {
        var attachmentId = getAttachmentId();
        if (!attachmentId) {
            showStatus(status, 'error', config.i18n?.noAttachment || 'Could not detect image. Please select an image first.');
            return;
        }

        btn.disabled = true;
        btn.classList.add('icais-loading');
        btn.querySelector('.icais-btn-text').textContent = config.i18n?.renaming || 'Renaming...';
        status.style.display = 'none';

        fetch(config.apiUrl + '/renamer/rename', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ attachment_id: attachmentId })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var msg = (config.i18n?.renameSuccess || 'File renamed to: %s').replace('%s', data.new_filename);
                showStatus(status, 'success', msg);
                try {
                    if (window.wp && wp.media && wp.media.model) {
                        var attachment = wp.media.model.Attachment.get(attachmentId);
                        if (attachment) { attachment.fetch(); }
                    }
                } catch (e) {}
            } else {
                showStatus(status, 'error', data.message || config.i18n?.renameError || 'Failed to rename file.');
            }
        })
        .catch(function () { showStatus(status, 'error', config.i18n?.renameError || 'Failed to rename file.'); })
        .finally(function () {
            btn.disabled = false;
            btn.classList.remove('icais-loading');
            btn.querySelector('.icais-btn-text').textContent = config.i18n?.rename || 'Rename File';
        });
    }

    /**
     * Handle the generate title button click
     */
    function handleGenerateTitle(btn, status) {
        var attachmentId = getAttachmentId();
        if (!attachmentId) {
            showStatus(status, 'error', config.i18n?.noAttachment || 'Could not detect image. Please select an image first.');
            return;
        }

        btn.disabled = true;
        btn.classList.add('icais-loading');
        btn.querySelector('.icais-btn-text').textContent = config.i18n?.generatingTitle || 'Generating title...';
        status.style.display = 'none';

        fetch(config.apiUrl + '/generator/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ attachment_id: attachmentId, title_only: true })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.generated_title) {
                // Update the title input field in the media modal
                // Try multiple selectors — WP uses different DOM structures in different views
                var titleInput = document.querySelector(
                    '.setting[data-setting="title"] input,' +
                    '[data-setting="title"] input,' +
                    '.setting[data-setting="title"] textarea,' +
                    '[data-setting="title"] textarea,' +
                    '#attachment-details-two-column-title,' +
                    'label[data-setting="title"] input'
                );
                if (titleInput) {
                    titleInput.value = data.generated_title;
                    titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                    titleInput.dispatchEvent(new Event('change', { bubbles: true }));
                    titleInput.dispatchEvent(new Event('keyup', { bubbles: true }));
                }

                // Update Backbone model directly — do NOT call fetch() as it
                // would overwrite with old server data before the title is persisted
                try {
                    if (window.wp && wp.media && wp.media.model) {
                        var attachment = wp.media.model.Attachment.get(attachmentId);
                        if (attachment) {
                            attachment.set('title', data.generated_title);
                        }
                    }
                } catch (e) {}

                var msg = (config.i18n?.titleSuccess || 'Title updated to: %s').replace('%s', data.generated_title);
                showStatus(status, 'success', msg);
            } else {
                showStatus(status, 'error', data.message || config.i18n?.titleError || 'Failed to generate title.');
            }
        })
        .catch(function () { showStatus(status, 'error', config.i18n?.titleError || 'Failed to generate title.'); })
        .finally(function () {
            btn.disabled = false;
            btn.classList.remove('icais-loading');
            btn.querySelector('.icais-btn-text').textContent = config.i18n?.generateTitle || 'Generate Title';
        });
    }

    /**
     * Inject button(s) after the title setting field.
     *
     * [Generate Title] — shown when icais_generate_title is enabled
     * [Rename File] — shown when rename_mode is title_and_filename
     * Both can appear simultaneously.
     */
    function injectRenameButton(settingSpan) {
        if (injectedRenameSpans.has(settingSpan)) {
            return;
        }
        injectedRenameSpans.add(settingSpan);

        var lastInserted = settingSpan;

        // Show Generate Title button when title generation is enabled
        if (config.generateTitle === '1' || config.generateTitle === 1) {
            var titleBtn = createSecondaryButton(
                config.i18n?.generateTitle || 'Generate Title',
                starIcon,
                handleGenerateTitle
            );
            lastInserted.parentNode.insertBefore(titleBtn, lastInserted.nextSibling);
            lastInserted = titleBtn;
        }

        // Show Rename File button when file rename mode is enabled
        if (config.renameMode === 'title_and_filename') {
            var renameBtn = createSecondaryButton(
                config.i18n?.rename || 'Rename File',
                editIcon,
                handleRename
            );
            lastInserted.parentNode.insertBefore(renameBtn, lastInserted.nextSibling);
        }
    }

    /**
     * Scan DOM for alt text and title setting fields and inject buttons
     */
    function scanAndInject() {
        var altSettings = document.querySelectorAll('.setting[data-setting="alt"], span[data-setting="alt"]');
        for (var i = 0; i < altSettings.length; i++) {
            injectButton(altSettings[i]);
        }

        var titleSettings = document.querySelectorAll('.setting[data-setting="title"], span[data-setting="title"]');
        for (var j = 0; j < titleSettings.length; j++) {
            injectRenameButton(titleSettings[j]);
        }
    }

    /**
     * Initialize MutationObserver to watch for media modal rendering
     */
    function init() {
        if (!config.apiUrl || !config.nonce) {
            return;
        }

        // Initial scan
        scanAndInject();

        // Watch for DOM changes (media modal is dynamically rendered)
        var observer = new MutationObserver(function (mutations) {
            var shouldScan = false;
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length > 0) {
                    shouldScan = true;
                    break;
                }
            }
            if (shouldScan) {
                scanAndInject();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
