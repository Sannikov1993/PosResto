import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

const API_URL = '/api';

export const useFloorEditorStore = defineStore('floorEditor', () => {
    // State
    const zones = ref([]);
    const selectedZoneId = ref(null);
    const objects = ref([]);
    const selectedObject = ref(null);
    const loading = ref(false);
    const toast = ref(null);

    // Canvas settings
    const canvasWidth = ref(1200);
    const canvasHeight = ref(800);
    const zoom = ref(100);
    const showGrid = ref(true);
    const snapToGrid = ref(true);
    const gridSize = ref(20);
    const showChairs = ref(true);
    const editMode = ref(true);

    // Background
    const backgroundImage = ref(null);
    const bgOpacity = ref(30);

    // Tools
    const currentTool = ref('select');
    const nextTableNumber = ref(1);

    // Polling
    let pollInterval = null;

    // Computed
    const freeTablesCount = computed(() => {
        return objects.value.filter(o => o.type === 'table' && getDisplayStatus(o) === 'free').length;
    });

    const occupiedTablesCount = computed(() => {
        return objects.value.filter(o => o.type === 'table' && getDisplayStatus(o) !== 'free').length;
    });

    // Methods
    async function loadZones(preselectedZoneId = null) {
        try {
            const res = await axios.get(`${API_URL}/tables/zones`);
            zones.value = res.data.data || [];
            if (zones.value.length) {
                if (preselectedZoneId && zones.value.find(z => z.id === preselectedZoneId)) {
                    selectedZoneId.value = preselectedZoneId;
                } else {
                    selectedZoneId.value = zones.value[0].id;
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadLayout(zoneId) {
        try {
            loading.value = true;
            const res = await axios.get(`${API_URL}/tables/floor-plan?zone_id=${zoneId}`);
            const data = res.data.data || {};

            // Load tables as objects
            const tables = data.tables || [];
            objects.value = tables.map(t => ({
                id: Date.now() + Math.random(),
                type: 'table',
                number: t.number,
                seats: t.seats,
                shape: t.shape || 'square',
                x: t.position_x || 100,
                y: t.position_y || 100,
                width: t.width || 80,
                height: t.height || 80,
                rotation: t.rotation || 0,
                minOrder: t.min_order || 0,
                surfaceStyle: t.surface_style || 'wood',
                chairStyle: t.chair_style || 'wood',
                status: t.status || 'free',
                dbId: t.id
            }));

            // Load other objects from layout
            const layout = data.layout || {};
            if (layout.objects && Array.isArray(layout.objects)) {
                const otherObjects = layout.objects.map(o => ({
                    id: Date.now() + Math.random(),
                    type: o.type,
                    x: o.x || 100,
                    y: o.y || 100,
                    width: o.width || 80,
                    height: o.height || 80,
                    rotation: o.rotation || 0,
                    text: o.text || null
                }));
                objects.value.push(...otherObjects);
            }

            // Load canvas settings
            if (layout.width) canvasWidth.value = layout.width;
            if (layout.height) canvasHeight.value = layout.height;
            if (layout.backgroundImage) backgroundImage.value = layout.backgroundImage;
            if (layout.bgOpacity !== undefined) bgOpacity.value = layout.bgOpacity;

            updateNextTableNumber();
            selectedObject.value = null;
        } catch (e) {
            console.error(e);
            objects.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function saveLayout() {
        try {
            const tables = objects.value.filter(o => o.type === 'table');
            const otherObjects = objects.value.filter(o => o.type !== 'table');

            const tablesData = tables.map(table => ({
                id: table.dbId || null,
                number: String(table.number),
                seats: table.seats,
                shape: table.shape,
                position_x: Math.round(table.x),
                position_y: Math.round(table.y),
                width: Math.round(table.width),
                height: Math.round(table.height),
                rotation: Math.round(table.rotation || 0),
                min_order: table.minOrder || 0,
                surface_style: table.surfaceStyle || 'wood',
                chair_style: table.chairStyle || 'wood'
            }));

            await axios.post(`${API_URL}/tables/layout`, {
                zone_id: selectedZoneId.value,
                tables: tablesData,
                layout: {
                    width: canvasWidth.value,
                    height: canvasHeight.value,
                    objects: otherObjects.map(obj => ({
                        type: obj.type,
                        x: Math.round(obj.x),
                        y: Math.round(obj.y),
                        width: Math.round(obj.width),
                        height: Math.round(obj.height),
                        rotation: Math.round(obj.rotation || 0),
                        text: obj.text || null
                    })),
                    backgroundImage: backgroundImage.value,
                    bgOpacity: bgOpacity.value
                }
            });

            await loadLayout(selectedZoneId.value);
            showToastMessage('План сохранён', 'success');
        } catch (e) {
            console.error(e);
            showToastMessage(e.response?.data?.message || 'Ошибка сохранения', 'error');
        }
    }

    function addObject(type, shape = null, scrollX = 0, scrollY = 0) {
        const tableDefaults = {
            square: { width: 80, height: 80, seats: 4 },
            round: { width: 80, height: 80, seats: 4 },
            rectangle: { width: 160, height: 70, seats: 6 },
            oval: { width: 120, height: 70, seats: 4 }
        };

        const defaults = {
            table: tableDefaults[shape] || { width: 80, height: 80, seats: 4 },
            wall: { width: 200, height: 12 },
            column: { width: 30, height: 30 },
            bar: { width: 200, height: 50 },
            sofa: { width: 120, height: 50 },
            door: { width: 60, height: 20 },
            window: { width: 80, height: 12 },
            plant: { width: 40, height: 40 },
            label: { width: 100, height: 30 }
        };

        const def = defaults[type] || { width: 60, height: 60 };

        const obj = {
            id: Date.now(),
            type,
            x: 200 + scrollX,
            y: 200 + scrollY,
            width: def.width,
            height: def.height,
            rotation: 0
        };

        if (type === 'table') {
            obj.shape = shape || 'square';
            obj.number = String(nextTableNumber.value++);
            obj.seats = def.seats;
            obj.minOrder = 0;
            obj.status = 'free';
            obj.totalAmount = 0;
            obj.hasBillRequest = false;
            obj.reservationTime = null;
        }

        if (type === 'label') {
            obj.text = 'Надпись';
        }

        objects.value.push(obj);
        selectedObject.value = obj;
        currentTool.value = 'select';
    }

    function deleteSelected() {
        if (!selectedObject.value) return;

        if (selectedObject.value.type === 'table' && selectedObject.value.dbId) {
            axios.delete(`${API_URL}/tables/${selectedObject.value.dbId}`).catch(console.error);
        }

        objects.value = objects.value.filter(o => o.id !== selectedObject.value.id);
        selectedObject.value = null;
    }

    function updateNextTableNumber() {
        const tables = objects.value.filter(o => o.type === 'table');
        const numbers = tables.map(t => {
            const match = String(t.number).match(/\d+/);
            return match ? parseInt(match[0], 10) : 0;
        });
        nextTableNumber.value = numbers.length ? Math.max(...numbers) + 1 : 1;
    }

    function getDisplayStatus(obj) {
        if (editMode.value) return 'free';
        return obj.status || 'free';
    }

    function showToastMessage(message, type = 'success') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

    // Polling for real-time updates
    function startPolling() {
        if (pollInterval) return;
        pollInterval = setInterval(() => {
            refreshTableStatuses();
        }, 10000);
        refreshTableStatuses();
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    async function refreshTableStatuses() {
        if (!selectedZoneId.value) return;
        try {
            const res = await axios.get(`${API_URL}/tables/floor-plan?zone_id=${selectedZoneId.value}`);
            const data = res.data.data || {};
            const tables = data.tables || [];

            tables.forEach(apiTable => {
                const localTable = objects.value.find(o =>
                    o.type === 'table' && (o.dbId === apiTable.id || o.number === apiTable.number)
                );
                if (localTable) {
                    localTable.status = apiTable.status || 'free';
                    localTable.totalAmount = apiTable.active_order?.total || 0;
                    localTable.hasBillRequest = apiTable.active_order?.bill_requested || false;
                    localTable.reservationTime = apiTable.next_reservation?.time || null;
                }
            });
        } catch (e) {
            console.error('Failed to refresh table statuses:', e);
        }
    }

    return {
        // State
        zones,
        selectedZoneId,
        objects,
        selectedObject,
        loading,
        toast,
        canvasWidth,
        canvasHeight,
        zoom,
        showGrid,
        snapToGrid,
        gridSize,
        showChairs,
        editMode,
        backgroundImage,
        bgOpacity,
        currentTool,
        nextTableNumber,

        // Computed
        freeTablesCount,
        occupiedTablesCount,

        // Methods
        loadZones,
        loadLayout,
        saveLayout,
        addObject,
        deleteSelected,
        getDisplayStatus,
        showToastMessage,
        startPolling,
        stopPolling,
        refreshTableStatuses
    };
});
