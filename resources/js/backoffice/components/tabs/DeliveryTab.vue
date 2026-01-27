<template>
    <div>
        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">
            <div class="flex border-b overflow-x-auto bg-gray-50">
                <button @click="activeTab = 'analytics'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 activeTab === 'analytics' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📊</span> Аналитика
                </button>
                <button @click="activeTab = 'zones'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 activeTab === 'zones' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📍</span> Зоны доставки
                </button>
                <button @click="activeTab = 'couriers'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 activeTab === 'couriers' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🚚</span> Курьеры
                </button>
                <button @click="activeTab = 'settings'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 activeTab === 'settings' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>⚙️</span> Настройки
                </button>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <!-- Period Selector -->
            <div class="flex items-center gap-4">
                <button v-for="p in ['today', 'week', 'month']" :key="p"
                        @click="analyticsPeriod = p; loadAnalytics()"
                        :class="['px-4 py-2 rounded-lg font-medium transition',
                                 analyticsPeriod === p ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100']">
                    {{ p === 'today' ? 'Сегодня' : p === 'week' ? 'Неделя' : 'Месяц' }}
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-3xl font-bold text-gray-900">{{ analytics.total_orders || 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">Заказов</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-3xl font-bold text-green-600">{{ formatMoney(analytics.total_revenue || 0) }}</div>
                    <div class="text-sm text-gray-500 mt-1">Выручка</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-3xl font-bold text-gray-900">{{ analytics.avg_delivery_time || 0 }} мин</div>
                    <div class="text-sm text-gray-500 mt-1">Среднее время</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-3xl font-bold text-blue-600">{{ analytics.on_time_percent || 0 }}%</div>
                    <div class="text-sm text-gray-500 mt-1">Вовремя</div>
                </div>
            </div>

            <!-- By Couriers -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">По курьерам</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Курьер</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Заказов</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Выручка</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="c in analytics.by_couriers" :key="c.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">{{ c.name }}</td>
                                <td class="px-6 py-4">{{ c.orders }}</td>
                                <td class="px-6 py-4">{{ formatMoney(c.revenue) }}</td>
                            </tr>
                            <tr v-if="!analytics.by_couriers?.length">
                                <td colspan="3" class="px-6 py-8 text-center text-gray-400">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- By Zones -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-semibold">По зонам</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Зона</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Заказов</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="z in analytics.by_zones" :key="z.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">{{ z.name }}</td>
                                <td class="px-6 py-4">{{ z.orders }}</td>
                            </tr>
                            <tr v-if="!analytics.by_zones?.length">
                                <td colspan="2" class="px-6 py-8 text-center text-gray-400">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Zones Tab -->
        <div v-if="activeTab === 'zones'" class="space-y-6">
            <!-- Map -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold">Карта зон доставки</h3>
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span> Ресторан
                    </div>
                </div>
                <div id="delivery-zones-map" class="h-[400px] bg-gray-100"></div>
            </div>

            <!-- Zones List -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Зоны доставки</h3>
                    <button @click="openZoneModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                        + Добавить зону
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div v-for="zone in zones" :key="zone.id"
                         class="flex items-center justify-between p-4 border rounded-xl hover:border-orange-300 transition cursor-pointer"
                         @click="highlightZoneOnMap(zone)">
                        <div class="flex items-center gap-4">
                            <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: zone.color }"></div>
                            <div>
                                <div class="font-medium">{{ zone.name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ zone.min_distance || 0 }}-{{ zone.max_distance || 0 }} км •
                                    {{ zone.delivery_fee > 0 ? formatMoney(zone.delivery_fee) : 'Бесплатно' }}
                                    <span v-if="zone.free_delivery_from"> (бесплатно от {{ formatMoney(zone.free_delivery_from) }})</span>
                                    <span v-if="zone.estimated_time"> • ~{{ zone.estimated_time }} мин</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="['px-2 py-1 text-xs font-medium rounded-full', zone.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                                {{ zone.is_active ? 'Активна' : 'Неактивна' }}
                            </span>
                            <button @click.stop="openZoneModal(zone)" class="text-gray-400 hover:text-orange-500">✏️</button>
                            <button @click.stop="deleteZone(zone.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                        </div>
                    </div>
                    <div v-if="!zones.length" class="text-center py-12 text-gray-400">
                        <div class="text-4xl mb-2">📍</div>
                        <p>Нет зон доставки</p>
                        <button @click="openZoneModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium mt-4 transition">
                            Создать первую зону
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Couriers Tab -->
        <div v-if="activeTab === 'couriers'" class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b">
                <h3 class="text-lg font-semibold">Курьеры</h3>
            </div>
            <div class="p-6 space-y-4">
                <div v-for="courier in couriers" :key="courier.id"
                     class="flex items-center justify-between p-4 border rounded-xl">
                    <div class="flex items-center gap-4">
                        <div :class="['w-12 h-12 rounded-full flex items-center justify-center text-white font-bold',
                                      courier.status === 'available' ? 'bg-green-500' :
                                      courier.status === 'busy' ? 'bg-yellow-500' : 'bg-gray-400']">
                            {{ courier.name?.charAt(0) || 'К' }}
                        </div>
                        <div>
                            <div class="font-medium">{{ courier.name }}</div>
                            <div class="text-sm text-gray-500">{{ courier.phone }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="text-center">
                            <div class="text-xl font-bold">{{ courier.current_orders || 0 }}</div>
                            <div class="text-xs text-gray-500">заказов</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-green-600">{{ formatMoney(courier.today_earnings || 0) }}</div>
                            <div class="text-xs text-gray-500">сегодня</div>
                        </div>
                        <span :class="['px-3 py-1 text-xs font-medium rounded-full',
                                       courier.status === 'available' ? 'bg-green-100 text-green-700' :
                                       courier.status === 'busy' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700']">
                            {{ courier.status === 'available' ? 'Свободен' : courier.status === 'busy' ? 'Занят' : 'Офлайн' }}
                        </span>
                    </div>
                </div>
                <div v-if="!couriers.length" class="text-center py-12 text-gray-400">
                    <div class="text-4xl mb-2">🚚</div>
                    <p>Нет курьеров</p>
                    <p class="text-sm mt-2">Добавьте сотрудника с ролью "Курьер" в разделе Персонал</p>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div v-if="activeTab === 'settings'" class="space-y-6">
            <!-- General Settings -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-6">Основные настройки</h3>
                <div class="max-w-2xl space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Минимальная сумма заказа</label>
                        <div class="flex items-center gap-2">
                            <input v-model.number="settings.min_order_amount" type="number"
                                   class="px-4 py-2 border rounded-lg w-40 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <span class="text-gray-500">₽</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Время приготовления по умолчанию</label>
                        <div class="flex items-center gap-2">
                            <input v-model.number="settings.default_prep_time" type="number"
                                   class="px-4 py-2 border rounded-lg w-40 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <span class="text-gray-500">минут</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Предупреждение о неназначенном заказе</label>
                        <div class="flex items-center gap-2">
                            <input v-model.number="settings.alert_unassigned_minutes" type="number"
                                   class="px-4 py-2 border rounded-lg w-40 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <span class="text-gray-500">минут</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="settings.allow_preorder" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                        <span class="text-sm text-gray-700">Разрешить предзаказы</span>
                    </div>
                    <div v-if="settings.allow_preorder">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Предзаказ на</label>
                        <div class="flex items-center gap-2">
                            <input v-model.number="settings.preorder_days" type="number"
                                   class="px-4 py-2 border rounded-lg w-40 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <span class="text-gray-500">дней вперёд</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-6">Уведомления</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 border rounded-xl">
                        <div>
                            <div class="font-medium">SMS клиенту при создании заказа</div>
                            <div class="text-sm text-gray-500">Отправлять подтверждение заказа</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="settings.sms_on_create" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 border rounded-xl">
                        <div>
                            <div class="font-medium">SMS клиенту когда курьер выехал</div>
                            <div class="text-sm text-gray-500">Уведомлять о начале доставки</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="settings.sms_on_courier" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 border rounded-xl">
                        <div>
                            <div class="font-medium">Push курьеру при новом заказе</div>
                            <div class="text-sm text-gray-500">Мгновенное уведомление о назначении</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="settings.push_courier" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button @click="saveSettings" class="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    Сохранить настройки
                </button>
            </div>
        </div>

        <!-- Zone Modal (Fullscreen) -->
        <Teleport to="body">
            <div v-if="showZoneModal" class="fixed inset-0 bg-white z-50 flex flex-col">
                <div class="p-4 border-b flex items-center justify-between shrink-0 bg-white">
                    <h3 class="text-lg font-semibold">{{ zoneForm.id ? 'Редактировать зону' : 'Новая зона доставки' }}</h3>
                    <button @click="closeZoneModal" class="text-gray-400 hover:text-gray-600 text-2xl w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100">✕</button>
                </div>
                <div class="flex-1 overflow-hidden">
                    <div class="flex flex-col lg:flex-row h-full">
                        <!-- Left: Settings -->
                        <div class="p-5 space-y-3 lg:w-[340px] shrink-0 lg:border-r overflow-y-auto">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                                <input v-model="zoneForm.name" type="text" placeholder="Центр города"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>

                            <!-- Zone Type Toggle -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Тип зоны</label>
                                <div class="flex gap-1">
                                    <button @click="zoneForm.usePolygon = false" type="button"
                                            :class="['flex-1 px-3 py-1.5 rounded-lg text-sm font-medium transition',
                                                     !zoneForm.usePolygon ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                                        По радиусу
                                    </button>
                                    <button @click="zoneForm.usePolygon = true" type="button"
                                            :class="['flex-1 px-3 py-1.5 rounded-lg text-sm font-medium transition',
                                                     zoneForm.usePolygon ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                                        Полигон
                                    </button>
                                </div>
                            </div>

                            <!-- Distance (for radius mode) -->
                            <div v-if="!zoneForm.usePolygon" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">От (км)</label>
                                    <input v-model.number="zoneForm.min_distance" type="number" min="0" step="0.1"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">До (км)</label>
                                    <input v-model.number="zoneForm.max_distance" type="number" min="0" step="0.1"
                                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                </div>
                            </div>

                            <!-- Polygon info -->
                            <div v-if="zoneForm.usePolygon && zoneForm.polygon && zoneForm.polygon.length >= 3" class="p-2 bg-green-50 rounded-lg">
                                <p class="text-sm text-green-700 text-center">
                                    Полигон: {{ zoneForm.polygon.length }} точек
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Доставка</label>
                                    <div class="flex items-center gap-1">
                                        <input v-model.number="zoneForm.delivery_fee" type="number" min="0"
                                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                        <span class="text-gray-500 text-sm">₽</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Бесплатно от</label>
                                    <div class="flex items-center gap-1">
                                        <input v-model.number="zoneForm.free_delivery_from" type="number" min="0" placeholder="0"
                                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                        <span class="text-gray-500 text-sm">₽</span>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Время</label>
                                    <div class="flex items-center gap-1">
                                        <input v-model.number="zoneForm.estimated_time" type="number" min="0" step="5" placeholder="45"
                                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                        <span class="text-gray-500 text-sm">мин</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Цвет</label>
                                    <input v-model="zoneForm.color" type="color" class="w-full h-[38px] rounded-lg border cursor-pointer">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <input type="checkbox" v-model="zoneForm.is_active" id="zone-active" class="w-4 h-4 text-orange-500 rounded">
                                <label for="zone-active" class="text-sm">Активна</label>
                            </div>
                        </div>

                        <!-- Right: Map -->
                        <div class="p-4 bg-gray-50 flex flex-col flex-1 overflow-hidden">
                            <div class="flex items-center justify-between mb-3 shrink-0">
                                <span class="text-sm font-medium text-gray-700">Карта зоны</span>
                                <div v-if="zoneForm.usePolygon" class="flex gap-2">
                                    <button @click="startDrawingPolygon" type="button"
                                            :class="['px-4 py-2 text-sm font-medium rounded-lg transition',
                                                     isDrawingPolygon ? 'bg-green-500 text-white hover:bg-green-600' : 'bg-blue-500 text-white hover:bg-blue-600']">
                                        {{ isDrawingPolygon ? 'Завершить' : (zoneForm.polygon?.length >= 3 ? 'Перерисовать' : 'Нарисовать зону') }}
                                    </button>
                                    <button v-if="zoneForm.polygon && zoneForm.polygon.length > 0"
                                            @click="clearPolygon" type="button"
                                            class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition">
                                        Очистить
                                    </button>
                                </div>
                            </div>
                            <div id="zone-editor-map" class="flex-1 rounded-lg overflow-hidden border bg-gray-200"></div>
                            <div v-if="zoneForm.usePolygon" class="text-xs text-gray-500 mt-2 text-center shrink-0">
                                <span v-if="isDrawingPolygon">Кликайте по карте для добавления точек. Кликните на первую точку чтобы замкнуть зону.</span>
                                <span v-else-if="!zoneForm.polygon || zoneForm.polygon.length < 3">Нажмите "Нарисовать зону" и обведите нужную область на карте.</span>
                                <span v-else>Перетаскивайте точки для изменения формы. Кликните на ребро чтобы добавить точку.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t flex gap-3 shrink-0 bg-white">
                    <button @click="closeZoneModal"
                            class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                        Отмена
                    </button>
                    <button @click="saveZone"
                            :disabled="!zoneForm.name || (zoneForm.usePolygon && (!zoneForm.polygon || zoneForm.polygon.length < 3))"
                            class="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                        Сохранить зону
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick, onUnmounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

