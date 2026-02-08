<template>
    <div class="h-full flex flex-col bg-dark-900" data-testid="delivery-tab">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-3 border-b border-gray-800 bg-dark-900" data-testid="delivery-header">
            <div class="flex items-center gap-4">
                <!-- View Mode Switcher -->
                <ViewModeSwitcher v-model="viewMode" />

                <!-- Compact Mode Toggle -->
                <button
                    @click="compactMode = !compactMode"
                    :class="[
                        'p-2 rounded-lg transition-colors',
                        compactMode ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400 hover:text-white'
                    ]"
                    title="Компактный режим"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                </button>

                <!-- Sound Toggle -->
                <button
                    @click="toggleSound"
                    :class="[
                        'p-2 rounded-lg transition-colors',
                        soundEnabled ? 'bg-green-600 text-white' : 'bg-dark-800 text-gray-400 hover:text-white'
                    ]"
                    :title="soundEnabled ? 'Звук включён' : 'Звук выключен'"
                >
                    <svg v-if="soundEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                    </svg>
                </button>

                <!-- Couriers Panel Toggle -->
                <button
                    @click="showCouriersPanel = !showCouriersPanel"
                    :class="[
                        'p-2 rounded-lg transition-colors flex items-center gap-2',
                        showCouriersPanel ? 'bg-purple-600 text-white' : 'bg-dark-800 text-gray-400 hover:text-white'
                    ]"
                    title="Панель курьеров"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="text-sm hidden xl:inline">Курьеры</span>
                </button>
            </div>

            <div class="flex items-center gap-3">
                <!-- Date Picker -->
                <div class="relative" ref="dateButtonRef">
                    <div class="flex items-center bg-dark-800 rounded-lg">
                        <!-- Prev Day -->
                        <button
                            @click="navigateDate(-1)"
                            class="p-2 text-gray-400 hover:text-white transition-colors"
                            title="Предыдущий день"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Date Button -->
                        <button
                            @click="showDateCalendar = !showDateCalendar"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors hover:bg-dark-700 rounded"
                            :class="!isToday(selectedDate) ? 'text-accent' : 'text-white'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>{{ getDisplayDate(selectedDate) }}</span>
                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Next Day -->
                        <button
                            @click="navigateDate(1)"
                            class="p-2 text-gray-400 hover:text-white transition-colors"
                            title="Следующий день"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Calendar Dropdown -->
                    <Transition name="dropdown">
                        <div
                            v-if="showDateCalendar"
                            class="absolute top-full right-0 mt-2 bg-dark-800 rounded-xl border border-dark-700/50 shadow-2xl z-50 p-4 w-72"
                            @click.stop
                        >
                            <!-- Backdrop -->
                            <div class="fixed inset-0 z-[-1]" @click="showDateCalendar = false"></div>

                            <!-- Calendar Header -->
                            <div class="flex items-center justify-between mb-4">
                                <button
                                    @click="calendarPrevMonth"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-dark-700 text-gray-400 hover:text-white transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <span class="text-white font-semibold text-sm">{{ calendarMonthYear }}</span>
                                <button
                                    @click="calendarNextMonth"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-dark-700 text-gray-400 hover:text-white transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Weekdays -->
                            <div class="grid grid-cols-7 gap-1 mb-2">
                                <div v-for="day in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="day" class="text-center text-xs text-gray-500 py-1 font-medium">
                                    {{ day }}
                                </div>
                            </div>

                            <!-- Days Grid -->
                            <div class="grid grid-cols-7 gap-1">
                                <button
                                    v-for="day in calendarDays"
                                    :key="day.date"
                                    @click="selectCalendarDate(day)"
                                    :disabled="day.disabled"
                                    :class="[
                                        'h-11 rounded-lg text-xs font-medium transition-colors flex flex-col items-center justify-center',
                                        day.isToday && !day.isSelected ? 'ring-2 ring-accent ring-inset' : '',
                                        day.isSelected ? 'bg-accent text-white' : '',
                                        day.isCurrentMonth && !day.disabled && !day.isSelected ? 'text-gray-300 hover:bg-dark-700 hover:text-white' : '',
                                        !day.isCurrentMonth ? 'text-gray-700' : '',
                                        day.disabled ? 'text-gray-700 cursor-not-allowed' : '',
                                        day.isWeekend && !day.isSelected && day.isCurrentMonth ? 'text-red-400 hover:text-red-300' : ''
                                    ]"
                                >
                                    <span>{{ day.day }}</span>
                                    <span
                                        v-if="day.isCurrentMonth && getOrdersCountForDate(day.date) > 0"
                                        :class="[
                                            'text-[9px] leading-none',
                                            day.isSelected ? 'text-white/70' : 'text-gray-500'
                                        ]"
                                    >
                                        {{ getOrdersCountForDate(day.date) }}
                                    </span>
                                </button>
                            </div>

                            <!-- Quick Select Buttons -->
                            <div class="flex gap-2 mt-4 pt-3 border-t border-dark-700">
                                <button
                                    @click="selectQuickDate('yesterday')"
                                    class="flex-1 py-2 text-xs rounded-lg font-medium transition-colors bg-dark-800 text-gray-400 hover:bg-dark-700 hover:text-white"
                                >
                                    Вчера
                                </button>
                                <button
                                    @click="selectQuickDate('today')"
                                    :class="[
                                        'flex-1 py-2 text-xs rounded-lg font-medium transition-colors',
                                        isToday(selectedDate) ? 'bg-accent text-white' : 'bg-dark-800 text-gray-400 hover:bg-dark-700 hover:text-white'
                                    ]"
                                >
                                    Сегодня
                                </button>
                                <button
                                    @click="selectQuickDate('tomorrow')"
                                    class="flex-1 py-2 text-xs rounded-lg font-medium transition-colors bg-dark-800 text-gray-400 hover:bg-dark-700 hover:text-white"
                                >
                                    Завтра
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Search -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        placeholder="Поиск... (Ctrl+F)"
                        data-testid="delivery-search"
                        class="w-48 lg:w-64 bg-dark-800 border border-gray-700 rounded-lg pl-10 pr-4 py-2 text-sm focus:border-accent focus:outline-none"
                    />
                </div>

                <!-- Filters -->
                <DeliveryFilters
                    v-model="filters"
                    :couriers="couriers"
                />

                <!-- Refresh -->
                <button
                    @click="loadOrders"
                    class="p-2 bg-dark-800 hover:bg-dark-700 rounded-lg text-gray-400 hover:text-white transition-colors"
                    title="Обновить (F5)"
                >
                    <svg class="w-5 h-5" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>

                <!-- New Order Button -->
                <button
                    @click="showNewOrderModal = true"
                    data-testid="new-delivery-order-btn"
                    class="px-4 py-2 bg-accent hover:bg-blue-600 rounded-lg text-white font-medium transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline">Новый заказ</span>
                    <kbd class="hidden lg:inline-block ml-2 px-1.5 py-0.5 bg-white/20 rounded text-xs">N</kbd>
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Orders Area with Bottom Drawer -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Main Orders View -->
                <div class="flex-1 overflow-hidden">
                    <!-- Loading -->
                    <div v-if="loading && orders.length === 0" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <div class="animate-spin w-10 h-10 border-4 border-accent border-t-transparent rounded-full mx-auto mb-4"></div>
                            <p class="text-gray-400">Загрузка заказов...</p>
                        </div>
                    </div>

                    <!-- Scheduled Orders Section (Preorders with time) -->
                    <div v-if="scheduledOrders.length > 0 && !loading" class="border-b border-dark-700 bg-dark-800/50">
                        <div class="px-4 py-2">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm font-medium text-orange-400">Предзаказы ко времени</span>
                                <span class="px-1.5 py-0.5 bg-orange-500/20 text-orange-400 rounded text-xs font-medium">
                                    {{ scheduledOrders.length }}
                                </span>
                            </div>
                            <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                                <div
                                    v-for="order in scheduledOrders"
                                    :key="order.id"
                                    @click="selectOrder(order)"
                                    :class="[
                                        'flex-shrink-0 px-3 py-2 rounded-lg cursor-pointer transition-all border',
                                        selectedOrder?.id === order.id
                                            ? 'bg-accent/20 border-accent'
                                            : 'bg-dark-800 border-dark-700 hover:border-dark-600'
                                    ]"
                                >
                                    <div class="flex items-center gap-3">
                                        <!-- Time badge with urgency color -->
                                        <div :class="['px-2 py-1 rounded-lg text-xs font-bold', getUrgencyClass(order)]">
                                            {{ getScheduledTimeDisplay(order) }}
                                        </div>
                                        <!-- Order info -->
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-white text-sm font-medium">{{ order.order_number || order.daily_number }}</span>
                                                <span class="text-gray-500 text-xs">{{ order.type === 'pickup' ? 'Самовывоз' : '' }}</span>
                                            </div>
                                            <div class="text-gray-400 text-xs truncate max-w-[150px]">
                                                {{ order.customer?.name || order.customer_name || order.phone }}
                                            </div>
                                        </div>
                                        <!-- Countdown -->
                                        <div :class="['text-xs font-medium whitespace-nowrap', getUrgencyClass(order).replace('bg-', 'text-').split(' ')[0].replace('text-', 'text-')]">
                                            {{ getTimeUntilDelivery(order)?.text }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kanban View -->
                    <DeliveryKanban
                        v-if="viewMode === 'kanban' && !loading"
                        :orders="activeOrders"
                        :selected-order-id="selectedOrder?.id"
                        :compact-mode="compactMode"
                        @select-order="selectOrder"
                        @assign-courier="openCourierModal"
                        @status-change="handleStatusChange"
                    />

                    <!-- Table View -->
                    <DeliveryTable
                        v-if="viewMode !== 'kanban' && !loading"
                        :orders="filteredOrders"
                        :selected-order-id="selectedOrder?.id"
                        @select-order="selectOrder"
                        @assign-courier="openCourierModal"
                        @status-change="handleStatusChange"
                    />
                </div>

                <!-- Completed Orders Drawer (only in Kanban) -->
                <div
                    v-if="viewMode === 'kanban' && completedOrders.length > 0"
                    class="border-t border-gray-700 bg-dark-800/80 backdrop-blur-sm"
                >
                    <!-- Drawer Header with Mini Stats -->
                    <button
                        @click="completedDrawerOpen = !completedDrawerOpen"
                        class="w-full px-4 py-2 flex items-center justify-between hover:bg-dark-700/50 transition-colors"
                    >
                        <div class="flex items-center gap-4">
                            <!-- Toggle icon -->
                            <svg
                                class="w-4 h-4 text-gray-500 transition-transform duration-200"
                                :class="{ 'rotate-180': completedDrawerOpen }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>

                            <!-- Title & Count -->
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm text-gray-300">Завершено</span>
                                <span class="px-1.5 py-0.5 bg-green-600/20 text-green-400 rounded text-xs font-medium">
                                    {{ completedOrders.length }}
                                </span>
                            </div>

                            <!-- Mini Stats (when collapsed) -->
                            <div v-if="!completedDrawerOpen" class="hidden sm:flex items-center gap-3 text-xs text-gray-500">
                                <span class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                    Доставка: {{ completedDeliveryCount }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                                    Самовывоз: {{ completedPickupCount }}
                                </span>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-green-400">
                                {{ formatPrice(completedOrdersTotal) }} ₽
                            </span>
                            <span v-if="!completedDrawerOpen" class="text-xs text-gray-500">
                                за сегодня
                            </span>
                        </div>
                    </button>

                    <!-- Drawer Content - Table -->
                    <transition name="drawer">
                        <div
                            v-if="completedDrawerOpen"
                            :class="['overflow-y-auto border-t border-gray-700/50', drawerHeightClass]"
                        >
                            <!-- Table Header -->
                            <div class="grid grid-cols-12 gap-2 px-4 py-2 text-xs text-gray-500 uppercase tracking-wide bg-dark-900/50 sticky top-0">
                                <div class="col-span-1">Время</div>
                                <div class="col-span-2">Номер</div>
                                <div class="col-span-3">Клиент</div>
                                <div class="col-span-2 text-right">Сумма</div>
                                <div class="col-span-2">Оплата</div>
                                <div class="col-span-2">Тип</div>
                            </div>

                            <!-- Table Rows -->
                            <div
                                v-for="order in completedOrders"
                                :key="order.id"
                                @click="selectOrder(order)"
                                class="grid grid-cols-12 gap-2 px-4 py-2 text-sm hover:bg-dark-700/50 cursor-pointer transition-colors border-b border-gray-800/50 last:border-b-0"
                            >
                                <div class="col-span-1 text-gray-500 font-mono text-xs">
                                    {{ formatTime(order.paid_at || order.delivered_at) }}
                                </div>
                                <div class="col-span-2 text-gray-400 font-mono text-xs">
                                    #{{ order.order_number }}
                                </div>
                                <div class="col-span-3 text-white truncate">
                                    {{ order.customer?.name || order.customer_name || 'Клиент' }}
                                </div>
                                <div class="col-span-2 text-right font-medium text-green-400">
                                    {{ formatPrice(order.total) }} ₽
                                </div>
                                <div class="col-span-2">
                                    <span class="text-xs text-gray-400">
                                        {{ order.payment_method === 'card' ? '💳 Карта' : '💵 Нал' }}
                                    </span>
                                </div>
                                <div class="col-span-2">
                                    <span
                                        :class="[
                                            'px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wide',
                                            order.type === 'delivery'
                                                ? 'bg-purple-500/20 text-purple-400'
                                                : 'bg-orange-500/20 text-orange-400'
                                        ]"
                                    >
                                        {{ order.type === 'delivery' ? 'Доставка' : 'Самовывоз' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>

            <!-- Couriers Panel -->
            <transition name="slide-left">
                <div
                    v-if="showCouriersPanel"
                    class="w-72 xl:w-80 bg-dark-800 border-l border-gray-700 flex flex-col overflow-hidden"
                >
                    <!-- Panel Header -->
                    <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="font-medium text-white">Курьеры</span>
                        </div>
                        <span class="px-2 py-0.5 bg-green-600/20 text-green-400 rounded text-xs font-medium">
                            {{ availableCouriersCount }}/{{ couriers.length }} свободно
                        </span>
                    </div>

                    <!-- Couriers List -->
                    <div class="flex-1 overflow-y-auto p-3 space-y-2">
                        <div
                            v-for="courier in couriersWithStats"
                            :key="courier.id"
                            class="bg-dark-900 rounded-lg p-3 hover:bg-dark-700 transition-colors cursor-pointer"
                            @click="filterByCourier(courier.id)"
                        >
                            <div class="flex items-center gap-3">
                                <!-- Avatar -->
                                <div
                                    :class="[
                                        'w-10 h-10 rounded-full flex items-center justify-center text-white font-medium',
                                        courier.courier_status === 'available' ? 'bg-green-600' : 'bg-yellow-600'
                                    ]"
                                >
                                    {{ courier.name?.charAt(0) || 'К' }}
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-white truncate">{{ courier.name }}</p>
                                    <p class="text-xs text-gray-500">{{ courier.phone }}</p>
                                </div>

                                <!-- Status Badge -->
                                <div class="text-right">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-xs font-medium',
                                            courier.courier_status === 'available'
                                                ? 'bg-green-600/20 text-green-400'
                                                : 'bg-yellow-600/20 text-yellow-400'
                                        ]"
                                    >
                                        {{ courier.courier_status === 'available' ? 'Свободен' : 'Занят' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Active Orders -->
                            <div v-if="courier.activeOrders > 0" class="mt-2 pt-2 border-t border-gray-700">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-400">Активных заказов:</span>
                                    <span class="font-medium text-white">{{ courier.activeOrders }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm mt-1">
                                    <span class="text-gray-400">На сумму:</span>
                                    <span class="font-medium text-green-400">{{ formatPrice(courier.activeTotal) }} ₽</span>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="mt-2 flex gap-2">
                                <button
                                    @click.stop="callCourier(courier)"
                                    class="flex-1 py-1.5 bg-dark-700 hover:bg-dark-600 rounded text-xs text-gray-300 transition-colors flex items-center justify-center gap-1"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    Позвонить
                                </button>
                                <button
                                    v-if="courier.activeOrders > 0"
                                    @click.stop="printCourierRoute(courier)"
                                    class="flex-1 py-1.5 bg-dark-700 hover:bg-dark-600 rounded text-xs text-gray-300 transition-colors flex items-center justify-center gap-1"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Маршрут
                                </button>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="couriers.length === 0" class="flex flex-col items-center justify-center h-40 text-gray-500">
                            <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-sm">Нет курьеров</span>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Order Detail Sidebar -->
        <Teleport to="body">
            <transition name="slide">
                <div v-if="selectedOrder" class="fixed inset-y-0 right-0 w-96 bg-dark-900 border-l border-gray-800 shadow-2xl z-50 flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-800">
                        <div>
                            <h2 class="text-lg font-semibold">Заказ #{{ selectedOrder.order_number || selectedOrder.id }}</h2>
                            <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusClass(selectedOrder.delivery_status)]">
                                {{ getStatusLabel(selectedOrder.delivery_status) }}
                            </span>
                        </div>
                        <button @click="selectedOrder = null" class="p-2 hover:bg-dark-800 rounded-lg text-gray-400" title="Закрыть (Esc)">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <!-- Customer -->
                        <div class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 uppercase mb-2">Клиент</p>
                            <!-- Если клиент есть в базе - показываем кликабельную кнопку -->
                            <div v-if="selectedOrder.customer?.id || selectedOrder.customer_id" class="flex items-center gap-2">
                                <button
                                    ref="customerCardAnchorRef"
                                    @click="openOrderCustomerCard"
                                    class="flex items-center gap-2 group"
                                >
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-xs font-semibold">{{ ((selectedOrder.customer?.name || selectedOrder.customer_name || 'К')[0]).toUpperCase() }}</span>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-medium text-white transition-colors group-hover:text-gray-300">{{ selectedOrder.customer?.name || selectedOrder.customer_name || 'Клиент' }}</p>
                                        <p class="text-accent text-sm">{{ selectedOrder.phone }}</p>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-500 transition-all group-hover:translate-x-1 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </div>
                            <!-- Если клиент не в базе - просто текст -->
                            <template v-else>
                                <p class="font-medium">{{ selectedOrder.customer?.name || selectedOrder.customer_name || 'Клиент' }}</p>
                                <a :href="'tel:' + selectedOrder.phone" class="text-accent text-sm">{{ selectedOrder.phone }}</a>
                            </template>
                        </div>

                        <!-- Address -->
                        <div v-if="selectedOrder.type === 'delivery'" class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 uppercase mb-2">Адрес доставки</p>
                            <p class="text-sm">{{ selectedOrder.delivery_address }}</p>
                            <p v-if="selectedOrder.delivery_notes" class="text-xs text-gray-400 mt-2">{{ selectedOrder.delivery_notes }}</p>
                        </div>

                        <!-- Delivery Time -->
                        <div class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 uppercase mb-2">Время доставки</p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span v-if="selectedOrder.is_asap || !selectedOrder.scheduled_at" class="text-green-400 font-medium">
                                        Ближайшее время
                                    </span>
                                    <span v-else class="font-medium text-white">
                                        {{ formatScheduledDateTime(selectedOrder.scheduled_at) }}
                                    </span>
                                </div>
                                <span
                                    v-if="selectedOrder.scheduled_at && !selectedOrder.is_asap"
                                    :class="getDeliveryTimeUrgencyClass(selectedOrder)"
                                    class="px-2 py-1 rounded text-xs font-medium"
                                >
                                    {{ getDeliveryTimeCountdown(selectedOrder) }}
                                </span>
                            </div>
                        </div>

                        <!-- Courier -->
                        <div v-if="selectedOrder.courier" class="bg-dark-800 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs text-gray-500 uppercase">Курьер</p>
                                <button
                                    @click="printCourierRoute(selectedOrder.courier)"
                                    class="text-xs text-accent hover:text-blue-400"
                                    title="Печать маршрутного листа"
                                >
                                    Маршрутный лист
                                </button>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-accent rounded-full flex items-center justify-center text-white font-medium">
                                    {{ selectedOrder.courier.name?.charAt(0) || 'К' }}
                                </div>
                                <div>
                                    <p class="font-medium">{{ selectedOrder.courier.name }}</p>
                                    <a :href="'tel:' + selectedOrder.courier.phone" class="text-accent text-sm">{{ selectedOrder.courier.phone }}</a>
                                </div>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 uppercase mb-3">Позиции заказа</p>
                            <div class="space-y-2">
                                <div v-for="item in selectedOrder.items" :key="item.id" class="flex justify-between text-sm">
                                    <span class="text-gray-300">{{ item.dish?.name || item.name }} x{{ item.quantity }}</span>
                                    <span class="text-white">{{ formatPrice(item.price * item.quantity) }} ₽</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-700 space-y-2">
                                <!-- Сумма товаров -->
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Сумма товаров:</span>
                                    <span class="text-white">{{ formatPrice(selectedOrder.subtotal || getOrderSubtotal(selectedOrder)) }} ₽</span>
                                </div>
                                <!-- Доставка -->
                                <div v-if="selectedOrder.delivery_fee > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-400">Доставка:</span>
                                    <span class="text-white">{{ formatPrice(selectedOrder.delivery_fee) }} ₽</span>
                                </div>
                                <div v-else-if="selectedOrder.type === 'delivery'" class="flex justify-between text-sm">
                                    <span class="text-gray-400">Доставка:</span>
                                    <span class="text-green-400">Бесплатно</span>
                                </div>
                                <!-- Скидка -->
                                <div v-if="selectedOrder.discount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-400">Скидка:</span>
                                    <span class="text-green-400">-{{ formatPrice(selectedOrder.discount) }} ₽</span>
                                </div>
                                <!-- Бонусы -->
                                <div v-if="selectedOrder.bonus_used > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-400">Бонусы:</span>
                                    <span class="text-yellow-400">-{{ formatPrice(selectedOrder.bonus_used) }} ₽</span>
                                </div>
                                <!-- Итого -->
                                <div class="flex justify-between pt-2 border-t border-gray-700">
                                    <span class="font-medium">Итого:</span>
                                    <span class="text-xl font-bold text-white">{{ formatPrice(selectedOrder.total) }} ₽</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment -->
                        <div class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 uppercase mb-2">Оплата</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">{{ getPaymentMethodLabel(selectedOrder.payment_method) }}</span>
                                <span :class="selectedOrder.payment_status === 'paid' ? 'text-green-400' : 'text-yellow-400'">
                                    {{ selectedOrder.payment_status === 'paid' ? 'Оплачен' : 'Ожидает оплаты' }}
                                </span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="selectedOrder.notes" class="bg-dark-800 rounded-lg p-4">
                            <p class="text-xs text-gray-500 uppercase mb-2">Комментарий</p>
                            <p class="text-sm text-gray-300">{{ selectedOrder.notes }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="p-4 border-t border-gray-800 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-if="selectedOrder.delivery_status === 'pending'"
                                @click="updateStatus(selectedOrder, 'preparing')"
                                :disabled="actionLoading"
                                class="py-2 bg-orange-600 hover:bg-orange-700 rounded-lg text-sm text-white font-medium disabled:opacity-50"
                            >
                                На кухню
                            </button>
                            <button
                                v-if="selectedOrder.delivery_status === 'preparing'"
                                @click="updateStatus(selectedOrder, 'ready')"
                                :disabled="actionLoading"
                                class="py-2 bg-cyan-600 hover:bg-cyan-700 rounded-lg text-sm text-white font-medium disabled:opacity-50"
                            >
                                Готов
                            </button>
                            <button
                                v-if="selectedOrder.delivery_status === 'ready' && selectedOrder.type === 'delivery'"
                                @click="openCourierModal(selectedOrder)"
                                class="py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm text-white font-medium"
                            >
                                Назначить курьера
                            </button>
                            <button
                                v-if="selectedOrder.delivery_status === 'ready' && selectedOrder.type === 'pickup'"
                                @click="updateStatus(selectedOrder, 'delivered')"
                                :disabled="actionLoading"
                                class="py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm text-white font-medium disabled:opacity-50"
                            >
                                Выдан
                            </button>
                            <button
                                v-if="selectedOrder.delivery_status === 'in_transit'"
                                @click="updateStatus(selectedOrder, 'delivered')"
                                :disabled="actionLoading"
                                class="py-2 bg-green-600 hover:bg-green-700 rounded-lg text-sm text-white font-medium disabled:opacity-50"
                            >
                                Доставлен
                            </button>
                            <button
                                v-if="selectedOrder.payment_status !== 'paid'"
                                @click="showPaymentModal = true"
                                class="py-2 bg-accent hover:bg-blue-600 rounded-lg text-sm text-white font-medium"
                            >
                                Оплата
                            </button>
                            <button
                                @click="printOrder"
                                class="py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-sm text-gray-300"
                            >
                                Печать
                            </button>
                        </div>
                        <button
                            v-if="!['delivered', 'cancelled'].includes(selectedOrder.delivery_status)"
                            @click="cancelOrder"
                            :disabled="actionLoading"
                            class="w-full py-2 bg-red-600/20 hover:bg-red-600/30 rounded-lg text-sm text-red-400 font-medium disabled:opacity-50"
                        >
                            Отменить заказ
                        </button>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- New Order Modal -->
        <NewDeliveryOrderModal
            :show="showNewOrderModal"
            @close="showNewOrderModal = false"
            @created="handleOrderCreated"
        />

        <!-- Courier Modal -->
        <Teleport to="body">
            <div v-if="showCourierModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
                <div class="bg-dark-900 rounded-xl w-full max-w-md">
                    <div class="flex items-center justify-between p-4 border-b border-gray-800">
                        <h3 class="font-semibold">Назначить курьера</h3>
                        <button @click="showCourierModal = false" class="text-gray-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-4 max-h-96 overflow-y-auto space-y-2">
                        <div
                            v-for="courier in couriers"
                            :key="courier.id"
                            @click="assignCourier(courier.id)"
                            class="flex items-center justify-between p-3 bg-dark-800 rounded-lg cursor-pointer hover:bg-dark-700"
                        >
                            <div class="flex items-center gap-3">
                                <div :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center text-white font-medium',
                                    courier.courier_status === 'available' ? 'bg-green-600' : 'bg-yellow-600'
                                ]">
                                    {{ courier.name?.charAt(0) || 'К' }}
                                </div>
                                <div>
                                    <p class="font-medium">{{ courier.name }}</p>
                                    <p class="text-sm text-gray-400">{{ courier.phone }}</p>
                                </div>
                            </div>
                            <span :class="[
                                'px-2 py-1 rounded text-xs',
                                courier.courier_status === 'available' ? 'bg-green-600/20 text-green-400' : 'bg-yellow-600/20 text-yellow-400'
                            ]">
                                {{ courier.courier_status === 'available' ? 'Свободен' : 'Занят' }}
                            </span>
                        </div>

                        <div v-if="couriers.length === 0" class="text-center text-gray-500 py-8">
                            Нет доступных курьеров
                        </div>
                    </div>

                    <div class="p-4 border-t border-gray-800">
                        <button
                            @click="autoAssignCourier"
                            :disabled="actionLoading || !couriers.some(c => c.courier_status === 'available')"
                            class="w-full py-2 bg-accent hover:bg-blue-600 rounded-lg text-white font-medium disabled:opacity-50"
                        >
                            Автоматически назначить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Payment Modal -->
        <UnifiedPaymentModal
            ref="paymentModalRef"
            v-model="showPaymentModal"
            :total="Number(selectedOrder?.total) || 0"
            :subtotal="Number(selectedOrder?.subtotal || selectedOrder?.total) || 0"
            :discount="Number(selectedOrder?.discount_amount) || 0"
            :loyaltyDiscount="Number(selectedOrder?.loyalty_discount_amount) || 0"
            :loyaltyLevelName="selectedOrder?.loyalty_level?.name || ''"
            :deliveryFee="Number(selectedOrder?.delivery_fee) || 0"
            :paid-amount="Number(selectedOrder?.prepayment) || 0"
            :customer="selectedOrder?.customer || null"
            :initial-method="selectedOrder?.payment_method || 'cash'"
            :bonusSettings="bonusSettings"
            :roundAmounts="roundAmounts"
            @confirm="handlePaymentConfirm"
        />

        <!-- Route Print Modal -->
        <Teleport to="body">
            <div v-if="showRouteModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-auto text-black">
                    <div class="p-6" ref="routePrintRef">
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold">Маршрутный лист</h2>
                            <p class="text-gray-600">{{ new Date().toLocaleDateString('ru-RU') }}</p>
                        </div>

                        <div class="mb-6 p-4 bg-gray-100 rounded-lg">
                            <h3 class="font-bold text-lg">{{ routeCourier?.name }}</h3>
                            <p class="text-gray-600">{{ routeCourier?.phone }}</p>
                        </div>

                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b-2 border-gray-300">
                                    <th class="text-left py-2 px-3">#</th>
                                    <th class="text-left py-2 px-3">Адрес</th>
                                    <th class="text-left py-2 px-3">Клиент</th>
                                    <th class="text-right py-2 px-3">Сумма</th>
                                    <th class="text-center py-2 px-3">Оплата</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(order, idx) in courierOrders" :key="order.id" class="border-b">
                                    <td class="py-3 px-3">{{ idx + 1 }}</td>
                                    <td class="py-3 px-3">{{ order.delivery_address }}</td>
                                    <td class="py-3 px-3">
                                        <div>{{ order.customer?.name || order.customer_name }}</div>
                                        <div class="text-sm text-gray-500">{{ order.phone }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold">{{ formatPrice(order.total) }} ₽</td>
                                    <td class="py-3 px-3 text-center">
                                        <span :class="order.payment_status === 'paid' ? 'text-green-600' : 'text-red-600'">
                                            {{ order.payment_status === 'paid' ? 'Оплачен' : 'Наличные' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-bold">
                                    <td colspan="3" class="py-3 px-3">Итого заказов: {{ courierOrders.length }}</td>
                                    <td class="py-3 px-3 text-right">{{ formatPrice(courierOrdersTotal) }} ₽</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="mt-8 pt-4 border-t text-center text-gray-500 text-sm">
                            Подпись курьера: ________________
                        </div>
                    </div>

                    <div class="p-4 border-t flex gap-3 bg-gray-50">
                        <button
                            @click="showRouteModal = false"
                            class="flex-1 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg font-medium"
                        >
                            Закрыть
                        </button>
                        <button
                            @click="doPrintRoute"
                            class="flex-1 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium"
                        >
                            Печать
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Cancel Order Modal -->
        <Teleport to="body">
            <div v-if="showCancelModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-60 p-4">
                <div class="bg-dark-900 rounded-xl w-full max-w-md border border-dark-700">
                    <!-- Header -->
                    <div class="p-4 border-b border-dark-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Отмена заказа #{{ selectedOrder?.order_number || selectedOrder?.id }}</h3>
                        <button v-if="cancelMode && !canCancelOrders" @click="cancelMode = null" class="text-gray-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-4 space-y-4">
                        <!-- Payment info (для оплаченных заказов) -->
                        <div v-if="selectedOrder?.payment_status === 'paid' || selectedOrder?.prepayment > 0" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3">
                            <div class="flex items-center gap-2 text-yellow-400 mb-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="font-medium">Заказ оплачен</span>
                            </div>
                            <p class="text-sm text-gray-400">
                                Сумма к возврату: <span class="text-white font-semibold">{{ formatPrice(Number(selectedOrder?.prepayment) > 0 ? selectedOrder.prepayment : selectedOrder?.total) }} ₽</span>
                            </p>
                        </div>

                        <!-- Выбор режима (для не-менеджеров) -->
                        <div v-if="cancelMode === null" class="space-y-3">
                            <p class="text-gray-400 text-sm">Выберите способ отмены заказа:</p>
                            <button
                                @click="selectCancelMode('pin')"
                                class="w-full p-4 bg-dark-800 hover:bg-dark-700 rounded-lg text-left transition-colors border border-dark-600 hover:border-accent"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-accent/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">Ввести PIN менеджера</div>
                                        <div class="text-gray-500 text-sm">Отмена будет выполнена сразу</div>
                                    </div>
                                </div>
                            </button>
                            <button
                                @click="selectCancelMode('request')"
                                class="w-full p-4 bg-dark-800 hover:bg-dark-700 rounded-lg text-left transition-colors border border-dark-600 hover:border-orange-500"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">Отправить заявку</div>
                                        <div class="text-gray-500 text-sm">Заказ будет отменён после одобрения</div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <!-- Режим PIN -->
                        <template v-if="cancelMode === 'pin'">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">PIN менеджера</label>
                                <input
                                    v-model="cancelManagerPin"
                                    type="password"
                                    maxlength="4"
                                    placeholder="****"
                                    class="w-full px-4 py-3 bg-dark-800 border border-dark-600 rounded-lg text-white text-center text-2xl tracking-widest focus:border-accent focus:outline-none"
                                    :class="cancelPinError ? 'border-red-500' : ''"
                                />
                                <p v-if="cancelPinError" class="text-red-400 text-sm mt-1">{{ cancelPinError }}</p>
                            </div>

                            <!-- Refund method (для оплаченных) -->
                            <div v-if="selectedOrder?.payment_status === 'paid' || selectedOrder?.prepayment > 0">
                                <label class="block text-sm text-gray-400 mb-2">Способ возврата</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        @click="cancelRefundMethod = 'cash'"
                                        :class="['py-3 rounded-lg font-medium transition-all', cancelRefundMethod === 'cash' ? 'bg-green-600 text-white' : 'bg-dark-800 text-gray-400 hover:bg-dark-700']"
                                    >
                                        Наличные
                                    </button>
                                    <button
                                        @click="cancelRefundMethod = 'card'"
                                        :class="['py-3 rounded-lg font-medium transition-all', cancelRefundMethod === 'card' ? 'bg-blue-600 text-white' : 'bg-dark-800 text-gray-400 hover:bg-dark-700']"
                                    >
                                        На карту
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Причина отмены</label>
                                <textarea v-model="cancelReason" rows="2" placeholder="Укажите причину..." class="w-full px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white resize-none focus:border-accent focus:outline-none"></textarea>
                            </div>
                        </template>

                        <!-- Режим заявки -->
                        <template v-if="cancelMode === 'request'">
                            <div class="bg-orange-500/10 border border-orange-500/30 rounded-lg p-3">
                                <p class="text-orange-400 text-sm">Заявка будет отправлена менеджеру на одобрение. Заказ будет отменён после подтверждения.</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Причина отмены <span class="text-red-400">*</span></label>
                                <textarea v-model="cancelReason" rows="3" placeholder="Обязательно укажите причину..." class="w-full px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white resize-none focus:border-accent focus:outline-none" :class="cancelPinError ? 'border-red-500' : ''"></textarea>
                                <p v-if="cancelPinError" class="text-red-400 text-sm mt-1">{{ cancelPinError }}</p>
                            </div>
                        </template>

                        <!-- Режим direct (менеджер) -->
                        <template v-if="cancelMode === 'direct'">
                            <!-- Refund method (для оплаченных) -->
                            <div v-if="selectedOrder?.payment_status === 'paid' || selectedOrder?.prepayment > 0">
                                <label class="block text-sm text-gray-400 mb-2">Способ возврата</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        @click="cancelRefundMethod = 'cash'"
                                        :class="['py-3 rounded-lg font-medium transition-all', cancelRefundMethod === 'cash' ? 'bg-green-600 text-white' : 'bg-dark-800 text-gray-400 hover:bg-dark-700']"
                                    >
                                        Наличные
                                    </button>
                                    <button
                                        @click="cancelRefundMethod = 'card'"
                                        :class="['py-3 rounded-lg font-medium transition-all', cancelRefundMethod === 'card' ? 'bg-blue-600 text-white' : 'bg-dark-800 text-gray-400 hover:bg-dark-700']"
                                    >
                                        На карту
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Причина отмены</label>
                                <textarea v-model="cancelReason" rows="2" placeholder="Укажите причину..." class="w-full px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white resize-none focus:border-accent focus:outline-none"></textarea>
                            </div>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-dark-700 flex gap-3">
                        <button
                            @click="closeCancelModal"
                            class="flex-1 py-2.5 bg-dark-800 hover:bg-dark-700 rounded-lg text-gray-300 font-medium transition-colors"
                        >
                            Закрыть
                        </button>
                        <button
                            v-if="cancelMode"
                            @click="confirmCancel"
                            :disabled="cancelLoading || (cancelMode === 'pin' && cancelManagerPin.length < 4) || (cancelMode === 'request' && !cancelReason.trim())"
                            class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 disabled:bg-red-600/50 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
                        >
                            <span v-if="cancelLoading">Обработка...</span>
                            <span v-else-if="cancelMode === 'request'">Отправить заявку</span>
                            <span v-else>Отменить заказ</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Customer Info Card -->
        <CustomerInfoCard
            :show="showCustomerCard"
            :customer="orderCustomerData"
            :anchor-el="customerCardAnchorRef"
            @close="showCustomerCard = false"
            @update="handleCustomerUpdate"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { usePosStore } from '../../stores/pos';
import { useRealtimeEvents } from '../../../shared/composables/useRealtimeEvents.js';
import { formatAmount } from '@/utils/formatAmount.js';
import ViewModeSwitcher from '../delivery/ViewModeSwitcher.vue';
import DeliveryKanban from '../delivery/DeliveryKanban.vue';
import DeliveryTable from '../delivery/DeliveryTable.vue';
import DeliveryFilters from '../delivery/DeliveryFilters.vue';
import CustomerInfoCard from '../../../components/CustomerInfoCard.vue';
import NewDeliveryOrderModal from '../delivery/NewDeliveryOrderModal.vue';
import UnifiedPaymentModal from '../../../components/UnifiedPaymentModal.vue';

const authStore = useAuthStore();
const posStore = usePosStore();

// Real-time subscription for instant delivery updates (using centralized store)
// Note: The centralized RealtimeStore is already connected by POS App.vue
// We just subscribe to events we care about - auto-cleanup happens on unmount
const { on: subscribeEvent, connected: realtimeConnected } = useRealtimeEvents();

const setupRealtimeSubscription = () => {
    // Handle delivery events
    subscribeEvent('delivery_new', () => {
        console.log('[DeliveryTab] New delivery order, refreshing...');
        loadOrders();
        loadCouriers();
        playNotificationSound();
    });

    subscribeEvent('delivery_status', () => {
        console.log('[DeliveryTab] Delivery status changed, refreshing...');
        loadOrders();
    });

    subscribeEvent('courier_assigned', () => {
        console.log('[DeliveryTab] Courier assigned, refreshing...');
        loadOrders();
        loadCouriers();
    });

    subscribeEvent('delivery_problem_created', () => {
        console.log('[DeliveryTab] Delivery problem created, refreshing...');
        loadOrders();
    });

    subscribeEvent('delivery_problem_resolved', () => {
        console.log('[DeliveryTab] Delivery problem resolved, refreshing...');
        loadOrders();
    });

    // Also listen for order status changes (affects delivery orders)
    subscribeEvent('order_status', (data) => {
        // Refresh if this might be a delivery order
        if (orders.value.some(o => o.id === data.order_id)) {
            console.log('[DeliveryTab] Order status changed, refreshing...');
            loadOrders();
        }
    });
};

// Date helper (defined early for use in ref initialization)
const formatDateForInput = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Refs
const searchInput = ref(null);
const routePrintRef = ref(null);
const dateButtonRef = ref(null);

// Date filter
const selectedDate = ref(formatDateForInput(new Date())); // Default to today
const showDateCalendar = ref(false);
const calendarDate = ref(new Date());

// View mode (сохраняем в localStorage)
const viewMode = ref(localStorage.getItem('delivery_view_mode') || 'kanban');
watch(viewMode, (val) => {
    localStorage.setItem('delivery_view_mode', val);
});

// Compact mode
const compactMode = ref(localStorage.getItem('delivery_compact_mode') === 'true');
watch(compactMode, (val) => {
    localStorage.setItem('delivery_compact_mode', val.toString());
});

// Couriers panel visibility
const showCouriersPanel = ref(localStorage.getItem('delivery_couriers_panel') !== 'false');
watch(showCouriersPanel, (val) => {
    localStorage.setItem('delivery_couriers_panel', val.toString());
});

// Completed drawer state
const completedDrawerOpen = ref(false);

// Sound
const soundEnabled = ref(localStorage.getItem('delivery_sound') !== 'false');
let notificationSound = null;

const toggleSound = () => {
    soundEnabled.value = !soundEnabled.value;
    localStorage.setItem('delivery_sound', soundEnabled.value.toString());
    if (soundEnabled.value) {
        playNotificationSound();
    }
};

const playNotificationSound = () => {
    if (!soundEnabled.value) return;

    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.3;

        oscillator.start();
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        oscillator.stop(audioContext.currentTime + 0.5);
    } catch (e) {
        console.log('Sound not available:', e);
    }
};

// State
const loading = ref(false);
const actionLoading = ref(false);
const orders = ref([]);
const couriers = ref([]);
const previousPendingCount = ref(0);

// Filters
const search = ref('');
const filters = ref({
    statuses: [],
    paymentStatus: null,
    type: null,
    courierId: null,
    period: null
});

// Selected order
const selectedOrder = ref(null);

// Customer Info Card
const showCustomerCard = ref(false);
const customerCardAnchorRef = ref(null);
const orderCustomerData = ref(null);

// Modals
const showNewOrderModal = ref(false);
const showCourierModal = ref(false);
const showPaymentModal = ref(false);
const paymentModalRef = ref(null);
const showRouteModal = ref(false);
const bonusSettings = ref(null);
const roundAmounts = ref(false);
const showCancelModal = ref(false);
const courierOrderId = ref(null);
const routeCourier = ref(null);

// Cancel order
const cancelMode = ref(null); // null = выбор, 'pin' = ввод PIN, 'request' = заявка
const cancelManagerPin = ref('');
const cancelRefundMethod = ref('cash');
const cancelReason = ref('');
const cancelLoading = ref(false);
const cancelPinError = ref('');

// Computed - может ли текущий пользователь отменять заказы
const canCancelOrders = computed(() => authStore.canCancelOrders);

// Couriers with stats
const couriersWithStats = computed(() => {
    return couriers.value.map(courier => {
        const activeOrders = orders.value.filter(o =>
            o.courier?.id === courier.id &&
            ['in_transit', 'picked_up'].includes(o.delivery_status)
        );
        return {
            ...courier,
            activeOrders: activeOrders.length,
            activeTotal: activeOrders.reduce((sum, o) => sum + (o.total || 0), 0)
        };
    });
});

// Available couriers count
const availableCouriersCount = computed(() => {
    return couriers.value.filter(c => c.courier_status === 'available').length;
});

// Helper to check if order matches selected date
const orderMatchesDate = (order, dateStr) => {
    // If order has scheduled_at, use that date
    if (order.scheduled_at) {
        // Handle both ISO format "2026-01-17T12:00:00" and simple "2026-01-17 12:00"
        const orderDate = order.scheduled_at.split('T')[0].split(' ')[0];
        return orderDate === dateStr;
    }
    // Otherwise use created_at
    if (order.created_at) {
        const orderDate = order.created_at.split('T')[0].split(' ')[0];
        return orderDate === dateStr;
    }
    return true; // Show if no date info
};

// Completed orders (delivered + paid) - filtered by date
const completedOrders = computed(() => {
    return orders.value.filter(o =>
        o.delivery_status === 'delivered' &&
        o.payment_status === 'paid' &&
        orderMatchesDate(o, selectedDate.value)
    );
});

// Completed orders total
const completedOrdersTotal = computed(() => {
    return completedOrders.value.reduce((sum, o) => sum + Number(o.total || 0), 0);
});

// Completed delivery count
const completedDeliveryCount = computed(() => {
    return completedOrders.value.filter(o => o.type === 'delivery').length;
});

// Completed pickup count
const completedPickupCount = computed(() => {
    return completedOrders.value.filter(o => o.type === 'pickup').length;
});

// Parse scheduled_at without timezone conversion (extract time directly from string)
const parseScheduledTime = (scheduledAt) => {
    if (!scheduledAt) return null;
    // Handle formats: "2026-01-17T20:00:00.000000Z" or "2026-01-17 20:00:00"
    const match = scheduledAt.match(/(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
    if (!match) return null;
    return {
        date: match[1],
        hours: parseInt(match[2]),
        minutes: parseInt(match[3]),
        timeStr: `${match[2]}:${match[3]}`
    };
};

// Scheduled orders (preorders with specific time) - not completed, has scheduled_at and is not ASAP
const scheduledOrders = computed(() => {
    return orders.value.filter(o => {
        // Must have scheduled_at and not be ASAP
        if (!o.scheduled_at || o.is_asap) return false;
        // Must not be completed or cancelled
        if (o.delivery_status === 'delivered' || o.delivery_status === 'cancelled') return false;
        // Must match selected date
        if (!orderMatchesDate(o, selectedDate.value)) return false;
        return true;
    }).sort((a, b) => {
        // Sort by scheduled time
        return new Date(a.scheduled_at) - new Date(b.scheduled_at);
    });
});

// Get time until scheduled delivery (using parseScheduledTime to avoid timezone issues)
const getTimeUntilDelivery = (order) => {
    const parsed = parseScheduledTime(order.scheduled_at);
    if (!parsed) return null;

    const now = new Date();
    const scheduled = new Date(parsed.date + 'T' + parsed.timeStr + ':00');
    const diffMs = scheduled - now;
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 0) return { text: 'Просрочено', urgent: 'overdue' };
    if (diffMins < 30) return { text: `${diffMins} мин`, urgent: 'critical' };
    if (diffMins < 60) return { text: `${diffMins} мин`, urgent: 'warning' };

    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    if (hours < 24) {
        return { text: mins > 0 ? `${hours}ч ${mins}м` : `${hours}ч`, urgent: 'normal' };
    }
    return { text: `${Math.floor(hours / 24)}д ${hours % 24}ч`, urgent: 'normal' };
};

// Get urgency color class for order
const getUrgencyClass = (order) => {
    if (order.is_asap || !order.scheduled_at) return 'bg-gray-500/20 text-gray-400';

    const timeInfo = getTimeUntilDelivery(order);
    if (!timeInfo) return 'bg-gray-500/20 text-gray-400';

    switch (timeInfo.urgent) {
        case 'overdue': return 'bg-red-500/30 text-red-400 animate-pulse';
        case 'critical': return 'bg-red-500/20 text-red-400';
        case 'warning': return 'bg-yellow-500/20 text-yellow-400';
        case 'normal': return 'bg-green-500/20 text-green-400';
        default: return 'bg-gray-500/20 text-gray-400';
    }
};

// Get scheduled time display (without timezone conversion)
const getScheduledTimeDisplay = (order) => {
    const parsed = parseScheduledTime(order.scheduled_at);
    return parsed ? parsed.timeStr : '';
};

// Format scheduled date and time for modal display
const formatScheduledDateTime = (scheduledAt) => {
    const parsed = parseScheduledTime(scheduledAt);
    if (!parsed) return '';

    const today = formatDateForInput(new Date());
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = formatDateForInput(tomorrow);

    let dateLabel = '';
    if (parsed.date === today) {
        dateLabel = 'Сегодня';
    } else if (parsed.date === tomorrowStr) {
        dateLabel = 'Завтра';
    } else {
        const d = new Date(parsed.date + 'T00:00:00');
        dateLabel = d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
    }

    return `${dateLabel} в ${parsed.timeStr}`;
};

// Get time until delivery for modal (using parsed time without timezone issues)
const getDeliveryTimeCountdown = (order) => {
    const parsed = parseScheduledTime(order.scheduled_at);
    if (!parsed) return '';

    // Create date object for comparison (local time)
    const now = new Date();
    const scheduled = new Date(parsed.date + 'T' + parsed.timeStr + ':00');
    const diffMs = scheduled - now;
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 0) return 'Просрочено';
    if (diffMins < 60) return `через ${diffMins} мин`;

    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    if (hours < 24) {
        return mins > 0 ? `через ${hours}ч ${mins}м` : `через ${hours}ч`;
    }
    return `через ${Math.floor(hours / 24)}д`;
};

// Get urgency class for delivery time in modal
const getDeliveryTimeUrgencyClass = (order) => {
    const parsed = parseScheduledTime(order.scheduled_at);
    if (!parsed) return 'bg-gray-500/20 text-gray-400';

    const now = new Date();
    const scheduled = new Date(parsed.date + 'T' + parsed.timeStr + ':00');
    const diffMins = Math.floor((scheduled - now) / 60000);

    if (diffMins < 0) return 'bg-red-500/30 text-red-400';
    if (diffMins < 30) return 'bg-red-500/20 text-red-400';
    if (diffMins < 60) return 'bg-yellow-500/20 text-yellow-400';
    return 'bg-green-500/20 text-green-400';
};

// Dynamic drawer height based on order count
const drawerHeightClass = computed(() => {
    const count = completedOrders.value.length;
    if (count <= 3) return 'max-h-36';       // ~3-4 rows
    if (count <= 6) return 'max-h-56';       // ~6 rows
    if (count <= 10) return 'max-h-80';      // ~10 rows
    if (count <= 15) return 'max-h-[40vh]';  // 40% screen
    return 'max-h-[50vh]';                   // 50% screen
});

// Active orders (non-completed) for Kanban
const activeOrders = computed(() => {
    let result = orders.value.filter(o =>
        !(o.delivery_status === 'delivered' && o.payment_status === 'paid')
    );

    // Apply date filter
    result = result.filter(o => orderMatchesDate(o, selectedDate.value));

    // Apply search filter
    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(o =>
            (o.order_number && o.order_number.toLowerCase().includes(q)) ||
            (o.customer?.name && o.customer.name.toLowerCase().includes(q)) ||
            (o.customer_name && o.customer_name.toLowerCase().includes(q)) ||
            (o.phone && o.phone.includes(q)) ||
            (o.delivery_address && o.delivery_address.toLowerCase().includes(q))
        );
    }

    return result;
});

const filteredOrders = computed(() => {
    let result = [...orders.value];

    // Apply date filter
    result = result.filter(o => orderMatchesDate(o, selectedDate.value));

    // Search
    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(o =>
            (o.order_number && o.order_number.toLowerCase().includes(q)) ||
            (o.customer?.name && o.customer.name.toLowerCase().includes(q)) ||
            (o.customer_name && o.customer_name.toLowerCase().includes(q)) ||
            (o.phone && o.phone.includes(q)) ||
            (o.delivery_address && o.delivery_address.toLowerCase().includes(q))
        );
    }

    // Status filter
    if (filters.value.statuses?.length > 0) {
        result = result.filter(o => filters.value.statuses.includes(o.delivery_status));
    }

    // Payment status filter
    if (filters.value.paymentStatus === 'paid') {
        result = result.filter(o => o.payment_status === 'paid');
    } else if (filters.value.paymentStatus === 'unpaid') {
        result = result.filter(o => o.payment_status !== 'paid');
    }

    // Type filter
    if (filters.value.type) {
        result = result.filter(o => o.type === filters.value.type);
    }

    // Courier filter
    if (filters.value.courierId) {
        result = result.filter(o => o.courier_id === filters.value.courierId || o.courier?.id === filters.value.courierId);
    }

    // Period filter
    if (filters.value.period) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        result = result.filter(o => {
            const orderDate = new Date(o.created_at);
            if (filters.value.period === 'today') {
                return orderDate >= today;
            } else if (filters.value.period === 'yesterday') {
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                return orderDate >= yesterday && orderDate < today;
            } else if (filters.value.period === 'week') {
                const weekAgo = new Date(today);
                weekAgo.setDate(weekAgo.getDate() - 7);
                return orderDate >= weekAgo;
            }
            return true;
        });
    }

    return result;
});

// Courier route orders
const courierOrders = computed(() => {
    if (!routeCourier.value) return [];
    return orders.value.filter(o =>
        o.courier?.id === routeCourier.value.id &&
        ['in_transit', 'picked_up'].includes(o.delivery_status)
    );
});

const courierOrdersTotal = computed(() => {
    return courierOrders.value.reduce((sum, o) => sum + (o.total || 0), 0);
});

// Status config
const statusConfig = {
    pending: { label: 'Новый', class: 'bg-blue-600/20 text-blue-400' },
    preparing: { label: 'Готовится', class: 'bg-orange-600/20 text-orange-400' },
    ready: { label: 'Готов', class: 'bg-cyan-600/20 text-cyan-400' },
    picked_up: { label: 'Забран', class: 'bg-purple-600/20 text-purple-400' },
    in_transit: { label: 'В пути', class: 'bg-purple-600/20 text-purple-400' },
    delivered: { label: 'Доставлен', class: 'bg-green-600/20 text-green-400' },
    cancelled: { label: 'Отменён', class: 'bg-red-600/20 text-red-400' }
};

const getStatusClass = (status) => statusConfig[status]?.class || 'bg-gray-600/20 text-gray-400';
const getStatusLabel = (status) => statusConfig[status]?.label || status;

const getPaymentMethodLabel = (method) => {
    const labels = { cash: 'Наличные', card: 'Карта', online: 'Онлайн' };
    return labels[method] || method;
};

const formatPrice = (price) => formatAmount(price).toLocaleString('ru-RU');

// Подсчёт суммы товаров (без доставки)
const getOrderSubtotal = (order) => {
    if (!order?.items) return 0;
    return order.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
};

// Date helpers
const getDisplayDate = (dateStr) => {
    if (!dateStr) return 'Сегодня';
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (dateStr === formatDateForInput(today)) return 'Сегодня';
    if (dateStr === formatDateForInput(tomorrow)) return 'Завтра';
    if (dateStr === formatDateForInput(yesterday)) return 'Вчера';

    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
};

const isToday = (dateStr) => dateStr === formatDateForInput(new Date());

// Get orders count for a specific date (for calendar display)
const getOrdersCountForDate = (dateStr) => {
    return orders.value.filter(o => {
        if (o.scheduled_at) {
            // Handle both ISO format and simple format
            return o.scheduled_at.split('T')[0].split(' ')[0] === dateStr;
        }
        if (o.created_at) {
            return o.created_at.split('T')[0].split(' ')[0] === dateStr;
        }
        return false;
    }).length;
};

const navigateDate = (direction) => {
    const current = new Date(selectedDate.value);
    current.setDate(current.getDate() + direction);
    selectedDate.value = formatDateForInput(current);
};

const selectQuickDate = (type) => {
    const date = new Date();
    if (type === 'tomorrow') {
        date.setDate(date.getDate() + 1);
    } else if (type === 'yesterday') {
        date.setDate(date.getDate() - 1);
    }
    selectedDate.value = formatDateForInput(date);
    showDateCalendar.value = false;
};

const selectCalendarDate = (day) => {
    if (day.disabled || !day.isCurrentMonth) return;
    selectedDate.value = day.date;
    showDateCalendar.value = false;
};

const calendarPrevMonth = () => {
    const newDate = new Date(calendarDate.value);
    newDate.setMonth(newDate.getMonth() - 1);
    calendarDate.value = newDate;
};

const calendarNextMonth = () => {
    const newDate = new Date(calendarDate.value);
    newDate.setMonth(newDate.getMonth() + 1);
    calendarDate.value = newDate;
};

const calendarMonthYear = computed(() => {
    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    return `${months[calendarDate.value.getMonth()]} ${calendarDate.value.getFullYear()}`;
});

const calendarDays = computed(() => {
    const year = calendarDate.value.getFullYear();
    const month = calendarDate.value.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    let startDay = firstDay.getDay() - 1;
    if (startDay < 0) startDay = 6;

    const days = [];

    // Previous month days
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = startDay - 1; i >= 0; i--) {
        const date = new Date(year, month - 1, prevMonthLastDay - i);
        days.push({
            day: prevMonthLastDay - i,
            date: formatDateForInput(date),
            isCurrentMonth: false,
            isToday: false,
            isSelected: false,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            disabled: true
        });
    }

    // Current month days
    for (let i = 1; i <= lastDay.getDate(); i++) {
        const date = new Date(year, month, i);
        const dateStr = formatDateForInput(date);
        days.push({
            day: i,
            date: dateStr,
            isCurrentMonth: true,
            isToday: date.getTime() === today.getTime(),
            isSelected: selectedDate.value === dateStr,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            disabled: false
        });
    }

    // Next month days
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        const date = new Date(year, month + 1, i);
        days.push({
            day: i,
            date: formatDateForInput(date),
            isCurrentMonth: false,
            isToday: false,
            isSelected: false,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            disabled: true
        });
    }

    return days;
});

