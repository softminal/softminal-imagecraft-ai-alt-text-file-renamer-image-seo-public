export default {
    baseURL: window.imageCraft?.apiUrl || '/wp-json/imagecraft/v1',
    nonce: window.imageCraft?.nonce || '',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.imageCraft?.nonce || ''
    }
}
