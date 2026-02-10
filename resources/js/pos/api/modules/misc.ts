import http from '../httpClient.js';

interface PayrollStatus {
    clocked_in: boolean;
    [key: string]: unknown;
}

interface BriefStats {
    [key: string]: unknown;
}

const payroll = {
    async getMyStatus(): Promise<PayrollStatus> {
        return http.get('/payroll/my-status') as Promise<PayrollStatus>;
    },

    async clockIn(): Promise<unknown> {
        return http.post('/payroll/my-clock-in');
    },

    async clockOut(): Promise<unknown> {
        return http.post('/payroll/my-clock-out');
    }
};

const realtime = {
    async sendEvent(channel: string, event: string, data: Record<string, any> = {}): Promise<unknown> {
        return http.post('/realtime/send', {
            channel,
            event,
            data
        });
    },

    async sendKitchenNotification(message: string, data: Record<string, any> = {}): Promise<unknown> {
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
    async getBriefStats(): Promise<BriefStats | null> {
        try {
            return await http.get('/dashboard/stats/brief') as BriefStats;
        } catch {
            return null;
        }
    }
};

export { payroll, realtime, dashboard };