// Orders count for other dates (for badge)
const ordersOnOtherDates = computed(() => {
    const todayStr = formatDateForInput(new Date());
    return orders.value.filter(o => {
        if (!o.scheduled_at) return false;
        const orderDate = o.scheduled_at.split(' ')[0];
        return orderDate !== todayStr;
    }).length;
});

const formatTime = (dt) => {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
};

// Filter by courier
const filterByCourier = (courierId) => {
    if (filters.value.courierId === courierId) {
        filters.value.courierId = null;
    } else {
        filters.value.courierId = courierId;
    }
};

// Call courier
const callCourier = (courier) => {
    window.open(`tel:${courier.phone}`, '_self');
};

// API calls
const loadOrders = async () => {
    loading.value = true;
    try {
        const response = await api.orders.getDelivery();
        const newOrders = Array.isArray(response) ? response : (response.data || []);

        // Check for new orders
        const newPendingCount = newOrders.filter(o => o.delivery_status === 'pending').length;
        if (newPendingCount > previousPendingCount.value && previousPendingCount.value > 0) {
            playNotificationSound();
            window.$toast?.('Новый заказ!', 'info');
        }
        previousPendingCount.value = newPendingCount;

        orders.value = newOrders;
    } catch (error) {
        console.error('Failed to load orders:', error);
        window.$toast?.('Ошибка загрузки заказов', 'error');
    } finally {
        loading.value = false;
    }
};

