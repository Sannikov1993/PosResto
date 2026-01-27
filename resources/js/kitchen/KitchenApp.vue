<template>
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Loading State -->
        <div v-if="deviceStatus === 'loading'" class="min-h-screen flex items-center justify-center">
            <div class="text-center">
                <div class="animate-spin text-6xl mb-4">⏳</div>
                <p class="text-xl text-gray-400">Подключение...</p>
            </div>
        </div>

        <!-- Pending Configuration State -->
        <div v-else-if="deviceStatus === 'pending'" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800">
            <div class="text-center max-w-lg">
                <div class="text-8xl mb-6">📱</div>
                <h1 class="text-3xl font-bold mb-4">Устройство зарегистрировано</h1>
                <p class="text-xl text-gray-400 mb-8">Ожидает настройки в админке</p>
                <div class="bg-gray-800 rounded-2xl p-6 mb-6">
                    <p class="text-sm text-gray-500 mb-2">ID устройства:</p>
                    <p class="font-mono text-lg text-blue-400 break-all">{{ deviceId }}</p>
                </div>
                <div class="bg-yellow-500/20 rounded-xl p-4 text-yellow-300 text-sm">
                    <p class="font-medium mb-2">Для настройки:</p>
                    <ol class="text-left list-decimal list-inside space-y-1">
                        <li>Откройте Бэк-офис → Настройки → Устройства кухни</li>
                        <li>Найдите это устройство в списке</li>
                        <li>Назначьте ему цех (станцию)</li>
                    </ol>
                </div>
                <button
                    @click="checkDeviceStatus"
                    class="mt-6 px-6 py-3 bg-blue-600 hover:bg-blue-500 rounded-xl font-medium transition"
                >
                    Проверить статус
                </button>
            </div>
        </div>

        <!-- Disabled State -->
        <div v-else-if="deviceStatus === 'disabled'" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-red-900/30">
            <div class="text-center max-w-lg">
                <div class="text-8xl mb-6">🚫</div>
                <h1 class="text-3xl font-bold mb-4 text-red-400">Устройство отключено</h1>
                <p class="text-xl text-gray-400 mb-8">Обратитесь к администратору</p>
                <div class="bg-gray-800 rounded-2xl p-6">
                    <p class="text-sm text-gray-500 mb-2">ID устройства:</p>
                    <p class="font-mono text-lg text-gray-400 break-all">{{ deviceId }}</p>
                </div>
                <button
                    @click="checkDeviceStatus"
                    class="mt-6 px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-xl font-medium transition"
                >
                    Повторить попытку
                </button>
            </div>
        </div>

        <!-- Not Registered State -->
        <div v-else-if="deviceStatus === 'not_registered'" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800">
            <div class="text-center max-w-lg">
                <div class="text-8xl mb-6">❓</div>
                <h1 class="text-3xl font-bold mb-4">Устройство не найдено</h1>
                <p class="text-xl text-gray-400 mb-8">Устройство было удалено из системы</p>
                <button
                    @click="registerDevice"
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-500 rounded-xl font-medium transition"
                >
                    Зарегистрировать заново
                </button>
            </div>
        </div>

        <!-- Configured - Main Kitchen Display -->
        <template v-else-if="deviceStatus === 'configured'">
        <!-- Header -->
        <header :class="['bg-gray-800 px-6 flex items-center justify-between sticky top-0 z-50 shadow-lg transition-all', focusMode ? 'py-2' : 'py-4']">
            <div class="flex items-center gap-4">
                <img v-if="!focusMode" src="/images/logo/posresto_logo_dark_bg.svg" alt="PosResto" class="h-10" />
                <div v-if="!focusMode" class="w-px h-8 bg-gray-600"></div>
                <h1 :class="['font-bold flex items-center gap-2', focusMode ? 'text-xl' : 'text-2xl']">
                    <span :class="focusMode ? 'text-2xl' : 'text-3xl'">{{ currentStation?.icon || '🍳' }}</span>
                    <span>{{ currentStation?.name || 'Кухня' }}</span>
                    <span v-if="stationSlug && !focusMode" class="text-sm font-normal text-gray-400 ml-2">({{ stationSlug }})</span>
                </h1>
            </div>
            <div class="flex items-center gap-6">
                <!-- Date Selector (hidden in focus mode) -->
                <div v-if="!focusMode" class="relative">
                    <div class="flex items-center gap-1 bg-gray-700/50 rounded-xl p-1">
                        <button
                            @click="goToPrevDay"
                            :disabled="isSelectedDateToday"
                            :class="[
                                'p-2 rounded-lg transition',
                                isSelectedDateToday ? 'text-gray-600 cursor-not-allowed' : 'hover:bg-gray-600 text-gray-400 hover:text-white'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button
                            @click="toggleCalendarPicker"
                            class="px-4 py-2 rounded-lg hover:bg-gray-600 text-white font-medium transition flex items-center gap-2"
                        >
                            <span>📅</span>
                            <span>{{ displaySelectedDate }}</span>
                        </button>
                        <button
                            @click="goToNextDay"
                            class="p-2 rounded-lg hover:bg-gray-600 text-gray-400 hover:text-white transition"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Calendar Dropdown -->
                    <div v-if="showCalendarPicker" class="absolute top-full left-0 mt-2 z-50">
                    <div class="bg-gray-800 rounded-xl shadow-2xl border border-gray-700 p-4 w-72">
                        <!-- Calendar Header -->
                        <div class="flex items-center justify-between mb-3">
                            <button @click="calendarPrevMonth" class="p-1 hover:bg-gray-700 rounded">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <span class="font-medium">{{ calendarMonthYear }}</span>
                            <button @click="calendarNextMonth" class="p-1 hover:bg-gray-700 rounded">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        <!-- Weekdays -->
                        <div class="grid grid-cols-7 gap-1 mb-2 text-center text-xs text-gray-500">
                            <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
                        </div>
                        <!-- Days -->
                        <div class="grid grid-cols-7 gap-1">
                            <button
                                v-for="day in calendarDays"
                                :key="day.date || day.day"
                                @click="day.date && !day.isPast && selectCalendarDate(day.date)"
                                :disabled="!day.date || day.isPast"
                                :class="[
                                    'h-8 w-8 rounded-lg text-sm transition relative',
                                    !day.date ? 'text-gray-700 cursor-default' :
                                    day.isPast ? 'text-gray-600 cursor-not-allowed' :
                                    day.isSelected ? 'bg-accent text-white' :
                                    day.isToday ? 'bg-gray-700 text-accent' :
                                    'hover:bg-gray-700 text-gray-300'
                                ]"
                            >
                                {{ day.day }}
                                <span
                                    v-if="day.count > 0 && !day.isPast"
                                    class="absolute -top-1 -right-1 min-w-4 h-4 flex items-center justify-center text-[10px] font-bold bg-orange-500 text-white rounded-full px-1"
                                >
                                    {{ day.count > 99 ? '99+' : day.count }}
                                </span>
                            </button>
                        </div>
                        <!-- Quick buttons -->
                        <div class="flex gap-2 mt-3 pt-3 border-t border-gray-700">
                            <button
                                @click="selectToday"
                                class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-700 hover:bg-gray-600 transition"
                            >
                                Сегодня
                            </button>
                            <button
                                @click="selectTomorrow"
                                class="flex-1 py-2 rounded-lg text-sm font-medium bg-gray-700 hover:bg-gray-600 transition"
                            >
                                Завтра
                            </button>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Stats (compact in focus mode) -->
                <div :class="['flex', focusMode ? 'gap-2' : 'gap-4']">
                    <div :class="['bg-blue-500/20 rounded-xl flex items-center gap-2', focusMode ? 'px-3 py-1' : 'px-4 py-2']">
                        <span :class="focusMode ? 'text-lg' : 'text-2xl'">📥</span>
                        <span :class="['font-bold text-blue-400', focusMode ? 'text-xl' : 'text-2xl']">{{ totalNewOrders }}</span>
                    </div>
                    <div :class="['bg-orange-500/20 rounded-xl flex items-center gap-2', focusMode ? 'px-3 py-1' : 'px-4 py-2']">
                        <span :class="focusMode ? 'text-lg' : 'text-2xl'">🔥</span>
                        <span :class="['font-bold text-orange-400', focusMode ? 'text-xl' : 'text-2xl']">{{ cookingOrders.length }}</span>
                    </div>
                    <div :class="['bg-green-500/20 rounded-xl flex items-center gap-2', focusMode ? 'px-3 py-1' : 'px-4 py-2']">
                        <span :class="focusMode ? 'text-lg' : 'text-2xl'">✅</span>
                        <span :class="['font-bold text-green-400', focusMode ? 'text-xl' : 'text-2xl']">{{ readyOrders.length }}</span>
                    </div>
                </div>
                <!-- Time (compact in focus mode) -->
                <div class="text-right">
                    <p :class="['font-bold', focusMode ? 'text-2xl' : 'text-3xl']">{{ currentTime }}</p>
                    <p v-if="!focusMode" class="text-sm text-gray-400">{{ currentDate }}</p>
                </div>
                <!-- Stop List Indicator (hidden in focus mode) -->
                <div v-if="!focusMode" class="relative">
                    <button
                        @click="showStopListDropdown = !showStopListDropdown"
                        :class="[
                            'p-3 rounded-xl text-2xl transition relative',
                            stopList.length > 0 ? 'bg-red-500/20 text-red-400' : 'bg-gray-700 text-gray-500'
                        ]"
                    >
                        🚫
                        <span
                            v-if="stopList.length > 0"
                            class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center"
                        >
                            {{ stopList.length }}
                        </span>
                    </button>
                    <!-- Dropdown -->
                    <div
                        v-if="showStopListDropdown"
                        class="absolute right-0 top-full mt-2 w-80 bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden z-50"
                    >
                        <div class="px-4 py-3 bg-red-500/20 border-b border-gray-700 flex items-center justify-between">
                            <span class="font-bold text-red-400">🚫 Стоп-лист</span>
                            <span class="text-sm text-gray-400">{{ stopList.length }} поз.</span>
                        </div>
                        <div v-if="stopList.length === 0" class="p-4 text-center text-gray-500">
                            <p class="text-2xl mb-2">✨</p>
                            <p>Все блюда доступны</p>
                        </div>
                        <div v-else class="max-h-80 overflow-y-auto divide-y divide-gray-700/50">
                            <div
                                v-for="item in stopList"
                                :key="item.id"
                                class="px-4 py-3 hover:bg-gray-700/50"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-gray-700 overflow-hidden flex-shrink-0">
                                        <img v-if="item.dish?.image" :src="item.dish.image" :alt="item.dish?.name" class="w-full h-full object-cover opacity-50" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-lg">🍽️</div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-white truncate">{{ item.dish?.name }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ item.reason }}</p>
                                    </div>
                                </div>
                                <div v-if="item.resume_at" class="mt-1 text-xs text-yellow-400 pl-13">
                                    ⏰ До {{ formatStopListTime(item.resume_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Single column mode toggle -->
                <button @click="singleColumnMode = !singleColumnMode"
                        :class="['p-3 rounded-xl text-2xl transition', singleColumnMode ? 'bg-cyan-500/20 text-cyan-400' : 'bg-gray-700 text-gray-500']"
                        :title="singleColumnMode ? 'Одна колонка' : 'Три колонки'">
                    {{ singleColumnMode ? '📱' : '🖥️' }}
                </button>
                <!-- Compact mode toggle -->
                <button @click="compactMode = !compactMode"
                        :class="['p-3 rounded-xl text-2xl transition', compactMode ? 'bg-purple-500/20 text-purple-400' : 'bg-gray-700 text-gray-500']"
                        :title="compactMode ? 'Компактный вид' : 'Полный вид'">
                    {{ compactMode ? '📋' : '📄' }}
                </button>
                <!-- Focus mode toggle -->
                <button @click="focusMode = !focusMode"
                        :class="['p-3 rounded-xl text-2xl transition', focusMode ? 'bg-orange-500/20 text-orange-400' : 'bg-gray-700 text-gray-500']"
                        :title="focusMode ? 'Режим фокуса' : 'Обычный режим'">
                    {{ focusMode ? '🎯' : '👁️' }}
                </button>
                <!-- Sound toggle -->
                <button @click="soundEnabled = !soundEnabled"
                        :class="['p-3 rounded-xl text-2xl transition', soundEnabled ? 'bg-green-500/20 text-green-400' : 'bg-gray-700 text-gray-500']">
                    {{ soundEnabled ? '🔔' : '🔕' }}
                </button>
                <!-- Fullscreen -->
                <button @click="toggleFullscreen" class="p-3 rounded-xl bg-gray-700 text-2xl hover:bg-gray-600 transition">
                    ⛶
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main :class="focusMode ? 'p-3' : 'p-6'">
            <!-- Single Column Mode Tabs -->
            <div v-if="singleColumnMode" class="flex gap-2 mb-4">
                <button @click="activeColumn = 'new'"
                        :class="['flex-1 py-3 rounded-xl text-xl font-bold transition flex items-center justify-center gap-2',
                                 activeColumn === 'new' ? 'bg-blue-500 text-white' : 'bg-gray-700 text-gray-400']">
                    📥 Новые
                    <span :class="['px-2 py-0.5 rounded-full text-sm', activeColumn === 'new' ? 'bg-white text-blue-500' : 'bg-gray-600']">
                        {{ totalNewOrders }}
                    </span>
                </button>
                <button @click="activeColumn = 'cooking'"
                        :class="['flex-1 py-3 rounded-xl text-xl font-bold transition flex items-center justify-center gap-2',
                                 activeColumn === 'cooking' ? 'bg-orange-500 text-white' : 'bg-gray-700 text-gray-400']">
                    🔥 Готовятся
                    <span :class="['px-2 py-0.5 rounded-full text-sm', activeColumn === 'cooking' ? 'bg-white text-orange-500' : 'bg-gray-600']">
                        {{ cookingOrders.length }}
                    </span>
                </button>
                <button @click="activeColumn = 'ready'"
                        :class="['flex-1 py-3 rounded-xl text-xl font-bold transition flex items-center justify-center gap-2',
                                 activeColumn === 'ready' ? 'bg-green-500 text-white' : 'bg-gray-700 text-gray-400']">
                    ✅ Готовы
                    <span :class="['px-2 py-0.5 rounded-full text-sm', activeColumn === 'ready' ? 'bg-white text-green-500' : 'bg-gray-600']">
                        {{ readyOrders.length }}
                    </span>
                </button>
            </div>

            <div :class="['gap-6', singleColumnMode ? 'flex flex-col' : 'flex',
                         singleColumnMode ? (focusMode ? 'h-[calc(100vh-130px)]' : 'h-[calc(100vh-180px)]') :
                         (focusMode ? 'h-[calc(100vh-60px)]' : 'h-[calc(100vh-120px)]')]">
                <!-- NEW Orders (with sections: Preorders + ASAP) -->
                <div v-show="!singleColumnMode || activeColumn === 'new'" class="flex-1 min-w-0 flex flex-col">
                    <div class="bg-blue-500 text-white px-4 py-3 rounded-t-2xl font-bold text-xl flex items-center justify-between">
                        <span>📥 НОВЫЕ</span>
                        <span class="bg-white text-blue-500 px-3 py-1 rounded-full text-lg">{{ totalNewOrders }}</span>
                    </div>
                    <div class="bg-gray-800 rounded-b-2xl flex-1 overflow-y-auto p-4 space-y-4">
                        <!-- Empty state -->
                        <div v-if="totalNewOrders === 0" class="flex flex-col items-center justify-center h-full text-gray-600">
                            <p class="text-6xl mb-4">📭</p>
                            <p class="text-xl">Нет новых заказов</p>
                        </div>

                        <template v-else>
                            <!-- Time Slots for Preorders -->
                            <div v-for="slot in preorderTimeSlots" :key="slot.key" class="mb-3">
                                <!-- Slot Header -->
                                <div
                                    :class="[
                                        'flex items-center gap-2 px-3 py-2 rounded-lg mb-2 text-sm font-medium',
                                        slot.urgency === 'overdue' ? 'bg-red-500/30 text-red-300' :
                                        slot.urgency === 'urgent' ? 'bg-red-500/20 text-red-400' :
                                        slot.urgency === 'warning' ? 'bg-yellow-500/20 text-yellow-400' :
                                        'bg-gray-700/50 text-gray-300'
                                    ]"
                                >
                                    <span>⏰</span>
                                    <span>{{ slot.label }}</span>
                                    <span class="ml-auto opacity-70">({{ slot.orders.length }})</span>
                                </div>

                                <!-- Slot Orders - Compact View -->
                                <div class="space-y-2 pl-2 border-l-2"
                                     :class="[
                                         slot.urgency === 'overdue' ? 'border-red-500' :
                                         slot.urgency === 'urgent' ? 'border-red-400' :
                                         slot.urgency === 'warning' ? 'border-yellow-400' :
                                         'border-gray-600'
                                     ]"
                                >
                                    <div
                                        v-for="order in slot.orders"
                                        :key="order.id"
                                        :class="[
                                            'bg-gray-800 rounded-xl p-3 cursor-pointer hover:bg-gray-750 transition',
                                            slot.urgency === 'overdue' || slot.urgency === 'urgent' ? 'ring-1 ring-red-500/50' : ''
                                        ]"
                                        @click="startCooking(order)"
                                    >
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg">{{ getOrderUrgencyDot(order.scheduled_at) }}</span>
                                                <span class="font-bold text-white">#{{ order.order_number }}</span>
                                                <span class="text-xs px-2 py-0.5 rounded bg-gray-700 text-gray-300">
                                                    {{ getOrderTypeIcon(order) }}
                                                </span>
                                                <span v-if="order.type === 'preorder' && order.table" class="text-xs px-2 py-0.5 rounded bg-purple-500/30 text-purple-300">
                                                    {{ order.table.name || order.table.number }}
                                                </span>
                                            </div>
                                            <span :class="['text-sm font-medium', getOrderUrgencyClass(order.scheduled_at)]">
                                                {{ formatTimeUntil(getMinutesUntil(order.scheduled_at)) }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-400 truncate">{{ getItemsSummary(order.items) }}</p>
                                        <button
                                            class="mt-2 w-full py-2 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-medium transition"
                                            @click.stop="startCooking(order)"
                                        >
                                            ВЗЯТЬ В РАБОТУ
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div v-if="preorderTimeSlots.length > 0 && newOrders.length > 0" class="border-t border-gray-700 my-3"></div>

                            <!-- ASAP Orders Section -->
                            <div v-if="newOrders.length > 0">
                                <!-- ASAP Header -->
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg mb-2 text-sm font-medium bg-blue-500/20 text-blue-400">
                                    <span>⚡</span>
                                    <span>Ближайшие</span>
                                    <span class="ml-auto opacity-70">({{ newOrders.length }})</span>
                                </div>

                                <!-- ASAP Orders -->
                                <div class="space-y-2 pl-2 border-l-2 border-blue-500">
                                    <NewOrderCard
                                        v-for="order in newOrders"
                                        :key="order.id"
                                        :order="order"
                                        :compact="compactMode"
                                        @start-cooking="startCooking"
                                        @show-dish-info="openDishModal"
                                    />
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- COOKING Orders -->
                <OrderColumn
                    v-show="!singleColumnMode || activeColumn === 'cooking'"
                    title="ГОТОВЯТСЯ"
                    icon="🔥"
                    color="orange"
                    :orders="cookingOrders"
                    :emptyIcon="'👨‍🍳'"
                    :emptyText="'Нет заказов в работе'"
                >
                    <template #card="{ order }">
                        <CookingOrderCard
                            :order="order"
                            :itemDoneState="itemDoneState"
                            :compact="compactMode"
                            @toggle-item="toggleItemDone"
                            @mark-ready="markReady"
                            @return-to-new="returnToNew"
                            @mark-item-ready="markItemReady"
                            @show-dish-info="openDishModal"
                        />
                    </template>
                </OrderColumn>

                <!-- READY Orders -->
                <OrderColumn
                    v-show="!singleColumnMode || activeColumn === 'ready'"
                    title="ГОТОВЫ"
                    icon="✅"
                    color="green"
                    :orders="readyOrders"
                    :emptyIcon="'✨'"
                    :emptyText="'Нет готовых заказов'"
                    :collapsible="true"
                >
                    <template #card="{ order }">
                        <ReadyOrderCard
                            :order="order"
                            :waiterCalled="waiterCalledOrders.has(order.id)"
                            :compact="compactMode"
                            @return-to-cooking="returnToCooking"
                            @call-waiter="callWaiter"
                        />
                    </template>
                </OrderColumn>
            </div>
        </main>

        <!-- New Order Alert -->
        <div v-if="showNewOrderAlert"
             class="fixed inset-0 bg-blue-500/90 flex items-center justify-center z-50"
             @click="dismissAlert">
            <div class="text-center text-white">
                <p class="text-9xl mb-8">📥</p>
                <p class="text-6xl font-bold mb-4">НОВЫЙ ЗАКАЗ!</p>
                <p class="text-4xl font-bold">#{{ newOrderNumber }}</p>
                <p class="text-2xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
            </div>
        </div>

        <!-- Cancellation Alert -->
        <div v-if="showCancellationAlert"
             class="fixed inset-0 bg-red-600/95 flex items-center justify-center z-50"
             @click="dismissCancellation">
            <div class="text-center text-white max-w-2xl">
                <p class="text-9xl mb-8">⛔</p>
                <p class="text-5xl font-bold mb-4">ОТМЕНА!</p>
                <p class="text-3xl font-bold mb-2">{{ cancellationData.item_name }}</p>
                <p class="text-2xl mb-4">×{{ cancellationData.quantity }}</p>
                <div class="bg-white/20 rounded-xl p-4 mb-4">
                    <p class="text-xl">Заказ: {{ cancellationData.order_number }}</p>
                    <p class="text-xl" v-if="cancellationData.table_number">Стол: {{ cancellationData.table_number }}</p>
                </div>
                <p class="text-2xl font-bold text-yellow-300">{{ cancellationData.reason_label }}</p>
                <p class="text-lg mt-2 opacity-75" v-if="cancellationData.reason_comment">{{ cancellationData.reason_comment }}</p>
                <p class="text-xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
            </div>
        </div>

        <!-- Stop List Alert -->
        <div v-if="showStopListAlert"
             class="fixed inset-0 bg-orange-600/95 flex items-center justify-center z-50"
             @click="dismissStopListAlert">
            <div class="text-center text-white max-w-2xl">
                <p class="text-9xl mb-8">🚫</p>
                <p class="text-5xl font-bold mb-4">СТОП-ЛИСТ!</p>
                <p class="text-3xl font-bold mb-4">{{ stopListData.dish_name }}</p>
                <div class="bg-white/20 rounded-xl p-4 mb-4">
                    <p class="text-xl">{{ stopListData.reason }}</p>
                </div>
                <p v-if="stopListData.resume_at" class="text-xl text-yellow-300">
                    Вернётся: {{ formatDateTime(stopListData.resume_at) }}
                </p>
                <p v-else class="text-xl text-red-300">Бессрочно</p>
                <p class="text-xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
            </div>
        </div>

        <!-- Overdue Order Alert -->
        <div v-if="showOverdueAlert"
             class="fixed inset-0 bg-gradient-to-br from-red-700 via-red-600 to-orange-600 flex items-center justify-center z-50 animate-pulse-bg"
             @click="dismissOverdueAlert">
            <div class="text-center text-white max-w-2xl">
                <p class="text-9xl mb-6 animate-bounce">⏰</p>
                <p class="text-5xl font-bold mb-4 animate-pulse">ПРОСРОЧЕН!</p>
                <p class="text-6xl font-extrabold mb-4">#{{ overdueAlertData.order_number }}</p>
                <div class="bg-white/20 rounded-xl p-6 mb-6">
                    <p class="text-3xl font-bold text-yellow-300">{{ formatCookingTime(overdueAlertData.cookingMinutes) }}</p>
                    <p class="text-xl mt-2">в работе</p>
                </div>
                <div v-if="overdueAlertData.table" class="mb-4">
                    <p class="text-2xl">🍽️ Стол {{ overdueAlertData.table.number || overdueAlertData.table.name }}</p>
                </div>
                <p class="text-lg opacity-75">{{ overdueAlertData.items?.length || 0 }} позиций</p>
                <p class="text-xl mt-8 opacity-75">Нажмите чтобы закрыть</p>
            </div>
        </div>

        <!-- Floating Overdue Counter Badge -->
        <div v-if="overdueOrders.length > 0 && !showOverdueAlert"
             class="fixed bottom-6 right-6 z-40">
            <div :class="[
                'rounded-2xl p-4 shadow-2xl cursor-pointer transition-all transform hover:scale-105',
                overdueOrders.some(o => o.isAlert) ? 'bg-red-600 animate-pulse' :
                overdueOrders.some(o => o.isCritical) ? 'bg-red-500' : 'bg-yellow-500'
            ]"
            @click="showOverdueAlert = true; overdueAlertData = overdueOrders[0]">
                <div class="flex items-center gap-3 text-white">
                    <span class="text-3xl">⚠️</span>
                    <div>
                        <p class="font-bold text-lg">{{ overdueOrders.length }} просрочено</p>
                        <p class="text-sm opacity-80">
                            до {{ formatCookingTime(Math.max(...overdueOrders.map(o => o.cookingMinutes))) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Waiter Call Success Toast -->
        <Transition name="slide-up">
            <div v-if="showWaiterCallSuccess"
                 class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50">
                <div class="bg-green-600 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4">
                    <span class="text-3xl">📣</span>
                    <div>
                        <p class="font-bold text-lg">Официант вызван!</p>
                        <p class="text-sm opacity-90">
                            {{ waiterCallData.waiterName }} · Заказ #{{ waiterCallData.orderNumber }}
                            <span v-if="waiterCallData.tableName"> · Стол {{ waiterCallData.tableName }}</span>
                        </p>
                    </div>
                    <span class="text-2xl">✅</span>
                </div>
            </div>
        </Transition>

        <!-- Dish Detail Modal (Recipe & Photo) -->
        <Teleport to="body">
            <div v-if="showDishModal"
                 class="fixed inset-0 bg-black/80 flex items-center justify-center z-[60] p-4"
                 @click.self="closeDishModal">
                <div class="bg-gray-800 rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl animate-scale-in">
                    <!-- Header with close button -->
                    <div class="relative">
                        <!-- Dish Image -->
                        <div class="h-64 bg-gray-700 relative overflow-hidden">
                            <img v-if="selectedDish?.image"
                                 :src="selectedDish.image"
                                 :alt="selectedDish.name"
                                 class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <span class="text-8xl opacity-50">🍽️</span>
                            </div>
                            <!-- Gradient overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-800 via-transparent to-transparent"></div>
                        </div>
                        <!-- Close button -->
                        <button @click="closeDishModal"
                                class="absolute top-4 right-4 w-10 h-10 bg-black/50 hover:bg-black/70 rounded-full flex items-center justify-center text-white text-2xl transition">
                            ×
                        </button>
                        <!-- Dish name overlay -->
                        <div class="absolute bottom-0 left-0 right-0 p-6">
                            <h2 class="text-3xl font-bold text-white drop-shadow-lg">{{ selectedDish?.name }}</h2>
                            <div class="flex items-center gap-4 mt-2 text-gray-300">
                                <span v-if="selectedDish?.cooking_time" class="flex items-center gap-1">
                                    <span>⏱️</span> {{ selectedDish.cooking_time }} мин
                                </span>
                                <span v-if="selectedDish?.weight" class="flex items-center gap-1">
                                    <span>⚖️</span> {{ selectedDish.weight }} г
                                </span>
                                <span v-if="selectedDish?.calories" class="flex items-center gap-1">
                                    <span>🔥</span> {{ selectedDish.calories }} ккал
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6 overflow-y-auto max-h-[40vh]">
                        <!-- Tags -->
                        <div v-if="selectedDish?.is_spicy || selectedDish?.is_vegetarian || selectedDish?.is_vegan" class="flex gap-2 mb-4">
                            <span v-if="selectedDish.is_spicy" class="px-3 py-1 bg-red-500/20 text-red-400 rounded-full text-sm">🌶️ Острое</span>
                            <span v-if="selectedDish.is_vegetarian" class="px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-sm">🌱 Вегетарианское</span>
                            <span v-if="selectedDish.is_vegan" class="px-3 py-1 bg-teal-500/20 text-teal-400 rounded-full text-sm">🥗 Веганское</span>
                        </div>

                        <!-- Description / Recipe -->
                        <div v-if="selectedDish?.description" class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-300 mb-2 flex items-center gap-2">
                                <span>📝</span> Описание / Рецепт
                            </h3>
                            <p class="text-gray-400 whitespace-pre-line leading-relaxed">{{ selectedDish.description }}</p>
                        </div>

                        <!-- Nutritional info -->
                        <div v-if="selectedDish?.proteins || selectedDish?.fats || selectedDish?.carbs" class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-300 mb-3 flex items-center gap-2">
                                <span>🧪</span> Пищевая ценность (на 100г)
                            </h3>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                                    <p class="text-2xl font-bold text-blue-400">{{ selectedDish.proteins || 0 }}</p>
                                    <p class="text-xs text-gray-500">Белки, г</p>
                                </div>
                                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                                    <p class="text-2xl font-bold text-yellow-400">{{ selectedDish.fats || 0 }}</p>
                                    <p class="text-xs text-gray-500">Жиры, г</p>
                                </div>
                                <div class="bg-gray-700/50 rounded-xl p-3 text-center">
                                    <p class="text-2xl font-bold text-green-400">{{ selectedDish.carbs || 0 }}</p>
                                    <p class="text-xs text-gray-500">Углеводы, г</p>
                                </div>
                            </div>
                        </div>

                        <!-- Modifiers if present -->
                        <div v-if="selectedItemModifiers?.length" class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-300 mb-2 flex items-center gap-2">
                                <span>➕</span> Модификаторы в заказе
                            </h3>
                            <div class="space-y-1">
                                <p v-for="mod in selectedItemModifiers" :key="mod.id" class="text-blue-300">
                                    + {{ mod.option_name || mod.name }}
                                </p>
                            </div>
                        </div>

                        <!-- Item comment if present -->
                        <div v-if="selectedItemComment" class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4">
                            <h3 class="text-lg font-semibold text-yellow-400 mb-1 flex items-center gap-2">
                                <span>💬</span> Комментарий к заказу
                            </h3>
                            <p class="text-yellow-300">{{ selectedItemComment }}</p>
                        </div>

                        <!-- No description placeholder -->
                        <div v-if="!selectedDish?.description && !selectedDish?.proteins" class="text-center py-8 text-gray-500">
                            <p class="text-4xl mb-2">📋</p>
                            <p>Описание не добавлено</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-gray-700 flex justify-end">
                        <button @click="closeDishModal"
                                class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-xl font-medium transition">
                            Закрыть
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import OrderColumn from './components/OrderColumn.vue';
import NewOrderCard from './components/NewOrderCard.vue';
import CookingOrderCard from './components/CookingOrderCard.vue';
import ReadyOrderCard from './components/ReadyOrderCard.vue';
import PreorderCard from './components/PreorderCard.vue';
import {
    setTimezone,
    getCurrentTimeWithSeconds,
    getCurrentDate,
    formatDateTime,
    getLocalDateString
} from '../utils/timezone';

// State
const orders = ref([]);
const currentTime = ref('');
const currentDate = ref('');
const soundEnabled = ref(true);
const compactMode = ref(false); // Компактный режим отображения
const focusMode = ref(false); // Режим фокуса (без отвлекающих элементов)
const singleColumnMode = ref(false); // Режим одной колонки
const activeColumn = ref('new'); // Активная колонка в режиме одной колонки: 'new', 'cooking', 'ready'
const stationSlug = ref(null);
const currentStation = ref(null);
const showNewOrderAlert = ref(false);
const newOrderNumber = ref('');
const showCancellationAlert = ref(false);
const cancellationData = ref({});
const lastEventId = ref(0);
const seenOrderIds = ref(new Set()); // Все когда-либо виденные заказы (чтобы возврат не считался новым)
const itemDoneState = ref({});

// Web Audio API Synthesizer для качественных звуков
let audioContext = null;

const getAudioContext = () => {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioContext;
};

// Синтезируем различные звуки уведомлений
const synthesizeSound = (type) => {
    const ctx = getAudioContext();
    const now = ctx.currentTime;

    switch (type) {
        case 'bell': {
            // Классический сервисный колокольчик с гармониками
            const fundamental = 880;
            [1, 2, 3, 4.2, 5.4].forEach((harmonic, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = fundamental * harmonic;
                gain.gain.setValueAtTime(0.3 / (i + 1), now);
                gain.gain.exponentialDecayTo?.(0.001, now + 1.5) || gain.gain.exponentialRampToValueAtTime(0.001, now + 1.5);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 1.5);
            });
            break;
        }
        case 'chime': {
            // Мелодичный перезвон (3 ноты как ветряные колокольчики)
            const notes = [1047, 1319, 1568]; // C6, E6, G6 - мажорный аккорд
            notes.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0, now + i * 0.15);
                gain.gain.linearRampToValueAtTime(0.25, now + i * 0.15 + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.15 + 1.2);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + i * 0.15);
                osc.stop(now + i * 0.15 + 1.2);
            });
            break;
        }
        case 'ding': {
            // Яркий одиночный звон
            const osc = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gain = ctx.createGain();
            const gain2 = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 1200;
            osc2.type = 'sine';
            osc2.frequency.value = 2400;
            gain.gain.setValueAtTime(0.4, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
            gain2.gain.setValueAtTime(0.15, now);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
            osc.connect(gain);
            osc2.connect(gain2);
            gain.connect(ctx.destination);
            gain2.connect(ctx.destination);
            osc.start(now);
            osc2.start(now);
            osc.stop(now + 0.8);
            osc2.stop(now + 0.5);
            break;
        }
        case 'kitchen': {
            // Двойной звонок кухни (ding-ding!)
            [0, 0.25].forEach((delay) => {
                const osc = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 1000;
                osc2.type = 'sine';
                osc2.frequency.value = 2000;
                gain.gain.setValueAtTime(0.35, now + delay);
                gain.gain.exponentialRampToValueAtTime(0.001, now + delay + 0.3);
                osc.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + delay);
                osc2.start(now + delay);
                osc.stop(now + delay + 0.3);
                osc2.stop(now + delay + 0.3);
            });
            break;
        }
        case 'alert': {
            // Двухтональный приятный сигнал
            const freqs = [880, 1100];
            freqs.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0, now + i * 0.2);
                gain.gain.linearRampToValueAtTime(0.3, now + i * 0.2 + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.2 + 0.4);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + i * 0.2);
                osc.stop(now + i * 0.2 + 0.4);
            });
            break;
        }
        case 'gong': {
            // Глубокий гонг с долгим затуханием
            const fundamental = 150;
            [1, 1.5, 2, 2.5, 3, 4].forEach((harmonic, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = i === 0 ? 'sine' : 'triangle';
                osc.frequency.value = fundamental * harmonic;
                const volume = 0.25 / (i + 1);
                gain.gain.setValueAtTime(volume, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 3);
            });
            break;
        }
        default: {
            // По умолчанию bell
            synthesizeSound('bell');
        }
    }
};

