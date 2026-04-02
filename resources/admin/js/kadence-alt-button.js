/**
 * ImageCraft - Kadence Image Block Integration
 *
 * Injects "Generate Alt with AI", "Generate Title with AI", and
 * "Generate Link Title with AI" buttons into the kadence/image
 * block inspector sidebar.
 *
 * Unlike core/image, Kadence stores alt/title/linkTitle as block
 * attributes only (no media library sync), so we only call
 * updateBlockAttributes().
 *
 * Standalone vanilla JS — no JSX, no Vite bundling.
 */
(function () {
    'use strict';

    var config = window.icaisGutenberg || {};
    var ALT_MARKER = 'icais-kadence-alt-wrapper';
    var TITLE_MARKER = 'icais-kadence-title-wrapper';
    var LINK_TITLE_MARKER = 'icais-kadence-link-title-wrapper';
    var debounceTimer = null;

    // Independent state per field
    var altState = {
        phase: 'idle',
        previewText: '',
        historyId: null,
        message: '',
        forBlockClientId: null
    };

    var titleState = {
        phase: 'idle',
        previewText: '',
        historyId: null,
        message: '',
        forBlockClientId: null
    };

    var linkTitleState = {
        phase: 'idle',
        previewText: '',
        historyId: null,
        message: '',
        forBlockClientId: null
    };

    var svgIcon =
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>' +
        '</svg>';

    // ── Helpers ──────────────────────────────────────────────

    function getSelectedKadenceImageBlock() {
        try {
            var block = wp.data.select('core/block-editor').getSelectedBlock();
            if (block && block.name === 'kadence/image') {
                return block;
            }
        } catch (e) {}
        return null;
    }

    function getPostTitle() {
        try {
            return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
        } catch (e) {
            return '';
        }
    }

    function getAttachmentId(block) {
        if (!block || !block.attributes) return null;
        // Kadence uses 'id' for the media attachment ID
        return block.attributes.id || null;
    }

    function applyBlockAttribute(clientId, attr, value) {
        var update = {};
        update[attr] = value;
        wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, update);
    }

    // ── Find inspector fields ───────────────────────────────

    function findFieldContainer(labelMatch, helpMatch) {
        var inspector = document.querySelector('.block-editor-block-inspector');
        if (!inspector) return null;

        // Strategy 1: find by label text
        var labels = inspector.querySelectorAll('.components-base-control__label');
        for (var i = 0; i < labels.length; i++) {
            var txt = labels[i].textContent.toLowerCase();
            if (txt.indexOf(labelMatch) !== -1) {
                return labels[i].closest('.components-base-control');
            }
        }

        // Strategy 2: find by help text
        if (helpMatch) {
            var helps = inspector.querySelectorAll('.components-base-control__help');
            for (var j = 0; j < helps.length; j++) {
                var htxt = helps[j].textContent.toLowerCase();
                if (htxt.indexOf(helpMatch) !== -1) {
                    return helps[j].closest('.components-base-control');
                }
            }
        }

        return null;
    }

    function findAltContainer() {
        return findFieldContainer('alt text', 'decorative');
    }

    function findTitleContainer() {
        return findFieldContainer('title attribute', 'role of this image');
    }

    function findLinkTitleContainer() {
        var inspector = document.querySelector('.block-editor-block-inspector');
        if (!inspector) return null;

        // The link title "Title" field lives inside the "Link Settings" panel.
        // We locate the panel first, then find the "Title" label within it.
        var panels = inspector.querySelectorAll('.components-panel__body');
        for (var i = 0; i < panels.length; i++) {
            var toggle = panels[i].querySelector('.components-panel__body-toggle');
            if (!toggle) continue;
            var toggleText = toggle.textContent.toLowerCase();
            if (toggleText.indexOf('link settings') === -1) continue;

            // Found the Link Settings panel — find the "Title" base-control inside it
            var labels = panels[i].querySelectorAll('.components-base-control__label');
            for (var j = 0; j < labels.length; j++) {
                var txt = labels[j].textContent.trim().toLowerCase();
                if (txt === 'title') {
                    return labels[j].closest('.components-base-control');
                }
            }
        }
        return null;
    }

    // ── UI Rendering ────────────────────────────────────────

    function renderInto(wrapper, fieldState, fieldType) {
        wrapper.innerHTML = '';

        var isTitle = fieldType === 'title' || fieldType === 'linkTitle';
        var generateLabel, generatingLabel, successLabel, errorLabel;

        if (fieldType === 'linkTitle') {
            generateLabel = config.i18n?.generateLinkTitle || 'Generate Link Title with AI';
            generatingLabel = config.i18n?.generatingLinkTitle || 'Generating link title...';
            successLabel = config.i18n?.linkTitleSuccess || 'Link title generated!';
            errorLabel = config.i18n?.linkTitleError || 'Failed to generate link title.';
        } else if (fieldType === 'title') {
            generateLabel = config.i18n?.generateTitle || 'Generate Title with AI';
            generatingLabel = config.i18n?.generatingTitle || 'Generating title...';
            successLabel = config.i18n?.titleSuccess || 'Title generated!';
            errorLabel = config.i18n?.titleError || 'Failed to generate title.';
        } else {
            generateLabel = config.i18n?.generate || 'Generate Alt with AI';
            generatingLabel = config.i18n?.generating || 'Generating...';
            successLabel = config.i18n?.success || 'Alt text generated!';
            errorLabel = config.i18n?.error || 'Failed to generate alt text.';
        }

        // Generate button
        if (fieldState.phase === 'idle' || fieldState.phase === 'success' || fieldState.phase === 'error') {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'components-button is-primary icais-gut-btn';
            var btnIcon = document.createElement('span');
            btnIcon.className = 'icais-gut-btn-icon';
            btnIcon.innerHTML = svgIcon;
            var btnText = document.createElement('span');
            btnText.textContent = generateLabel;
            btn.appendChild(btnIcon);
            btn.appendChild(btnText);
            btn.addEventListener('click', function () {
                handleGenerate(fieldState, fieldType);
            });
            wrapper.appendChild(btn);
        }

        // Loading
        if (fieldState.phase === 'loading') {
            var busy = document.createElement('button');
            busy.type = 'button';
            busy.className = 'components-button is-primary icais-gut-btn icais-gut-loading';
            busy.disabled = true;
            var busyIcon = document.createElement('span');
            busyIcon.className = 'icais-gut-btn-icon icais-gut-spin';
            busyIcon.innerHTML = svgIcon;
            var busyText = document.createElement('span');
            busyText.textContent = generatingLabel;
            busy.appendChild(busyIcon);
            busy.appendChild(busyText);
            wrapper.appendChild(busy);
        }

        // Preview
        if (fieldState.phase === 'preview') {
            var preview = document.createElement('div');
            preview.className = 'icais-gut-preview';

            var input;
            if (isTitle) {
                input = document.createElement('input');
                input.type = 'text';
                input.className = 'components-text-control__input icais-gut-preview-ta';
                input.value = fieldState.previewText;
            } else {
                input = document.createElement('textarea');
                input.className = 'components-textarea-control__input icais-gut-preview-ta';
                input.value = fieldState.previewText;
                input.rows = 3;
            }
            input.addEventListener('input', function (e) {
                fieldState.previewText = e.target.value;
            });
            preview.appendChild(input);

            var actions = document.createElement('div');
            actions.className = 'icais-gut-preview-actions';

            var acceptBtn = document.createElement('button');
            acceptBtn.type = 'button';
            acceptBtn.className = 'components-button is-primary is-compact';
            acceptBtn.textContent = config.i18n?.accept || 'Accept';
            acceptBtn.addEventListener('click', function () {
                handleAccept(fieldState, fieldType);
            });

            var rejectBtn = document.createElement('button');
            rejectBtn.type = 'button';
            rejectBtn.className = 'components-button is-tertiary is-compact is-destructive';
            rejectBtn.textContent = config.i18n?.reject || 'Reject';
            rejectBtn.addEventListener('click', function () {
                handleReject(fieldState, fieldType);
            });

            actions.appendChild(acceptBtn);
            actions.appendChild(rejectBtn);
            preview.appendChild(actions);
            wrapper.appendChild(preview);
        }

        // Status message
        if (fieldState.message) {
            var msg = document.createElement('div');
            msg.className = 'icais-gut-status';
            if (fieldState.phase === 'success') msg.className += ' icais-gut-status-success';
            if (fieldState.phase === 'error') msg.className += ' icais-gut-status-error';
            msg.textContent = fieldState.message;
            wrapper.appendChild(msg);
        }
    }

    function refreshUI(fieldType) {
        var markerClass, fieldState;
        if (fieldType === 'linkTitle') {
            markerClass = LINK_TITLE_MARKER;
            fieldState = linkTitleState;
        } else if (fieldType === 'title') {
            markerClass = TITLE_MARKER;
            fieldState = titleState;
        } else {
            markerClass = ALT_MARKER;
            fieldState = altState;
        }
        var el = document.querySelector('.' + markerClass);
        if (el) renderInto(el, fieldState, fieldType);
    }

    // ── Handlers ────────────────────────────────────────────

    function handleGenerate(fieldState, fieldType) {
        var block = getSelectedKadenceImageBlock();
        var attachmentId = block ? getAttachmentId(block) : null;
        if (!block || !attachmentId) return;

        fieldState.phase = 'loading';
        fieldState.message = '';
        fieldState.forBlockClientId = block.clientId;
        refreshUI(fieldType);

        var isTitle = fieldType === 'title' || fieldType === 'linkTitle';
        var body = { attachment_id: attachmentId };
        if (fieldType === 'linkTitle') body.link_title_only = true;
        else if (fieldType === 'title') body.title_only = true;

        var title = getPostTitle();
        if (title) body.post_title = title;

        try {
            var postId = wp.data.select('core/editor').getCurrentPostId();
            if (postId) body.post_id = postId;
        } catch (e) {}

        var successLabel, errorLabel;
        if (fieldType === 'linkTitle') {
            successLabel = config.i18n?.linkTitleSuccess || 'Link title generated!';
            errorLabel = config.i18n?.linkTitleError || 'Failed to generate link title.';
        } else if (fieldType === 'title') {
            successLabel = config.i18n?.titleSuccess || 'Title generated!';
            errorLabel = config.i18n?.titleError || 'Failed to generate title.';
        } else {
            successLabel = config.i18n?.success || 'Alt text generated!';
            errorLabel = config.i18n?.error || 'Failed to generate alt text.';
        }

        fetch(config.apiUrl + '/generator/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var text = isTitle ? (data.generated_title || '') : (data.alt_text || '');
                fieldState.historyId = data.history_id || null;

                var shouldPreview = isTitle ? config.titlePreviewBeforeAccept : config.previewBeforeAccept;
                if (shouldPreview) {
                    fieldState.phase = 'preview';
                    fieldState.previewText = text;
                } else {
                    var attrName = fieldType === 'linkTitle' ? 'linkTitle' : (fieldType === 'title' ? 'title' : 'alt');
                    applyBlockAttribute(block.clientId, attrName, text);
                    fieldState.phase = 'success';
                    fieldState.message = successLabel;
                    clearMessageLater(fieldState, fieldType);
                }
            } else {
                fieldState.phase = 'error';
                fieldState.message = data.message || errorLabel;
            }
            refreshUI(fieldType);
        })
        .catch(function () {
            fieldState.phase = 'error';
            fieldState.message = errorLabel;
            refreshUI(fieldType);
        });
    }

    function handleAccept(fieldState, fieldType) {
        var block = getSelectedKadenceImageBlock();
        var isTitle = fieldType === 'title' || fieldType === 'linkTitle';
        var attrName = fieldType === 'linkTitle' ? 'linkTitle' : (fieldType === 'title' ? 'title' : 'alt');

        if (block) {
            applyBlockAttribute(block.clientId, attrName, fieldState.previewText);
        }

        if (fieldState.historyId) {
            var acceptBody = { history_id: fieldState.historyId, skip_meta_update: true };
            if (!isTitle) acceptBody.alt_text = fieldState.previewText;
            if (isTitle) acceptBody.generated_title = fieldState.previewText;

            fetch(config.apiUrl + '/generator/accept', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify(acceptBody)
            }).catch(function () {});
        }

        var successLabel;
        if (fieldType === 'linkTitle') {
            successLabel = config.i18n?.linkTitleSuccess || 'Link title generated!';
        } else if (fieldType === 'title') {
            successLabel = config.i18n?.titleSuccess || 'Title generated!';
        } else {
            successLabel = config.i18n?.success || 'Alt text generated!';
        }

        fieldState.phase = 'success';
        fieldState.message = successLabel;
        fieldState.historyId = null;
        fieldState.previewText = '';
        refreshUI(fieldType);
        clearMessageLater(fieldState, fieldType);
    }

    function handleReject(fieldState, fieldType) {
        if (fieldState.historyId) {
            fetch(config.apiUrl + '/generator/reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify({ history_id: fieldState.historyId })
            }).catch(function () {});
        }

        fieldState.phase = 'idle';
        fieldState.previewText = '';
        fieldState.historyId = null;
        fieldState.message = '';
        refreshUI(fieldType);
    }

    function clearMessageLater(fieldState, fieldType) {
        setTimeout(function () {
            if (fieldState.phase === 'success') {
                fieldState.phase = 'idle';
                fieldState.message = '';
                refreshUI(fieldType);
            }
        }, 4000);
    }

    // ── Injection Logic ─────────────────────────────────────

    function tryInjectField(block, fieldType, findContainer, markerClass, fieldState) {
        // No kadence/image block selected — clean up
        if (!block) {
            var orphan = document.querySelector('.' + markerClass);
            if (orphan) orphan.remove();
            return;
        }

        // Reset state when a different block is selected
        if (block.clientId !== fieldState.forBlockClientId) {
            fieldState.phase = 'idle';
            fieldState.previewText = '';
            fieldState.historyId = null;
            fieldState.message = '';
            fieldState.forBlockClientId = block.clientId;
        }

        // Already injected and still in the DOM under the right container?
        var existing = document.querySelector('.' + markerClass);
        var container = findContainer();

        if (existing) {
            if (container && container.contains(existing)) {
                return; // all good
            }
            existing.remove();
        }

        // Find container and inject
        if (!container) return;

        var wrapper = document.createElement('div');
        wrapper.className = markerClass;
        renderInto(wrapper, fieldState, fieldType);
        container.appendChild(wrapper);
    }

    function tryInject() {
        var block = getSelectedKadenceImageBlock();
        var validBlock = block && getAttachmentId(block) ? block : null;

        tryInjectField(validBlock, 'alt', findAltContainer, ALT_MARKER, altState);
        tryInjectField(validBlock, 'title', findTitleContainer, TITLE_MARKER, titleState);
        tryInjectField(validBlock, 'linkTitle', findLinkTitleContainer, LINK_TITLE_MARKER, linkTitleState);
    }

    // ── Init ────────────────────────────────────────────────

    function init() {
        if (!config.apiUrl || !config.nonce) return;

        var observer = new MutationObserver(function () {
            if (debounceTimer) return;
            debounceTimer = setTimeout(function () {
                debounceTimer = null;
                tryInject();
            }, 80);
        });

        observer.observe(document.body, { childList: true, subtree: true });
        tryInject();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
