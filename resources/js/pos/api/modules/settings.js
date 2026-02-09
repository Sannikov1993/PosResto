import http, { extractArray } from '../httpClient';

const settings = {
    async get() {
        try {
            const response = await http.get('/settings/pos');
            return response?.data ?? response;
        } catch {
            return null;
        }
    },

    async getGeneral() {
        try {
            const response = await http.get('/settings/general');
            return response?.data ?? response;
        } catch {
            return null;
        }
    },

    async save(settings) {
        return http.post('/settings/pos', settings);
    },

    async getPrinters() {
        const res = await http.get('/printers');
        return extractArray(res);
    },

    async testPrinter(id) {
        return http.post(`/printers/${id}/test`);
    }
};

export default settings;
