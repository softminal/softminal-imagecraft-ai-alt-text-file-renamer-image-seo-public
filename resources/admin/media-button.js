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

    /**
     * Get the current attachment ID from the media modal
     */
    function getAttachmentId() {
        // Method 1: Standard media modal selection
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

        // Method 2: Two-column edit view (attachment details in sidebar)
        try {
            var frame = wp.media.frame;
            if (frame && frame.model && frame.model.get) {
                return frame.model.get('id');
            }
        } catch (e) {}

        // Method 3: Walk up DOM to find attachment-details and parse edit link
        try {
            var details = document.querySelector('.attachment-details');
            if (details) {
                var editLink = details.querySelector('a[href*="post="]');
                if (editLink) {
                    var match = editLink.href.match(/post=(\d+)/);
                    if (match) {
                        return parseInt(match[1], 10);
                    }
                }
            }
        } catch (e) {}

        // Method 4: Check for data-id attribute on selected attachment
        try {
            var selected = document.querySelector('.attachment.selected, .attachment.details');
            if (selected && selected.dataset.id) {
                return parseInt(selected.dataset.id, 10);
            }
        } catch (e) {}

        return null;
    }

    /**
     * Create the Generate with AI button element
     */
    function createButton(altSettingSpan) {
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
            handleGenerate(btn, status, altSettingSpan);
        });

        return wrapper;
    }

    /**
     * Handle the generate button click
     */
    function handleGenerate(btn, status, altSettingSpan) {
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
                // Find the textarea inside the alt setting span
                var textarea = altSettingSpan.querySelector('textarea');
                if (!textarea) {
                    textarea = document.querySelector('.setting[data-setting="alt"] textarea, [data-setting="alt"] textarea');
                }

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

        var buttonEl = createButton(settingSpan);

        // Insert the button wrapper as a sibling AFTER the alt setting span
        settingSpan.parentNode.insertBefore(buttonEl, settingSpan.nextSibling);
    }

    /**
     * Scan DOM for alt text setting fields and inject buttons
     */
    function scanAndInject() {
        var altSettings = document.querySelectorAll('.setting[data-setting="alt"], span[data-setting="alt"]');
        for (var i = 0; i < altSettings.length; i++) {
            injectButton(altSettings[i]);
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
