<template>
    <aside class="w-72 bg-neutral-900 border-l border-neutral-700 shrink-0 flex flex-col">
        <div class="p-4 border-b border-neutral-700">
            <h3 class="font-semibold text-white">Свойства</h3>
        </div>

        <div v-if="store.selectedObject" class="p-4 space-y-4 overflow-y-auto flex-1">
            <!-- Table Properties -->
            <template v-if="store.selectedObject.type === 'table'">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Номер стола</label>
                    <input v-model="store.selectedObject.number" type="text" maxlength="10" placeholder="1, VIP1, A1..."
                           class="w-full bg-neutral-800 border border-neutral-600 text-white rounded-lg px-3 py-2 focus:border-orange-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Количество мест</label>
                    <input v-model.number="store.selectedObject.seats" type="number"
                           class="w-full bg-neutral-800 border border-neutral-600 text-white rounded-lg px-3 py-2 focus:border-orange-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Форма</label>
                    <select v-model="store.selectedObject.shape"
                            class="w-full bg-neutral-800 border border-neutral-600 text-white rounded-lg px-3 py-2 focus:border-orange-500 focus:outline-none">
                        <option value="square">Квадратный</option>
                        <option value="round">Круглый</option>
                        <option value="rectangle">Прямоугольный</option>
                        <option value="oval">Овальный</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Минимальный заказ</label>
                    <input v-model.number="store.selectedObject.minOrder" type="number"
                           class="w-full bg-neutral-800 border border-neutral-600 text-white rounded-lg px-3 py-2 focus:border-orange-500 focus:outline-none">
                </div>

                <!-- Current status (read-only) -->
                <div class="pt-3 border-t border-neutral-700">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Текущий статус</label>
                    <div class="flex items-center gap-2 p-3 bg-neutral-800 rounded-lg">
                        <div class="w-3 h-3 rounded-full" :style="{ background: statusDotColor }"></div>
                        <span class="text-gray-300 text-sm">{{ statusLabel }}</span>
                    </div>
                </div>
            </template>

            <!-- Label Properties -->
            <template v-if="store.selectedObject.type === 'label'">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Текст</label>
                    <input v-model="store.selectedObject.text"
                           class="w-full bg-neutral-800 border border-neutral-600 text-white rounded-lg px-3 py-2 focus:border-orange-500 focus:outline-none"
                           placeholder="Введите текст">
                </div>
            </template>

            <!-- Position & Size (for all) -->
            <div class="pt-4 border-t border-neutral-700">
                <h4 class="text-sm font-medium text-gray-400 mb-3">Позиция и размер</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">X</label>
                        <input v-model.number="store.selectedObject.x" type="number"
                               class="w-full bg-neutral-800 border border-neutral-600 text-white rounded px-2 py-1.5 text-sm focus:border-orange-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Y</label>
                        <input v-model.number="store.selectedObject.y" type="number"
                               class="w-full bg-neutral-800 border border-neutral-600 text-white rounded px-2 py-1.5 text-sm focus:border-orange-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Ширина</label>
                        <input v-model.number="store.selectedObject.width" type="number"
                               class="w-full bg-neutral-800 border border-neutral-600 text-white rounded px-2 py-1.5 text-sm focus:border-orange-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Высота</label>
                        <input v-model.number="store.selectedObject.height" type="number"
                               class="w-full bg-neutral-800 border border-neutral-600 text-white rounded px-2 py-1.5 text-sm focus:border-orange-500 focus:outline-none">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-500 mb-1">Поворот (°)</label>
                    <input v-model.number="store.selectedObject.rotation" type="number"
                           class="w-full bg-neutral-800 border border-neutral-600 text-white rounded px-2 py-1.5 text-sm focus:border-orange-500 focus:outline-none">
                </div>
            </div>

            <button @click="store.deleteSelected()" class="w-full py-2.5 bg-red-900/30 text-red-400 rounded-lg text-sm font-medium hover:bg-red-900/50 transition-colors border border-red-900/50">
                Удалить объект
            </button>
        </div>

        <div v-else class="p-4 text-center text-gray-500 flex-1 flex items-center justify-center">
            <div>
                <div class="text-4xl mb-2 opacity-50">👆</div>
                <p class="text-sm">Выберите объект для редактирования</p>
            </div>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useFloorEditorStore } from '../stores/floorEditor';

const store = useFloorEditorStore();

const statusDotColor = computed(() => {
    const status = store.selectedObject?.status || 'free';
    const colors = {
        'free': '#22c55e',
        'occupied': '#f97316',
        'reserved': '#3b82f6',
        'billing': '#8b5cf6',
        'bill': '#8b5cf6',
        'alert': '#ef4444',
        'ready': '#10b981'
    };
    return (colors as Record<string, any>)[status] || '#22c55e';
});

const statusLabel = computed(() => {
    const status = store.selectedObject?.status || 'free';
    const labels = {
        'free': 'Свободен',
        'occupied': 'Занят',
        'reserved': 'Забронирован',
        'billing': 'Счёт запрошен',
        'bill': 'Счёт запрошен',
        'alert': 'Требует внимания',
        'ready': 'Готов к выдаче'
    };
    return (labels as Record<string, any>)[status] || 'Свободен';
});
</script>
