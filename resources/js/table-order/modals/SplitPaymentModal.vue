<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="$emit('update:modelValue', false)">
            <div class="w-full max-w-[300px] bg-[#1E293B] rounded-[20px] overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-[22px] border-b border-[rgba(255,255,255,0.06)] flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-[38px] h-[38px] rounded-[10px] bg-[#3B82F6] flex items-center justify-center">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <span class="text-[17px] font-semibold text-white">Раздельный счёт</span>
                    </div>
                    <button @click="$emit('update:modelValue', false)"
                            class="w-8 h-8 rounded-lg bg-[rgba(255,255,255,0.06)] text-[rgba(255,255,255,0.5)] hover:text-white flex items-center justify-center transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>

                <!-- Guest List -->
                <div class="px-3 py-2">
                    <div v-for="guest in guests" :key="(guest as any).number"
                         @click="toggleGuest((guest as any).number)"
                         class="flex items-center p-[14px] my-1.5 rounded-[14px] cursor-pointer transition-all duration-200"
                         :class="selectedGuests.includes((guest as any).number)
                             ? 'bg-[rgba(59,130,246,0.15)] border border-[rgba(59,130,246,0.3)]'
                             : 'bg-[rgba(255,255,255,0.02)] border border-transparent hover:bg-[rgba(255,255,255,0.04)]'">

                        <!-- Checkbox -->
                        <div class="w-[22px] h-[22px] rounded-[6px] flex items-center justify-center transition-all"
                             :class="selectedGuests.includes((guest as any).number)
                                 ? 'bg-[#3B82F6]'
                                 : 'border-2 border-[rgba(255,255,255,0.2)]'">
                            <svg v-if="selectedGuests.includes((guest as any).number)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>

                        <!-- Guest Avatar -->
                        <div class="w-[38px] h-[38px] rounded-[10px] ml-3 flex items-center justify-center text-[14px] font-semibold"
                             :class="selectedGuests.includes((guest as any).number)
                                 ? 'bg-gradient-to-br from-[#3B82F6] to-[#1D4ED8] text-white'
                                 : 'bg-gradient-to-br from-[rgba(255,255,255,0.1)] to-[rgba(255,255,255,0.05)] text-[rgba(255,255,255,0.5)]'">
                            {{ (guest as any).number }}
                        </div>

                        <!-- Guest Name -->
                        <div class="ml-3 flex-1">
                            <div class="text-[15px] font-medium text-white">Гость {{ (guest as any).number }}</div>
                        </div>

                        <!-- Amount -->
                        <div class="text-[15px] font-semibold"
                             :class="selectedGuests.includes((guest as any).number) ? 'text-[#60A5FA]' : 'text-[rgba(255,255,255,0.5)]'">
                            {{ formatPrice((guest as any).total) }}
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="px-6 py-5 flex justify-between items-center">
                    <span class="text-[14px] text-[rgba(255,255,255,0.5)]">К оплате</span>
                    <span class="text-[28px] font-bold text-white">
                        {{ formatPriceNumber(selectedTotal) }} <span class="text-[16px] opacity-70">₽</span>
                    </span>
                </div>

                <!-- Buttons -->
                <div class="px-3 pb-4 flex gap-2.5">
                    <button @click="pay('check')"
                            class="flex-1 py-4 rounded-xl border border-[rgba(255,255,255,0.1)] bg-[rgba(255,255,255,0.05)] text-white text-[14px] font-medium flex items-center justify-center gap-2 hover:bg-[rgba(255,255,255,0.08)] transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        Счёт
                    </button>
                    <button @click="pay('cash')"
                            class="flex-1 py-4 rounded-xl border border-[rgba(255,255,255,0.1)] bg-[rgba(255,255,255,0.05)] text-white text-[14px] font-medium flex items-center justify-center gap-2 hover:bg-[rgba(255,255,255,0.08)] transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="6" width="20" height="12" rx="2"/>
                            <circle cx="12" cy="12" r="2"/>
                            <path d="M6 12h.01M18 12h.01"/>
                        </svg>
                        Нал
                    </button>
                    <button @click="pay('card')"
                            class="flex-1 py-4 rounded-xl border-none bg-[#3B82F6] text-white text-[14px] font-medium flex items-center justify-center gap-2 hover:bg-[#2563EB] transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Карта
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    guests: Array,
    guestColors: Array,
    tipsPercent: Number
});

const emit = defineEmits(['update:modelValue', 'update:tipsPercent', 'pay']);

const selectedGuests = ref<any[]>([]);

watch(() => props.modelValue, (val) => {
    if (val) {
        selectedGuests.value = (props.guests as any)?.length > 0 ? [(props.guests as any)[0].number] : [];
    }
});

const toggleGuest = (guestNumber: any) => {
    const idx = selectedGuests.value.indexOf(guestNumber);
    if (idx >= 0) {
        selectedGuests.value.splice(idx, 1);
    } else {
        selectedGuests.value.push(guestNumber);
    }
};

const selectedTotal = computed(() => {
    return props.guests!
        .filter((g: any) => selectedGuests.value.includes(g.number))
        .reduce((sum: any, g: any) => sum + g.total, 0);
});

const pay = (method: any) => {
    emit('pay', { guestIds: selectedGuests.value, method });
};

const formatPrice = (price: any) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0) + ' ₽';
};

const formatPriceNumber = (price: any) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0);
};
</script>
