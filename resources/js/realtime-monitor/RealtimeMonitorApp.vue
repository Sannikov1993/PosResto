<template>
    <div class="min-h-screen bg-gray-900 text-gray-100 p-6">
        <header class="mb-6">
            <h1 class="text-2xl font-bold">Realtime Monitor</h1>
            <p class="text-gray-400">Отладочный инструмент для real-time событий</p>
        </header>

        <!-- Connection Status -->
        <div class="mb-6 flex items-center gap-4">
            <div :class="['w-3 h-3 rounded-full', connected ? 'bg-green-500' : 'bg-red-500']"></div>
            <span>{{ connected ? 'Подключено' : 'Отключено' }}</span>
            <button @click="reconnect" class="px-3 py-1 bg-blue-600 rounded text-sm">Переподключить</button>
            <button @click="clearEvents" class="px-3 py-1 bg-gray-700 rounded text-sm">Очистить</button>
        </div>

        <!-- Channels -->
        <div class="mb-6">
            <h3 class="text-sm font-medium text-gray-400 mb-2">Каналы</h3>
            <div class="flex flex-wrap gap-2">
                <label v-for="ch in channels" :key="ch"
                       :class="['px-3 py-1 rounded cursor-pointer text-sm',
                                activeChannels.includes(ch) ? 'bg-blue-600' : 'bg-gray-700']">
                    <input type="checkbox" :value="ch" v-model="activeChannels" class="hidden">
                    {{ ch }}
                </label>
            </div>
        </div>

        <!-- Events -->
        <div class="bg-gray-800 rounded-xl overflow-hidden">
            <div class="px-4 py-2 bg-gray-700 flex justify-between items-center">
                <span class="font-medium">События ({{ events.length }})</span>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="autoScroll">
                    Автопрокрутка
                </label>
            </div>
            <div ref="eventsContainer" class="h-[500px] overflow-y-auto p-4 font-mono text-sm space-y-2">
                <div v-for="(event, idx) in events" :key="idx"
                     class="p-3 bg-gray-900 rounded border-l-4"
                     :class="getEventBorderColor(event.channel)">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>{{ event.channel }} / {{ event.event }}</span>
                        <span>{{ event.time }}</span>
                    </div>
                    <pre class="text-green-400 whitespace-pre-wrap">{{ JSON.stringify(event.data, null, 2) }}</pre>
                </div>
                <div v-if="events.length === 0" class="text-center text-gray-500 py-8">
                    Ожидание событий...
                </div>
            </div>
        </div>

        <!-- Simulate Event -->
        <div class="mt-6 bg-gray-800 rounded-xl p-4">
            <h3 class="font-medium mb-3">Симуляция события</h3>
            <div class="grid grid-cols-3 gap-4">
                <select v-model="simChannel" class="bg-gray-700 rounded px-3 py-2">
                    <option v-for="ch in channels" :key="ch" :value="ch">{{ ch }}</option>
                </select>
                <input v-model="simEvent" placeholder="Событие" class="bg-gray-700 rounded px-3 py-2">
                <button @click="simulateEvent" class="bg-orange-500 rounded px-4 py-2 font-medium">
                    Отправить
                </button>
            </div>
            <textarea v-model="simData" rows="3" placeholder='{"key": "value"}'
                      class="w-full mt-3 bg-gray-700 rounded px-3 py-2 font-mono text-sm"></textarea>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';

const connected = ref(false);
const events = ref<any[]>([]);
const autoScroll = ref(true);
const eventsContainer = ref<any>(null);

const channels = ['orders', 'kitchen', 'delivery', 'tables', 'staff', 'reservations'];
const activeChannels = ref(['orders', 'kitchen', 'tables']);

const simChannel = ref('orders');
const simEvent = ref('test_event');
const simData = ref('{"message": "Test"}');

let eventSource: any = null;

function connect() {
    if (eventSource) eventSource.close();

    const channelsParam = activeChannels.value.join(',');
    eventSource = new EventSource(`/api/realtime/stream?channels=${channelsParam}`);

    eventSource.onopen = () => { connected.value = true; };
    eventSource.onerror = () => { connected.value = false; };

    eventSource.onmessage = (e: any) => {
        try {
            const data = JSON.parse(e.data);
            events.value.push({
                channel: data.channel || 'unknown',
                event: data.event || 'message',
                data: data.data || data,
                time: new Date().toLocaleTimeString()
            });

            if (autoScroll.value && eventsContainer.value) {
                nextTick(() => {
                    eventsContainer.value.scrollTop = eventsContainer.value.scrollHeight;
                });
            }
        } catch (err: any) { console.error(err); }
    };
}

function reconnect() {
    connect();
}

function clearEvents() {
    events.value = [];
}

function getEventBorderColor(channel: any) {
    const colors = {
        orders: 'border-blue-500',
        kitchen: 'border-yellow-500',
        delivery: 'border-purple-500',
        tables: 'border-green-500',
        staff: 'border-orange-500',
        reservations: 'border-pink-500'
    };
    return (colors as Record<string, any>)[channel] || 'border-gray-500';
}

async function simulateEvent() {
    try {
        let data = {};
        try { data = JSON.parse(simData.value); } catch {}

        await fetch('/api/realtime/simulate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                channel: simChannel.value,
                event: simEvent.value,
                data
            })
        });
    } catch (e: any) { console.error(e); }
}

watch(activeChannels, () => { connect(); });

onMounted(() => { connect(); });
onBeforeUnmount(() => { if (eventSource) eventSource.close(); });
</script>
