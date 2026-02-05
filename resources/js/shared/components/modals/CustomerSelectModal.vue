<template>
    <!-- Modal variant: Teleport to body with backdrop -->
    <Teleport v-if="variant === 'modal'" to="body">
        <Transition name="fade">
            <div
                v-if="modelValue"
                class="fixed inset-0 bg-black/70 flex items-center justify-center z-[100]"
                @click.self="close"
            >
                <Transition name="scale">
                    <div
                        v-if="modelValue"
                        class="bg-dark-900 rounded-2xl w-full max-w-md max-h-[85vh] flex flex-col shadow-2xl overflow-hidden"
                    >
                        <!-- Content -->
                        <CustomerContent
                            ref="contentRef"
                            :loading="loading"
                            :customers="filteredCustomers"
                            :search-query="searchQuery"
                            :show-close="true"
                            @update:search-query="searchQuery = $event"
                            @select="selectCustomer"
                            @close="close"
                        />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Panel variant: Inline panel filling parent container -->
    <Transition v-else-if="variant === 'panel'" :name="slideFrom === 'right' ? 'slide-right-panel' : 'slide-left'">
        <div
            v-if="modelValue"
            class="absolute inset-0 bg-dark-900 flex flex-col z-50"
        >
            <CustomerContent
                ref="contentRef"
                :loading="loading"
                :customers="filteredCustomers"
                :search-query="searchQuery"
                :show-close="true"
                @update:search-query="searchQuery = $event"
                @select="selectCustomer"
                @close="close"
            />
        </div>
    </Transition>

    <!-- Fullwidth variant: Full width inside parent modal -->
    <Transition v-else name="fade">
        <div
            v-if="modelValue"
            class="bg-dark-900 rounded-xl flex flex-col overflow-hidden h-full"
        >
            <CustomerContent
                ref="contentRef"
                :loading="loading"
                :customers="filteredCustomers"
                :search-query="searchQuery"
                :show-close="true"
                @update:search-query="searchQuery = $event"
                @select="selectCustomer"
                @close="close"
            />
        </div>
    </Transition>
</template>

<script setup>
/**
 * CustomerSelectModal - Reusable customer selection component
 *
 * Variants:
 * - modal (default): Centered modal with backdrop, teleported to body
 * - panel: Inline panel filling parent container (absolute positioned)
 * - fullwidth: Full width inside parent modal
 *
 * Used in: Delivery, Hall (GuestPanel), Reservations
 *
 * @module shared/components/modals/CustomerSelectModal
 */

import { ref, computed, watch, nextTick, h, defineComponent } from 'vue';
import { useCustomers } from '../../../pos/composables/useCustomers';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    /** Display variant: 'modal' | 'panel' | 'fullwidth' */
    variant: {
        type: String,
        default: 'modal',
        validator: (v) => ['modal', 'panel', 'fullwidth'].includes(v),
    },
    /** Slide direction for panel variant: 'left' | 'right' */
    slideFrom: {
        type: String,
        default: 'left',
        validator: (v) => ['left', 'right'].includes(v),
    },
    /** Pre-selected customer (for highlighting) */
    selected: {
        type: Object,
        default: null,
    },
    /** Auto-focus search input on open */
    autofocus: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

// Composable
const { customers, loading, loadCustomers, filterCustomers } = useCustomers();

// Local state
const searchQuery = ref('');
const contentRef = ref(null);

// Computed
const filteredCustomers = computed(() => {
    if (!searchQuery.value) {
        return customers.value;
    }
    return filterCustomers(searchQuery.value);
});

// Methods
const close = () => {
    emit('update:modelValue', false);
    searchQuery.value = '';
};

const selectCustomer = (customer) => {
    emit('select', customer);
    close();
};

// Watch for modal open
watch(() => props.modelValue, async (isOpen) => {
    if (isOpen) {
        // Load customers if not loaded
        await loadCustomers();

        // Focus search input
        if (props.autofocus) {
            await nextTick();
            contentRef.value?.focusSearch?.();
        }
    } else {
        // Reset search on close
        searchQuery.value = '';
    }
});