// Date picker state
const selectedDate = ref(getLocalDateString(new Date()));
const showCalendarPicker = ref(false);
const calendarViewDate = ref(new Date());
const orderCountsByDate = ref({}); // { '2024-01-15': 3, '2024-01-16': 5 }

// Stop list state
const stopList = ref([]);
const showStopListDropdown = ref(false);

// Device registration state
const deviceId = ref(null);
const deviceStatus = ref('loading'); // loading, not_registered, pending, configured, disabled
const deviceData = ref(null);

// Generate UUID for device
const generateDeviceId = () => {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
};

// Get or create device ID from localStorage
const getDeviceId = () => {
    let id = localStorage.getItem('kitchen_device_id');
    if (!id) {
        id = generateDeviceId();
        localStorage.setItem('kitchen_device_id', id);
    }
    return id;
};

// Register device on server
const registerDevice = async () => {
    deviceStatus.value = 'loading';
    try {
        const res = await axios.post('/api/kitchen-devices/register', {
            device_id: deviceId.value,
            name: navigator.userAgent.includes('Mobile') ? 'Планшет' : 'Устройство'
        });
        if (res.data.success) {
            deviceData.value = res.data.data;
            await checkDeviceStatus();
        }
    } catch (e) {
        console.error('Error registering device:', e);
        deviceStatus.value = 'not_registered';
    }
};

