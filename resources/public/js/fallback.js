/**
 * ImageCraft — broken-image fallback.
 *
 * Swaps any <img> that fails to load to the configured fallback attachment and
 * fills in its alt text (SEO-aware). Two capture paths are required:
 *
 *  1. A CAPTURE-PHASE error listener — <img> error events do NOT bubble, so a
 *     normal delegated listener would catch nothing.
 *  2. An initial sweep for images that already failed BEFORE this deferred
 *     script ran (they never re-fire the error event).
 *
 * Vanilla JS, zero dependencies. Config injected via wp_localize_script.
 */
(function () {
    'use strict';

    var config = window.icaisFallback;
    if (!config || !config.imageUrl) {
        return;
    }

    /**
     * Swap a broken image to the fallback. Property assignment only — never
     * string-concatenate into HTML.
     */
    function swap(img) {
        // Infinite-loop guard: never re-swap an image we already swapped
        // (the marker is robust against browser URL normalization).
        if (img.getAttribute('data-icais-fallback') === '1') {
            return;
        }
        // Skip images with no real source to recover.
        var src = img.getAttribute('src');
        if (!src || src.indexOf('data:') === 0) {
            return;
        }

        img.setAttribute('data-icais-fallback', '1');
        // Clear responsive sources so the browser doesn't keep using srcset.
        if (img.srcset) {
            img.srcset = '';
        }
        if (img.getAttribute('sizes')) {
            img.removeAttribute('sizes');
        }
        img.src = config.imageUrl;
        if (!img.alt && config.altText) {
            img.alt = config.altText;
        }
    }

    // 1. Capture-phase listener for images that fail after this point.
    document.addEventListener('error', function (event) {
        var el = event.target;
        if (el && el.tagName === 'IMG') {
            swap(el);
        }
    }, true);

    // 2. Initial sweep for images that failed before the script ran.
    function sweep() {
        var images = document.images;
        for (var i = 0; i < images.length; i++) {
            var img = images[i];
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
                swap(img);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sweep);
    } else {
        sweep();
    }
})();
