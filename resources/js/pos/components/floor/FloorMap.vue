<template>
    <div class="floor-map relative bg-dark-800 rounded-xl border border-gray-700"
         :class="{ 'multi-select-mode': multiSelectMode, 'cursor-pointer': multiSelectMode }"
         :style="{ width: floorWidth + 'px', height: floorHeight + 'px', minWidth: '100%', minHeight: '100%' }">
        <!-- Сетка -->
        <div class="absolute inset-0 opacity-10"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 50px 50px;"></div>

        <!-- SVG линии между выбранными столами -->
        <svg v-if="selectedTables.length > 1"
             class="absolute inset-0 pointer-events-none"
             :style="{ width: floorWidth + 'px', height: floorHeight + 'px', zIndex: 10 }">
            <line v-for="(line, idx) in selectionLines" :key="'sel-' + idx"
                  :x1="line.x1" :y1="line.y1"
                  :x2="line.x2" :y2="line.y2"
                  class="selection-link-line" />
        </svg>

        <!-- Рамки вокруг связанных столов -->
        <LinkedGroup
            v-for="group in linkedTablesGroups"
            :key="group.id"
            :group="group"
            :isFloorDateToday="isFloorDateToday"
            @click="$emit('openLinkedGroupOrder', group)"
            @clickReservation="$emit('openLinkedGroupReservation', group)"
            @openReservation="(res) => $emit('openTodayReservationModal', res)"
            @contextmenu="$emit('showGroupContextMenu', $event, group)"
        />

        <!-- Объекты зала (диваны, бар и т.д.) -->
        <template v-for="(obj, idx) in floorObjects" :key="'obj-' + idx">
            <!-- Бар - рендерится как FloorTable если есть barTable, иначе декоративный -->
            <template v-if="obj.type === 'bar'">
                <FloorTable
                    v-if="barTable"
                    :key="'bar-table-' + barTable.id"
                    :table="barTable"
                    :scale="floorScale"
                    :isFloorDateToday="isFloorDateToday"
                    :isSelected="selectedTable?.id === barTable.id"
                    :isMultiSelected="isTableSelected(barTable.id)"
                    :multiSelectMode="multiSelectMode"
                    :isInLinkedGroup="false"
                    :isInHoveredGroup="false"
                    :isInLinkedReservation="false"
                    :tableReservations="[]"
                    @click="$emit('selectTable', barTable)"
                    @contextmenu="$emit('showTableContextMenu', $event, barTable)"
                />
                <div v-else
                     class="floor-object floor-object-bar"
                     :style="getFloorObjectStyle(obj)">
                    <div class="bar-content">
                        <span class="bar-icon">🍸</span>
                        <span class="bar-label">БАР</span>
                    </div>
                </div>
            </template>

            <!-- Дверь - вид сверху -->
            <div v-else-if="obj.type === 'door'"
                 class="floor-object floor-object-door"
                 :style="getFloorObjectStyle(obj)">
                <svg class="w-full h-full" viewBox="0 0 100 80" preserveAspectRatio="none">
                    <rect x="0" y="55" width="8" height="16" fill="#4b5563"/>
                    <rect x="85" y="55" width="15" height="16" fill="#4b5563"/>
                    <rect x="8" y="57" width="77" height="12" fill="none" stroke="#6b7280" stroke-width="2"/>
                    <rect x="8" y="59" width="55" height="8" fill="#3b82f6" rx="1"/>
                    <path d="M 63 63 A 55 55 0 0 0 8 8" stroke="#3b82f6" stroke-width="1.5" fill="none" stroke-dasharray="4,3"/>
                    <circle cx="8" cy="63" r="3" fill="#1e3a8a"/>
                </svg>
            </div>

            <!-- Окно -->
            <div v-else-if="obj.type === 'window'"
                 class="floor-object floor-object-window"
                 :style="getFloorObjectStyle(obj)">
                <div class="w-full h-full bg-blue-500/30 border border-blue-400"></div>
            </div>

            <!-- Остальные объекты -->
            <div v-else
                 class="floor-object"
                 :class="'floor-object-' + obj.type"
                 :style="getFloorObjectStyle(obj)">
                <template v-if="obj.type === 'plant'">🌿</template>
            </div>
        </template>

        <!-- Столы -->
        <FloorTable
            v-for="table in tables"
            :key="table.id"
            :table="table"
            :scale="floorScale"
            :isFloorDateToday="isFloorDateToday"
            :isSelected="selectedTable?.id === table.id"
            :isMultiSelected="isTableSelected(table.id)"
            :multiSelectMode="multiSelectMode"
            :isInLinkedGroup="!!getTableLinkedOrderGroup(table.id)"
            :isInHoveredGroup="isTableInHoveredGroup(table.id)"
            :isInLinkedReservation="isTableInLinkedReservation(table.id)"
            :tableReservations="getTableReservations(table.id)"
            @click="$emit('selectTable', table)"
            @contextmenu="$emit('showTableContextMenu', $event, table)"
            @mouseenter="onTableMouseEnter(table)"
            @mouseleave="onTableMouseLeave"
            @openReservation="(res) => $emit('openTodayReservationModal', res)"
        />

        <!-- Состояние загрузки -->
        <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
            <div class="animate-spin w-8 h-8 border-4 border-accent border-t-transparent rounded-full mb-4"></div>
            <p>Загрузка столов...</p>
        </div>

        <!-- Пустое состояние -->
        <div v-else-if="!tables.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-500">
            <p class="text-4xl mb-4">🪑</p>
            <p>Нет столов в этой зоне</p>
            <p class="text-sm">Добавьте столы в бэк-офисе</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import FloorTable from './FloorTable.vue';