// Check device status and get station config
const checkDeviceStatus = async () => {
    try {
        const res = await axios.get('/api/kitchen-devices/my-station', {
            params: { device_id: deviceId.value }
        });

        if (res.data.success) {
            deviceData.value = res.data.data;
            const status = res.data.status;

            if (status === 'configured') {
                deviceStatus.value = 'configured';
                // Set station from device config
                if (deviceData.value.kitchen_station) {
                    stationSlug.value = deviceData.value.kitchen_station.slug;
                    currentStation.value = deviceData.value.kitchen_station;
                }
                // Start loading orders
                fetchOrders();
            } else if (status === 'pending') {
                deviceStatus.value = 'pending';
            } else if (status === 'disabled') {
                deviceStatus.value = 'disabled';
            }
        } else {
            deviceStatus.value = res.data.status || 'pending';
        }
    } catch (e) {
        if (e.response?.status === 404) {
            // Device not found - register it
            await registerDevice();
        } else if (e.response?.status === 403) {
            deviceStatus.value = 'disabled';
        } else {
            console.error('Error checking device status:', e);
            deviceStatus.value = 'not_registered';
        }
    }
};

// Initialize device
const initDevice = async () => {
    deviceId.value = getDeviceId();
    await checkDeviceStatus();
};

// Parse scheduled_at without timezone conversion
const parseScheduledTime = (scheduledAt) => {
    if (!scheduledAt) return null;
    const match = scheduledAt.match(/(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
    if (!match) return null;
    return {
        date: match[1],
        hours: parseInt(match[2]),
        minutes: parseInt(match[3]),
        timeStr: `${match[2]}:${match[3]}`
    };
};

// Check if order is a scheduled preorder (not ASAP)
const isPreorder = (order) => {
    return order.scheduled_at && !order.is_asap;
};

// Date picker computed
const displaySelectedDate = computed(() => {
    const today = new Date();
    const todayStr = getLocalDateString(today);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = getLocalDateString(tomorrow);

    if (selectedDate.value === todayStr) return 'Сегодня';
    if (selectedDate.value === tomorrowStr) return 'Завтра';

    // Парсим дату как локальную (не UTC) чтобы избежать сдвига дня
    const [year, month, day] = selectedDate.value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
});

const isSelectedDateToday = computed(() => {
    return selectedDate.value === getLocalDateString(new Date());
});

const calendarMonthYear = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[calendarViewDate.value.getMonth()]} ${calendarViewDate.value.getFullYear()}`;
});

const calendarDays = computed(() => {
    const year = calendarViewDate.value.getFullYear();
    const month = calendarViewDate.value.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = getLocalDateString(today);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    let startDay = firstDay.getDay() - 1;
    if (startDay < 0) startDay = 6;

    const days = [];

    // Previous month padding
    for (let i = 0; i < startDay; i++) {
        days.push({ day: '', date: null, count: 0 });
    }

    // Current month days
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const date = new Date(year, month, d);
        const dateStr = getLocalDateString(date);
        days.push({
            day: d,
            date: dateStr,
            isToday: dateStr === todayStr,
            isSelected: dateStr === selectedDate.value,
            isPast: dateStr < todayStr,
            count: orderCountsByDate.value[dateStr] || 0
        });
    }

    return days;
});

// Приоритет типа заказа (выше = важнее)
const getOrderTypePriority = (order) => {
    // Бронь с предзаказом ко времени - высший приоритет
    if (order.type === 'preorder' || (order.type === 'dine_in' && order.scheduled_at)) {
        return 4;
    }
    const priorities = {
        'dine_in': 3,    // Зал - высокий приоритет (гость ждёт)
        'pickup': 2,     // Самовывоз - средний приоритет
        'delivery': 1    // Доставка - базовый приоритет (есть время на дорогу)
    };
    return priorities[order.type] ?? 1;
};

// Computed
const newOrders = computed(() => {
    return orders.value
        .filter(o => ['confirmed', 'cooking', 'ready'].includes(o.status))
        .filter(o => !isPreorder(o)) // Exclude preorders - they have their own column
        .map(o => ({
            ...o,
            items: (o.items || []).filter(i => i.status === 'cooking' && !i.cooking_started_at)
        }))
        .filter(o => o.items.length > 0)
        // Сортировка: сначала по времени ожидания (старые первыми), затем по приоритету типа
        .sort((a, b) => {
            const waitA = a.created_at ? new Date() - new Date(a.created_at) : 0;
            const waitB = b.created_at ? new Date() - new Date(b.created_at) : 0;

            // Если разница во времени ожидания больше 5 минут - сортируем по времени
            const waitDiffMinutes = Math.abs(waitA - waitB) / 60000;
            if (waitDiffMinutes > 5) {
                return waitB - waitA; // Старые заказы первыми
            }

            // Иначе сортируем по приоритету типа
            const priorityA = getOrderTypePriority(a);
            const priorityB = getOrderTypePriority(b);
            if (priorityA !== priorityB) {
                return priorityB - priorityA; // Высокий приоритет первым
            }

            // Если приоритет одинаковый - по времени
            return waitB - waitA;
        });
});

const cookingOrders = computed(() => {
    return orders.value
        .filter(o => ['confirmed', 'cooking', 'ready'].includes(o.status))
        .map(o => ({
            ...o,
            items: (o.items || [])
                .filter(i => i.status === 'cooking' && i.cooking_started_at)
                .map(item => ({
                    ...item,
                    done: itemDoneState.value[`${o.id}-${item.id}`] || false
                }))
        }))
        .filter(o => o.items.length > 0)
        // Сортировка: долго готовящиеся первыми
        .sort((a, b) => {
            const startA = a.cooking_started_at || a.updated_at;
            const startB = b.cooking_started_at || b.updated_at;
            if (!startA || !startB) return 0;
            return new Date(startA) - new Date(startB); // Старые первыми
        });
});

const readyOrders = computed(() => {
    return orders.value
        .filter(o => ['confirmed', 'cooking', 'ready'].includes(o.status))
        .map(o => ({
            ...o,
            items: (o.items || []).filter(i => i.status === 'ready')
        }))
        .filter(o => o.items.length > 0);
});

// Preorders - scheduled orders that cook hasn't started yet
// Shows regardless of manager sending to kitchen, but hides once cook takes it to work
const preorderOrders = computed(() => {
    return orders.value
        .filter(o => isPreorder(o))
        // Exclude completed/cancelled orders
        .filter(o => !['completed', 'cancelled'].includes(o.status))
        // Only show if cook hasn't started working on it yet
        .filter(o => {
            const items = o.items || [];
            // If no items, still show the preorder (will be filtered later if truly empty)
            if (items.length === 0) return true;
            // If ANY item has cooking_started_at - cook has started, don't show in preorders
            const cookingStarted = items.some(i => i.cooking_started_at);
            // If ALL items are ready/served/cancelled - don't show (order is done)
            const allDone = items.length > 0 && items.every(i => ['ready', 'served', 'cancelled'].includes(i.status));
            return !cookingStarted && !allDone;
        })
        .map(o => ({
            ...o,
            // Show all non-cancelled items
            items: (o.items || []).filter(i => i.status !== 'cancelled')
        }))
        // Only filter out if items array exists AND is empty after filtering cancelled
        .filter(o => !o.items || o.items.length > 0)
        .sort((a, b) => {
            const timeA = parseScheduledTime(a.scheduled_at);
            const timeB = parseScheduledTime(b.scheduled_at);
            if (!timeA || !timeB) return 0;
            return (timeA.date + timeA.timeStr).localeCompare(timeB.date + timeB.timeStr);
        });
});

// Total new orders (preorders + ASAP)
const totalNewOrders = computed(() => preorderOrders.value.length + newOrders.value.length);

// Get time slot key for grouping (30-minute slots)
const getTimeSlotKey = (scheduledAt) => {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;
    const slotMinutes = parsed.minutes < 30 ? '00' : '30';
    return `${parsed.date}-${parsed.hours.toString().padStart(2, '0')}:${slotMinutes}`;
};

// Get time slot label
const getTimeSlotLabel = (slotKey) => {
    if (!slotKey) return '';
    const [date, time] = slotKey.split('-').slice(-2);
    const [hours, mins] = (date.includes(':') ? date : time).split(':');
    const h = parseInt(hours);
    const m = parseInt(mins);
    const endM = m + 30;
    const endH = endM >= 60 ? h + 1 : h;
    const endMins = endM >= 60 ? '00' : '30';
    return `${hours}:${mins} - ${endH.toString().padStart(2, '0')}:${endMins}`;
};

// Get slot urgency based on time remaining
const getSlotUrgency = (slotKey) => {
    if (!slotKey) return 'normal';
    const parts = slotKey.split('-');
    const timePart = parts[parts.length - 1];
    const datePart = parts.slice(0, 3).join('-');
    const [hours, mins] = timePart.split(':').map(Number);

    const now = new Date();
    const todayStr = getLocalDateString(now);

    // If different date
    if (datePart !== todayStr) {
        return datePart > todayStr ? 'normal' : 'overdue';
    }

    const slotStart = hours * 60 + mins;
    const currentMins = now.getHours() * 60 + now.getMinutes();
    const diff = slotStart - currentMins;

    if (diff < 0) return 'overdue';
    if (diff <= 30) return 'urgent';
    if (diff <= 60) return 'warning';
    return 'normal';
};

// Group preorders by 30-minute time slots
const preorderTimeSlots = computed(() => {
    const slots = {};

    preorderOrders.value.forEach(order => {
        const slotKey = getTimeSlotKey(order.scheduled_at);
        if (!slotKey) return;

        if (!slots[slotKey]) {
            slots[slotKey] = {
                key: slotKey,
                label: getTimeSlotLabel(slotKey),
                orders: [],
                urgency: 'normal'
            };
        }
        slots[slotKey].orders.push(order);
    });

    // Calculate urgency for each slot and sort
    return Object.values(slots)
        .map(slot => ({
            ...slot,
            urgency: getSlotUrgency(slot.key)
        }))
        .sort((a, b) => a.key.localeCompare(b.key));
});

// Get minutes until order time
const getMinutesUntil = (scheduledAt) => {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return null;

    const now = new Date();
    const todayStr = getLocalDateString(now);

    if (parsed.date !== todayStr) {
        return parsed.date > todayStr ? 9999 : -9999;
    }

    const currentMins = now.getHours() * 60 + now.getMinutes();
    const targetMins = parsed.hours * 60 + parsed.minutes;
    return targetMins - currentMins;
};

// Format time until
const formatTimeUntil = (mins) => {
    if (mins === null) return '';
    if (mins >= 9999) return 'завтра';
    if (mins <= -9999) return 'просрочен';
    if (mins < 0) return `просрочен ${Math.abs(mins)}м`;
    if (mins === 0) return 'сейчас';
    if (mins < 60) return `через ${mins}м`;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return m > 0 ? `через ${h}ч ${m}м` : `через ${h}ч`;
};

// Get order urgency color class
const getOrderUrgencyClass = (scheduledAt) => {
    const mins = getMinutesUntil(scheduledAt);
    if (mins === null) return 'text-gray-400';
    if (mins < 0) return 'text-red-400';
    if (mins <= 30) return 'text-red-400';
    if (mins <= 60) return 'text-yellow-400';
    return 'text-green-400';
};

// Get order type icon
const getOrderTypeIcon = (order) => {
    if (order.type === 'delivery') return '🛵';
    if (order.type === 'pickup') return '🏃';
    if (order.type === 'preorder') return '📅'; // Бронь с предзаказом
    return '🍽️'; // dine_in
};

// Get order urgency dot
const getOrderUrgencyDot = (scheduledAt) => {
    const mins = getMinutesUntil(scheduledAt);
    if (mins === null) return '⚪';
    if (mins < 0) return '🔴';
    if (mins <= 30) return '🔴';
    if (mins <= 60) return '🟡';
    return '🟢';
};

// Get items summary for compact display
const getItemsSummary = (items) => {
    if (!items || items.length === 0) return '';
    const names = items.slice(0, 2).map(i => i.name);
    if (items.length > 2) {
        return names.join(', ') + ` +${items.length - 2}`;
    }
    return names.join(', ');
};

// Time update (uses timezone from settings)
const updateTime = () => {
    currentTime.value = getCurrentTimeWithSeconds();
    currentDate.value = getCurrentDate();
};

// Actions
const toggleItemDone = (order, item) => {
    const key = `${order.id}-${item.id}`;
    itemDoneState.value[key] = !itemDoneState.value[key];
};

// Load order counts for calendar
const loadOrderCounts = async () => {
    try {
        const year = calendarViewDate.value.getFullYear();
        const month = calendarViewDate.value.getMonth();
        const startDate = getLocalDateString(new Date(year, month, 1));
        const endDate = getLocalDateString(new Date(year, month + 1, 0));

        let url = `/api/orders/count-by-dates?start_date=${startDate}&end_date=${endDate}`;
        if (stationSlug.value) {
            url += `&station=${stationSlug.value}`;
        }

        const res = await axios.get(url);
        if (res.data.success) {
            orderCountsByDate.value = res.data.data || {};
        }
    } catch (e) {
        console.error('Error loading order counts:', e);
    }
};

// Date picker methods
const toggleCalendarPicker = () => {
    showCalendarPicker.value = !showCalendarPicker.value;
    if (showCalendarPicker.value) {
        // Sync calendar view with selected date
        // Парсим дату как локальную (не UTC) чтобы избежать сдвига дня
        const [year, month, day] = selectedDate.value.split('-').map(Number);
        calendarViewDate.value = new Date(year, month - 1, day);
        loadOrderCounts();
    }
};

const goToPrevDay = () => {
    // Парсим дату как локальную (не UTC) чтобы избежать сдвига дня
    const [year, month, day] = selectedDate.value.split('-').map(Number);
    const date = new Date(year, month - 1, day - 1);
    selectedDate.value = getLocalDateString(date);
    fetchOrders();
};

const goToNextDay = () => {
    // Парсим дату как локальную (не UTC) чтобы избежать сдвига дня
    const [year, month, day] = selectedDate.value.split('-').map(Number);
    const date = new Date(year, month - 1, day + 1);
    selectedDate.value = getLocalDateString(date);
    fetchOrders();
};

const calendarPrevMonth = () => {
    const date = new Date(calendarViewDate.value);
    date.setMonth(date.getMonth() - 1);
    calendarViewDate.value = date;
    loadOrderCounts();
};

const calendarNextMonth = () => {
    const date = new Date(calendarViewDate.value);
    date.setMonth(date.getMonth() + 1);
    calendarViewDate.value = date;
    loadOrderCounts();
};

const selectCalendarDate = (dateStr) => {
    selectedDate.value = dateStr;
    showCalendarPicker.value = false;
    fetchOrders();
};

const selectToday = () => {
    selectedDate.value = getLocalDateString(new Date());
    showCalendarPicker.value = false;
    fetchOrders();
};

const selectTomorrow = () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    selectedDate.value = getLocalDateString(tomorrow);
    showCalendarPicker.value = false;
    fetchOrders();
};

const startCooking = async (order) => {
    console.log('startCooking called for order:', order.id, order.order_number, 'type:', order.type);
    try {
        const payload = { status: 'cooking' };
        // Передаём station чтобы обновить только позиции своего цеха
        if (stationSlug.value) {
            payload.station = stationSlug.value;
        }
        console.log('Sending payload:', payload);
        const res = await axios.patch(`/api/orders/${order.id}/status`, payload);
        console.log('Response:', res.data);
        if (res.data.success) {
            fetchOrders();
        } else {
            console.error('API returned success:false', res.data);
            alert('Ошибка: ' + (res.data.message || 'Неизвестная ошибка'));
        }
    } catch (e) {
        console.error('Error starting cooking:', e);
        console.error('Response:', e.response?.data);
        alert('Ошибка при взятии в работу: ' + (e.response?.data?.message || e.message));
    }
};

const markReady = async (order) => {
    try {
        const payload = { status: 'ready' };
        // Передаём station чтобы обновить только позиции своего цеха
        if (stationSlug.value) {
            payload.station = stationSlug.value;
        }
        const res = await axios.patch(`/api/orders/${order.id}/status`, payload);
        if (res.data.success) {
            // Clear done states for this order
            Object.keys(itemDoneState.value).forEach(key => {
                if (key.startsWith(`${order.id}-`)) {
                    delete itemDoneState.value[key];
                }
            });
            fetchOrders();
            playNotification();
        }
    } catch (e) {
        console.error('Error marking ready:', e);
    }
};

// Отметить отдельную позицию как готовую
const markItemReady = async (order, item) => {
    try {
        const res = await axios.patch(`/api/orders/${order.id}/items/${item.id}/status`, {
            status: 'ready'
        });
        if (res.data.success) {
            // Отмечаем позицию как готовую в локальном состоянии
            itemDoneState.value[`${order.id}-${item.id}`] = true;
            // Обновляем заказы
            fetchOrders();
        } else {
            alert('Ошибка: ' + (res.data.message || 'Не удалось отметить позицию'));
        }
    } catch (e) {
        console.error('Error marking item ready:', e);
        alert('Ошибка: ' + (e.response?.data?.message || e.message));
    }
};

// Вернуть заказ из "Готовится" в "Новые"
const returnToNew = async (order) => {
    try {
        const payload = { status: 'return_to_new' };
        if (stationSlug.value) {
            payload.station = stationSlug.value;
        }
        const res = await axios.patch(`/api/orders/${order.id}/status`, payload);
        if (res.data.success) {
            // Очищаем состояние чекбоксов для этого заказа
            Object.keys(itemDoneState.value).forEach(key => {
                if (key.startsWith(`${order.id}-`)) {
                    delete itemDoneState.value[key];
                }
            });
            fetchOrders();
        } else {
            alert('Ошибка: ' + (res.data.message || 'Не удалось вернуть заказ'));
        }
    } catch (e) {
        console.error('Error returning to new:', e);
        alert('Ошибка при возврате заказа: ' + (e.response?.data?.message || e.message));
    }
};

// Вернуть заказ из "Готово" в "Готовится"
const returnToCooking = async (order) => {
    try {
        const payload = { status: 'return_to_cooking' };
        if (stationSlug.value) {
            payload.station = stationSlug.value;
        }
        const res = await axios.patch(`/api/orders/${order.id}/status`, payload);
        if (res.data.success) {
            fetchOrders();
        } else {
            alert('Ошибка: ' + (res.data.message || 'Не удалось вернуть заказ'));
        }
    } catch (e) {
        console.error('Error returning to cooking:', e);
        alert('Ошибка при возврате заказа: ' + (e.response?.data?.message || e.message));
    }
};

const fetchOrders = async () => {
    try {
        let url = `/api/orders?date=${selectedDate.value}`;
        if (stationSlug.value) {
            url += `&station=${stationSlug.value}`;
        }
        const res = await axios.get(url);
        if (res.data.success) {
            processOrders(res.data.data);
        }
    } catch (e) {
        console.error('Error fetching orders:', e);
    }
};

const loadStationInfo = async () => {
    if (!stationSlug.value) {
        currentStation.value = null;
        return;
    }
    try {
        const res = await axios.get('/api/kitchen-stations/active');
        if (res.data.success) {
            currentStation.value = res.data.data.find(s => s.slug === stationSlug.value) || null;
        }
    } catch (e) {
        console.error('Error loading station info:', e);
    }
};

const processOrders = (allOrders) => {
    const activeStatuses = ['confirmed', 'cooking', 'ready'];

    // Filter orders: include active statuses OR preorders (regardless of status)
    const newData = allOrders.filter(o => {
        // Always include preorders so kitchen can see upcoming scheduled orders
        if (isPreorder(o) && !['completed', 'cancelled'].includes(o.status)) {
            return true;
        }
        // Regular orders - only show active statuses
        return activeStatuses.includes(o.status);
    });

    // Check for new orders (only truly new, not returned from cooking)
    const confirmedOrders = newData.filter(o => o.status === 'confirmed');
    confirmedOrders.forEach(order => {
        if (!seenOrderIds.value.has(order.id)) {
            // Действительно новый заказ - показываем уведомление
            newOrderNumber.value = order.order_number;
            showNewOrderAlert.value = true;
            playNotification();
            setTimeout(() => showNewOrderAlert.value = false, 5000);
        }
    });

    // Добавляем все текущие заказы в "виденные" (не перезаписываем!)
    newData.forEach(o => seenOrderIds.value.add(o.id));
    orders.value = newData;
};

// Воспроизвести звук уведомления станции
const playNotification = () => {
    if (!soundEnabled.value) return;

    try {
        // Получаем звук станции или используем bell по умолчанию
        const stationSound = currentStation.value?.notification_sound || 'bell';
        synthesizeSound(stationSound);
    } catch (e) {
        console.error('Error playing notification:', e);
    }
};

const dismissAlert = () => {
    showNewOrderAlert.value = false;
};

const dismissCancellation = () => {
    showCancellationAlert.value = false;
};

// Stop list alert state
const showStopListAlert = ref(false);
const stopListData = ref({});

const dismissStopListAlert = () => {
    showStopListAlert.value = false;
};

// === OVERDUE ORDER WARNING SYSTEM ===
// Пороги времени (в минутах)
const OVERDUE_WARNING_THRESHOLD = 10;  // Жёлтое предупреждение (10 мин)
const OVERDUE_CRITICAL_THRESHOLD = 15; // Красное критичное (15 мин)
const OVERDUE_ALERT_THRESHOLD = 20;    // Полноэкранный алерт (20 мин)

// State
const showOverdueAlert = ref(false);
const overdueAlertData = ref({});
const lastOverdueAlertTime = ref(0);
const overdueOrders = ref([]);

// Синтезируем тревожный звук для просрочки
const synthesizeOverdueSound = () => {
    const ctx = getAudioContext();
    const now = ctx.currentTime;

    // Тревожный двухтональный сигнал (как в больнице)
    const playTone = (startTime, freq1, freq2) => {
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gain = ctx.createGain();

        osc1.type = 'sine';
        osc1.frequency.value = freq1;
        osc2.type = 'sine';
        osc2.frequency.value = freq2;

        gain.gain.setValueAtTime(0.25, startTime);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.4);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(ctx.destination);

        osc1.start(startTime);
        osc2.start(startTime);
        osc1.stop(startTime + 0.4);
        osc2.stop(startTime + 0.4);
    };

    // Три коротких сигнала с нарастающей тональностью
    playTone(now, 440, 554);        // A4 + C#5
    playTone(now + 0.5, 554, 698);  // C#5 + F5
    playTone(now + 1.0, 698, 880);  // F5 + A5
};

// Проверяем заказы на просрочку
const checkOverdueOrders = () => {
    const now = Date.now();
    const overdue = [];

    cookingOrders.value.forEach(order => {
        const startTime = order.cooking_started_at || order.updated_at;
        if (!startTime) return;

        const cookingMinutes = Math.floor((now - new Date(startTime).getTime()) / 60000);

        if (cookingMinutes >= OVERDUE_WARNING_THRESHOLD) {
            overdue.push({
                ...order,
                cookingMinutes,
                isWarning: cookingMinutes >= OVERDUE_WARNING_THRESHOLD && cookingMinutes < OVERDUE_CRITICAL_THRESHOLD,
                isCritical: cookingMinutes >= OVERDUE_CRITICAL_THRESHOLD && cookingMinutes < OVERDUE_ALERT_THRESHOLD,
                isAlert: cookingMinutes >= OVERDUE_ALERT_THRESHOLD
            });
        }
    });

    overdueOrders.value = overdue;

    // Показываем полноэкранный алерт для критичных просрочек
    const alertOrders = overdue.filter(o => o.isAlert);
    if (alertOrders.length > 0 && soundEnabled.value) {
        // Не показываем алерт чаще чем раз в 30 секунд
        if (now - lastOverdueAlertTime.value > 30000) {
            lastOverdueAlertTime.value = now;
            overdueAlertData.value = alertOrders[0];
            showOverdueAlert.value = true;
            synthesizeOverdueSound();

            // Автоматически скрываем через 10 секунд
            setTimeout(() => {
                showOverdueAlert.value = false;
            }, 10000);
        }
    }

    // Звуковое предупреждение для критичных (но не alert) заказов
    const criticalOrders = overdue.filter(o => o.isCritical && !o.isAlert);
    if (criticalOrders.length > 0 && soundEnabled.value) {
        // Звук раз в минуту для критичных
        if (now - lastOverdueAlertTime.value > 60000) {
            lastOverdueAlertTime.value = now;
            synthesizeOverdueSound();
        }
    }
};

const dismissOverdueAlert = () => {
    showOverdueAlert.value = false;
};

// Форматирование времени готовки
const formatCookingTime = (minutes) => {
    if (minutes < 60) return `${minutes} мин`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}ч ${m}м` : `${h}ч`;
};

