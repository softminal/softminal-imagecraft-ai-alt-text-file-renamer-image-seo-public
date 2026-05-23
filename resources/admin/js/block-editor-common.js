/**
 * ImageCraft – Shared block editor utilities
 *
 * Must be loaded before kadence-alt-button.js and spectra-alt-button.js.
 * Exposes window.IcaisBlockEditorCommon with functions shared by both integrations.
 */
(function () {
    'use strict';

    var svgIcon =
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>' +
        '</svg>';

    function getPostTitle() {
        try {
            return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
        } catch (e) {
            return '';
        }
    }

    function getAttachmentId(block) {
        if (!block || !block.attributes) return null;
        return block.attributes.id || null;
    }

    function applyBlockAttribute(clientId, attr, value) {
        var update = {};
        update[attr] = value;
        wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, update);
    }

    /**
     * Render the button/preview/status UI into a wrapper element.
     *
     * @param {HTMLElement} wrapper     - Target container element.
     * @param {object}      fieldState  - Mutable state object for this field.
     * @param {string}      fieldType   - 'alt' | 'title' | 'linkTitle'
     * @param {object}      callbacks   - { generate, accept, reject } handler functions.
     */
    function renderInto(wrapper, fieldState, fieldType, callbacks) {
        var config = window.icaisGutenberg || {};
        wrapper.innerHTML = '';

        var isTitle = fieldType === 'title' || fieldType === 'linkTitle';
        var generateLabel, generatingLabel, successLabel, errorLabel;

        if (fieldType === 'linkTitle') {
            generateLabel   = config.i18n && config.i18n.generateLinkTitle  || 'Generate Link Title with AI';
            generatingLabel = config.i18n && config.i18n.generatingLinkTitle || 'Generating link title...';
            successLabel    = config.i18n && config.i18n.linkTitleSuccess    || 'Link title generated!';
            errorLabel      = config.i18n && config.i18n.linkTitleError      || 'Failed to generate link title.';
        } else if (fieldType === 'title') {
            generateLabel   = config.i18n && config.i18n.generateTitle  || 'Generate Title with AI';
            generatingLabel = config.i18n && config.i18n.generatingTitle || 'Generating title...';
            successLabel    = config.i18n && config.i18n.titleSuccess    || 'Title generated!';
            errorLabel      = config.i18n && config.i18n.titleError      || 'Failed to generate title.';
        } else {
            generateLabel   = config.i18n && config.i18n.generate  || 'Generate Alt with AI';
            generatingLabel = config.i18n && config.i18n.generating || 'Generating...';
            successLabel    = config.i18n && config.i18n.success    || 'Alt text generated!';
            errorLabel      = config.i18n && config.i18n.error      || 'Failed to generate alt text.';
        }

        // Generate / idle / success / error button
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
                callbacks.generate(fieldState, fieldType);
            });
            wrapper.appendChild(btn);
        }

        // Loading spinner
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

        // Preview accept/reject
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
            acceptBtn.textContent = config.i18n && config.i18n.accept || 'Accept';
            acceptBtn.addEventListener('click', function () {
                callbacks.accept(fieldState, fieldType);
            });

            var rejectBtn = document.createElement('button');
            rejectBtn.type = 'button';
            rejectBtn.className = 'components-button is-tertiary is-compact is-destructive';
            rejectBtn.textContent = config.i18n && config.i18n.reject || 'Reject';
            rejectBtn.addEventListener('click', function () {
                callbacks.reject(fieldState, fieldType);
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
            if (fieldState.phase === 'error')   msg.className += ' icais-gut-status-error';
            msg.textContent = fieldState.message;
            wrapper.appendChild(msg);
        }

        void successLabel; // referenced in label resolution above; suppress unused-var for linters
        void errorLabel;
    }

    /**
     * Reset field state and call refreshUI after a 4-second success display.
     *
     * @param {object}   fieldState
     * @param {string}   fieldType
     * @param {function} refreshUI
     */
    function clearMessageLater(fieldState, fieldType, refreshUI) {
        setTimeout(function () {
            if (fieldState.phase === 'success') {
                fieldState.phase = 'idle';
                fieldState.message = '';
                refreshUI(fieldType);
            }
        }, 4000);
    }

    /**
     * Reject the current preview: call the reject API and reset state.
     *
     * @param {object}   fieldState
     * @param {string}   fieldType
     * @param {function} refreshUI
     */
    function handleReject(fieldState, fieldType, refreshUI) {
        var config = window.icaisGutenberg || {};

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

    /**
     * Inject (or re-inject) the wrapper div after a specific inspector field container.
     *
     * @param {object|null} block           - Selected block, or null if none.
     * @param {string}      fieldType       - 'alt' | 'title' | 'linkTitle'
     * @param {function}    findContainer   - Returns the DOM container to inject into.
     * @param {string}      markerClass     - Unique CSS class that identifies the injected wrapper.
     * @param {object}      fieldState      - Mutable state for this field.
     * @param {function}    renderFn        - Local renderInto(wrapper, fieldState, fieldType).
     * @param {object}      [opts]          - Optional: { marginTop: string }
     */
    function tryInjectField(block, fieldType, findContainer, markerClass, fieldState, renderFn, opts) {
        if (!block) {
            var orphan = document.querySelector('.' + markerClass);
            if (orphan) orphan.remove();
            return;
        }

        if (block.clientId !== fieldState.forBlockClientId) {
            fieldState.phase = 'idle';
            fieldState.previewText = '';
            fieldState.historyId = null;
            fieldState.message = '';
            fieldState.forBlockClientId = block.clientId;
        }

        var existing = document.querySelector('.' + markerClass);
        var container = findContainer();

        if (existing) {
            if (container && container.contains(existing)) {
                return;
            }
            existing.remove();
        }

        if (!container) return;

        var wrapper = document.createElement('div');
        wrapper.className = markerClass;
        if (opts && opts.marginTop) wrapper.style.marginTop = opts.marginTop;
        renderFn(wrapper, fieldState, fieldType);
        container.appendChild(wrapper);
    }

    /**
     * Start the MutationObserver that drives re-injection on DOM changes.
     *
     * @param {function} tryInject - Per-integration tryInject function.
     */
    function startObserver(tryInject) {
        var config = window.icaisGutenberg || {};
        if (!config.apiUrl || !config.nonce) return;

        var debounceTimer = null;
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

    window.IcaisBlockEditorCommon = {
        svgIcon: svgIcon,
        getPostTitle: getPostTitle,
        getAttachmentId: getAttachmentId,
        applyBlockAttribute: applyBlockAttribute,
        renderInto: renderInto,
        clearMessageLater: clearMessageLater,
        handleReject: handleReject,
        tryInjectField: tryInjectField,
        startObserver: startObserver
    };
})();
