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

            if (!response.ok) {
                throw new Error(`API Error: ${response.status}`)
            }

            const data = await response.json()
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