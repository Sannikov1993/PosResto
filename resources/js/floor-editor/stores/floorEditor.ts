import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { createHttpClient } from '../../shared/services/httpClient.js';
import { createLogger } from '../../shared/services/logger.js';

const { http, extractData } = createHttpClient({ module: 'FloorEditor' });
const log = createLogger('FloorEditor');

interface FloorObject {
    id: number;
    type: string;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation: number;
    // Table-specific
    number?: string;
    seats?: number;
    shape?: string;
    minOrder?: number;
    surfaceStyle?: string;
    chairStyle?: string;
    status?: string;
    totalAmount?: number;
    hasBillRequest?: boolean;
    reservationTime?: string | null;
    dbId?: number | null;
    // Label-specific
    text?: string | null;
}

interface Zone {
    id: number;
    name: string;
    [key: string]: unknown;
}

interface ToastMessage {
    message: string;
    type: string;
}

export const useFloorEditorStore = defineStore('floorEditor', () => {
    // State
    const zones = ref<Zone[]>([]);
    const selectedZoneId = ref<number | null>(null);
    const objects = ref<FloorObject[]>([]);
    const selectedObject = ref<FloorObject | null>(null);
    const loading = ref(false);
    const toast = ref<ToastMessage | null>(null);

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
    const backgroundImage = ref<string | null>(null);
    const bgOpacity = ref(30);

    // Tools
    const currentTool = ref('select');
    const nextTableNumber = ref(1);

    // Polling
    let pollInterval: ReturnType<typeof setInterval> | null = null;

    // Computed
    const freeTablesCount = computed(() => {
        return objects.value.filter((o: any) => o.type === 'table' && getDisplayStatus(o) === 'free').length;
    });

    const occupiedTablesCount = computed(() => {
        return objects.value.filter((o: any) => o.type === 'table' && getDisplayStatus(o) !== 'free').length;
    });

    // Methods
    async function loadZones(preselectedZoneId: number | null = null) {
        try {
            const res = await http.get('/tables/zones');
            zones.value = extractData(res) || [];
            if (zones.value.length) {
                if (preselectedZoneId && zones.value.find((z: any) => z.id === preselectedZoneId)) {
                    selectedZoneId.value = preselectedZoneId;
                } else {
                    selectedZoneId.value = zones.value[0].id;
                }
            }
        } catch (e: unknown) {
            log.error('Failed to load zones:', (e as Error).message);
        }
    }

    async function loadLayout(zoneId: number) {
        try {
            loading.value = true;
            const res = await http.get(`/tables/floor-plan?zone_id=${zoneId}`);
            const data = extractData(res) || {} as Record<string, any>;

            const tables = (data as Record<string, any>).tables as Array<Record<string, any>> || [];
            objects.value = tables.map((t: any) => ({
                id: Date.now() + Math.random(),
                type: 'table',
                number: t.number as string,
                seats: t.seats as number,
                shape: (t.shape as string) || 'square',
                x: (t.position_x as number) ?? 100,
                y: (t.position_y as number) ?? 100,
                width: (t.width as number) ?? 80,
                height: (t.height as number) ?? 80,
                rotation: (t.rotation as number) ?? 0,
                minOrder: (t.min_order as number) ?? 0,
                surfaceStyle: (t.surface_style as string) || 'wood',
                chairStyle: (t.chair_style as string) || 'wood',
                status: (t.status as string) || 'free',
                dbId: t.id as number
            }));

            const layout = (data as Record<string, any>).layout as Record<string, any> || {};
            if (layout.objects && Array.isArray(layout.objects)) {
                const otherObjects: FloorObject[] = (layout.objects as Array<Record<string, any>>).map((o: any) => ({
                    id: Date.now() + Math.random(),
                    type: o.type as string,
                    x: (o.x as number) ?? 100,
                    y: (o.y as number) ?? 100,
                    width: (o.width as number) ?? 80,
                    height: (o.height as number) ?? 80,
                    rotation: (o.rotation as number) ?? 0,
                    text: (o.text as string) ?? null
                }));
                objects.value.push(...otherObjects);
            }

            if (layout.width) canvasWidth.value = layout.width as number;
            if (layout.height) canvasHeight.value = layout.height as number;
            if (layout.backgroundImage) backgroundImage.value = layout.backgroundImage as string;
            if (layout.bgOpacity !== undefined) bgOpacity.value = layout.bgOpacity as number;

            updateNextTableNumber();
            selectedObject.value = null;
        } catch (e: unknown) {
            log.error('Failed to load layout:', (e as Error).message);
            objects.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function saveLayout() {
        try {
            const tables = objects.value.filter((o: any) => o.type === 'table');
            const otherObjects = objects.value.filter((o: any) => o.type !== 'table');

            const tablesData = tables.map((table: any) => ({
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

            await http.post('/tables/layout', {
                zone_id: selectedZoneId.value,
                tables: tablesData,
                layout: {
                    width: canvasWidth.value,
                    height: canvasHeight.value,
                    objects: otherObjects.map((obj: any) => ({
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

            await loadLayout(selectedZoneId.value!);
            showToastMessage('План сохранён', 'success');
        } catch (e: unknown) {
            log.error('Failed to save layout:', (e as Error).message);
            showToastMessage((e as Record<string, Record<string, Record<string, string>>>).response?.data?.message || 'Ошибка сохранения', 'error');
        }
    }

    function addObject(type: string, shape: string | null = null, scrollX: number = 0, scrollY: number = 0) {
        const tableDefaults: Record<string, { width: number; height: number; seats: number }> = {
            square: { width: 80, height: 80, seats: 4 },
            round: { width: 80, height: 80, seats: 4 },
            rectangle: { width: 160, height: 70, seats: 6 },
            oval: { width: 120, height: 70, seats: 4 }
        };

        const defaults: Record<string, { width: number; height: number; seats?: number }> = {
            table: tableDefaults[shape || ''] || { width: 80, height: 80, seats: 4 },
            wall: { width: 200, height: 12 },
            column: { width: 30, height: 30 },
            bar: { width: 200, height: 50 },
            sofa: { width: 120, height: 50 },
            door: { width: 80, height: 40 },
            window: { width: 80, height: 12 },
            plant: { width: 40, height: 40 },
            label: { width: 100, height: 30 }
        };

        const def = defaults[type] || { width: 60, height: 60 };

        const obj: FloorObject = {
            id: Date.now() + Math.random(),
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
            obj.seats = def.seats || 4;
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
            http.delete(`/tables/${selectedObject.value.dbId}`).catch((e: Error) => log.error('Failed to delete table:', e.message));
        }

        objects.value = objects.value.filter((o: any) => o.id !== selectedObject.value!.id);
        selectedObject.value = null;
    }

    function duplicateSelected() {
        if (!selectedObject.value) return;

        const source = selectedObject.value;
        const offset = 40;

        const newObj: FloorObject = {
            id: Date.now(),
            type: source.type,
            x: source.x + offset,
            y: source.y + offset,
            width: source.width,
            height: source.height,
            rotation: source.rotation || 0
        };

        if (source.type === 'table') {
            newObj.shape = source.shape;
            newObj.number = String(nextTableNumber.value++);
            newObj.seats = source.seats;
            newObj.minOrder = source.minOrder || 0;
            newObj.surfaceStyle = source.surfaceStyle;
            newObj.chairStyle = source.chairStyle;
            newObj.status = 'free';
            newObj.totalAmount = 0;
            newObj.hasBillRequest = false;
            newObj.reservationTime = null;
        }

        if (source.type === 'label') {
            newObj.text = source.text;
        }

        objects.value.push(newObj);
        selectedObject.value = newObj;
        showToastMessage('Объект скопирован', 'success');
    }

    function updateNextTableNumber() {
        const tables = objects.value.filter((o: any) => o.type === 'table');
        const numbers = tables.map((t: any) => {
            const match = String(t.number).match(/\d+/);
            return match ? parseInt(match[0], 10) : 0;
        });
        nextTableNumber.value = numbers.length ? Math.max(...numbers) + 1 : 1;
    }

    function getDisplayStatus(obj: FloorObject): string {
        if (editMode.value) return 'free';
        return obj.status || 'free';
    }

    function showToastMessage(message: string, type: string = 'success') {
        toast.value = { message, type };
        setTimeout(() => { toast.value = null; }, 3000);
    }

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
            const res = await http.get(`/tables/floor-plan?zone_id=${selectedZoneId.value}`);
            const data = extractData(res) || {} as Record<string, any>;
            const tables = (data as Record<string, any>).tables as Array<Record<string, any>> || [];

            tables.forEach((apiTable: any) => {
                const localTable = objects.value.find((o: any) =>
                    o.type === 'table' && (o.dbId === apiTable.id || o.number === apiTable.number)
                );
                if (localTable) {
                    localTable.status = (apiTable.status as string) || 'free';
                    localTable.totalAmount = (apiTable.active_order as Record<string, any>)?.total as number || 0;
                    localTable.hasBillRequest = (apiTable.active_order as Record<string, any>)?.bill_requested as boolean || false;
                    localTable.reservationTime = (apiTable.next_reservation as Record<string, any>)?.time as string || null;
                }
            });
        } catch (e: unknown) {
            log.warn('Failed to refresh table statuses:', (e as Error).message);
        }
    }

    return {
        zones, selectedZoneId, objects, selectedObject, loading, toast,
        canvasWidth, canvasHeight, zoom, showGrid, snapToGrid, gridSize, showChairs, editMode,
        backgroundImage, bgOpacity, currentTool, nextTableNumber,
        freeTablesCount, occupiedTablesCount,
        loadZones, loadLayout, saveLayout, addObject, deleteSelected, duplicateSelected,
        getDisplayStatus, showToastMessage, startPolling, stopPolling, refreshTableStatuses
    };
});
