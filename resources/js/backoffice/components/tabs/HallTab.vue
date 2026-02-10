<template>
    <div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Zones Panel -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Зоны</h3>
                    <button @click="openZoneModal()" class="text-orange-500 text-sm font-medium hover:text-orange-600">+ Добавить</button>
                </div>

                <div v-if="store.zones.length === 0" class="text-center py-8 text-gray-400">
                    <div class="text-4xl mb-2">🏠</div>
                    <p>Нет зон</p>
                    <button @click="openZoneModal()" class="text-orange-500 text-sm mt-2 hover:underline">Создать первую зону</button>
                </div>

                <div class="space-y-2">
                    <div v-for="zone in store.zones" :key="zone.id"
                         :class="['p-3 rounded-lg cursor-pointer transition group',
                                  selectedZone === zone.id ? 'bg-orange-50 border-orange-200 border' : 'bg-gray-50 hover:bg-gray-100']">
                        <div class="flex items-center justify-between" @click="selectedZone = zone.id">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{backgroundColor: zone.color || '#3b82f6'}"></div>
                                <span class="font-medium">{{ zone.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500">{{ getZoneTablesCount(zone.id) }} столов</span>
                                <button @click.stop="openZoneModal(zone)" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-gray-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                                <button v-can="'settings.edit'" @click.stop="deleteZone(zone)" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Panel -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Столы {{ selectedZoneName ? '- ' + selectedZoneName : '' }}</h3>
                </div>

                <div v-if="zoneTables.length === 0" class="text-center py-12 text-gray-400">
                    <div class="text-5xl mb-3">🪑</div>
                    <p v-if="store.zones.length">В этой зоне нет столов</p>
                    <p v-else>Сначала создайте зону</p>
                    <button @click="openFloorEditor" class="text-orange-500 text-sm mt-2 hover:underline">Добавить столы в редакторе</button>
                </div>

                <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div v-for="table in zoneTables" :key="table.id"
                         @click="openTableModal(table)"
                         :class="['p-4 rounded-xl text-center cursor-pointer transition group relative', tableStatusClass(table.status)]">
                        <!-- Shape indicator -->
                        <div class="absolute top-1 right-1 text-xs opacity-50">
                            {{ table.shape === 'round' ? '⭕' : table.shape === 'rectangle' ? '▬' : '⬜' }}
                        </div>
                        <div class="text-2xl font-bold mb-1">{{ table.number }}</div>
                        <div class="text-xs">{{ table.seats }} мест</div>
                        <button v-can="'settings.edit'" @click.stop="deleteTable(table)"
                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                            ×
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floor Plan Mini Preview -->
        <div class="bg-white rounded-xl shadow-sm p-4 mt-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🗺️</span>
                    <div>
                        <p class="font-semibold">Карта зала {{ selectedZoneName ? '- ' + selectedZoneName : '' }}</p>
                        <p class="text-sm text-gray-500">Нажмите для редактирования</p>
                    </div>
                </div>
                <button @click="openFloorEditor" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition flex items-center gap-2 whitespace-nowrap">
                    <span>✏️</span>
                    <span>Редактировать</span>
                </button>
            </div>

            <!-- Mini Map Preview -->
            <div v-if="floorPlanLoading" class="h-[200px] flex items-center justify-center bg-gray-50 rounded-lg">
                <div class="animate-spin w-8 h-8 border-4 border-orange-500 border-t-transparent rounded-full"></div>
            </div>
            <div v-else-if="!floorPlan || floorPlanTables.length === 0" class="h-[200px] flex items-center justify-center bg-gray-50 rounded-lg text-gray-400">
                <div class="text-center">
                    <div class="text-4xl mb-2">🏗️</div>
                    <p>Карта пуста</p>
                    <button @click="openFloorEditor" class="text-orange-500 text-sm mt-1 hover:underline">Создать планировку</button>
                </div>
            </div>
            <div v-else @click="openFloorEditor" class="h-[200px] bg-gray-50 rounded-lg overflow-hidden cursor-pointer hover:ring-2 hover:ring-orange-300 transition relative group">
                <!-- SVG Floor Plan -->
                <svg :viewBox="`0 0 ${floorPlanWidth} ${floorPlanHeight}`" class="w-full h-full" preserveAspectRatio="xMidYMid meet">
                    <!-- Grid pattern -->
                    <defs>
                        <pattern id="miniGrid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#e5e7eb" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#miniGrid)"/>

                    <!-- Decor objects -->
                    <template v-for="obj in floorPlanObjects" :key="obj.id || obj.type + obj.x">
                        <rect v-if="obj.type === 'wall'"
                              :x="obj.x" :y="obj.y" :width="obj.width" :height="obj.height"
                              :transform="obj.rotation ? `rotate(${obj.rotation} ${obj.x + obj.width/2} ${obj.y + obj.height/2})` : ''"
                              fill="#4b5563" rx="2"/>
                        <rect v-else-if="obj.type === 'bar'"
                              :x="obj.x" :y="obj.y" :width="obj.width" :height="obj.height"
                              :transform="obj.rotation ? `rotate(${obj.rotation} ${obj.x + obj.width/2} ${obj.y + obj.height/2})` : ''"
                              fill="#92400e" rx="4"/>
                        <circle v-else-if="obj.type === 'column'"
                                :cx="obj.x + obj.width/2" :cy="obj.y + obj.height/2" :r="obj.width/2"
                                fill="#6b7280"/>
                        <rect v-else-if="obj.type === 'sofa'"
                              :x="obj.x" :y="obj.y" :width="obj.width" :height="obj.height"
                              :transform="obj.rotation ? `rotate(${obj.rotation} ${obj.x + obj.width/2} ${obj.y + obj.height/2})` : ''"
                              fill="#7c3aed" rx="6"/>
                    </template>

                    <!-- Tables -->
                    <g v-for="table in floorPlanTables" :key="table.id">
                        <template v-if="table.shape === 'round'">
                            <circle
                                :cx="table.position_x + table.width/2"
                                :cy="table.position_y + table.height/2"
                                :r="Math.min(table.width, table.height)/2"
                                :fill="getTableColor(table.status)"
                                stroke="#fff"
                                stroke-width="2"/>
                        </template>
                        <template v-else>
                            <rect
                                :x="table.position_x"
                                :y="table.position_y"
                                :width="table.width"
                                :height="table.height"
                                :rx="table.shape === 'oval' ? table.height/2 : 4"
                                :transform="table.rotation ? `rotate(${table.rotation} ${table.position_x + table.width/2} ${table.position_y + table.height/2})` : ''"
                                :fill="getTableColor(table.status)"
                                stroke="#fff"
                                stroke-width="2"/>
                        </template>
                        <text
                            :x="table.position_x + table.width/2"
                            :y="table.position_y + table.height/2"
                            text-anchor="middle"
                            dominant-baseline="central"
                            fill="#fff"
                            font-weight="bold"
                            :font-size="Math.min(table.width, table.height) * 0.4">
                            {{ table.number }}
                        </text>
                    </g>
                </svg>

                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-orange-500/0 group-hover:bg-orange-500/10 transition flex items-center justify-center">
                    <span class="text-orange-600 font-medium opacity-0 group-hover:opacity-100 transition bg-white/90 px-3 py-1 rounded-full text-sm">
                        Открыть редактор
                    </span>
                </div>

                <!-- Legend -->
                <div class="absolute bottom-2 left-2 flex gap-2 text-xs bg-white/90 rounded px-2 py-1">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-500"></span> Свободен</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500"></span> Занят</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-yellow-500"></span> Бронь</span>
                </div>
            </div>
        </div>

        <!-- Zone Modal -->
        <Teleport to="body">
            <div v-if="showZoneModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showZoneModal = false">
                <div class="bg-white rounded-2xl w-[400px] p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">{{ zoneForm.id ? 'Редактировать зону' : 'Новая зона' }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                            <input v-model="zoneForm.name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Например: Основной зал">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                            <input v-model="zoneForm.description" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Описание зоны">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Цвет</label>
                            <div class="flex gap-2 flex-wrap">
                                <button v-for="color in zoneColors" :key="color"
                                        @click="zoneForm.color = color"
                                        :style="{backgroundColor: color}"
                                        :class="['w-8 h-8 rounded-full transition', zoneForm.color === color ? 'ring-2 ring-offset-2 ring-gray-400' : 'hover:scale-110']">
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showZoneModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveZone" :disabled="!zoneForm.name" class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Table Modal -->
        <Teleport to="body">
            <div v-if="showTableModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showTableModal = false">
                <div class="bg-white rounded-2xl w-[450px] p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">{{ tableForm.id ? 'Редактировать стол' : 'Новый стол' }}</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Номер стола *</label>
                                <input v-model.number="tableForm.number" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Мест *</label>
                                <input v-model.number="tableForm.seats" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="4">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Зона *</label>
                            <select v-model="tableForm.zone_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Выберите зону</option>
                                <option v-for="zone in store.zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Форма стола</label>
                            <div class="flex gap-3">
                                <button v-for="shape in tableShapes" :key="shape.id"
                                        @click="tableForm.shape = shape.id"
                                        :class="['flex-1 p-3 rounded-lg border-2 text-center transition',
                                                 tableForm.shape === shape.id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-gray-300']">
                                    <div class="text-2xl mb-1">{{ shape.icon }}</div>
                                    <div class="text-xs">{{ shape.label }}</div>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Минимальный заказ</label>
                            <div class="relative">
                                <input v-model.number="tableForm.min_order" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent pr-12" placeholder="0">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₽</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showTableModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveTable"
                                :disabled="!tableForm.number || !tableForm.seats || !tableForm.zone_id"
                                class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

// State
const selectedZone = ref<any>(null);
const floorPlan = ref<any>(null);
const floorPlanLoading = ref(false);
const showZoneModal = ref(false);
const showTableModal = ref(false);

// Forms
const zoneForm = ref({
    id: null as any,
    name: '',
    description: '',
    color: '#3b82f6'
});

const tableForm = ref({
    id: null as any,
    number: null as any,
    seats: 4,
    zone_id: null as any,
    shape: 'square',
    min_order: 0
});

// Constants
const zoneColors = ['#3b82f6', '#22c55e', '#f97316', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

const tableShapes = [
    { id: 'square', icon: '⬜', label: 'Квадрат' },
    { id: 'round', icon: '⭕', label: 'Круг' },
    { id: 'rectangle', icon: '▬', label: 'Прямоугольник' }
];

// Computed
const selectedZoneName = computed(() => {
    if (!selectedZone.value) return '';
    const zone = store.zones.find((z: any) => z.id === selectedZone.value);
    return zone?.name || '';
});

const zoneTables = computed(() => {
    if (!selectedZone.value) {
        return store.tables;
    }
    return store.tables.filter((t: any) => t.zone_id === selectedZone.value);
});

// Floor plan computed
const floorPlanWidth = computed(() => floorPlan.value?.layout?.width || 800);
const floorPlanHeight = computed(() => floorPlan.value?.layout?.height || 600);
const floorPlanTables = computed(() => floorPlan.value?.tables || []);
const floorPlanObjects = computed(() => floorPlan.value?.layout?.objects || []);

// Methods
function getZoneTablesCount(zoneId: any) {
    return store.tables.filter((t: any) => t.zone_id === zoneId).length;
}

function tableStatusClass(status: any) {
    const classes = {
        free: 'bg-green-100 text-green-800 hover:bg-green-200',
        occupied: 'bg-red-100 text-red-800 hover:bg-red-200',
        reserved: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'
    };
    return (classes as Record<string, any>)[status] || 'bg-gray-100 text-gray-800 hover:bg-gray-200';
}

function getTableColor(status: any) {
    const colors = {
        free: '#22c55e',
        occupied: '#ef4444',
        reserved: '#eab308',
        bill_requested: '#f97316'
    };
    return (colors as Record<string, any>)[status] || '#22c55e';
}

async function loadFloorPlan(zoneId: any) {
    if (!zoneId) {
        floorPlan.value = null;
        return;
    }

    try {
        floorPlanLoading.value = true;
        const data = await store.api(`/tables/floor-plan?zone_id=${zoneId}`);
        floorPlan.value = data.data || null;
    } catch (e: any) {
        console.error('Failed to load floor plan:', e);
        floorPlan.value = null;
    } finally {
        floorPlanLoading.value = false;
    }
}

// Zone CRUD
function openZoneModal(zone: any = null) {
    if (zone) {
        zoneForm.value = { ...zone };
    } else {
        zoneForm.value = {
            id: null as any,
            name: '',
            description: '',
            color: '#3b82f6'
        };
    }
    showZoneModal.value = true;
}

async function saveZone() {
    if (!zoneForm.value.name) return;

    try {
        const url = zoneForm.value.id
            ? `/backoffice/zones/${zoneForm.value.id}`
            : '/backoffice/zones';
        const method = zoneForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(zoneForm.value)
        });

        showZoneModal.value = false;
        store.loadZones();
        store.showToast(zoneForm.value.id ? 'Зона обновлена' : 'Зона создана', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteZone(zone: any) {
    const tablesCount = getZoneTablesCount(zone.id);
    if (tablesCount > 0) {
        store.showToast(`Невозможно удалить зону с ${tablesCount} столами`, 'error');
        return;
    }

    if (!confirm(`Удалить зону "${zone.name}"?`)) return;

    try {
        await store.api(`/backoffice/zones/${zone.id}`, { method: 'DELETE' });
        store.loadZones();
        if (selectedZone.value === zone.id) {
            selectedZone.value = null;
        }
        store.showToast('Зона удалена', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// Table CRUD
function openTableModal(table: any = null) {
    if (table) {
        tableForm.value = { ...table };
    } else {
        tableForm.value = {
            id: null as any,
            number: null as any,
            seats: 4,
            zone_id: selectedZone.value || (store.zones.length ? store.zones[0].id : null),
            shape: 'square',
            min_order: 0
        };
    }
    showTableModal.value = true;
}

async function saveTable() {
    if (!tableForm.value.number || !tableForm.value.seats || !tableForm.value.zone_id) return;

    try {
        const url = tableForm.value.id
            ? `/backoffice/tables/${tableForm.value.id}`
            : '/backoffice/tables';
        const method = tableForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(tableForm.value)
        });

        showTableModal.value = false;
        store.loadTables();
        store.showToast(tableForm.value.id ? 'Стол обновлён' : 'Стол создан', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteTable(table: any) {
    if (!confirm(`Удалить стол №${table.number}?`)) return;

    try {
        await store.api(`/backoffice/tables/${table.id}`, { method: 'DELETE' });
        store.loadTables();
        store.showToast('Стол удалён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

function openFloorEditor() {
    window.open('/floor-editor', '_blank');
}

// Watch zone changes to reload floor plan
watch(selectedZone, (newZoneId) => {
    if (newZoneId) {
        loadFloorPlan(newZoneId);
    }
});

// Watch store.zones changes to auto-select first zone
watch(() => store.zones, (newZones) => {
    if (newZones.length && !selectedZone.value) {
        selectedZone.value = newZones[0].id;
    }
}, { immediate: true });

// Init
onMounted(async () => {
    if (store.zones.length === 0) {
        await store.loadZones();
    }
    if (store.tables.length === 0) {
        store.loadTables();
    }
});
</script>