const loadCouriers = async () => {
    try {
        const response = await api.couriers.getAll();
        couriers.value = Array.isArray(response) ? response : (response.data || []);
    } catch (error) {
        console.error('Failed to load couriers:', error);
    }
};

// Order actions
const selectOrder = (order) => {
    selectedOrder.value = { ...order };
};

// Customer Info Card методы
const openOrderCustomerCard = (e) => {
    if (selectedOrder.value?.customer || selectedOrder.value?.customer_id) {
        customerCardAnchorRef.value = e.currentTarget;
        orderCustomerData.value = selectedOrder.value.customer || {
            id: selectedOrder.value.customer_id,
            name: selectedOrder.value.customer_name,
            phone: selectedOrder.value.phone
        };
        showCustomerCard.value = true;
    }
};

const handleCustomerUpdate = (updatedCustomer) => {
    orderCustomerData.value = updatedCustomer;
    // Также обновим в selectedOrder если он открыт
    if (selectedOrder.value && selectedOrder.value.customer) {
        selectedOrder.value.customer = updatedCustomer;
    }
};

const updateStatus = async (order, status) => {
    actionLoading.value = true;
    try {
        await api.orders.updateDeliveryStatus(order.id, status);
        window.$toast?.('Статус обновлён', 'success');
        await loadOrders();
        if (selectedOrder.value?.id === order.id) {
            selectedOrder.value = orders.value.find(o => o.id === order.id);
        }
    } catch (error) {
        console.error('Failed to update status:', error);
        window.$toast?.('Ошибка обновления статуса', 'error');
    } finally {
        actionLoading.value = false;
    }
};