import LinkedGroup from './LinkedGroup.vue';

const props = defineProps({
    tables: { type: Array, default: () => [] },
    floorObjects: { type: Array, default: () => [] },
    floorScale: { type: Number, default: 1 },
    floorWidth: { type: Number, default: 1200 },
    floorHeight: { type: Number, default: 800 },
    loading: { type: Boolean, default: false },
    selectedTable: { type: Object, default: null },
    selectedTables: { type: Array, default: () => [] },
    multiSelectMode: { type: Boolean, default: false },
    isFloorDateToday: { type: Boolean, default: true },
    linkedTablesMap: { type: Object, default: () => ({}) },
    reservations: { type: Array, default: () => [] },
    barTable: { type: Object, default: null }
});

const emit = defineEmits([
    'selectTable',
    'showTableContextMenu',
    'showGroupContextMenu',
    'openLinkedGroupOrder',
    'openLinkedGroupReservation',
    'openTodayReservationModal'
]);

// Hovered linked group
const hoveredLinkedGroup = ref(null);

// Check if table is in selection
const isTableSelected = (tableId) => {
    return props.selectedTables.some(t => t.id === tableId);
};

// Get linked order group for a table
const getTableLinkedOrderGroup = (tableId) => {
    for (const [resId, group] of Object.entries(props.linkedTablesMap)) {
        if (group.tableIds.includes(tableId) && group.type === 'order') {
            return group;
        }
    }
    return null;
};

// Check if table is in hovered group
const isTableInHoveredGroup = (tableId) => {
    if (!hoveredLinkedGroup.value) return false;
    return hoveredLinkedGroup.value.tableIds.includes(tableId);
};

// Check if table is in linked reservation
const isTableInLinkedReservation = (tableId) => {
    for (const [resId, group] of Object.entries(props.linkedTablesMap)) {
        if (group.tableIds.includes(tableId) && group.type === 'reservation' && group.tableIds.length > 1) {
            return true;
        }
    }
    return false;
};

// Mouse events for linked groups
const onTableMouseEnter = (table) => {
    const group = getTableLinkedOrderGroup(table.id);
    if (group) {
        hoveredLinkedGroup.value = group;
    }
};

const onTableMouseLeave = () => {
    hoveredLinkedGroup.value = null;
};

