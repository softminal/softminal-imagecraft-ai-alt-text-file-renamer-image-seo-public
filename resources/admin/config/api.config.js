export default {
    baseURL: window.altTextAi?.apiUrl || '/wp-json/smart-alt-text-ai/v1',
    nonce: window.altTextAi?.nonce || '',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.altTextAi?.nonce || ''
    }
}