// Internal content component (to avoid template duplication)
const CustomerContent = defineComponent({
    name: 'CustomerContent',
    props: {
        loading: Boolean,
        customers: Array,
        searchQuery: String,
        showClose: Boolean,
    },
    emits: ['update:search-query', 'select', 'close'],
    setup(props, { emit, expose }) {
        const searchInput = ref(null);

        const focusSearch = () => {
            searchInput.value?.focus();
        };

        expose({ focusSearch });

        const getInitials = (customer) => {
            if (!customer.name) return '?';
            const parts = customer.name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return customer.name.substring(0, 2).toUpperCase();
        };

        const formatPhone = (phone) => {
            if (!phone) return '';
            const digits = phone.replace(/\D/g, '');
            if (digits.length < 11) return phone;
            return `+${digits[0]} (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
        };

        const formatNumber = (n) => {
            return Math.floor(n || 0).toLocaleString('ru-RU');
        };

        const getCustomersWord = (count) => {
            const lastTwo = count % 100;
            const lastOne = count % 10;
            if (lastTwo >= 11 && lastTwo <= 19) return 'клиентов';
            if (lastOne === 1) return 'клиент';
            if (lastOne >= 2 && lastOne <= 4) return 'клиента';
            return 'клиентов';
        };

        return () => h('div', { class: 'flex flex-col h-full' }, [
            // Header
            h('div', { class: 'flex items-center justify-between px-4 py-3 bg-dark-800 border-b border-gray-700/50 flex-shrink-0' }, [
                h('h3', { class: 'font-semibold text-white text-lg' }, 'Выбрать клиента'),
                props.showClose && h('button', {
                    onClick: () => emit('close'),
                    class: 'w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors'
                }, [
                    h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M6 18L18 6M6 6l12 12' })
                    ])
                ])
            ]),

            // Search
            h('div', { class: 'px-4 py-3 border-b border-gray-700/50 flex-shrink-0' }, [
                h('div', { class: 'relative' }, [
                    h('svg', { class: 'absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' })
                    ]),
                    h('input', {
                        ref: searchInput,
                        value: props.searchQuery,
                        type: 'text',
                        placeholder: 'Поиск по имени или телефону...',
                        class: 'w-full bg-dark-800 border border-gray-700 rounded-xl pl-10 pr-10 py-2.5 text-white text-sm placeholder-gray-500 focus:ring-2 focus:ring-accent focus:border-transparent focus:outline-none transition-all',
                        onInput: (e) => emit('update:search-query', e.target.value)
                    }),
                    props.searchQuery && h('button', {
                        onClick: () => { emit('update:search-query', ''); searchInput.value?.focus(); },
                        class: 'absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white'
                    }, [
                        h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                            h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M6 18L18 6M6 6l12 12' })
                        ])
                    ])
                ])
            ]),

            // Customer List
            h('div', { class: 'flex-1 overflow-y-auto' }, [
                // Loading
                props.loading && h('div', { class: 'flex flex-col items-center justify-center py-16' }, [
                    h('div', { class: 'w-10 h-10 border-4 border-accent border-t-transparent rounded-full animate-spin mb-4' }),
                    h('p', { class: 'text-gray-500 text-sm' }, 'Загрузка клиентов...')
                ]),

                // Empty state
                !props.loading && props.customers.length === 0 && h('div', { class: 'flex flex-col items-center justify-center py-16 px-4' }, [
                    h('svg', { class: 'w-16 h-16 text-gray-700 mb-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
                        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '1.5', d: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' })
                    ]),
                    props.searchQuery
                        ? h('p', { class: 'text-gray-500 text-center', innerHTML: `Клиенты по запросу<br>"${props.searchQuery}" не найдены` })
                        : h('p', { class: 'text-gray-500' }, 'Нет клиентов')
                ]),

                // Customer list
                !props.loading && props.customers.length > 0 && h('div', { class: 'divide-y divide-gray-800/50' },
                    props.customers.map(customer =>
                        h('button', {
                            key: customer.id,
                            onClick: () => emit('select', customer),
                            class: 'w-full flex items-center gap-3 px-4 py-3 hover:bg-dark-800 active:bg-dark-700 transition-colors text-left'
                        }, [
                            // Avatar
                            h('div', { class: 'w-12 h-12 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0' }, [
                                h('span', { class: 'text-white text-lg font-semibold' }, getInitials(customer))
                            ]),
                            // Info
                            h('div', { class: 'flex-1 min-w-0' }, [
                                h('div', { class: 'flex items-center gap-2' }, [
                                    h('p', { class: 'text-white font-medium truncate' }, customer.name || 'Без имени'),
                                    customer.loyalty_level && h('span', { class: 'px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded text-xs font-medium flex-shrink-0' },
                                        `${customer.loyalty_level.icon} ${customer.loyalty_level.name}`
                                    ),
                                    customer.is_blacklisted && h('span', { class: 'px-1.5 py-0.5 bg-red-500/20 text-red-400 rounded text-xs font-medium flex-shrink-0' }, 'ЧС')
                                ]),
                                h('p', { class: 'text-gray-400 text-sm' }, formatPhone(customer.phone) || 'Нет телефона')
                            ]),
                            // Stats
                            h('div', { class: 'text-right flex-shrink-0' }, [
                                (customer.total_orders || customer.orders_count) && h('p', { class: 'text-sm text-gray-500' },
                                    `${customer.total_orders || customer.orders_count} заказов`
                                ),
                                customer.bonus_balance && h('p', { class: 'text-sm text-amber-400 font-medium' },
                                    `${formatNumber(customer.bonus_balance)} ★`
                                )
                            ])
                        ])
                    )
                )
            ]),

            // Footer with count
            !props.loading && props.customers.length > 0 && h('div', { class: 'px-4 py-2 bg-dark-800 border-t border-gray-700/50 flex-shrink-0' }, [
                h('p', { class: 'text-xs text-gray-500 text-center' },
                    `${props.customers.length} ${getCustomersWord(props.customers.length)}`
                )
            ])
        ]);
    }
});
</script>

<style scoped>
/* Fade transition for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Scale transition for modal */
.scale-enter-active,
.scale-leave-active {
    transition: all 0.2s ease;
}
.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

/* Slide left transition for panel */
.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.25s ease;
}
.slide-left-enter-from {
    opacity: 0;
    transform: translateX(-100%);
}
.slide-left-leave-to {
    opacity: 0;
    transform: translateX(-100%);
}

/* Slide right transition for panel */
.slide-right-panel-enter-active,
.slide-right-panel-leave-active {
    transition: all 0.25s ease;
}
.slide-right-panel-enter-from {
    opacity: 0;
    transform: translateX(100%);
}
.slide-right-panel-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