// State
const activeTab = ref('analytics');
const analyticsPeriod = ref('week');

const zones = ref([]);
const couriers = ref([]);
const analytics = ref({ total_orders: 0, total_revenue: 0, avg_delivery_time: 0, on_time_percent: 0, by_couriers: [], by_zones: [] });
const settings = ref({
    min_order_amount: 500,
    default_prep_time: 30,
    alert_unassigned_minutes: 10,
    allow_preorder: true,
    preorder_days: 7,
    sms_on_create: true,
    sms_on_courier: true,
    push_courier: true
});

// Modals
const showZoneModal = ref(false);
const zoneForm = ref({
    id: null, name: '', min_distance: 0, max_distance: 5,
    delivery_fee: 0, free_delivery_from: 0, estimated_time: 45,
    color: '#3b82f6', is_active: true, polygon: null, usePolygon: false
});
const isDrawingPolygon = ref(false);

// Methods
function formatMoney(val) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

async function loadDelivery() {
    try {
        const [zonesRes, couriersRes, settingsRes] = await Promise.all([
            store.api('/backoffice/delivery/zones'),
            store.api('/backoffice/delivery/couriers'),
            store.api('/backoffice/delivery/settings')
        ]);

        zones.value = zonesRes.data || zonesRes || [];
        couriers.value = couriersRes.data || couriersRes || [];
        if (settingsRes.data || settingsRes) settings.value = { ...settings.value, ...(settingsRes.data || settingsRes) };

        loadAnalytics();
    } catch (e) {
        console.error('Failed to load delivery:', e);
        loadMockData();
    }
}

