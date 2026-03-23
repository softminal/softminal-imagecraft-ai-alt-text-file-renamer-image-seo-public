/**
 * Creates a debounced version of a function.
 *
 * @param {Function} fn - The function to debounce
 * @param {number} delay - Delay in milliseconds (default 300)
 * @returns {Function} Debounced function
 */
export function debounce(fn, delay = 300) {
    let timer = null
    return function (...args) {
        if (timer) clearTimeout(timer)
        timer = setTimeout(() => {
            fn.apply(this, args)
            timer = null
        }, delay)
    }
}
