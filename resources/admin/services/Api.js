import config from '../config/api.config'

class Api {
    constructor() {
        this.baseURL = config.baseURL
        this.headers = config.headers
    }

    async request(endpoint, options = {}) {
        try {
            const url = `${this.baseURL}${endpoint}`
            
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
                    } catch (e) {
                        throw new Error(`Expected JSON response but received: ${contentType || 'unknown'}`)
                    }
                }
            }
            
            return data

        } catch (error) {
            console.error('API Request Failed:', error)
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