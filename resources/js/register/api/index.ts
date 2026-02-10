import axios from 'axios'

export default {
    async validateToken(token: string) {
        const response = await axios.get('/api/register/validate-token', {
            params: { token },
        })
        return response.data
    },

    async register(data: Record<string, any>) {
        const response = await axios.post('/api/register', data)
        return response.data
    },
}