const handleStatusChange = ({ order, status }) => {
    updateStatus(order, status);
};

const openCourierModal = (order) => {
    courierOrderId.value = order.id;
    showCourierModal.value = true;
};

const assignCourier = async (courierId) => {
    actionLoading.value = true;
    try {
        await api.couriers.assign(courierOrderId.value, courierId);
        await api.orders.updateDeliveryStatus(courierOrderId.value, 'in_transit');
        window.$toast?.('Курьер назначен', 'success');
        showCourierModal.value = false;
        await loadOrders();
        await loadCouriers();
        if (selectedOrder.value?.id === courierOrderId.value) {
            selectedOrder.value = orders.value.find(o => o.id === courierOrderId.value);
        }
    } catch (error) {
        console.error('Failed to assign courier:', error);
        window.$toast?.('Ошибка назначения курьера', 'error');
    } finally {
        actionLoading.value = false;
    }
};

const autoAssignCourier = async () => {
    const availableCourier = couriers.value.find(c => c.courier_status === 'available');
    if (!availableCourier) {
        window.$toast?.('Нет свободных курьеров', 'warning');
        return;
    }
    await assignCourier(availableCourier.id);
};

const cancelOrder = () => {
    if (!selectedOrder.value) return;
    // Reset modal state
    cancelMode.value = canCancelOrders.value ? 'direct' : null; // Менеджер - сразу, остальные - выбор
    cancelManagerPin.value = '';
    cancelRefundMethod.value = 'cash';
    cancelReason.value = '';
    cancelPinError.value = '';
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    cancelMode.value = null;
    cancelManagerPin.value = '';
    cancelRefundMethod.value = 'cash';
    cancelReason.value = '';
    cancelPinError.value = '';
};

