import http from '../httpClient';

const payroll = {
    async getMyStatus() {
        return http.get('/payroll/my-status');
    },

    async clockIn() {
        return http.post('/payroll/my-clock-in');
    },

    async clockOut() {
        return http.post('/payroll/my-clock-out');
    }
};

const realtime = {
    async sendEvent(channel, event, data = {}) {
        return http.post('/realtime/send', {
            channel,
            event,
            data
        });
    },

    async sendKitchenNotification(message, data = {}) {
        return http.post('/realtime/send', {
            channel: 'kitchen',
            event: 'stop_list_notification',
            data: {
                message,
                priority: 'high',
                sound: true,
                ...data
            }
        });
    }
};

const dashboard = {
    async getBriefStats() {
        try {
            return await http.get('/dashboard/stats/brief');
        } catch {
            return null;
        }
    }
};

export { payroll, realtime, dashboard };
