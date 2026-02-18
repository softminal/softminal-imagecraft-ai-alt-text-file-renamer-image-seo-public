export default {
    baseURL: window.icais?.apiUrl || '/wp-json/icais/v1',
    nonce: window.icais?.nonce || '',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.icais?.nonce || ''
    }
}
