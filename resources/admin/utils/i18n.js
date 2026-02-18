/**
 * Standalone i18n translation helper.
 *
 * Reads translated strings from window.icais.i18n (populated by
 * wp_localize_script in Enqueue.php). Falls back to the original English key
 * when no translation is found.
 *
 * Usage in <script setup>:
 *   import { $t } from '../utils/i18n'
 *   notification.success($t('Settings saved successfully'))
 *
 * Usage in templates (registered as globalProperties.$t in main.js):
 *   {{ $t('Total Images') }}
 *
 * Supports sprintf-style placeholders (%s, %d, %1s):
 *   $t('Generate for %s Selected', count)
 */
export function $t(string) {
    const i18n = (window.icais && window.icais.i18n) || {}
    string = i18n[string] || string

    const args = Array.prototype.slice.call(arguments, 1)

    if (args.length === 0) {
        return string
    }

    const regex = /%(\d+\$)?[sd]/g
    let argIndex = 0

    string = string.replace(regex, (match, number) => {
        if (number) {
            const index = parseInt(number, 10) - 1
            return index < args.length ? args[index] : match
        } else {
            return argIndex < args.length ? args[argIndex++] : match
        }
    })

    return string
}