// === DISH DETAIL MODAL ===
const showDishModal = ref(false);
const selectedDish = ref(null);
const selectedItemModifiers = ref([]);
const selectedItemComment = ref('');

const openDishModal = (item) => {
    if (!item?.dish) return;
    selectedDish.value = item.dish;
    selectedItemModifiers.value = item.modifiers || [];
    selectedItemComment.value = item.comment || item.notes || '';
    showDishModal.value = true;
};

const closeDishModal = () => {
    showDishModal.value = false;
    selectedDish.value = null;
    selectedItemModifiers.value = [];
    selectedItemComment.value = '';
};

// === WAITER CALL SYSTEM ===
const waiterCalledOrders = ref(new Set());
const showWaiterCallSuccess = ref(false);
const waiterCallData = ref({});

const callWaiter = async (order) => {
    if (waiterCalledOrders.value.has(order.id)) return;

    try {
        const res = await axios.post(`/api/orders/${order.id}/call-waiter`);
        if (res.data.success) {
            waiterCalledOrders.value.add(order.id);
            waiterCallData.value = {
                orderNumber: order.order_number,
                waiterName: order.waiter?.name,
                tableName: order.table?.name || order.table?.number
            };
            showWaiterCallSuccess.value = true;

            // Воспроизводим звук подтверждения
            synthesizeSound('chime');

            // Скрываем уведомление через 3 секунды
            setTimeout(() => {
                showWaiterCallSuccess.value = false;
            }, 3000);
        }
    } catch (e) {
        console.error('Error calling waiter:', e);
        alert('Ошибка вызова официанта: ' + (e.response?.data?.message || e.message));
    }
};