async function loadAnalytics() {
    try {
        const res = await store.api(`/backoffice/delivery/analytics?period=${analyticsPeriod.value}`);
        analytics.value = res.data || res || analytics.value;
    } catch (e) {
        console.error('Failed to load analytics:', e);
    }
}

function loadMockData() {
    zones.value = [
        { id: 1, name: 'Центр города', min_distance: 0, max_distance: 3, delivery_fee: 0, free_delivery_from: 0, color: '#22c55e', is_active: true },
        { id: 2, name: 'Средняя зона', min_distance: 3, max_distance: 7, delivery_fee: 150, free_delivery_from: 1500, color: '#3b82f6', is_active: true },
        { id: 3, name: 'Дальняя зона', min_distance: 7, max_distance: 15, delivery_fee: 300, free_delivery_from: 2500, color: '#f59e0b', is_active: true }
    ];

    couriers.value = [
        { id: 1, name: 'Алексей Курьеров', phone: '+7 999 111-22-33', status: 'available', current_orders: 0, today_earnings: 2500 },
        { id: 2, name: 'Дмитрий Быстров', phone: '+7 999 222-33-44', status: 'busy', current_orders: 2, today_earnings: 3800 },
        { id: 3, name: 'Сергей Скоров', phone: '+7 999 333-44-55', status: 'offline', current_orders: 0, today_earnings: 1200 }
    ];

    analytics.value = {
        total_orders: 156,
        total_revenue: 245800,
        avg_delivery_time: 42,
        on_time_percent: 87,
        by_couriers: [
            { id: 1, name: 'Алексей Курьеров', orders: 52, revenue: 82500 },
            { id: 2, name: 'Дмитрий Быстров', orders: 68, revenue: 105200 },
            { id: 3, name: 'Сергей Скоров', orders: 36, revenue: 58100 }
        ],
        by_zones: [
            { id: 1, name: 'Центр города', orders: 89 },
            { id: 2, name: 'Средняя зона', orders: 52 },
            { id: 3, name: 'Дальняя зона', orders: 15 }
        ]
    };
}

