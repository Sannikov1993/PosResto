<template>
    <div class="h-screen flex flex-col bg-gray-100 overflow-hidden">
        <!-- Header -->
        <header class="h-14 bg-neutral-900 border-b border-neutral-700 flex items-center justify-between px-4 shrink-0">
            <div class="flex items-center gap-4">
                <a href="/backoffice" class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors">
                    <span>←</span>
                    <span class="text-sm">Назад</span>
                </a>
                <div class="w-px h-6 bg-neutral-700"></div>
                <h1 class="font-semibold text-white">Редактор зала</h1>
                <select v-model="store.selectedZoneId" @change="onZoneChange"
                        class="text-sm bg-neutral-800 border border-neutral-600 text-white rounded-lg px-3 py-1.5">
                    <option v-for="zone in store.zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
                </select>
            </div>
            <div class="flex items-center gap-4">
                <!-- Mode Toggle -->
                <div class="mode-toggle">
                    <div @click="store.editMode = true" :class="['mode-btn', store.editMode ? 'active' : '']">Редактор</div>
                    <div @click="store.editMode = false" :class="['mode-btn', !store.editMode ? 'active' : '']">Просмотр</div>
                </div>
                <div class="w-px h-6 bg-neutral-700"></div>
                <span class="text-sm text-gray-400">{{ store.objects.length }} объектов</span>
                <button @click="store.saveLayout()" class="bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-orange-600 transition-colors">
                    Сохранить
                </button>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">
            <!-- Left Panel - Tools -->
            <aside v-if="store.editMode" class="w-64 bg-neutral-900 border-r border-neutral-700 flex flex-col shrink-0">
                <!-- Tools -->
                <div class="p-4 border-b border-neutral-700">
                    <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-3">Инструменты</h3>
                    <div class="grid grid-cols-4 gap-2">
                        <button @click="store.currentTool = 'select'" :class="['p-3 rounded-xl text-center transition-all', store.currentTool === 'select' ? 'bg-orange-500 text-white' : 'bg-neutral-800 text-gray-400 hover:bg-neutral-700']">
                            <div class="text-xl mb-1">👆</div>
                            <div class="text-xs">Выбор</div>
                        </button>
                        <button @click="store.currentTool = 'pan'" :class="['p-3 rounded-xl text-center transition-all', store.currentTool === 'pan' ? 'bg-orange-500 text-white' : 'bg-neutral-800 text-gray-400 hover:bg-neutral-700']">
                            <div class="text-xl mb-1">✋</div>
                            <div class="text-xs">Рука</div>
                        </button>
                        <button @click="store.duplicateSelected()" class="p-3 rounded-xl text-center bg-neutral-800 text-blue-400 hover:bg-blue-900/30 transition-all" :disabled="!store.selectedObject" title="Ctrl+D">
                            <div class="text-xl mb-1">📋</div>
                            <div class="text-xs">Копия</div>
                        </button>
                        <button @click="store.deleteSelected()" class="p-3 rounded-xl text-center bg-neutral-800 text-red-400 hover:bg-red-900/30 transition-all" :disabled="!store.selectedObject">
                            <div class="text-xl mb-1">🗑️</div>
                            <div class="text-xs">Удалить</div>
                        </button>
                    </div>
                </div>

                <!-- Add Objects -->
                <div class="p-4 flex-1 overflow-y-auto">
                    <!-- Tables Section -->
                    <div class="mb-6">
                        <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-3">Столы</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="addTable('square')">
                                <div class="relative">
                                    <div class="w-10 h-10 rounded-lg table-free"></div>
                                    <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-4 h-1 rounded-t-full bg-[#5D4E37]"></div>
                                    <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-4 h-1 rounded-b-full bg-[#5D4E37]"></div>
                                    <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-1 h-4 rounded-l-full bg-[#5D4E37]"></div>
                                    <div class="absolute -right-1 top-1/2 -translate-y-1/2 w-1 h-4 rounded-r-full bg-[#5D4E37]"></div>
                                </div>
                                <span class="text-gray-400 text-xs">Квадрат</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="addTable('round')">
                                <div class="relative">
                                    <div class="w-10 h-10 rounded-full table-free"></div>
                                    <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-3 h-1 rounded-t-full bg-[#5D4E37]"></div>
                                    <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-3 h-1 rounded-b-full bg-[#5D4E37]"></div>
                                    <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-1 h-3 rounded-l-full bg-[#5D4E37]"></div>
                                    <div class="absolute -right-1 top-1/2 -translate-y-1/2 w-1 h-3 rounded-r-full bg-[#5D4E37]"></div>
                                </div>
                                <span class="text-gray-400 text-xs">Круглый</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="addTable('rectangle')">
                                <div class="relative">
                                    <div class="w-14 h-6 rounded-lg table-free"></div>
                                </div>
                                <span class="text-gray-400 text-xs">Длинный</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="addTable('oval')">
                                <div class="relative">
                                    <div class="w-12 h-7 rounded-full table-free"></div>
                                </div>
                                <span class="text-gray-400 text-xs">Овальный</span>
                            </div>
                        </div>
                    </div>

                    <!-- Furniture Section -->
                    <div class="mb-6">
                        <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-3">Мебель</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('bar')">
                                <div class="w-12 h-4 bg-amber-700 rounded"></div>
                                <span class="text-gray-400 text-xs">Бар</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('sofa')">
                                <div class="w-10 h-5 bg-purple-400/30 border-2 border-purple-500 rounded"></div>
                                <span class="text-gray-400 text-xs">Диван</span>
                            </div>
                        </div>
                    </div>

                    <!-- Constructions Section -->
                    <div class="mb-6">
                        <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-3">Конструкции</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('wall')">
                                <div class="w-12 h-2 bg-neutral-600 rounded"></div>
                                <span class="text-gray-400 text-xs">Стена</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('column')">
                                <div class="w-5 h-5 bg-neutral-500 rounded"></div>
                                <span class="text-gray-400 text-xs">Колонна</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('window')">
                                <div class="w-8 h-2 bg-blue-400/50 border border-blue-500 rounded"></div>
                                <span class="text-gray-400 text-xs">Окно</span>
                            </div>
                        </div>
                    </div>

                    <!-- Doors Section -->
                    <div class="mb-6">
                        <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-3">Двери</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('door')">
                                <!-- Иконка двери сверху (вариант 2) -->
                                <svg class="w-12 h-8" viewBox="0 0 60 40">
                                    <rect x="0" y="15" width="5" height="10" fill="#4b5563"/>
                                    <rect x="50" y="15" width="10" height="10" fill="#4b5563"/>
                                    <rect x="5" y="16" width="45" height="8" fill="none" stroke="#6b7280" stroke-width="1"/>
                                    <rect x="5" y="18" width="35" height="4" fill="#3b82f6" rx="1"/>
                                    <path d="M 40 20 A 35 35 0 0 0 5 -15" stroke="#3b82f6" stroke-width="1" fill="none" stroke-dasharray="2,2"/>
                                    <circle cx="5" cy="20" r="2" fill="#1e3a8a"/>
                                </svg>
                                <span class="text-gray-400 text-xs">Дверь</span>
                            </div>
                        </div>
                    </div>

                    <!-- Decor Section -->
                    <div>
                        <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-3">Декор</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('plant')">
                                <div class="text-xl">🌿</div>
                                <span class="text-gray-400 text-xs">Растение</span>
                            </div>
                            <div class="draggable-item bg-neutral-800 rounded-xl p-3 flex flex-col items-center gap-2 border border-white/5 cursor-pointer"
                                 @click="store.addObject('label')">
                                <div class="text-xl">🏷️</div>
                                <span class="text-gray-400 text-xs">Надпись</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Background Image -->
                <div class="p-4 border-t border-neutral-700">
                    <h3 class="text-gray-500 text-xs font-medium uppercase tracking-wider mb-2">Фоновый план</h3>
                    <input type="file" @change="loadBackgroundImage" accept="image/*" class="hidden" ref="bgInput">
                    <button @click="$refs.bgInput.click()" class="w-full py-2 px-3 border border-dashed border-neutral-600 rounded-lg text-sm text-gray-400 hover:border-gray-500 hover:text-gray-300 transition-colors">
                        {{ store.backgroundImage ? 'Заменить изображение' : 'Загрузить план' }}
                    </button>
                    <div v-if="store.backgroundImage" class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-gray-500">План загружен</span>
                        <button @click="store.backgroundImage = null" class="text-red-400 hover:text-red-300">Удалить</button>
                    </div>
                    <div v-if="store.backgroundImage" class="mt-2">
                        <label class="text-xs text-gray-500">Прозрачность</label>
                        <input type="range" v-model="store.bgOpacity" min="10" max="100" class="w-full accent-orange-500">
                    </div>
                </div>
            </aside>

            <!-- Canvas Area -->
            <main class="flex-1 relative overflow-hidden bg-neutral-800" ref="canvasContainer"
                  @mousedown="onCanvasMouseDown"
                  @mousemove="onCanvasMouseMove"
                  @mouseup="onCanvasMouseUp"
                  @mouseleave="onCanvasMouseUp"
                  @wheel="onCanvasWheel"
                  @click="onCanvasClick">

                <!-- Zoom Controls -->
                <div class="absolute top-4 left-4 z-20 flex items-center gap-2 bg-neutral-900/90 backdrop-blur rounded-lg shadow-lg p-1 border border-neutral-700">
                    <button @click="store.zoom = Math.max(25, store.zoom - 25)" class="w-8 h-8 rounded hover:bg-neutral-700 text-lg text-gray-300">−</button>
                    <span class="text-sm w-12 text-center text-gray-300">{{ store.zoom }}%</span>
                    <button @click="store.zoom = Math.min(200, store.zoom + 25)" class="w-8 h-8 rounded hover:bg-neutral-700 text-lg text-gray-300">+</button>
                    <button @click="store.zoom = 100" class="px-2 h-8 rounded hover:bg-neutral-700 text-xs text-gray-400">Сброс</button>
                </div>

                <!-- Grid Toggle -->
                <div v-if="store.editMode" class="absolute top-4 right-4 z-20 flex items-center gap-3 bg-neutral-900/90 backdrop-blur rounded-lg shadow-lg px-3 py-2 border border-neutral-700">
                    <label class="flex items-center gap-2 text-sm cursor-pointer text-gray-400">
                        <input type="checkbox" v-model="store.showGrid" class="accent-orange-500">
                        Сетка
                    </label>
                    <div class="w-px h-4 bg-neutral-600"></div>
                    <label class="flex items-center gap-2 text-sm cursor-pointer text-gray-400">
                        <input type="checkbox" v-model="store.snapToGrid" class="accent-orange-500">
                        Привязка
                    </label>
                    <div class="w-px h-4 bg-neutral-600"></div>
                    <label class="flex items-center gap-2 text-sm cursor-pointer text-gray-400">
                        <input type="checkbox" v-model="store.showChairs" class="accent-orange-500">
                        Стулья
                    </label>
                </div>

                <!-- Canvas with zoom and pan -->
                <div class="absolute inset-0 overflow-auto" ref="scrollContainer">
                    <div class="floor-canvas relative"
                         :style="{
                             width: store.canvasWidth + 'px',
                             height: store.canvasHeight + 'px',
                             transform: `scale(${store.zoom / 100})`,
                             transformOrigin: 'top left',
                             backgroundImage: store.showGrid ? undefined : 'none'
                         }">

                        <!-- Background Image -->
                        <img v-if="store.backgroundImage" :src="store.backgroundImage"
                             class="absolute inset-0 w-full h-full object-contain pointer-events-none"
                             :style="{ opacity: store.bgOpacity / 100 }">

                        <!-- Objects -->
                        <FloorObject
                            v-for="obj in store.objects"
                            :key="obj.id"
                            :obj="obj"
                            :selected="store.selectedObject?.id === obj.id"
                            :dragging="draggedObject?.id === obj.id"
                            :edit-mode="store.editMode"
                            :show-chairs="store.showChairs"
                            @start-drag="startDrag"
                            @start-resize="startResize"
                            @start-rotate="startRotate"
                            @table-click="openTableDetails"
                        />
                    </div>
                </div>

                <!-- Legend (view mode only) -->
                <div v-if="!store.editMode" class="absolute bottom-4 left-4 bg-neutral-900/90 backdrop-blur rounded-xl p-3 flex gap-4 text-xs border border-neutral-700">
                    <div class="legend-item">
                        <div class="legend-dot table-free"></div>
                        <span class="text-gray-400">Свободен</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot table-occupied"></div>
                        <span class="text-gray-400">Занят</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot table-reserved"></div>
                        <span class="text-gray-400">Бронь</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot table-alert"></div>
                        <span class="text-gray-400">Внимание</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot table-ready"></div>
                        <span class="text-gray-400">Готов</span>
                    </div>
                </div>

                <!-- Info (view mode) -->
                <div v-if="!store.editMode" class="absolute top-4 right-4 bg-neutral-900/90 backdrop-blur rounded-xl px-4 py-2 border border-neutral-700">
                    <div class="flex items-center gap-4 text-sm">
                        <div class="text-gray-400">
                            Свободно: <span class="text-green-400 font-medium">{{ store.freeTablesCount }}</span>
                        </div>
                        <div class="text-gray-400">
                            Занято: <span class="text-orange-400 font-medium">{{ store.occupiedTablesCount }}</span>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Right Panel - Properties -->
            <PropertiesPanel v-if="store.editMode" />
        </div>

        <!-- Toast -->
        <div v-if="store.toast" class="fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white"
             :class="store.toast.type === 'success' ? 'bg-green-500' : 'bg-red-500'">
            {{ store.toast.message }}
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useFloorEditorStore } from './stores/floorEditor';
import FloorObject from './components/FloorObject.vue';
import PropertiesPanel from './components/PropertiesPanel.vue';

const store = useFloorEditorStore();

// Refs
const canvasContainer = ref(null);
const scrollContainer = ref(null);
const bgInput = ref(null);

// Drag state
const draggedObject = ref(null);
const dragOffset = ref({ x: 0, y: 0 });
const resizing = ref(null);
const rotating = ref(null);

// Zone change handler
function onZoneChange() {
    if (store.selectedZoneId) {
        store.loadLayout(store.selectedZoneId);
    }
}

// Add table helper
function addTable(shape) {
    const scrollX = scrollContainer.value?.scrollLeft || 0;
    const scrollY = scrollContainer.value?.scrollTop || 0;
    store.addObject('table', shape, scrollX, scrollY);
}


// Drag & Drop
function startDrag(obj, e) {
    if (store.currentTool !== 'select') return;

    store.selectedObject = obj;
    draggedObject.value = obj;

    const container = scrollContainer.value;
    const containerRect = container.getBoundingClientRect();
    const scale = store.zoom / 100;

    // Вычисляем позицию клика в координатах canvas
    const clickX = (e.clientX - containerRect.left + container.scrollLeft) / scale;
    const clickY = (e.clientY - containerRect.top + container.scrollTop) / scale;

    // Смещение от позиции объекта
    dragOffset.value = {
        x: clickX - obj.x,
        y: clickY - obj.y
    };
}

function onCanvasMouseDown(e) {
    // Pan mode - future implementation
}

function onCanvasMouseMove(e) {
    if (draggedObject.value) {
        const container = scrollContainer.value;
        const rect = container.getBoundingClientRect();
        const scale = store.zoom / 100;

        let newX = (e.clientX - rect.left + container.scrollLeft) / scale - dragOffset.value.x;
        let newY = (e.clientY - rect.top + container.scrollTop) / scale - dragOffset.value.y;

        if (store.snapToGrid) {
            newX = Math.round(newX / store.gridSize) * store.gridSize;
            newY = Math.round(newY / store.gridSize) * store.gridSize;
        }

        // Мягкие ограничения - можно выходить за границы, но не слишком далеко
        newX = Math.max(-200, Math.min(newX, store.canvasWidth + 200));
        newY = Math.max(-200, Math.min(newY, store.canvasHeight + 200));

        draggedObject.value.x = newX;
        draggedObject.value.y = newY;
    }

    if (resizing.value) {
        const container = scrollContainer.value;
        const rect = container.getBoundingClientRect();
        const scale = store.zoom / 100;

        const mouseX = (e.clientX - rect.left + container.scrollLeft) / scale;
        const mouseY = (e.clientY - rect.top + container.scrollTop) / scale;

        let newWidth = mouseX - resizing.value.obj.x;
        let newHeight = mouseY - resizing.value.obj.y;

        if (store.snapToGrid) {
            newWidth = Math.round(newWidth / store.gridSize) * store.gridSize;
            newHeight = Math.round(newHeight / store.gridSize) * store.gridSize;
        }

        resizing.value.obj.width = Math.max(30, newWidth);
        resizing.value.obj.height = Math.max(30, newHeight);
    }

    if (rotating.value) {
        const obj = rotating.value.obj;
        const centerX = obj.x + obj.width / 2;
        const centerY = obj.y + obj.height / 2;

        const container = scrollContainer.value;
        const rect = container.getBoundingClientRect();
        const scale = store.zoom / 100;

        const mouseX = (e.clientX - rect.left + container.scrollLeft) / scale;
        const mouseY = (e.clientY - rect.top + container.scrollTop) / scale;

        const angle = Math.atan2(mouseY - centerY, mouseX - centerX) * 180 / Math.PI + 90;
        obj.rotation = Math.round(angle / 15) * 15;
    }
}

function onCanvasMouseUp() {
    draggedObject.value = null;
    resizing.value = null;
    rotating.value = null;
}

function onCanvasClick(e) {
    if (e.target === scrollContainer.value || e.target.classList.contains('floor-canvas')) {
        store.selectedObject = null;
    }
}

function onCanvasWheel(e) {
    if (e.ctrlKey) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -10 : 10;
        store.zoom = Math.max(25, Math.min(200, store.zoom + delta));
    }
}

// Resize & Rotate
function startResize(obj, handle, e) {
    resizing.value = { obj, handle };
    e.preventDefault();
}

function startRotate(obj, e) {
    rotating.value = { obj };
    e.preventDefault();
}

// Keyboard
function onKeyDown(e) {
    // Delete - удалить выбранный объект
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (store.selectedObject && document.activeElement.tagName !== 'INPUT') {
            store.deleteSelected();
        }
    }
    // Escape - снять выделение
    if (e.key === 'Escape') {
        store.selectedObject = null;
    }
    // Ctrl+D - дублировать выбранный объект
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault(); // Предотвращаем bookmark в браузере
        if (store.selectedObject && document.activeElement.tagName !== 'INPUT') {
            store.duplicateSelected();
        }
    }
    // Ctrl+S - сохранить
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        store.saveLayout();
    }
}