// Загрузка стоп-листа
const loadStopList = async () => {
    try {
        const res = await axios.get('/api/stop-list');
        if (res.data.success) {
            stopList.value = res.data.data || [];
        }
    } catch (e) {
        console.error('Error loading stop list:', e);
    }
};

// Форматирование времени возврата в продажу
const formatStopListTime = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = date - now;
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));

    if (diffHours < 0) return 'истекло';
    if (diffHours < 1) return 'менее часа';
    if (diffHours < 24) return `${diffHours} ч.`;

    return date.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Закрытие dropdown при клике вне
const closeStopListDropdown = (e) => {
    if (showStopListDropdown.value && !e.target.closest('.relative')) {
        showStopListDropdown.value = false;
    }
};

// Инициализация lastEventId - пропускаем старые события
const initLastEventId = async () => {
    try {
        const res = await axios.get('/api/realtime/status');
        if (res.data.success && res.data.data?.last_id) {
            lastEventId.value = res.data.data.last_id;
        }
    } catch (e) {
        // Если не удалось - начнём с 0, но это покажет старые события
    }
};

const checkCancellations = async () => {
    try {
        const res = await axios.get(`/api/realtime/poll?last_id=${lastEventId.value}&channels=kitchen,global`);
        if (res.data.success && res.data.data) {
            // Обрабатываем события
            if (res.data.data.events?.length > 0) {
                res.data.data.events.forEach(event => {
                    // Обработка отмены позиции
                    if (event.event === 'item_cancelled') {
                        cancellationData.value = event.data;
                        showCancellationAlert.value = true;
                        playNotification();
                        setTimeout(() => showCancellationAlert.value = false, 10000);
                    }
                    // Обработка уведомления о стоп-листе
                    if (event.event === 'stop_list_notification') {
                        stopListData.value = event.data;
                        showStopListAlert.value = true;
                        playNotification();
                        setTimeout(() => showStopListAlert.value = false, 8000);
                        // Обновляем список стоп-листа
                        loadStopList();
                    }
                    // Обработка изменения стоп-листа (добавление/удаление)
                    if (event.event === 'stop_list_changed') {
                        loadStopList();
                    }
                });
            }
            // Обновляем last_id из ответа
            if (res.data.data.last_id) {
                lastEventId.value = res.data.data.last_id;
            }
        }
    } catch (e) {
        // Silent fail for polling
    }
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
};

