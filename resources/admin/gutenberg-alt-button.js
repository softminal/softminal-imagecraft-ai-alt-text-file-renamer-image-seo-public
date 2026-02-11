/**
 * Smart Alt Text AI - Gutenberg Block Editor Integration
 *
 * Injects a "Generate with AI" button directly below the existing
 * "Alternative Text" field in the core/image block inspector sidebar.
 *
 * Uses MutationObserver + DOM injection (same pattern as media-button.js).
 * State persists across React re-renders via closure variables.
 *
 * Standalone vanilla JS — no JSX, no Vite bundling.
 */
(function () {
    'use strict';

    var config = window.sataiGutenberg || {};
    var MARKER_CLASS = 'satai-gut-wrapper';
    var debounceTimer = null;

    // Persistent state across React re-renders
    var state = {
        phase: 'idle', // idle | loading | preview | success | error
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

    function getSelectedImageBlock() {
        try {
            var block = wp.data.select('core/block-editor').getSelectedBlock();
            if (block && block.name === 'core/image') {
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

    function applyAltText(text) {
        var block = getSelectedImageBlock();
        if (block) {
            wp.data.dispatch('core/block-editor').updateBlockAttributes(
                block.clientId,
                { alt: text }
            );
        }
    }

    // ── Find the Alt Text field container ────────────────────

    function findAltTextContainer() {
        var inspector = document.querySelector('.block-editor-block-inspector');
        if (!inspector) return null;

        // Strategy 1: help text that mentions "Describe the purpose" / "decorative"
        var helps = inspector.querySelectorAll('.components-base-control__help');
        for (var i = 0; i < helps.length; i++) {
            var txt = helps[i].textContent.toLowerCase();
            if (txt.indexOf('describe the purpose') !== -1 || txt.indexOf('decorative') !== -1) {
                return helps[i].closest('.components-base-control');
            }
        }

        // Strategy 2: first textarea in the inspector (alt text is always first)
        var ta = inspector.querySelector('textarea.components-textarea-control__input');
        if (ta) {
            return ta.closest('.components-base-control');
        }

        return null;
    }

    // ── UI Rendering (pure DOM) ──────────────────────────────

    function renderInto(wrapper) {
        wrapper.innerHTML = '';

        // Generate button (idle / success / error)
        if (state.phase === 'idle' || state.phase === 'success' || state.phase === 'error') {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'components-button is-secondary satai-gut-btn';
            btn.innerHTML =
                '<span class="satai-gut-btn-icon">' + svgIcon + '</span>' +
                '<span>' + (config.i18n?.generate || 'Generate with AI') + '</span>';
            btn.addEventListener('click', handleGenerate);
            wrapper.appendChild(btn);
        }

        // Loading
        if (state.phase === 'loading') {
            var busy = document.createElement('button');
            busy.type = 'button';
            busy.className = 'components-button is-secondary satai-gut-btn satai-gut-loading';
            busy.disabled = true;
            busy.innerHTML =
                '<span class="satai-gut-btn-icon satai-gut-spin">' + svgIcon + '</span>' +
                '<span>' + (config.i18n?.generating || 'Generating...') + '</span>';
            wrapper.appendChild(busy);
        }

        // Preview
        if (state.phase === 'preview') {
            var preview = document.createElement('div');
            preview.className = 'satai-gut-preview';

            var ta = document.createElement('textarea');
            ta.className = 'components-textarea-control__input satai-gut-preview-ta';
            ta.value = state.previewText;
            ta.rows = 3;
            ta.addEventListener('input', function (e) {
                state.previewText = e.target.value;
            });
            preview.appendChild(ta);

            var actions = document.createElement('div');
            actions.className = 'satai-gut-preview-actions';

            var acceptBtn = document.createElement('button');
            acceptBtn.type = 'button';
            acceptBtn.className = 'components-button is-primary is-compact';
            acceptBtn.textContent = config.i18n?.accept || 'Accept';
            acceptBtn.addEventListener('click', handleAccept);

            var rejectBtn = document.createElement('button');
            rejectBtn.type = 'button';
            rejectBtn.className = 'components-button is-tertiary is-compact is-destructive';
            rejectBtn.textContent = config.i18n?.reject || 'Reject';
            rejectBtn.addEventListener('click', handleReject);

            actions.appendChild(acceptBtn);
            actions.appendChild(rejectBtn);
            preview.appendChild(actions);
            wrapper.appendChild(preview);
        }

        // Status message
        if (state.message) {
            var msg = document.createElement('div');
            msg.className = 'satai-gut-status';
            if (state.phase === 'success') msg.className += ' satai-gut-status-success';
            if (state.phase === 'error') msg.className += ' satai-gut-status-error';
            msg.textContent = state.message;
            wrapper.appendChild(msg);
        }
    }

    function refreshUI() {
        var el = document.querySelector('.' + MARKER_CLASS);
        if (el) renderInto(el);
    }

    // ── Handlers ─────────────────────────────────────────────

    function handleGenerate() {
        var block = getSelectedImageBlock();
        if (!block || !block.attributes.id) return;

        state.phase = 'loading';
        state.message = '';
        state.forBlockClientId = block.clientId;
        refreshUI();

        var body = { attachment_id: block.attributes.id };
        var title = getPostTitle();
        if (title) body.post_title = title;

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
                state.historyId = data.history_id || null;

                if (config.previewBeforeAccept) {
                    state.phase = 'preview';
                    state.previewText = data.alt_text;
                } else {
                    applyAltText(data.alt_text);
                    state.phase = 'success';
                    state.message = config.i18n?.success || 'Alt text generated!';
                    clearMessageLater();
                }
            } else {
                state.phase = 'error';
                state.message = data.message || config.i18n?.error || 'Failed to generate alt text.';
            }
            refreshUI();
        })
        .catch(function () {
            state.phase = 'error';
            state.message = config.i18n?.error || 'Failed to generate alt text.';
            refreshUI();
        });
    }

    function handleAccept() {
        applyAltText(state.previewText);

        if (state.historyId) {
            fetch(config.apiUrl + '/generator/accept', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify({ history_id: state.historyId, alt_text: state.previewText })
            }).catch(function () {});
        }

        state.phase = 'success';
        state.message = config.i18n?.success || 'Alt text generated!';
        state.historyId = null;
        state.previewText = '';
        refreshUI();
        clearMessageLater();
    }

    function handleReject() {
        if (state.historyId) {
            fetch(config.apiUrl + '/generator/reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify({ history_id: state.historyId })
            }).catch(function () {});
        }

        state.phase = 'idle';
        state.previewText = '';
        state.historyId = null;
        state.message = '';
        refreshUI();
    }

    function clearMessageLater() {
        setTimeout(function () {
            if (state.phase === 'success') {
                state.phase = 'idle';
                state.message = '';
                refreshUI();
            }
        }, 4000);
    }

    // ── Injection Logic ──────────────────────────────────────

    function tryInject() {
        var block = getSelectedImageBlock();

        // No image block selected — clean up
        if (!block || !block.attributes.id) {
            var orphan = document.querySelector('.' + MARKER_CLASS);
            if (orphan) orphan.remove();
            return;
        }

        // Reset state when a different block is selected
        if (block.clientId !== state.forBlockClientId) {
            state.phase = 'idle';
            state.previewText = '';
            state.historyId = null;
            state.message = '';
            state.forBlockClientId = block.clientId;
        }

        // Already injected and still in the DOM under the right container?
        var existing = document.querySelector('.' + MARKER_CLASS);
        if (existing) {
            var container = findAltTextContainer();
            if (container && container.contains(existing)) {
                return; // all good
            }
            existing.remove();
        }

        // Find alt text container and inject
        var container = findAltTextContainer();
        if (!container) return;

        var wrapper = document.createElement('div');
        wrapper.className = MARKER_CLASS;
        renderInto(wrapper);
        container.appendChild(wrapper);
    }

    // ── Init ─────────────────────────────────────────────────

    function init() {
        if (!config.apiUrl || !config.nonce) return;

        // Watch for DOM changes (React re-renders in the sidebar)
        var observer = new MutationObserver(function () {
            if (debounceTimer) return;
            debounceTimer = setTimeout(function () {
                debounceTimer = null;
                tryInject();
            }, 80);
        });

        observer.observe(document.body, { childList: true, subtree: true });

        // Initial attempt
        tryInject();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
