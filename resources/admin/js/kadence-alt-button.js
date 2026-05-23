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
 * Requires block-editor-common.js (loaded first via wp_script_add_data dependency).
 */
(function () {
    'use strict';

    var common = window.IcaisBlockEditorCommon;
    if (!common) return; // guard: common module must load first

    var config = window.icaisGutenberg || {};
    var ALT_MARKER        = 'icais-kadence-alt-wrapper';
    var TITLE_MARKER      = 'icais-kadence-title-wrapper';
    var LINK_TITLE_MARKER = 'icais-kadence-link-title-wrapper';

    var altState = {
        phase: 'idle', previewText: '', historyId: null, message: '', forBlockClientId: null
    };
    var titleState = {
        phase: 'idle', previewText: '', historyId: null, message: '', forBlockClientId: null
    };
    var linkTitleState = {
        phase: 'idle', previewText: '', historyId: null, message: '', forBlockClientId: null
    };

    // ── Block & field helpers ────────────────────────────────

    function getSelectedBlock() {
        try {
            var block = wp.data.select('core/block-editor').getSelectedBlock();
            if (block && block.name === 'kadence/image') return block;
        } catch (e) {}
        return null;
    }

    function findFieldContainer(labelMatch, helpMatch) {
        var inspector = document.querySelector('.block-editor-block-inspector');
        if (!inspector) return null;

        var labels = inspector.querySelectorAll('.components-base-control__label');
        for (var i = 0; i < labels.length; i++) {
            if (labels[i].textContent.toLowerCase().indexOf(labelMatch) !== -1) {
                return labels[i].closest('.components-base-control');
            }
        }

        if (helpMatch) {
            var helps = inspector.querySelectorAll('.components-base-control__help');
            for (var j = 0; j < helps.length; j++) {
                if (helps[j].textContent.toLowerCase().indexOf(helpMatch) !== -1) {
                    return helps[j].closest('.components-base-control');
                }
            }
        }
        return null;
    }

    function findAltContainer()   { return findFieldContainer('alt text', 'decorative'); }
    function findTitleContainer() { return findFieldContainer('title attribute', 'role of this image'); }

    function findLinkTitleContainer() {
        var inspector = document.querySelector('.block-editor-block-inspector');
        if (!inspector) return null;

        var panels = inspector.querySelectorAll('.components-panel__body');
        for (var i = 0; i < panels.length; i++) {
            var toggle = panels[i].querySelector('.components-panel__body-toggle');
            if (!toggle || toggle.textContent.toLowerCase().indexOf('link settings') === -1) continue;

            var labels = panels[i].querySelectorAll('.components-base-control__label');
            for (var j = 0; j < labels.length; j++) {
                if (labels[j].textContent.trim().toLowerCase() === 'title') {
                    return labels[j].closest('.components-base-control');
                }
            }
        }
        return null;
    }

    // ── UI ──────────────────────────────────────────────────

    function renderInto(wrapper, fieldState, fieldType) {
        common.renderInto(wrapper, fieldState, fieldType, {
            generate: handleGenerate,
            accept:   handleAccept,
            reject:   handleReject
        });
    }

    function refreshUI(fieldType) {
        var markerClass, fieldState;
        if (fieldType === 'linkTitle') {
            markerClass = LINK_TITLE_MARKER; fieldState = linkTitleState;
        } else if (fieldType === 'title') {
            markerClass = TITLE_MARKER; fieldState = titleState;
        } else {
            markerClass = ALT_MARKER; fieldState = altState;
        }
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

        var isTitle = fieldType === 'title' || fieldType === 'linkTitle';
        var body = { attachment_id: attachmentId, source: 'kadence' };
        if (fieldType === 'linkTitle') body.link_title_only = true;
        else if (fieldType === 'title') body.title_only = true;

        var title = common.getPostTitle();
        if (title) body.post_title = title;

        try {
            var postId = wp.data.select('core/editor').getCurrentPostId();
            if (postId) body.post_id = postId;
        } catch (e) {}

        var successLabel, errorLabel;
        if (fieldType === 'linkTitle') {
            successLabel = config.i18n && config.i18n.linkTitleSuccess || 'Link title generated!';
            errorLabel   = config.i18n && config.i18n.linkTitleError   || 'Failed to generate link title.';
        } else if (fieldType === 'title') {
            successLabel = config.i18n && config.i18n.titleSuccess || 'Title generated!';
            errorLabel   = config.i18n && config.i18n.titleError   || 'Failed to generate title.';
        } else {
            successLabel = config.i18n && config.i18n.success || 'Alt text generated!';
            errorLabel   = config.i18n && config.i18n.error   || 'Failed to generate alt text.';
        }

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
                    var attrName = fieldType === 'linkTitle' ? 'linkTitle' : (fieldType === 'title' ? 'title' : 'alt');
                    common.applyBlockAttribute(block.clientId, attrName, text);
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
        var isTitle = fieldType === 'title' || fieldType === 'linkTitle';
        var attrName = fieldType === 'linkTitle' ? 'linkTitle' : (fieldType === 'title' ? 'title' : 'alt');

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

        var successLabel;
        if (fieldType === 'linkTitle') {
            successLabel = config.i18n && config.i18n.linkTitleSuccess || 'Link title generated!';
        } else if (fieldType === 'title') {
            successLabel = config.i18n && config.i18n.titleSuccess || 'Title generated!';
        } else {
            successLabel = config.i18n && config.i18n.success || 'Alt text generated!';
        }

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
        common.tryInjectField(block, fieldType, findContainer, markerClass, fieldState, renderInto);
    }

    function tryInject() {
        var block = getSelectedBlock();
        var validBlock = block && common.getAttachmentId(block) ? block : null;

        tryInjectField(validBlock, 'alt',       findAltContainer,       ALT_MARKER,        altState);
        tryInjectField(validBlock, 'title',     findTitleContainer,     TITLE_MARKER,      titleState);
        tryInjectField(validBlock, 'linkTitle', findLinkTitleContainer, LINK_TITLE_MARKER, linkTitleState);
    }

    // ── Init ─────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { common.startObserver(tryInject); });
    } else {
        common.startObserver(tryInject);
    }
})();