function openZoneModal(zone = null) {
    if (zone) {
        const hasPolygon = zone.polygon && Array.isArray(zone.polygon) && zone.polygon.length >= 3;
        zoneForm.value = { ...zone, usePolygon: hasPolygon };
    } else {
        zoneForm.value = {
            id: null, name: '', min_distance: 0, max_distance: 5,
            delivery_fee: 0, free_delivery_from: 0, estimated_time: 45,
            color: '#3b82f6', is_active: true, polygon: null, usePolygon: false
        };
    }
    isDrawingPolygon.value = false;
    showZoneModal.value = true;

    // Инициализируем карту редактора после открытия модалки
    nextTick(() => {
        setTimeout(() => initEditorMap(), 100);
    });
}

function closeZoneModal() {
    showZoneModal.value = false;
    isDrawingPolygon.value = false;
    destroyEditorMap();
}

async function saveZone() {
    try {
        // Подготавливаем данные для отправки
        const data = { ...zoneForm.value };

        // Если не используем полигон - очищаем его
        if (!data.usePolygon) {
            data.polygon = null;
        }
        // Удаляем служебное поле
        delete data.usePolygon;

        if (data.id) {
            await store.api(`/backoffice/delivery/zones/${data.id}`, {
                method: 'PUT', body: JSON.stringify(data)
            });
        } else {
            await store.api('/backoffice/delivery/zones', {
                method: 'POST', body: JSON.stringify(data)
            });
        }
        closeZoneModal();
        store.showToast('Зона сохранена', 'success');
        loadDelivery();
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteZone(id) {
    if (!confirm('Удалить зону?')) return;
    try {
        await store.api(`/backoffice/delivery/zones/${id}`, { method: 'DELETE' });
        zones.value = zones.value.filter(z => z.id !== id);
        store.showToast('Зона удалена', 'success');
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function saveSettings() {
    try {
        await store.api('/backoffice/delivery/settings', {
            method: 'PUT', body: JSON.stringify(settings.value)
        });
        store.showToast('Настройки сохранены', 'success');
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

// ========== КАРТА ЯНДЕКС ==========
let map = null;
let mapCircles = [];
let mapPolygons = [];
const restaurantCoords = ref([55.7558, 37.6173]); // Default Moscow, will be loaded from config
const yandexApiKey = ref('');

// Загрузка координат ресторана и API ключа
async function loadMapConfig() {
    try {
        const res = await store.api('/delivery/map-data');
        const data = res?.data || res;
        if (data?.restaurant?.lat && data?.restaurant?.lng) {
            restaurantCoords.value = [data.restaurant.lat, data.restaurant.lng];
        }
        if (data?.yandex_api_key) {
            yandexApiKey.value = data.yandex_api_key;
        }
    } catch (e) {
        console.warn('Using default map config');
    }
}

// Инициализация карты
function initMap() {
    if (map || !window.ymaps) return;

    window.ymaps.ready(() => {
        const mapContainer = document.getElementById('delivery-zones-map');
        if (!mapContainer) return;

        map = new window.ymaps.Map('delivery-zones-map', {
            center: restaurantCoords.value,
            zoom: 11,
            controls: ['zoomControl', 'fullscreenControl']
        });

        // Метка ресторана
        const restaurantPlacemark = new window.ymaps.Placemark(restaurantCoords.value, {
            hintContent: 'Ресторан'
        }, {
            preset: 'islands#redFoodIcon'
        });
        map.geoObjects.add(restaurantPlacemark);

        // Отрисовка зон
        renderZonesOnMap();
    });
}

// Отрисовка зон на карте
function renderZonesOnMap() {
    if (!map) return;

    // Удаляем старые объекты
    mapCircles.forEach(c => map.geoObjects.remove(c));
    mapPolygons.forEach(p => map.geoObjects.remove(p));
    mapCircles = [];
    mapPolygons = [];

    zones.value.forEach(zone => {
        if (!zone.is_active) return;

        // Если есть полигон - рисуем его
        if (zone.polygon && Array.isArray(zone.polygon) && zone.polygon.length > 2) {
            const coords = zone.polygon.map(p => [p.lat || p[0], p.lng || p[1]]);
            const polygon = new window.ymaps.Polygon([coords], {
                hintContent: zone.name,
                balloonContent: `${zone.name}<br>Доставка: ${zone.delivery_fee > 0 ? zone.delivery_fee + '₽' : 'Бесплатно'}`
            }, {
                fillColor: zone.color + '40',
                strokeColor: zone.color,
                strokeWidth: 2,
                strokeOpacity: 0.8
            });
            map.geoObjects.add(polygon);
            mapPolygons.push(polygon);
        } else {
            // Иначе рисуем круги по расстоянию
            if (zone.max_distance > 0) {
                const outerCircle = new window.ymaps.Circle([restaurantCoords.value, zone.max_distance * 1000], {
                    hintContent: zone.name,
                    balloonContent: `${zone.name}<br>Доставка: ${zone.delivery_fee > 0 ? zone.delivery_fee + '₽' : 'Бесплатно'}`
                }, {
                    fillColor: zone.color + '30',
                    strokeColor: zone.color,
                    strokeWidth: 2,
                    strokeOpacity: 0.8
                });
                map.geoObjects.add(outerCircle);
                mapCircles.push(outerCircle);
            }
        }
    });
}

// Подсветка зоны при клике в списке
function highlightZoneOnMap(zone) {
    if (!map) return;

    // Центрируем карту на зоне
    if (zone.polygon && zone.polygon.length > 0) {
        const firstPoint = zone.polygon[0];
        map.setCenter([firstPoint.lat || firstPoint[0], firstPoint.lng || firstPoint[1]], 12);
    } else if (zone.max_distance) {
        map.setCenter(restaurantCoords.value, Math.max(10, 14 - Math.floor(zone.max_distance / 2)));
    }
}

// Уничтожение карты
function destroyMap() {
    if (map) {
        map.destroy();
        map = null;
        mapCircles = [];
        mapPolygons = [];
    }
}

// ========== РЕДАКТОР ЗОНЫ (МОДАЛКА) ==========
let editorMap = null;
let editorPolygon = null;

function initEditorMap() {
    if (!window.ymaps) {
        console.warn('Yandex Maps API not loaded yet');
        return;
    }

    window.ymaps.ready(() => {
        const container = document.getElementById('zone-editor-map');
        if (!container || editorMap) return;

        editorMap = new window.ymaps.Map('zone-editor-map', {
            center: restaurantCoords.value,
            zoom: 12,
            controls: ['zoomControl']
        });

        // Метка ресторана
        const restaurantMark = new window.ymaps.Placemark(restaurantCoords.value, {
            hintContent: 'Ресторан'
        }, {
            preset: 'islands#redFoodIcon'
        });
        editorMap.geoObjects.add(restaurantMark);

        // Отрисовка существующего полигона или круга
        renderEditorZone();
    });
}

function renderEditorZone() {
    if (!editorMap) return;

    // Удаляем старый полигон
    if (editorPolygon) {
        editorMap.geoObjects.remove(editorPolygon);
        editorPolygon = null;
    }

    const form = zoneForm.value;
    const color = form.color || '#3b82f6';

    if (form.usePolygon && form.polygon && form.polygon.length >= 3) {
        // Рисуем полигон
        const coords = form.polygon.map(p => [p.lat || p[0], p.lng || p[1]]);
        editorPolygon = new window.ymaps.Polygon([coords], {
            hintContent: form.name || 'Новая зона'
        }, {
            fillColor: color + '40',
            strokeColor: color,
            strokeWidth: 3,
            editorDrawingCursor: 'crosshair',
            editorMaxPoints: 50
        });
        editorMap.geoObjects.add(editorPolygon);

        // Включаем редактирование полигона
        editorPolygon.editor.startEditing();
        editorPolygon.editor.events.add('geometrychange', () => {
            savePolygonCoords();
        });

        // Центрируем на полигоне
        editorMap.setBounds(editorPolygon.geometry.getBounds(), { checkZoomRange: true, zoomMargin: 50 });
    } else if (!form.usePolygon && form.max_distance > 0) {
        // Рисуем круг по радиусу
        editorPolygon = new window.ymaps.Circle([restaurantCoords.value, form.max_distance * 1000], {
            hintContent: `${form.name || 'Зона'}: ${form.max_distance} км`
        }, {
            fillColor: color + '30',
            strokeColor: color,
            strokeWidth: 2
        });
        editorMap.geoObjects.add(editorPolygon);
    }
}

function startDrawingPolygon() {
    if (!editorMap || !window.ymaps) return;

    if (isDrawingPolygon.value) {
        // Завершаем рисование
        finishDrawing();
        return;
    }

    // Начинаем рисование
    isDrawingPolygon.value = true;

    // Очищаем старый полигон
    if (editorPolygon) {
        editorMap.geoObjects.remove(editorPolygon);
        editorPolygon = null;
    }

    // Создаём новый полигон с редактором
    const color = zoneForm.value.color || '#3b82f6';
    editorPolygon = new window.ymaps.Polygon([], {}, {
        fillColor: color + '40',
        strokeColor: color,
        strokeWidth: 3,
        editorDrawingCursor: 'crosshair',
        editorMaxPoints: 100
    });

    editorMap.geoObjects.add(editorPolygon);

    // Запускаем режим рисования
    editorPolygon.editor.startDrawing();

    // Слушаем завершение рисования (когда замкнули полигон)
    editorPolygon.editor.events.add('drawingstop', () => {
        finishDrawing();
    });
}

function finishDrawing() {
    if (!editorPolygon) return;

    // Останавливаем рисование
    editorPolygon.editor.stopDrawing();
    isDrawingPolygon.value = false;

    // Проверяем что есть минимум 3 точки
    const coords = editorPolygon.geometry.getCoordinates()[0];
    if (!coords || coords.length < 3) {
        store.showToast('Нужно минимум 3 точки для зоны', 'warning');
        editorMap.geoObjects.remove(editorPolygon);
        editorPolygon = null;
        return;
    }

    // Сохраняем координаты
    savePolygonCoords();

    // Переключаемся в режим редактирования
    editorPolygon.editor.startEditing();
    editorPolygon.editor.events.add('geometrychange', savePolygonCoords);

    store.showToast('Зона создана! Перетаскивайте точки для редактирования.', 'success');
}

function savePolygonCoords() {
    if (!editorPolygon) return;

    const coords = editorPolygon.geometry.getCoordinates()[0];
    if (coords && coords.length >= 3) {
        // Убираем последнюю точку если она дублирует первую (замыкание)
        let points = coords;
        if (coords.length > 3) {
            const first = coords[0];
            const last = coords[coords.length - 1];
            if (first[0] === last[0] && first[1] === last[1]) {
                points = coords.slice(0, -1);
            }
        }
        zoneForm.value.polygon = points.map(p => ({ lat: p[0], lng: p[1] }));
    }
}

function clearPolygon() {
    zoneForm.value.polygon = null;
    isDrawingPolygon.value = false;

    if (editorPolygon) {
        editorPolygon.editor.stopDrawing();
        editorPolygon.editor.stopEditing();
        editorMap.geoObjects.remove(editorPolygon);
        editorPolygon = null;
    }
}

function destroyEditorMap() {
    if (editorPolygon) {
        editorPolygon.editor.stopDrawing();
        editorPolygon.editor.stopEditing();
    }
    if (editorMap) {
        editorMap.destroy();
        editorMap = null;
        editorPolygon = null;
    }
    isDrawingPolygon.value = false;
}

// Watch для обновления карты редактора при смене типа зоны
watch(() => zoneForm.value.usePolygon, () => {
    if (showZoneModal.value && editorMap) {
        renderEditorZone();
    }
});

watch(() => zoneForm.value.max_distance, () => {
    if (showZoneModal.value && editorMap && !zoneForm.value.usePolygon) {
        renderEditorZone();
    }
});

watch(() => zoneForm.value.color, () => {
    if (showZoneModal.value && editorMap) {
        renderEditorZone();
    }
});

// Watch для инициализации карты при переходе на вкладку
watch(activeTab, async (newTab) => {
    if (newTab === 'zones') {
        await nextTick();
        // Загружаем Yandex Maps API если ещё не загружен
        if (!window.ymaps) {
            const apiKey = yandexApiKey.value || '962fae0f-1a48-4549-8a44-08430baddf41';
            const script = document.createElement('script');
            script.src = `https://api-maps.yandex.ru/2.1/?apikey=${apiKey}&lang=ru_RU`;
            script.onload = () => initMap();
            document.head.appendChild(script);
        } else {
            initMap();
        }
    }
});

// Watch для обновления карты при изменении зон
watch(zones, () => {
    if (activeTab.value === 'zones') {
        renderZonesOnMap();
    }
}, { deep: true });

// Init
onMounted(async () => {
    await loadMapConfig();
    loadDelivery();
});

onUnmounted(() => {
    destroyMap();
});
</script>
