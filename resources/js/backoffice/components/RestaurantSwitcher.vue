<template>
    <div v-if="store.hasMultipleRestaurants" class="relative">
        <button
            @click="isOpen = !isOpen"
            class="flex items-center gap-2 px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
        >
            <span class="text-gray-600">{{ store.currentRestaurant?.name || 'Выберите точку' }}</span>
            <svg
                :class="['w-4 h-4 text-gray-500 transition-transform', isOpen && 'rotate-180']"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute top-full right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50"
        >
            <div class="px-3 py-2 border-b border-gray-100">
                <span class="text-xs text-gray-500 uppercase tracking-wide">Ваши точки</span>
            </div>

            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="restaurant in store.restaurants"
                    :key="restaurant.id"
                    @click="selectRestaurant(restaurant)"
                    :class="[
                        'w-full px-3 py-2 text-left hover:bg-gray-50 flex items-center gap-3 transition-colors',
                        restaurant.is_current && 'bg-orange-50'
                    ]"
                >
                    <div
                        :class="[
                            'w-8 h-8 rounded-lg flex items-center justify-center text-sm font-medium',
                            restaurant.is_current ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600'
                        ]"
                    >
                        {{ restaurant.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">{{ restaurant.name }}</div>
                        <div v-if="restaurant.address" class="text-xs text-gray-500 truncate">{{ restaurant.address }}</div>
                    </div>
                    <span v-if="restaurant.is_main" class="text-xs text-orange-600 bg-orange-100 px-2 py-0.5 rounded">
                        Главная
                    </span>
                    <svg
                        v-if="restaurant.is_current"
                        class="w-5 h-5 text-orange-500 flex-shrink-0"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <!-- Tenant info -->
            <div v-if="store.tenant" class="px-3 py-2 border-t border-gray-100 mt-1">
                <div class="text-xs text-gray-500">
                    {{ store.tenant.name }}
                    <span v-if="store.tenant.is_on_trial" class="text-orange-600">
                        (Пробный период: {{ store.tenant.days_until_expiration }} дн.)
                    </span>
                </div>
            </div>
        </div>

        <!-- Backdrop -->
        <div v-if="isOpen" @click="isOpen = false" class="fixed inset-0 z-40"></div>
    </div>

    <!-- Single restaurant - just show name -->
    <div v-else-if="store.currentRestaurant" class="text-sm text-gray-600">
        {{ store.currentRestaurant.name }}
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBackofficeStore } from '../stores/backoffice';

const store = useBackofficeStore();
const isOpen = ref(false);
const loading = ref(false);

const selectRestaurant = async (restaurant) => {
    if (restaurant.is_current || loading.value) return;

    loading.value = true;
    try {
        await store.switchRestaurant(restaurant.id);
        isOpen.value = false;
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    if (store.isAuthenticated) {
        await Promise.all([
            store.loadTenant(),
            store.loadRestaurants()
        ]);
    }
});
</script>
