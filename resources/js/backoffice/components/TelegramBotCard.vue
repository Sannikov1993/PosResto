<template>
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Telegram бот для гостей</h2>
                    <p class="text-sm text-gray-500">Уведомления о бронированиях от имени вашего ресторана</p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Loading -->
            <div v-if="loading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent"></div>
                <p class="mt-2 text-gray-500">Загрузка...</p>
            </div>

            <!-- Not Configured -->
            <div v-else-if="!status.configured" class="space-y-6">
                <!-- Info Banner -->
                <div class="bg-blue-50 rounded-lg p-4">
                    <h3 class="font-medium text-blue-900 mb-2">Зачем нужен свой бот?</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>Гости видят название вашего ресторана, а не "MenuLab"</li>
                        <li>Брендированные уведомления о бронированиях</li>
                        <li>Повышение доверия и узнаваемости</li>
                    </ul>
                </div>

                <!-- Setup Form -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Токен бота от @BotFather
                    </label>
                    <input
                        v-model="form.token"
                        type="text"
                        class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                        placeholder="123456789:ABC-DEFGhijklMNOpqrstuvwxyz"
                        :disabled="saving"
                    >
                    <p class="text-xs text-gray-500 mt-2">
                        Создайте бота через
                        <a href="https://t.me/BotFather" target="_blank" class="text-blue-600 hover:underline">@BotFather</a>
                        в Telegram и вставьте токен
                    </p>
                </div>

                <!-- Instructions -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">Как создать бота:</h4>
                    <ol class="text-sm text-gray-600 space-y-2">
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white rounded-full text-xs flex items-center justify-center">1</span>
                            <span>Откройте <a href="https://t.me/BotFather" target="_blank" class="text-blue-600 hover:underline">@BotFather</a> в Telegram</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white rounded-full text-xs flex items-center justify-center">2</span>
                            <span>Отправьте команду <code class="bg-gray-200 px-1 rounded">/newbot</code></span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white rounded-full text-xs flex items-center justify-center">3</span>
                            <span>Введите название (например: "Ресторан Пушкин")</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white rounded-full text-xs flex items-center justify-center">4</span>
                            <span>Введите username (например: <code class="bg-gray-200 px-1 rounded">PushkinRestaurantBot</code>)</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white rounded-full text-xs flex items-center justify-center">5</span>
                            <span>Скопируйте токен и вставьте выше</span>
                        </li>
                    </ol>
                </div>

                <!-- Error -->
                <div v-if="error" class="bg-red-50 text-red-700 rounded-lg p-4 text-sm">
                    {{ error }}
                </div>

                <!-- Connect Button -->
                <button
                    @click="connectBot"
                    :disabled="saving || !form.token.trim()"
                    class="w-full px-4 py-3 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition flex items-center justify-center gap-2"
                >
                    <span v-if="saving" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                    {{ saving ? 'Подключение...' : 'Подключить бота' }}
                </button>
            </div>

            <!-- Configured but not active -->
            <div v-else-if="!status.active" class="space-y-6">
                <!-- Bot Info -->
                <div class="flex items-center gap-4 p-4 bg-yellow-50 rounded-lg">
                    <div class="w-12 h-12 bg-yellow-200 rounded-full flex items-center justify-center text-2xl">
                        <svg class="w-6 h-6 text-yellow-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900">@{{ status.bot?.username }}</span>
                            <span class="px-2 py-0.5 bg-yellow-200 text-yellow-800 text-xs rounded-full">Ожидает активации</span>
                        </div>
                        <p class="text-sm text-yellow-700 mt-1">Нажмите "Активировать" чтобы начать принимать сообщения</p>
                    </div>
                </div>

                <!-- Error -->
                <div v-if="error" class="bg-red-50 text-red-700 rounded-lg p-4 text-sm">
                    {{ error }}
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button
                        @click="activateBot"
                        :disabled="saving"
                        class="flex-1 px-4 py-3 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                    >
                        {{ saving ? 'Активация...' : 'Активировать бота' }}
                    </button>
                    <button
                        @click="confirmRemove"
                        :disabled="saving"
                        class="px-4 py-3 border border-red-300 text-red-600 hover:bg-red-50 rounded-lg transition"
                    >
                        Удалить
                    </button>
                </div>
            </div>

            <!-- Active -->
            <div v-else class="space-y-6">
                <!-- Bot Info -->
                <div class="flex items-center gap-4 p-4 bg-green-50 rounded-lg">
                    <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900">@{{ status.bot?.username }}</span>
                            <span class="px-2 py-0.5 bg-green-200 text-green-800 text-xs rounded-full">Активен</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">
                            Бот принимает сообщения и отправляет уведомления
                        </p>
                        <p v-if="status.bot?.verified_at" class="text-xs text-gray-500 mt-1">
                            Подключён: {{ formatDate(status.bot.verified_at) }}
                        </p>
                    </div>
                    <a :href="`https://t.me/${status.bot?.username}`"
                       target="_blank"
                       class="px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition">
                        Открыть бота
                    </a>
                </div>

                <!-- Webhook Status -->
                <div v-if="webhookInfo" class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2">Webhook</h4>
                    <div class="text-sm space-y-1">
                        <div class="flex items-center gap-2">
                            <span :class="webhookInfo.url ? 'text-green-500' : 'text-yellow-600'">
                                {{ webhookInfo.url ? 'Настроен' : 'Локальный режим (без webhook)' }}
                            </span>
                        </div>
                        <div v-if="!webhookInfo.url" class="text-yellow-600 text-xs">
                            Бот не будет получать входящие сообщения. На продакшене с HTTPS webhook установится автоматически.
                        </div>
                        <div v-if="webhookInfo.pending_update_count" class="text-gray-600">
                            Ожидает обработки: {{ webhookInfo.pending_update_count }}
                        </div>
                        <div v-if="webhookInfo.last_error_message" class="text-red-600">
                            Последняя ошибка: {{ webhookInfo.last_error_message }}
                        </div>
                    </div>
                </div>

                <!-- Local mode warning -->
                <div v-else-if="status.active" class="bg-yellow-50 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <div>
                            <h4 class="font-medium text-yellow-800">Локальный режим</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                Бот может отправлять уведомления, но не получает входящие сообщения (webhook требует HTTPS).
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Test Section -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">Тестирование</h4>
                    <div class="flex gap-2">
                        <input
                            v-model="testChatId"
                            type="text"
                            class="flex-1 px-3 py-2 border rounded-lg text-sm"
                            placeholder="Chat ID (ваш Telegram ID)"
                        >
                        <button
                            @click="sendTest"
                            :disabled="testing || !testChatId"
                            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 text-white rounded-lg text-sm transition"
                        >
                            {{ testing ? 'Отправка...' : 'Тест' }}
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        Узнать свой ID можно через <a href="https://t.me/userinfobot" target="_blank" class="text-blue-600 hover:underline">@userinfobot</a>
                    </p>
                    <div v-if="testResult" :class="['mt-2 p-2 rounded text-sm', testResult.success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                        {{ testResult.success ? 'Сообщение отправлено!' : testResult.message || 'Ошибка отправки' }}
                    </div>
                </div>

                <!-- Error -->
                <div v-if="error" class="bg-red-50 text-red-700 rounded-lg p-4 text-sm">
                    {{ error }}
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button
                        @click="deactivateBot"
                        :disabled="saving"
                        class="px-4 py-2 border border-yellow-400 text-yellow-700 hover:bg-yellow-50 rounded-lg transition"
                    >
                        Деактивировать
                    </button>
                    <button
                        @click="confirmRemove"
                        :disabled="saving"
                        class="px-4 py-2 border border-red-300 text-red-600 hover:bg-red-50 rounded-lg transition"
                    >
                        Удалить бота
                    </button>
                </div>
            </div>
        </div>

        <!-- Remove Confirmation Modal -->
        <div v-if="showRemoveModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div class="p-6 text-center">
                    <div class="text-5xl mb-4">
                        <svg class="w-16 h-16 mx-auto text-red-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Удалить бота?</h3>
                    <p class="text-gray-500 mb-6">
                        Бот @{{ status.bot?.username }} будет отключён. Гости перестанут получать уведомления через Telegram.
                    </p>
                    <div class="flex gap-3">
                        <button
                            @click="showRemoveModal = false"
                            class="flex-1 px-4 py-2 border rounded-lg hover:bg-gray-50 transition"
                        >
                            Отмена
                        </button>
                        <button
                            @click="removeBot"
                            :disabled="saving"
                            class="flex-1 px-4 py-2 bg-red-500 hover:bg-red-600 disabled:bg-red-300 text-white rounded-lg font-medium transition"
                        >
                            {{ saving ? 'Удаление...' : 'Удалить' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBackofficeStore } from '../stores/backoffice';

const store = useBackofficeStore();

// State
const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const status = ref({
    configured: false,
    active: false,
    bot: null
});
const webhookInfo = ref(null);

// Form
const form = ref({
    token: ''
});

// Test
const testChatId = ref('');
const testing = ref(false);
const testResult = ref(null);

// Modal
const showRemoveModal = ref(false);

// Methods
const loadStatus = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await store.api('/restaurant/telegram-bot');
        status.value = response.data || {};
        webhookInfo.value = response.data?.webhook || null;
    } catch (e) {
        console.error('Failed to load bot status:', e);
        error.value = 'Не удалось загрузить статус бота';
    } finally {
        loading.value = false;
    }
};