const selectCancelMode = (mode) => {
    cancelMode.value = mode;
    cancelPinError.value = '';
};

const confirmCancel = async () => {
    if (!selectedOrder.value) return;

    const isPaid = selectedOrder.value.payment_status === 'paid' || selectedOrder.value.prepayment > 0;

    // Режим заявки - отправляем на одобрение
    if (cancelMode.value === 'request') {
        if (!cancelReason.value.trim()) {
            cancelPinError.value = 'Укажите причину отмены';
            return;
        }

        cancelLoading.value = true;
        try {
            // Отправляем заявку на отмену
            await api.orders.requestCancellation(
                selectedOrder.value.id,
                cancelReason.value,
                authStore.user?.id
            );

            window.$toast?.('Заявка на отмену отправлена', 'success');
            closeCancelModal();
            selectedOrder.value = null;
            await loadOrders();
        } catch (error) {
            console.error('Failed to send cancel request:', error);
            window.$toast?.('Ошибка: ' + (error.response?.data?.message || error.message), 'error');
        } finally {
            cancelLoading.value = false;
        }
        return;
    }

    // Режим PIN - проверяем PIN менеджера
    if (cancelMode.value === 'pin') {
        if (cancelManagerPin.value.length < 4) {
            cancelPinError.value = 'Введите PIN менеджера';
            return;
        }

        cancelLoading.value = true;
        try {
            const authResult = await api.auth.loginWithPin(cancelManagerPin.value);
            const managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
            const userRole = authResult.data?.user?.role;
            if (!authResult.success || !managerRoles.includes(userRole)) {
                cancelPinError.value = 'Неверный PIN или недостаточно прав';
                cancelLoading.value = false;
                return;
            }
        } catch (error) {
            cancelPinError.value = 'Неверный PIN';
            cancelLoading.value = false;
            return;
        }
    }

    // Режим direct (менеджер) или после успешной проверки PIN - отменяем
    cancelLoading.value = true;
    try {
        // If order was paid, create refund transaction
        if (isPaid) {
            const refundAmount = Number(selectedOrder.value.prepayment) > 0
                ? Number(selectedOrder.value.prepayment)
                : Number(selectedOrder.value.total);
            await api.cashOperations.refund(
                refundAmount,
                cancelRefundMethod.value,
                selectedOrder.value.id,
                selectedOrder.value.order_number,
                cancelReason.value || 'Отмена заказа'
            );
        }

        // Cancel the order
        await api.orders.updateDeliveryStatus(selectedOrder.value.id, 'cancelled');

        window.$toast?.('Заказ отменён', 'success');
        closeCancelModal();
        selectedOrder.value = null;
        await loadOrders();
    } catch (error) {
        console.error('Failed to cancel order:', error);
        window.$toast?.('Ошибка отмены: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
        cancelLoading.value = false;
    }
};

// Handle payment from UnifiedPaymentModal
const handlePaymentConfirm = async (paymentData) => {
    // If already handled (from showSuccessAndClose callback), just refresh
    if (paymentData._handled) {
        showPaymentModal.value = false;
        await loadOrders();
        if (!paymentData._stayOpen) {
            selectedOrder.value = orders.value.find(o => o.id === selectedOrder.value?.id) || null;
        }
        return;
    }

    if (!selectedOrder.value) {
        paymentModalRef.value?.showError('Заказ не выбран');
        return;
    }

    try {
        // Prepare payment data for API
        const apiPaymentData = {
            method: paymentData.method,
            amount: paymentData.amount,
            cash_received: paymentData.method === 'cash' ? (paymentData.amount + (paymentData.change || 0)) : null,
            change: paymentData.change || 0,
            bonus_used: paymentData.bonusUsed || 0
        };

        // Mixed payment
        if (paymentData.method === 'mixed') {
            apiPaymentData.cash_amount = paymentData.cashAmount;
            apiPaymentData.card_amount = paymentData.cardAmount;
        }

        // Call API to process payment
        await api.orders.pay(selectedOrder.value.id, apiPaymentData);

        // Show success animation and close
        paymentModalRef.value?.showSuccessAndClose(paymentData, false);

    } catch (error) {
        console.error('Payment failed:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Ошибка оплаты';
        paymentModalRef.value?.showError(errorMessage);
    }
};

const handlePaymentCompleted = async ({ order }) => {
    showPaymentModal.value = false;
    window.$toast?.('Оплата принята', 'success');
    await loadOrders();
    selectedOrder.value = orders.value.find(o => o.id === order?.id) || null;
};

const printOrder = async () => {
    if (!selectedOrder.value?.id) {
        window.$toast?.('Заказ не выбран', 'error');
        return;
    }

    try {
        window.$toast?.('Отправка на печать...', 'info');
        // Interceptor бросит исключение при success: false
        await api.orders.printReceipt(selectedOrder.value.id);
        window.$toast?.('Чек напечатан', 'success');
    } catch (error) {
        console.error('Print error:', error);
        window.$toast?.(error.response?.data?.message || error.message || 'Ошибка печати', 'error');
    }
};

const printCourierRoute = (courier) => {
    routeCourier.value = courier;
    showRouteModal.value = true;
};

const doPrintRoute = () => {
    const printContent = routePrintRef.value?.innerHTML;
    if (!printContent) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Маршрутный лист</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                    th { background: #f5f5f5; }
                </style>
            </head>
            <body>${printContent}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
};

const handleOrderCreated = () => {
    loadOrders();
    playNotificationSound();
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        if (e.key === 'Escape') {
            e.target.blur();
            // Продолжаем обработку Esc для закрытия панелей
        } else {
            return;
        }
    }

    // Ctrl+F - Focus search (проверяем ДО обработки одиночных клавиш)
    if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F' || e.key === 'а' || e.key === 'А')) {
        e.preventDefault();
        searchInput.value?.focus();
        return;
    }

    // N - New order (только без модификаторов)
    if (!e.ctrlKey && !e.metaKey && !e.altKey && (e.key === 'n' || e.key === 'N' || e.key === 'т' || e.key === 'Т')) {
        e.preventDefault();
        showNewOrderModal.value = true;
    }

    // F5 - Refresh
    if (e.key === 'F5') {
        e.preventDefault();
        loadOrders();
    }

    // Escape - Close panels
    if (e.key === 'Escape') {
        // Форма нового заказа сама обрабатывает Escape (свои sub-модалки)
        if (showNewOrderModal.value) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        if (showCancelModal.value) {
            showCancelModal.value = false;
        } else if (showDateCalendar.value) {
            showDateCalendar.value = false;
        } else if (showCourierModal.value) {
            showCourierModal.value = false;
        } else if (showPaymentModal.value) {
            showPaymentModal.value = false;
        } else if (showRouteModal.value) {
            showRouteModal.value = false;
        } else if (selectedOrder.value) {
            selectedOrder.value = null;
        }
    }

};

// Auto refresh
let refreshInterval = null;

onMounted(async () => {
    await Promise.all([loadOrders(), loadCouriers()]);

    previousPendingCount.value = orders.value.filter(o => o.delivery_status === 'pending').length;

    // Setup real-time subscription for instant updates
    setupRealtimeSubscription();

    // Load bonus settings
    try {
        // Interceptor бросит исключение при success: false
        const response = await api.loyalty.getBonusSettings();
        bonusSettings.value = response?.data || response || {};
    } catch (e) {
        console.warn('Failed to load bonus settings:', e);
    }

    // Load general settings (rounding)
    try {
        const data = await api.settings.getGeneral();
        if (data) {
            roundAmounts.value = data.round_amounts || false;
        }
    } catch (e) {
        console.warn('Failed to load general settings:', e);
    }

    // Auto refresh every 30 seconds (fallback if WebSocket disconnects)
    refreshInterval = setInterval(() => {
        loadOrders();
        loadCouriers();
    }, 30000);

    document.addEventListener('keydown', handleKeydown, true);
});

onUnmounted(() => {
    // Note: useRealtimeEvents auto-cleans up subscriptions on unmount
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    document.removeEventListener('keydown', handleKeydown, true);
});
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.3s ease;
}

.slide-left-enter-from,
.slide-left-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

/* Drawer animation */
.drawer-enter-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.drawer-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-enter-from {
    opacity: 0;
    max-height: 0 !important;
    transform: translateY(20px);
}

.drawer-leave-to {
    opacity: 0;
    max-height: 0 !important;
    transform: translateY(10px);
}

/* Fade animation for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Modal animation */
.modal-enter-active,
.modal-leave-active {
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

/* Dropdown animation */
.dropdown-enter-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.dropdown-leave-active {
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}
</style>
