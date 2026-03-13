/**
 * Returns the full URL for a file inside resources/images/.
 *
 * Usage:
 *   import { useAsset } from '../composables/useAsset'
 *   const { asset } = useAsset()
 *   asset('imagecraft-logo.png') // → full URL to the image
 */
export function useAsset() {
  const pluginUrl = window.icais?.pluginUrl || ''

  const asset = (filename) => {
    return `${pluginUrl}resources/images/${filename}`
  }

  return { asset }
}