const connectBot = async () => {
    if (!form.value.token.trim()) return;

    saving.value = true;
    error.value = null;

    try {
        await store.api('/restaurant/telegram-bot', {
            method: 'POST',
            body: JSON.stringify({ bot_token: form.value.token })
        });

        store.showToast('Бот подключён! Теперь активируйте его.', 'success');
        form.value.token = '';
        await loadStatus();
    } catch (e) {
        error.value = e.data?.message || e.message || 'Не удалось подключить бота';
    } finally {
        saving.value = false;
    }
};

const activateBot = async () => {
    saving.value = true;
    error.value = null;

    try {
        const response = await store.api('/restaurant/telegram-bot/webhook', {
            method: 'POST'
        });

        // Check for local mode warning
        if (response.data?.warning === 'local_mode') {
            store.showToast(response.data.message, 'warning');
        } else {
            store.showToast('Бот активирован!', 'success');
        }

        await loadStatus();
    } catch (e) {
        error.value = e.data?.message || e.message || 'Не удалось активировать бота';
    } finally {
        saving.value = false;
    }
};

const deactivateBot = async () => {
    saving.value = true;
    error.value = null;

    try {
        await store.api('/restaurant/telegram-bot/webhook', {
            method: 'DELETE'
        });

        store.showToast('Бот деактивирован', 'success');
        await loadStatus();
    } catch (e) {
        error.value = e.data?.message || e.message || 'Не удалось деактивировать бота';
    } finally {
        saving.value = false;
    }
};

const confirmRemove = () => {
    showRemoveModal.value = true;
};

const removeBot = async () => {
    saving.value = true;
    error.value = null;

    try {
        await store.api('/restaurant/telegram-bot', {
            method: 'DELETE'
        });

        store.showToast('Бот удалён', 'success');
        showRemoveModal.value = false;
        await loadStatus();
    } catch (e) {
        error.value = e.data?.message || e.message || 'Не удалось удалить бота';
    } finally {
        saving.value = false;
    }
};

const sendTest = async () => {
    if (!testChatId.value) return;

    testing.value = true;
    testResult.value = null;

    try {
        const response = await store.api('/restaurant/telegram-bot/test', {
            method: 'POST',
            body: JSON.stringify({ chat_id: testChatId.value })
        });

        testResult.value = { success: true };
    } catch (e) {
        testResult.value = {
            success: false,
            message: e.data?.message || e.message || 'Ошибка отправки'
        };
    } finally {
        testing.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Init
onMounted(() => {
    loadStatus();
});
</script>