// Lifecycle
let timeInterval, fetchInterval, eventsInterval, cookingTimeInterval, deviceCheckInterval;

onMounted(async () => {
    // Load timezone from settings
    try {
        const response = await fetch('/api/settings/general');
        const data = await response.json();
        if (data.success && data.data?.timezone) {
            setTimezone(data.data.timezone);
            // Обновляем selectedDate после загрузки правильной таймзоны
            selectedDate.value = getLocalDateString(new Date());
        }
    } catch (e) {
        console.warn('[Kitchen] Failed to load timezone:', e);
    }

    updateTime();
    timeInterval = setInterval(updateTime, 1000);

    // Проверяем URL параметр station - если есть, работаем в режиме без привязки устройства
    const urlParams = new URLSearchParams(window.location.search);
    const urlStation = urlParams.get('station');

    if (urlStation) {
        // Режим по URL параметру (для отладки или обратной совместимости)
        stationSlug.value = urlStation;
        deviceStatus.value = 'configured';
        await loadStationInfo();
        fetchOrders();
    } else {
        // Режим привязки устройства
        await initDevice();
    }

    // Запускаем интервалы только если устройство настроено
    if (deviceStatus.value === 'configured') {
        // Инициализируем lastEventId чтобы не показывать старые события
        await initLastEventId();
        checkCancellations();

        // Загружаем стоп-лист
        loadStopList();

        fetchInterval = setInterval(() => {
            if (!document.hidden && deviceStatus.value === 'configured') fetchOrders();
        }, 10000);
        eventsInterval = setInterval(() => {
            if (!document.hidden && deviceStatus.value === 'configured') checkCancellations();
        }, 15000);
        cookingTimeInterval = setInterval(() => {
            orders.value = [...orders.value];
            // Проверяем просроченные заказы каждые 5 секунд
            checkOverdueOrders();
        }, 5000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && deviceStatus.value === 'configured') fetchOrders();
        });

        // Закрытие dropdown при клике вне
        document.addEventListener('click', closeStopListDropdown);
    } else {
        // Периодически проверяем статус устройства (может админ настроил)
        deviceCheckInterval = setInterval(() => {
            if (deviceStatus.value === 'pending') {
                checkDeviceStatus();
            }
        }, 30000); // Каждые 30 секунд
    }
});

onUnmounted(() => {
    clearInterval(timeInterval);
    clearInterval(fetchInterval);
    clearInterval(eventsInterval);
    clearInterval(cookingTimeInterval);
    clearInterval(deviceCheckInterval);
    document.removeEventListener('click', closeStopListDropdown);
});
</script>

<style scoped>
/* Пульсирующий фон для алерта просрочки */
.animate-pulse-bg {
    animation: pulse-bg 1.5s ease-in-out infinite;
}

@keyframes pulse-bg {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

/* Анимация появления модала */
.animate-scale-in {
    animation: scale-in 0.2s ease-out;
}

@keyframes scale-in {
    0% {
        opacity: 0;
        transform: scale(0.9);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

/* Анимация slide-up для toast */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translate(-50%, 20px);
}

.slide-up-enter-to,
.slide-up-leave-from {
    opacity: 1;
    transform: translate(-50%, 0);
}
</style>
