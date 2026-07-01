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
    // Role-based permission: when false, all ImageCraft buttons (generate alt,
    // generate title, rename, compress) are hidden. Defaults to allowed when
    // unset for backwards compatibility.
    var canUse = config.canUse !== false;
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

    var compressIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>';

    /**
     * Create the AI field picker (Alt / Title / Description checkboxes + a single
     * Generate button), mirroring the bulk actions page. A Compress button is
     * added alongside when compression is enabled.
     */
    function createButton() {
        var wrapper = document.createElement('div');
        wrapper.className = 'icais-media-btn-wrapper';

        // Field checkboxes — defaults come from the Generate-All settings.
        var picker = document.createElement('div');
        picker.className = 'icais-field-picker';
        var fieldDefs = [
            { key: 'alt', label: config.i18n?.fieldAlt || 'Alt text', def: config.metaIncludeAlt },
            { key: 'title', label: config.i18n?.fieldTitle || 'Title', def: config.metaIncludeTitle },
            { key: 'caption', label: config.i18n?.fieldDescription || 'Description', def: config.metaIncludeCaption }
        ];
        fieldDefs.forEach(function (f) {
            var lbl = document.createElement('label');
            lbl.className = 'icais-field-option';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'icais-field-cb';
            cb.setAttribute('data-field', f.key);
            // Default ticked unless the setting is explicitly off.
            cb.checked = (f.def !== '0' && f.def !== 0 && f.def !== false);
            var span = document.createElement('span');
            span.textContent = f.label;
            lbl.appendChild(cb);
            lbl.appendChild(span);
            picker.appendChild(lbl);
        });
        wrapper.appendChild(picker);

        var btnRow = document.createElement('div');
        btnRow.className = 'icais-media-btn-row';

        // Generate button (runs the combined generation for the ticked fields).
        var genBtn = document.createElement('button');
        genBtn.type = 'button';
        genBtn.className = 'icais-media-btn';
        genBtn.innerHTML = '<span class="icais-btn-icon">' + starIcon + '</span><span class="icais-btn-text">' + (config.i18n?.generate || 'Generate') + '</span>';
        var status = document.createElement('div');
        status.className = 'icais-media-status';
        status.style.display = 'none';
        genBtn.addEventListener('click', function (e) {
            e.preventDefault();
            handlePickerGenerate(wrapper, genBtn, status);
        });
        btnRow.appendChild(genBtn);

        // Compress button — separate action, only when compression is enabled.
        var compressStatus = null;
        if (config.compressionEnabled === '1' || config.compressionEnabled === 1 || config.compressionEnabled === true) {
            var compressBtn = document.createElement('button');
            compressBtn.type = 'button';
            compressBtn.className = 'icais-media-btn icais-compress-btn';
            compressBtn.innerHTML = '<span class="icais-btn-icon">' + compressIcon + '</span><span class="icais-btn-text">' + (config.i18n?.compress || 'Compress') + '</span>';
            compressStatus = document.createElement('div');
            compressStatus.className = 'icais-media-status';
            compressStatus.style.display = 'none';
            compressBtn.addEventListener('click', function (e) {
                e.preventDefault();
                handleCompress(compressBtn, compressStatus);
            });
            btnRow.appendChild(compressBtn);
        }

        wrapper.appendChild(btnRow);
        wrapper.appendChild(status);
        if (compressStatus) {
            wrapper.appendChild(compressStatus);
        }

        return wrapper;
    }

    /**
     * Handle the compress button click — compress the image in place.
     */
    function handleCompress(btn, status) {
        var attachmentId = getAttachmentId();
        if (!attachmentId) {
            showStatus(status, 'error', config.i18n?.noAttachment || 'Could not detect image. Please select an image first.');
            return;
        }

        btn.disabled = true;
        btn.classList.add('icais-loading');
        btn.querySelector('.icais-btn-text').textContent = config.i18n?.compressing || 'Compressing...';
        status.style.display = 'none';

        fetch(config.apiUrl + '/compressor/compress', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ attachment_id: attachmentId })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (data.skipped) {
                    showStatus(status, 'success', config.i18n?.compressSkipped || 'Already optimized.');
                } else {
                    var msg = (config.i18n?.compressSuccess || 'Compressed — saved %s').replace('%s', data.saved_human || '0 B');
                    showStatus(status, 'success', msg);
                    // Refresh the attachment so the preview/size reflect the new file.
                    try {
                        if (window.wp && wp.media && wp.media.model) {
                            var attachment = wp.media.model.Attachment.get(attachmentId);
                            if (attachment) { attachment.fetch(); }
                        }
                    } catch (e) {}
                }
            } else {
                showStatus(status, 'error', data.message || config.i18n?.compressError || 'Failed to compress image.');
            }
        })
        .catch(function () { showStatus(status, 'error', config.i18n?.compressError || 'Failed to compress image.'); })
        .finally(function () {
            btn.disabled = false;
            btn.classList.remove('icais-loading');
            btn.querySelector('.icais-btn-text').textContent = config.i18n?.compress || 'Compress';
        });
    }

    /**
     * Handle the picker "Generate" click — generate the ticked fields (alt /
     * title / caption) in one combined call and populate the matching modal
     * fields. Modal edits are persisted by WordPress; we also nudge the model.
     */
    function handlePickerGenerate(wrapper, btn, status) {
        var attachmentId = getAttachmentId();
        if (!attachmentId) {
            showStatus(status, 'error', config.i18n?.noAttachment || 'Could not detect image. Please select an image first.');
            return;
        }

        // Collect the ticked fields.
        var fields = [];
        var cbs = wrapper.querySelectorAll('.icais-field-cb');
        for (var i = 0; i < cbs.length; i++) {
            if (cbs[i].checked) {
                fields.push(cbs[i].getAttribute('data-field'));
            }
        }
        if (fields.length === 0) {
            showStatus(status, 'error', config.i18n?.metaError || 'Select at least one field.');
            return;
        }

        btn.disabled = true;
        btn.classList.add('icais-loading');
        btn.querySelector('.icais-btn-text').textContent = config.i18n?.generating || 'Generating...';
        status.style.display = 'none';

        var requestBody = { attachment_id: attachmentId, fields: fields };
        var postIdInput = document.getElementById('post_ID');
        var postTypeInput = document.getElementById('post_type');
        if (postIdInput) {
            requestBody.post_id = parseInt(postIdInput.value, 10);
            if (postTypeInput && postTypeInput.value === 'product') {
                requestBody.product_id = requestBody.post_id;
            }
        }

        fetch(config.apiUrl + '/generator/generate-meta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify(requestBody)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                showStatus(status, 'error', data.message || config.i18n?.metaError || 'Failed to generate metadata.');
                return;
            }

            var attachment = null;
            try {
                if (window.wp && wp.media && wp.media.model) {
                    attachment = wp.media.model.Attachment.get(attachmentId);
                }
            } catch (e) {}

            setField('alt', data.alt_text, attachment, 'alt');
            setField('title', data.title, attachment, 'title');
            setField('caption', data.caption, attachment, 'caption');

            // When "preview before accept" is on, generate-meta only returns a
            // preview — it hasn't written anything to the DB yet. The modal
            // field update above is optimistic only, so commit it explicitly
            // instead of relying on WordPress's own compat-field autosave to
            // catch it (otherwise it can silently revert on next page load).
            if (data.preview && data.history_id) {
                fetch(config.apiUrl + '/generator/accept-meta', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                    body: JSON.stringify({
                        history_id: data.history_id,
                        alt_text: data.alt_text || '',
                        title: data.title || '',
                        caption: data.caption || ''
                    })
                }).catch(function () {});
            }

            showStatus(status, 'success', config.i18n?.metaSuccess || 'Generated!');
        })
        .catch(function () { showStatus(status, 'error', config.i18n?.metaError || 'Failed to generate metadata.'); })
        .finally(function () {
            btn.disabled = false;
            btn.classList.remove('icais-loading');
            btn.querySelector('.icais-btn-text').textContent = config.i18n?.generate || 'Generate';
        });
    }

    /**
     * Populate a media-modal setting field and sync the Backbone model.
     * Skips empty values so disabled/skipped fields are left untouched.
     */
    function setField(setting, value, attachment, modelKey) {
        if (!value) {
            return;
        }
        var el = document.querySelector(
            '.setting[data-setting="' + setting + '"] textarea,' +
            '[data-setting="' + setting + '"] textarea,' +
            '.setting[data-setting="' + setting + '"] input,' +
            '[data-setting="' + setting + '"] input'
        );
        if (el) {
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
        try {
            if (attachment && modelKey) {
                attachment.set(modelKey, value);
            }
        } catch (e) {}
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
        // Hidden entirely when the user's role may not use ImageCraft.
        if (!canUse) {
            return;
        }
        if (injectedSpans.has(settingSpan)) {
            return;
        }
        injectedSpans.add(settingSpan);

        var buttonEl = createButton();

        // Insert the picker ABOVE the Alt Text field. The picker generates alt +
        // title + description, so it belongs at the head of that field group
        // rather than wedged between Alt Text and Title. Same (robust) alt anchor.
        settingSpan.parentNode.insertBefore(buttonEl, settingSpan);
    }
    const starIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';

    /**
     * Handle the rename button click — AI generates filename then renames
     */
    function handleRename(btn, status) {
        var attachmentId = getAttachmentId();
        if (!attachmentId) {
            showStatus(status, 'error', config.i18n?.noAttachment || 'Could not detect image. Please select an image first.');
            return;
        }

        // eslint-disable-next-line no-alert -- intentional confirm before a destructive URL change
        if (!confirm(config.i18n?.renameConfirm || 'This will change the image URL. External links and cached pages may show broken images.\n\nAre you sure you want to rename this file?')) {
            return;
        }

        btn.disabled = true;
        btn.classList.add('icais-loading');
        btn.querySelector('.icais-btn-text').textContent = config.i18n?.renaming || 'Renaming...';
        status.style.display = 'none';

        fetch(config.apiUrl + '/generator/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ attachment_id: attachmentId, filename_only: true })
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
     * Inject the Rename File button after the title setting field.
     *
     * Title generation now lives in the alt-area field picker, so the title
     * area only carries the optional [Rename File] action (when enabled).
     */
    function injectRenameButton(settingSpan) {
        if (injectedRenameSpans.has(settingSpan)) {
            return;
        }
        injectedRenameSpans.add(settingSpan);

        // Hidden entirely when the user's role may not use ImageCraft.
        if (!canUse) return;

        var hasRename = config.autoRename === '1' || config.autoRename === 1;
        if (!hasRename) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'icais-media-btn-wrapper icais-rename-btn-wrapper';

        var btnRow = document.createElement('div');
        btnRow.className = 'icais-media-btn-row';

        var renameBtn = document.createElement('button');
        renameBtn.type = 'button';
        renameBtn.className = 'icais-media-btn icais-rename-btn';
        renameBtn.innerHTML = '<span class="icais-btn-icon">' + starIcon + '</span><span class="icais-btn-text">' + (config.i18n?.rename || 'Rename File') + '</span>';
        var renameStatus = document.createElement('div');
        renameStatus.className = 'icais-media-status';
        renameStatus.style.display = 'none';
        renameBtn.addEventListener('click', function (e) { e.preventDefault(); handleRename(renameBtn, renameStatus); });
        btnRow.appendChild(renameBtn);

        wrapper.appendChild(btnRow);
        wrapper.appendChild(renameStatus);

        settingSpan.parentNode.insertBefore(wrapper, settingSpan.nextSibling);
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