// Get reservations for a specific table (only active single reservations)
const getTableReservations = (tableId) => {
    const validStatuses = ['pending', 'confirmed'];
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();

    return props.reservations
        .filter(r => {
            // Исключаем связанные брони - они отображаются через LinkedGroup
            const linkedIds = r.linked_table_ids;
            const isLinkedReservation = Array.isArray(linkedIds) && linkedIds.length > 0;
            if (isLinkedReservation) return false;

            // Фильтруем по table_id (только для одиночных броней)
            const isCorrectTable = r.table_id === tableId;
            const isValidStatus = r.status && validStatuses.includes(r.status);
            return isCorrectTable && isValidStatus;
        })
        .sort((a, b) => {
            // Конвертируем время в минуты для сравнения
            const getMinutes = (timeStr) => {
                if (!timeStr) return 0;
                const [h, m] = timeStr.split(':').map(Number);
                return h * 60 + m;
            };

            const aMinutes = getMinutes(a.time_from);
            const bMinutes = getMinutes(b.time_from);

            // Определяем, прошло ли время брони
            const aIsPast = aMinutes < currentMinutes;
            const bIsPast = bMinutes < currentMinutes;

            // Будущие брони идут первыми, отсортированные по времени
            if (!aIsPast && !bIsPast) {
                return aMinutes - bMinutes; // Обе в будущем - по возрастанию
            }
            if (aIsPast && bIsPast) {
                return aMinutes - bMinutes; // Обе в прошлом - по возрастанию
            }
            // Будущие перед прошлыми
            return aIsPast ? 1 : -1;
        });
};

// Selection lines between multi-selected tables
const selectionLines = computed(() => {
    const lines = [];
    const tables = props.selectedTables;
    if (tables.length < 2) return lines;

    for (let i = 0; i < tables.length - 1; i++) {
        const t1 = tables[i];
        const t2 = tables[i + 1];
        const x1 = (t1.position_x + (t1.width || 80) / 2) * props.floorScale;
        const y1 = (t1.position_y + (t1.height || 80) / 2) * props.floorScale;
        const x2 = (t2.position_x + (t2.width || 80) / 2) * props.floorScale;
        const y2 = (t2.position_y + (t2.height || 80) / 2) * props.floorScale;
        lines.push({ x1, y1, x2, y2 });
    }
    return lines;
});

// Linked tables groups for rendering
const linkedTablesGroups = computed(() => {
    const groups = [];
    const map = props.linkedTablesMap;
    const allTables = props.tables || [];
    const padding = 20;
    const scale = props.floorScale;

    // Группируем брони по одинаковому набору столов
    const tableSetMap = new Map(); // key: sorted tableIds string, value: { type, tableIds, reservations: [], order }

    for (const [resId, group] of Object.entries(map)) {
        const tableIds = group.tableIds;
        if (tableIds.length < 2) continue;

        const sortedKey = [...tableIds].sort((a, b) => a - b).join('-');

        if (!tableSetMap.has(sortedKey)) {
            tableSetMap.set(sortedKey, {
                type: group.type,
                tableIds: tableIds,
                reservations: [],
                order: group.order
            });
        }

        const existing = tableSetMap.get(sortedKey);
        if (group.type === 'reservation' && group.reservation) {
            existing.reservations.push(group.reservation);
        }
        if (group.type === 'order' && group.order) {
            existing.order = group.order;
            existing.type = 'order'; // Заказ имеет приоритет над бронью
        }
    }

    // Теперь создаём группы для отображения
    for (const [tableSetKey, groupData] of tableSetMap) {
        const tableIds = groupData.tableIds;

        // Только объединённые брони (с linked_table_ids) отображаются в LinkedGroup
        // Одиночные брони отображаются на своих столах отдельно

        const groupTables = tableIds
            .map(id => allTables.find(t => t.id === id))
            .filter(t => t);

        if (groupTables.length < 2) continue;

        // Collect corner points for each table with padding
        const allPoints = [];
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

        groupTables.forEach(t => {
            const x = t.position_x * scale;
            const y = t.position_y * scale;
            const w = (t.width || 80) * scale;
            const h = (t.height || 80) * scale;
            const p = padding;

            allPoints.push({ x: x - p, y: y - p });
            allPoints.push({ x: x + w + p, y: y - p });
            allPoints.push({ x: x + w + p, y: y + h + p });
            allPoints.push({ x: x - p, y: y + h + p });

            minX = Math.min(minX, x - p);
            minY = Math.min(minY, y - p);
            maxX = Math.max(maxX, x + w + p);
            maxY = Math.max(maxY, y + h + p);
        });

        // Calculate convex hull
        const hull = convexHull(allPoints);
        const svgPath = hullToSvgPath(hull, minX, minY);

        // Найти позицию для бейджа - по центру сверху самого верхнего стола
        const topTable = [...groupTables].sort((a, b) => a.position_y - b.position_y)[0];
        const tableX = topTable.position_x * scale;
        const tableY = topTable.position_y * scale;
        const tableW = (topTable.width || 80) * scale;
        // Бейдж по центру сверху стола (относительно контейнера группы)
        const badgeX = (tableX + tableW / 2) - minX;
        const badgeY = tableY - minY - padding + 18; // На верхнем краю стола

        // Сортируем брони по времени
        const sortedReservations = [...groupData.reservations].sort((a, b) => {
            const getMinutes = (timeStr) => {
                if (!timeStr) return 0;
                const [h, m] = timeStr.split(':').map(Number);
                return h * 60 + m;
            };
            return getMinutes(a.time_from) - getMinutes(b.time_from);
        });

        groups.push({
            id: tableSetKey,
            type: groupData.type,
            tableIds: tableIds,
            tableNumbers: groupTables.map(t => t.number).join(', '),
            tablesCount: groupTables.length,
            totalSeats: groupTables.reduce((sum, t) => sum + (t.seats || 4), 0),
            reservation: sortedReservations[0] || null, // Первая бронь для обратной совместимости
            reservations: sortedReservations, // Все брони
            order: groupData.order,
            x: minX,
            y: minY,
            width: maxX - minX,
            height: maxY - minY,
            svgPath: svgPath,
            badgeX: badgeX,
            badgeY: badgeY
        });
    }

    return groups;
});

