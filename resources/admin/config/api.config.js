export default {
    baseURL: window.smartAltTextAi?.apiUrl || '/wp-json/smart-alt-text-ai/v1',
    nonce: window.smartAltTextAi?.nonce || '',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.smartAltTextAi?.nonce || ''
    }
}