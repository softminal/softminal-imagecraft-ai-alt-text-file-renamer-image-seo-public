export default {
    baseURL: window.wpAiAltText?.apiUrl || '/wp-json/wp-ai-alt-text/v1',
    nonce: window.wpAiAltText?.nonce || '',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.wpAiAltText?.nonce || ''
    }
}