// Convex hull algorithm
const convexHull = (points) => {
    if (points.length < 3) return points;

    const sorted = [...points].sort((a, b) => a.x - b.x || a.y - b.y);

    const cross = (o, a, b) => (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);

    const lower = [];
    for (const p of sorted) {
        while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], p) <= 0) {
            lower.pop();
        }
        lower.push(p);
    }

    const upper = [];
    for (let i = sorted.length - 1; i >= 0; i--) {
        const p = sorted[i];
        while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], p) <= 0) {
            upper.pop();
        }
        upper.push(p);
    }

    lower.pop();
    upper.pop();
    return lower.concat(upper);
};

// Convert hull to SVG path with rounded corners
const hullToSvgPath = (hull, offsetX, offsetY) => {
    if (hull.length < 3) return '';

    const radius = 15;
    let path = '';

    for (let i = 0; i < hull.length; i++) {
        const p0 = hull[(i - 1 + hull.length) % hull.length];
        const p1 = hull[i];
        const p2 = hull[(i + 1) % hull.length];

        const x1 = p1.x - offsetX;
        const y1 = p1.y - offsetY;

        const dx1 = p1.x - p0.x;
        const dy1 = p1.y - p0.y;
        const dx2 = p2.x - p1.x;
        const dy2 = p2.y - p1.y;

        const len1 = Math.sqrt(dx1 * dx1 + dy1 * dy1);
        const len2 = Math.sqrt(dx2 * dx2 + dy2 * dy2);

        const r = Math.min(radius, len1 / 2, len2 / 2);

        const startX = x1 - (dx1 / len1) * r;
        const startY = y1 - (dy1 / len1) * r;
        const endX = x1 + (dx2 / len2) * r;
        const endY = y1 + (dy2 / len2) * r;

        if (i === 0) {
            path = `M ${startX} ${startY}`;
        } else {
            path += ` L ${startX} ${startY}`;
        }
        path += ` Q ${x1} ${y1} ${endX} ${endY}`;
    }

    path += ' Z';
    return path;
};

// Floor object style
const getFloorObjectStyle = (obj) => {
    return {
        left: (obj.x * props.floorScale) + 'px',
        top: (obj.y * props.floorScale) + 'px',
        width: (obj.width * props.floorScale) + 'px',
        height: (obj.height * props.floorScale) + 'px',
        transform: obj.rotation ? `rotate(${obj.rotation}deg)` : 'none'
    };
};
</script>

<style scoped>
.selection-link-line {
    stroke: #8B5CF6;
    stroke-width: 3;
    stroke-dasharray: 8 4;
    stroke-linecap: round;
}

.multi-select-mode {
    border-color: #8B5CF6 !important;
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3), inset 0 0 30px rgba(139, 92, 246, 0.05);
}
</style>
