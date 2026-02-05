<template>
    <div class="space-y-6">
        <!-- Telegram Bot Section -->
        <TelegramBotCard />

        <!-- Stats Cards -->
        <div class="flex flex-wrap gap-2 mb-4">
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-blue-500 flex items-center gap-2">
                <span class="text-lg">🔑</span>
                <div>
                    <p class="text-xs text-blue-600">API клиенты</p>
                    <p class="text-lg font-bold text-blue-900 leading-tight">{{ clients.length }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-green-500 flex items-center gap-2">
                <span class="text-lg">✅</span>
                <div>
                    <p class="text-xs text-green-600">Активные</p>
                    <p class="text-lg font-bold text-green-900 leading-tight">{{ activeClientsCount }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm px-3 py-2 border-l-3 border-purple-500 flex items-center gap-2">
                <span class="text-lg">📊</span>
                <div>
                    <p class="text-xs text-purple-600">Запросов</p>
                    <p class="text-lg font-bold text-purple-900 leading-tight">{{ totalRequests }}</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-xl shadow-sm">
            <!-- Header -->
            <div class="p-6 border-b flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">API Интеграции</h2>
                    <p class="text-sm text-gray-500 mt-1">Управление API ключами для внешних интеграций</p>
                </div>
                <button @click="openModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition flex items-center gap-2">
                    <span>+</span> Создать API клиент
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="p-12 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-orange-500 border-t-transparent"></div>
                <p class="mt-4 text-gray-500">Загрузка...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="clients.length === 0" class="p-12 text-center">
                <div class="text-6xl mb-4">🔗</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Нет API клиентов</h3>
                <p class="text-gray-500 mb-4">Создайте API клиент для интеграции с внешними системами</p>
                <button @click="openModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Создать API клиент
                </button>
            </div>

            <!-- Clients List -->
            <div v-else class="divide-y">
                <div v-for="client in clients" :key="client.id"
                     class="p-6 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-4">
                            <!-- Status Indicator -->
                            <div :class="['w-12 h-12 rounded-xl flex items-center justify-center',
                                          client.is_active ? 'bg-green-100' : 'bg-gray-100']">
                                <span class="text-2xl">🔑</span>
                            </div>

                            <!-- Info -->
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-gray-900">{{ client.name }}</h3>
                                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
                                                   client.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600']">
                                        {{ client.is_active ? 'Активен' : 'Неактивен' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        {{ ratePlanLabels[client.rate_plan] || client.rate_plan }}
                                    </span>
                                </div>
                                <p v-if="client.description" class="text-sm text-gray-500 mt-1">{{ client.description }}</p>

                                <!-- API Key -->
                                <div class="flex items-center gap-2 mt-2">
                                    <code class="px-2 py-1 bg-gray-100 rounded text-sm font-mono text-gray-600">
                                        {{ client.api_key_masked }}
                                    </code>
                                    <button @click="copyToClipboard(client.api_key)"
                                            class="p-1 text-gray-400 hover:text-gray-600" title="Копировать ключ">
                                        📋
                                    </button>
                                </div>

                                <!-- Scopes -->
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <span v-for="scope in client.scopes" :key="scope"
                                          class="px-2 py-0.5 bg-purple-50 text-purple-600 text-xs rounded">
                                        {{ scope }}
                                    </span>
                                </div>

                                <!-- Stats -->
                                <div class="flex items-center gap-4 mt-3 text-sm text-gray-500">
                                    <span>Запросов: {{ client.request_logs_count || 0 }}</span>
                                    <span v-if="client.last_used_at">Последний: {{ formatDate(client.last_used_at) }}</span>
                                    <span v-if="client.webhook_url" class="flex items-center gap-1">
                                        <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                        Webhook настроен
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button @click="viewClient(client)"
                                    class="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition"
                                    title="Подробности">
                                👁️
                            </button>
                            <button @click="openModal(client)"
                                    class="px-3 py-2 text-orange-600 hover:bg-orange-50 rounded-lg transition"
                                    title="Редактировать">
                                ✏️
                            </button>
                            <button @click="toggleActive(client)"
                                    :class="['px-3 py-2 rounded-lg transition',
                                             client.is_active ? 'text-yellow-600 hover:bg-yellow-50' : 'text-green-600 hover:bg-green-50']"
                                    :title="client.is_active ? 'Деактивировать' : 'Активировать'">
                                {{ client.is_active ? '⏸️' : '▶️' }}
                            </button>
                            <button @click="confirmDelete(client)"
                                    class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition"
                                    title="Удалить">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                <!-- Modal Header -->
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold">{{ editingClient ? 'Редактировать API клиент' : 'Создать API клиент' }}</h3>
                    <button @click="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg">✕</button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                    <form @submit.prevent="saveClient">
                        <!-- Name -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                            <input v-model="form.name" type="text" required
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                   placeholder="Мобильное приложение">
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                            <textarea v-model="form.description" rows="2"
                                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                      placeholder="Для чего используется этот API клиент"></textarea>
                        </div>

                        <!-- Rate Plan -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Тарифный план</label>
                            <select v-model="form.rate_plan"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="free">Free (60 запросов/мин)</option>
                                <option value="business">Business (300 запросов/мин)</option>
                                <option value="enterprise">Enterprise (1000 запросов/мин)</option>
                            </select>
                        </div>

                        <!-- Scopes -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Права доступа (Scopes)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label v-for="scope in availableScopes" :key="scope.scope"
                                       class="flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" v-model="form.scopes" :value="scope.scope"
                                           class="mt-1 rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ scope.scope }}</div>
                                        <div class="text-xs text-gray-500">{{ scope.description }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Webhook URL -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                            <input v-model="form.webhook_url" type="url"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                   placeholder="https://example.com/webhook">
                        </div>

                        <!-- Webhook Events -->
                        <div v-if="form.webhook_url" class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Webhook события</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label v-for="event in availableWebhookEvents" :key="event.event"
                                       class="flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" v-model="form.webhook_events" :value="event.event"
                                           class="mt-1 rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ event.event }}</div>
                                        <div class="text-xs text-gray-500">{{ event.description }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t flex justify-end gap-3">
                    <button @click="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                        Отмена
                    </button>
                    <button @click="saveClient" :disabled="saving"
                            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-orange-300 text-white rounded-lg font-medium transition">
                        {{ saving ? 'Сохранение...' : (editingClient ? 'Сохранить' : 'Создать') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- View Details Modal -->
        <div v-if="showDetailsModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
                <!-- Modal Header -->
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold">API клиент: {{ selectedClient?.name }}</h3>
                    <button @click="showDetailsModal = false" class="p-2 hover:bg-gray-100 rounded-lg">✕</button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
                    <div v-if="loadingDetails" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-orange-500 border-t-transparent"></div>
                    </div>
                    <div v-else-if="clientDetails">
                        <!-- Credentials -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Учётные данные</h4>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500">API Key</label>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 px-3 py-2 bg-white rounded border font-mono text-sm">{{ clientDetails.api_key }}</code>
                                        <button @click="copyToClipboard(clientDetails.api_key)" class="p-2 hover:bg-gray-200 rounded">📋</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">API Secret</label>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 px-3 py-2 bg-white rounded border font-mono text-sm">{{ showSecret ? clientDetails.api_secret : '••••••••••••••••' }}</code>
                                        <button @click="showSecret = !showSecret" class="p-2 hover:bg-gray-200 rounded">{{ showSecret ? '🙈' : '👁️' }}</button>
                                        <button @click="copyToClipboard(clientDetails.api_secret)" class="p-2 hover:bg-gray-200 rounded">📋</button>
                                    </div>
                                </div>
                                <div v-if="clientDetails.webhook_secret">
                                    <label class="text-xs text-gray-500">Webhook Secret</label>
                                    <div class="flex items-center gap-2">
                                        <code class="flex-1 px-3 py-2 bg-white rounded border font-mono text-sm">{{ clientDetails.webhook_secret }}</code>
                                        <button @click="copyToClipboard(clientDetails.webhook_secret)" class="p-2 hover:bg-gray-200 rounded">📋</button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <button @click="regenerateCredentials('both')" :disabled="regenerating"
                                        class="px-3 py-2 text-sm bg-yellow-100 text-yellow-700 hover:bg-yellow-200 rounded-lg transition">
                                    🔄 Перегенерировать ключи
                                </button>
                                <button v-if="clientDetails.webhook_url" @click="regenerateCredentials('webhook')" :disabled="regenerating"
                                        class="px-3 py-2 text-sm bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg transition">
                                    🔄 Перегенерировать webhook secret
                                </button>
                            </div>
                        </div>

                        <!-- Webhook -->
                        <div v-if="clientDetails.webhook_url" class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Webhook</h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="mb-2">
                                    <label class="text-xs text-gray-500">URL</label>
                                    <div class="font-mono text-sm">{{ clientDetails.webhook_url }}</div>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    <span v-for="event in clientDetails.webhook_events" :key="event"
                                          class="px-2 py-0.5 bg-blue-100 text-blue-600 text-xs rounded">
                                        {{ event }}
                                    </span>
                                </div>
                                <button @click="testWebhook" :disabled="testingWebhook"
                                        class="mt-3 px-3 py-2 text-sm bg-green-100 text-green-700 hover:bg-green-200 rounded-lg transition">
                                    {{ testingWebhook ? 'Тестирование...' : '🧪 Тестировать webhook' }}
                                </button>
                                <div v-if="webhookTestResult" :class="['mt-2 p-3 rounded-lg text-sm',
                                                                       webhookTestResult.success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700']">
                                    <div class="font-medium">{{ webhookTestResult.success ? 'Успешно' : 'Ошибка' }}</div>
                                    <div v-if="webhookTestResult.status_code">Status: {{ webhookTestResult.status_code }}</div>
                                    <div v-if="webhookTestResult.error" class="text-xs mt-1">{{ webhookTestResult.error }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Usage Stats -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Статистика (последние 7 дней)</h4>
                            <div v-if="clientDetails.stats?.length" class="bg-gray-50 rounded-lg p-4">
                                <div class="space-y-2">
                                    <div v-for="stat in clientDetails.stats" :key="stat.date"
                                         class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">{{ stat.date }}</span>
                                        <div class="flex items-center gap-4">
                                            <span>{{ stat.requests }} запросов</span>
                                            <span class="text-gray-500">{{ Math.round(stat.avg_response_time) }}ms</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">
                                Нет данных
                            </div>
                        </div>

                        <!-- API Documentation Link -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-blue-900 mb-2">Документация API</h4>
                            <p class="text-sm text-blue-700">
                                Используйте эти учётные данные для доступа к API MenuLab v1.
                            </p>
                            <div class="mt-3 text-sm font-mono bg-white rounded p-3 border border-blue-200">
                                <div class="text-gray-600"># Пример запроса</div>
                                <div class="text-blue-800">curl -X GET "{{ apiBaseUrl }}/api/v1/menu/dishes" \</div>
                                <div class="text-blue-800 pl-4">-H "X-API-Key: {{ clientDetails.api_key }}" \</div>
                                <div class="text-blue-800 pl-4">-H "X-API-Secret: YOUR_SECRET"</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t flex justify-end">
                    <button @click="showDetailsModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                        Закрыть
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div class="p-6 text-center">
                    <div class="text-5xl mb-4">⚠️</div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Удалить API клиент?</h3>
                    <p class="text-gray-500 mb-6">
                        Клиент "{{ deletingClient?.name }}" будет удалён. Все интеграции, использующие этот ключ, перестанут работать.
                    </p>
                    <div class="flex gap-3">
                        <button @click="showDeleteModal = false" class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50 transition">
                            Отмена
                        </button>
                        <button @click="deleteClient" :disabled="deleting"
                                class="flex-1 px-4 py-2 bg-red-500 hover:bg-red-600 disabled:bg-red-300 text-white rounded-lg font-medium transition">
                            {{ deleting ? 'Удаление...' : 'Удалить' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';
import TelegramBotCard from '../TelegramBotCard.vue';

const store = useBackofficeStore();

// State
const loading = ref(true);
const clients = ref([]);
const availableScopes = ref([]);
const availableWebhookEvents = ref([]);

// Modal state
const showModal = ref(false);
const editingClient = ref(null);
const saving = ref(false);
const form = ref({
    name: '',
    description: '',
    rate_plan: 'free',
    scopes: ['menu:read'],
    webhook_url: '',
    webhook_events: []
});

// Details modal state
const showDetailsModal = ref(false);
const selectedClient = ref(null);
const clientDetails = ref(null);
const loadingDetails = ref(false);
const showSecret = ref(false);
const regenerating = ref(false);
const testingWebhook = ref(false);
const webhookTestResult = ref(null);

// Delete modal state
const showDeleteModal = ref(false);
const deletingClient = ref(null);
const deleting = ref(false);

// Labels
const ratePlanLabels = {
    free: 'Free',
    business: 'Business',
    enterprise: 'Enterprise'
};

// Computed
const activeClientsCount = computed(() => clients.value.filter(c => c.is_active).length);
const totalRequests = computed(() => clients.value.reduce((sum, c) => sum + (c.request_logs_count || 0), 0));
const apiBaseUrl = computed(() => window.location.origin);

// Methods
const loadClients = async () => {
    loading.value = true;
    try {
        const response = await store.api('/backoffice/api-clients');
        clients.value = response.data || [];
    } catch (e) {
        store.showToast('Ошибка загрузки API клиентов', 'error');
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const loadScopes = async () => {
    try {
        const response = await store.api('/backoffice/api-clients/scopes');
        availableScopes.value = response.data || [];
    } catch (e) {
        console.error('Failed to load scopes:', e);
    }
};

const loadWebhookEvents = async () => {
    try {
        const response = await store.api('/backoffice/api-clients/webhook-events');
        availableWebhookEvents.value = response.data || [];
    } catch (e) {
        console.error('Failed to load webhook events:', e);
    }
};

const openModal = (client = null) => {
    editingClient.value = client;
    if (client) {
        form.value = {
            name: client.name,
            description: client.description || '',
            rate_plan: client.rate_plan || 'free',
            scopes: [...(client.scopes || [])],
            webhook_url: client.webhook_url || '',
            webhook_events: [...(client.webhook_events || [])]
        };
    } else {
        form.value = {
            name: '',
            description: '',
            rate_plan: 'free',
            scopes: ['menu:read'],
            webhook_url: '',
            webhook_events: []
        };
    }
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingClient.value = null;
};

const saveClient = async () => {
    if (!form.value.name.trim()) {
        store.showToast('Введите название', 'error');
        return;
    }

    saving.value = true;
    try {
        if (editingClient.value) {
            await store.api(`/backoffice/api-clients/${editingClient.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(form.value)
            });
            store.showToast('API клиент обновлён', 'success');
        } else {
            const response = await store.api('/backoffice/api-clients', {
                method: 'POST',
                body: JSON.stringify(form.value)
            });
            store.showToast(`API клиент создан. Ключ: ${response.data.api_key}`, 'success');
        }
        closeModal();
        await loadClients();
    } catch (e) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        saving.value = false;
    }
};

const viewClient = async (client) => {
    selectedClient.value = client;
    clientDetails.value = null;
    showDetailsModal.value = true;
    loadingDetails.value = true;
    showSecret.value = false;
    webhookTestResult.value = null;

    try {
        const response = await store.api(`/backoffice/api-clients/${client.id}`);
        clientDetails.value = response.data;
    } catch (e) {
        store.showToast('Ошибка загрузки данных', 'error');
        showDetailsModal.value = false;
    } finally {
        loadingDetails.value = false;
    }
};

const toggleActive = async (client) => {
    try {
        await store.api(`/backoffice/api-clients/${client.id}/toggle-active`, {
            method: 'POST'
        });
        client.is_active = !client.is_active;
        store.showToast(client.is_active ? 'API клиент активирован' : 'API клиент деактивирован', 'success');
    } catch (e) {
        store.showToast('Ошибка изменения статуса', 'error');
    }
};

const confirmDelete = (client) => {
    deletingClient.value = client;
    showDeleteModal.value = true;
};

const deleteClient = async () => {
    deleting.value = true;
    try {
        await store.api(`/backoffice/api-clients/${deletingClient.value.id}`, {
            method: 'DELETE'
        });
        store.showToast('API клиент удалён', 'success');
        showDeleteModal.value = false;
        await loadClients();
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    } finally {
        deleting.value = false;
    }
};

const regenerateCredentials = async (type) => {
    if (!confirm('Перегенерировать учётные данные? Старые ключи перестанут работать.')) return;

    regenerating.value = true;
    try {
        const response = await store.api(`/backoffice/api-clients/${selectedClient.value.id}/regenerate`, {
            method: 'POST',
            body: JSON.stringify({ type })
        });
        clientDetails.value = { ...clientDetails.value, ...response.data };
        store.showToast('Учётные данные перегенерированы', 'success');
        await loadClients();
    } catch (e) {
        store.showToast('Ошибка перегенерации', 'error');
    } finally {
        regenerating.value = false;
    }
};

const testWebhook = async () => {
    testingWebhook.value = true;
    webhookTestResult.value = null;
    try {
        const response = await store.api(`/backoffice/api-clients/${selectedClient.value.id}/test-webhook`, {
            method: 'POST'
        });
        webhookTestResult.value = response.data;
    } catch (e) {
        webhookTestResult.value = { success: false, error: e.message };
    } finally {
        testingWebhook.value = false;
    }
};

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        store.showToast('Скопировано в буфер обмена', 'success');
    } catch (e) {
        store.showToast('Не удалось скопировать', 'error');
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Init
onMounted(async () => {
    await Promise.all([
        loadClients(),
        loadScopes(),
        loadWebhookEvents()
    ]);
});
</script>

<style scoped>
.border-l-3 {
    border-left-width: 3px;
}
</style>
