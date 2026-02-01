<template>
    <div class="space-y-6">
        <!-- Current Subscription Status -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Текущий тариф</h3>
                    <p class="text-sm text-gray-500 mt-1">Управление подпиской и тарифным планом</p>
                </div>
                <div v-if="subscription" :class="[
                    'px-3 py-1 rounded-full text-sm font-medium',
                    subscription.has_active_subscription
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700'
                ]">
                    {{ subscription.has_active_subscription ? 'Активна' : 'Истекла' }}
                </div>
            </div>

            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-orange-500 border-t-transparent"></div>
            </div>

            <div v-else-if="subscription" class="space-y-6">
                <!-- Current Plan Card -->
                <div :class="[
                    'rounded-xl p-6 border-2',
                    subscription.is_on_trial
                        ? 'bg-purple-50 border-purple-200'
                        : 'bg-orange-50 border-orange-200'
                ]">
                    <div class="flex items-center gap-4">
                        <div :class="[
                            'w-14 h-14 rounded-xl flex items-center justify-center text-2xl',
                            subscription.is_on_trial ? 'bg-purple-100' : 'bg-orange-100'
                        ]">
                            {{ getPlanIcon(subscription.plan) }}
                        </div>
                        <div class="flex-1">
                            <h4 class="text-xl font-bold text-gray-900">
                                {{ subscription.plan_info?.name || 'Неизвестный тариф' }}
                            </h4>
                            <p class="text-sm text-gray-600">{{ subscription.plan_info?.description }}</p>
                        </div>
                        <div class="text-right">
                            <div v-if="!subscription.plan_info?.is_free" class="text-2xl font-bold text-gray-900">
                                {{ formatPrice(subscription.plan_info?.price_monthly) }}
                                <span class="text-sm font-normal text-gray-500">/мес</span>
                            </div>
                            <div v-else class="text-lg font-medium text-purple-600">Бесплатно</div>
                        </div>
                    </div>

                    <!-- Expiration Info -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">
                                {{ subscription.is_on_trial ? 'Пробный период до:' : 'Подписка активна до:' }}
                            </span>
                            <span class="font-medium">
                                {{ formatDate(subscription.is_on_trial ? subscription.trial_ends_at : subscription.subscription_ends_at) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm mt-2">
                            <span class="text-gray-600">Осталось дней:</span>
                            <span :class="[
                                'font-medium',
                                subscription.days_remaining <= 3 ? 'text-red-600' :
                                subscription.days_remaining <= 7 ? 'text-orange-600' : 'text-green-600'
                            ]">
                                {{ subscription.days_remaining ?? 0 }}
                            </span>
                        </div>
                    </div>

                    <!-- Current Usage -->
                    <div class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">Точки</div>
                            <div class="font-medium">
                                {{ subscription.current_usage?.restaurants || 0 }}
                                <span class="text-gray-400">
                                    / {{ subscription.plan_info?.limits?.max_restaurants || 'безлимит' }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Сотрудники</div>
                            <div class="font-medium">
                                {{ subscription.current_usage?.users || 0 }}
                                <span class="text-gray-400">
                                    / {{ subscription.plan_info?.limits?.max_users || 'безлимит' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trial Warning -->
                <div v-if="subscription.is_on_trial && subscription.days_remaining <= 7"
                     class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start gap-3">
                    <div class="text-yellow-500 text-xl">&#9888;</div>
                    <div>
                        <h4 class="font-medium text-yellow-800">Пробный период заканчивается</h4>
                        <p class="text-sm text-yellow-700 mt-1">
                            Выберите тарифный план, чтобы продолжить работу с системой.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Plans -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Доступные тарифы</h3>

            <!-- Period Toggle -->
            <div class="flex items-center justify-center gap-4 mb-6">
                <button
                    @click="selectedPeriod = 'monthly'"
                    :class="[
                        'px-4 py-2 rounded-lg font-medium text-sm transition',
                        selectedPeriod === 'monthly'
                            ? 'bg-orange-500 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    Ежемесячно
                </button>
                <button
                    @click="selectedPeriod = 'yearly'"
                    :class="[
                        'px-4 py-2 rounded-lg font-medium text-sm transition',
                        selectedPeriod === 'yearly'
                            ? 'bg-orange-500 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    Ежегодно
                    <span class="ml-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">-17%</span>
                </button>
            </div>

            <!-- Plans Grid -->
            <div v-if="plansLoading" class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-2 border-orange-500 border-t-transparent"></div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    :class="[
                        'rounded-xl border-2 p-6 transition-all relative',
                        plan.is_popular ? 'border-orange-500 shadow-lg' : 'border-gray-200 hover:border-gray-300',
                        subscription?.plan === plan.id ? 'ring-2 ring-orange-500 ring-offset-2' : ''
                    ]"
                >
                    <!-- Popular Badge -->
                    <div v-if="plan.is_popular"
                         class="absolute -top-3 left-1/2 -translate-x-1/2 bg-orange-500 text-white text-xs font-medium px-3 py-1 rounded-full">
                        Популярный
                    </div>

                    <div class="text-center mb-6">
                        <div class="text-3xl mb-2">{{ getPlanIcon(plan.id) }}</div>
                        <h4 class="text-xl font-bold text-gray-900">{{ plan.name }}</h4>
                        <p class="text-sm text-gray-500 mt-1">{{ plan.description }}</p>
                    </div>

                    <div class="text-center mb-6">
                        <div class="text-3xl font-bold text-gray-900">
                            {{ formatPrice(selectedPeriod === 'yearly' ? plan.price_yearly / 12 : plan.price_monthly) }}
                        </div>
                        <div class="text-sm text-gray-500">/мес</div>
                        <div v-if="selectedPeriod === 'yearly'" class="text-xs text-green-600 mt-1">
                            {{ formatPrice(plan.price_yearly) }} при оплате за год
                        </div>
                    </div>

                    <!-- Features -->
                    <ul class="space-y-3 mb-6">
                        <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-gray-600">{{ feature }}</span>
                        </li>
                    </ul>

                    <!-- Action Button -->
                    <button
                        v-if="subscription?.plan !== plan.id"
                        @click="selectPlan(plan)"
                        :disabled="changingPlan"
                        :class="[
                            'w-full py-3 rounded-xl font-medium transition',
                            plan.is_popular
                                ? 'bg-orange-500 text-white hover:bg-orange-600'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        ]"
                    >
                        {{ subscription?.is_on_trial ? 'Выбрать' : 'Перейти' }}
                    </button>
                    <div v-else class="text-center py-3 text-green-600 font-medium">
                        Текущий тариф
                    </div>
                </div>
            </div>
        </div>

        <!-- Extend Subscription (if not on trial) -->
        <div v-if="subscription && !subscription.is_on_trial && subscription.plan !== 'trial'"
             class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Продлить подписку</h3>
            <div class="flex flex-wrap gap-4">
                <button
                    @click="extendSubscription('monthly')"
                    :disabled="extending"
                    class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition disabled:opacity-50"
                >
                    +1 месяц ({{ formatPrice(subscription.plan_info?.price_monthly) }})
                </button>
                <button
                    @click="extendSubscription('yearly')"
                    :disabled="extending"
                    class="px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition disabled:opacity-50"
                >
                    +1 год ({{ formatPrice(subscription.plan_info?.price_yearly) }})
                </button>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showConfirmModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Подтвердите выбор тарифа</h3>
                <p class="text-gray-600 mb-6">
                    Вы выбрали тариф <strong>{{ selectedPlan?.name }}</strong>
                    на {{ selectedPeriod === 'yearly' ? 'год' : 'месяц' }}.
                </p>

                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Тариф:</span>
                        <span class="font-medium">{{ selectedPlan?.name }}</span>
                    </div>
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Период:</span>
                        <span class="font-medium">{{ selectedPeriod === 'yearly' ? '1 год' : '1 месяц' }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold pt-2 border-t">
                        <span>Итого:</span>
                        <span class="text-orange-500">
                            {{ formatPrice(selectedPeriod === 'yearly' ? selectedPlan?.price_yearly : selectedPlan?.price_monthly) }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="showConfirmModal = false"
                        class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition"
                    >
                        Отмена
                    </button>
                    <button
                        @click="confirmPlanChange"
                        :disabled="changingPlan"
                        class="flex-1 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <svg v-if="changingPlan" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Подтвердить
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

const loading = ref(true);
const plansLoading = ref(true);
const subscription = ref(null);
const plans = ref([]);
const selectedPeriod = ref('monthly');
const selectedPlan = ref(null);
const showConfirmModal = ref(false);
const changingPlan = ref(false);
const extending = ref(false);

const getPlanIcon = (planId) => {
    const icons = {
        trial: '🎁',
        start: '🚀',
        business: '💼',
        premium: '👑'
    };
    return icons[planId] || '📦';
};

const formatPrice = (price) => {
    if (!price && price !== 0) return '—';
    return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
};

const loadSubscription = async () => {
    loading.value = true;
    try {
        const data = await store.api('/tenant/subscription');
        if (data.success) {
            subscription.value = data.data;
        }
    } catch (e) {
        console.error('Failed to load subscription:', e);
    } finally {
        loading.value = false;
    }
};

const loadPlans = async () => {
    plansLoading.value = true;
    try {
        const data = await store.api('/tenant/plans');
        if (data.success) {
            plans.value = data.data;
        }
    } catch (e) {
        console.error('Failed to load plans:', e);
    } finally {
        plansLoading.value = false;
    }
};

const selectPlan = (plan) => {
    selectedPlan.value = plan;
    showConfirmModal.value = true;
};

const confirmPlanChange = async () => {
    if (!selectedPlan.value) return;

    changingPlan.value = true;
    try {
        const data = await store.api('/tenant/subscription/change', {
            method: 'POST',
            body: JSON.stringify({
                plan: selectedPlan.value.id,
                period: selectedPeriod.value
            })
        });

        if (data.success) {
            store.showToast(data.message || 'Тариф изменён');
            showConfirmModal.value = false;
            await loadSubscription();
        } else {
            store.showToast(data.message || 'Ошибка', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        changingPlan.value = false;
    }
};

const extendSubscription = async (period) => {
    extending.value = true;
    try {
        const data = await store.api('/tenant/subscription/extend', {
            method: 'POST',
            body: JSON.stringify({ period })
        });

        if (data.success) {
            store.showToast(data.message || 'Подписка продлена');
            await loadSubscription();
        } else {
            store.showToast(data.message || 'Ошибка', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        extending.value = false;
    }
};

onMounted(() => {
    loadSubscription();
    loadPlans();
});
</script>
