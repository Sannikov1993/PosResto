import http, { extractArray } from '../httpClient.js';

interface PosSettings {
    [key: string]: unknown;
}

interface Printer {
    id: number;
    name: string;
    [key: string]: unknown;
}

const settings = {
    async get(): Promise<PosSettings | null> {
        try {
            const response = await http.get('/settings/pos') as Record<string, any>;
            return (response?.data ?? response) as PosSettings;
        } catch {
            return null;
        }
    },

    async getGeneral(): Promise<PosSettings | null> {
        try {
            const response = await http.get('/settings/general') as Record<string, any>;
            return (response?.data ?? response) as PosSettings;
        } catch {
            return null;
        }
    },

    async save(settingsData: Record<string, any>): Promise<unknown> {
        return http.post('/settings/pos', settingsData);
    },

    async getPrinters(): Promise<Printer[]> {
        const res = await http.get('/printers');
        return extractArray<Printer>(res);
    },

    async testPrinter(id: number): Promise<unknown> {
        return http.post(`/printers/${id}/test`);
    }
};

export default settings;
