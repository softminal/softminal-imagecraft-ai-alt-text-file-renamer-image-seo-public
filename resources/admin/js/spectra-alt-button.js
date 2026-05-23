/**
 * ImageCraft - Spectra Image Block Integration
 *
 * Injects "Generate Alt with AI" and "Generate Title with AI"
 * buttons into the uagb/image block inspector sidebar.
 *
 * Spectra stores alt/title as block attributes, so we only
 * call updateBlockAttributes().
 *
 * Standalone vanilla JS — no JSX, no Vite bundling.
 * Requires block-editor-common.js (loaded first via wp_script_add_data dependency).
 */
(function () {
    'use strict';

    var common = window.IcaisBlockEditorCommon;
    if (!common) return; // guard: common module must load first

    var config = window.icaisGutenberg || {};
    var ALT_MARKER   = 'icais-spectra-alt-wrapper';
    var TITLE_MARKER = 'icais-spectra-title-wrapper';

    var altState = {
        phase: 'idle', previewText: '', historyId: null, message: '', forBlockClientId: null
    };
    var titleState = {
        phase: 'idle', previewText: '', historyId: null, message: '', forBlockClientId: null
    };

    // ── Block & field helpers ────────────────────────────────

    function getSelectedBlock() {
        try {
            var block = wp.data.select('core/block-editor').getSelectedBlock();
            if (block && block.name === 'uagb/image') return block;
        } catch (e) {}
        return null;
    }

    function findFieldContainer(labelMatch) {
        var inspector = document.querySelector('.block-editor-block-inspector');
        if (!inspector) return null;

        // Strategy 1: Spectra uses .uag-control-label inside .uagb-text-control
        var uagLabels = inspector.querySelectorAll('.uagb-text-control .uag-control-label');
        for (var i = 0; i < uagLabels.length; i++) {
            if (uagLabels[i].textContent.toLowerCase().trim() === labelMatch) {
                return uagLabels[i].closest('.uagb-text-control');
            }
        }

        // Strategy 2: Standard WordPress components-base-control__label
        var labels = inspector.querySelectorAll('.components-base-control__label');
        for (var j = 0; j < labels.length; j++) {
            if (labels[j].textContent.toLowerCase().trim() === labelMatch) {
                return labels[j].closest('.components-base-control');
            }
        }

        return null;
    }

    function findAltContainer()   { return findFieldContainer('alt text'); }
    function findTitleContainer() { return findFieldContainer('title'); }

    // ── UI ──────────────────────────────────────────────────

    function renderInto(wrapper, fieldState, fieldType) {
        common.renderInto(wrapper, fieldState, fieldType, {
            generate: handleGenerate,
            accept:   handleAccept,
            reject:   handleReject
        });
    }

    function refreshUI(fieldType) {
        var markerClass = fieldType === 'title' ? TITLE_MARKER : ALT_MARKER;
        var fieldState  = fieldType === 'title' ? titleState   : altState;
        var el = document.querySelector('.' + markerClass);
        if (el) renderInto(el, fieldState, fieldType);
    }

    // ── Handlers ────────────────────────────────────────────

    function handleGenerate(fieldState, fieldType) {
        var block = getSelectedBlock();
        var attachmentId = block ? common.getAttachmentId(block) : null;
        if (!block || !attachmentId) return;

        fieldState.phase = 'loading';
        fieldState.message = '';
        fieldState.forBlockClientId = block.clientId;
        refreshUI(fieldType);

        var isTitle = fieldType === 'title';
        var body = { attachment_id: attachmentId, source: 'spectra' };
        if (isTitle) body.title_only = true;

        var title = common.getPostTitle();
        if (title) body.post_title = title;

        try {
            var postId = wp.data.select('core/editor').getCurrentPostId();
            if (postId) body.post_id = postId;
        } catch (e) {}

        var successLabel = isTitle
            ? (config.i18n && config.i18n.titleSuccess || 'Title generated!')
            : (config.i18n && config.i18n.success      || 'Alt text generated!');
        var errorLabel = isTitle
            ? (config.i18n && config.i18n.titleError || 'Failed to generate title.')
            : (config.i18n && config.i18n.error      || 'Failed to generate alt text.');

        fetch(config.apiUrl + '/generator/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
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
                    common.applyBlockAttribute(block.clientId, isTitle ? 'title' : 'alt', text);
                    fieldState.phase = 'success';
                    fieldState.message = successLabel;
                    common.clearMessageLater(fieldState, fieldType, refreshUI);
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
        var block = getSelectedBlock();
        var isTitle = fieldType === 'title';
        var attrName = isTitle ? 'title' : 'alt';

        if (block) {
            common.applyBlockAttribute(block.clientId, attrName, fieldState.previewText);
        }

        if (fieldState.historyId) {
            var acceptBody = { history_id: fieldState.historyId, skip_meta_update: true };
            if (!isTitle) acceptBody.alt_text = fieldState.previewText;
            if (isTitle)  acceptBody.generated_title = fieldState.previewText;

            fetch(config.apiUrl + '/generator/accept', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify(acceptBody)
            }).catch(function () {});
        }

        var successLabel = isTitle
            ? (config.i18n && config.i18n.titleSuccess || 'Title generated!')
            : (config.i18n && config.i18n.success      || 'Alt text generated!');

        fieldState.phase = 'success';
        fieldState.message = successLabel;
        fieldState.historyId = null;
        fieldState.previewText = '';
        refreshUI(fieldType);
        common.clearMessageLater(fieldState, fieldType, refreshUI);
    }

    function handleReject(fieldState, fieldType) {
        common.handleReject(fieldState, fieldType, refreshUI);
    }

    // ── Injection ────────────────────────────────────────────

    function tryInjectField(block, fieldType, findContainer, markerClass, fieldState) {
        common.tryInjectField(block, fieldType, findContainer, markerClass, fieldState, renderInto, { marginTop: '5px' });
    }

    function tryInject() {
        var block = getSelectedBlock();
        var validBlock = block && common.getAttachmentId(block) ? block : null;

        tryInjectField(validBlock, 'alt',   findAltContainer,   ALT_MARKER,   altState);
        tryInjectField(validBlock, 'title', findTitleContainer, TITLE_MARKER, titleState);
    }

    // ── Init ─────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { common.startObserver(tryInject); });
    } else {
        common.startObserver(tryInject);
    }
})();