// Background image
function loadBackgroundImage(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (ev) => {
        store.backgroundImage = ev.target.result;
    };
    reader.readAsDataURL(file);
}

// Table details
function openTableDetails(obj) {
    if (obj.type === 'table' && obj.dbId) {
        // TODO: Implement table details modal
    }
}

// Watch edit mode for polling
watch(() => store.editMode, (isEdit) => {
    if (!isEdit) {
        store.startPolling();
        store.selectedObject = null;
    } else {
        store.stopPolling();
    }
});

// Watch zone change
watch(() => store.selectedZoneId, (newId) => {
    if (newId) store.loadLayout(newId);
});

// Lifecycle
onMounted(async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const zoneIdFromUrl = urlParams.get('zone_id');
    await store.loadZones(zoneIdFromUrl ? parseInt(zoneIdFromUrl) : null);
    document.addEventListener('keydown', onKeyDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeyDown);
    store.stopPolling();
});
</script>

<style>
.floor-canvas {
    background-color: #1a1a1a;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 20px 20px;
}

/* Mode toggle */
.mode-toggle {
    display: flex;
    background: #262626;
    border-radius: 8px;
    padding: 2px;
}
.mode-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s;
    cursor: pointer;
}
.mode-btn.active {
    background: #f97316;
    color: white;
}
.mode-btn:not(.active) {
    color: #9ca3af;
}
.mode-btn:not(.active):hover {
    color: white;
}

/* Legend */
.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}
.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 4px;
}

/* Table status colors */
.table-free { background: linear-gradient(135deg, #8B5A2B 0%, #6B4423 50%, #5D3A1A 100%); }
.table-occupied { background: linear-gradient(135deg, #D97706 0%, #B45309 50%, #92400E 100%); }
.table-reserved { background: linear-gradient(135deg, #5D6D7E 0%, #4A5568 50%, #3D4852 100%); }
.table-alert { background: linear-gradient(135deg, #A04040 0%, #8B3232 50%, #6B2828 100%); }
.table-ready { background: linear-gradient(135deg, #4A7C59 0%, #3D6B4A 50%, #2D5A3A 100%); }

/* Draggable items */
.draggable-item {
    transition: all 0.15s;
}
.draggable-item:hover {
    background: #262626 !important;
    border-color: rgba(249, 115, 22, 0.3) !important;
}
</style>
