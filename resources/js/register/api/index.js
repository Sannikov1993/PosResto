import axios from 'axios'

export default {
    async validateToken(token) {
        const response = await axios.get('/api/register/validate-token', {
            params: { token },
        })
        return response.data
    },

    async register(data) {
        const response = await axios.post('/api/register', data)
        return response.data
    },
}
