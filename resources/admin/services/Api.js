import config from '../config/api.config'

class Api {
    constructor() {
        this.baseURL = config.baseURL
        this.headers = config.headers
        
        // Debug: Log API configuration
        if (typeof window !== 'undefined' && window.wpAiAltText) {
            console.log('API Config loaded:', {
                baseURL: this.baseURL,
                hasNonce: !!this.headers['X-WP-Nonce']
            })
        } else {
            console.warn('API Config: wpAiAltText not found in window, using fallback URL')
        }
    }

    async request(endpoint, options = {}) {
        try {
            const url = `${this.baseURL}${endpoint}`
            
            // Debug logging
            if (typeof window !== 'undefined' && (window.wpAiAltText?.debug || import.meta.env.DEV)) {
                console.log(`API Request: ${options.method || 'GET'} ${url}`)
            }
            
            const response = await fetch(url, {
                ...options,
                headers: this.headers
            })

            // Check content type before parsing
            const contentType = response.headers.get('content-type')
            const isJson = contentType && contentType.includes('application/json')

            if (!response.ok) {
                // Try to get error message from response
                let errorMessage = `API Error: ${response.status} ${response.statusText}`
                
                if (isJson) {
                    try {
                        const errorData = await response.json()
                        errorMessage = errorData.message || errorData.error || errorMessage
                    } catch (e) {
                        // If JSON parsing fails, use default message
                    }
                } else {
                    // If response is HTML (like a 404 page), get text for debugging
                    try {
                        const text = await response.text()
                        console.error('Non-JSON error response:', text.substring(0, 200))
                        errorMessage = `API endpoint not found or returned HTML. Check if the endpoint exists: ${url}`
                    } catch (e) {
                        // If text parsing also fails, use default message
                    }
                }
                
                throw new Error(errorMessage)
            }

            // Parse JSON response
            let data;
            if (isJson) {
                data = await response.json()
            } else {
                // Try to extract JSON from response (in case HTML is prepended)
                const text = await response.text()
                
                // Try to find JSON in the response (look for { or [)
                const jsonMatch = text.match(/\{[\s\S]*\}|\[[\s\S]*\]/)
                if (jsonMatch) {
                    try {
                        data = JSON.parse(jsonMatch[0])
                        console.warn('Extracted JSON from non-JSON response. HTML was prepended to response.')
                    } catch (e) {
                        console.error('Failed to parse extracted JSON:', e)
                        throw new Error(`Expected JSON response but received: ${contentType || 'unknown'}`)
                    }
                } else {
                    console.warn('Response is not JSON:', text.substring(0, 200))
                    throw new Error(`Expected JSON response but received: ${contentType || 'unknown'}`)
                }
            }
            
            return data

        } catch (error) {
            console.error('API Request Failed:', error)
            // Log additional debugging info
            if (error.message.includes('Unexpected token')) {
                console.error('This usually means the API endpoint returned HTML instead of JSON.')
                console.error('Check if:', [
                    '1. The REST API endpoint is registered correctly',
                    '2. The baseURL is correct:',
                    `   Current: ${this.baseURL}${endpoint}`,
                    '3. WordPress REST API is enabled',
                    '4. The endpoint requires authentication and nonce is valid'
                ].join('\n'))
            }
            throw error
        }
    }

    async get(endpoint) {
        return this.request(endpoint, {
            method: 'GET'
        })
    }

    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        })
    }

    async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        })
    }

    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        })
    }
}

export default new Api()