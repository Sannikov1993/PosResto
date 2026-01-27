<template>
    <Teleport to="body">
        <!-- Toast notification -->
        <Transition name="toast-slide">
            <div v-if="toast.show"
                 :class="[
                     'fixed top-4 right-4 z-[10000] px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[280px] max-w-[400px]',
                     toast.type === 'success' ? 'bg-green-600 text-white' :
                     toast.type === 'error' ? 'bg-red-600 text-white' :
                     toast.type === 'warning' ? 'bg-yellow-600 text-white' :
                     'bg-blue-600 text-white'
                 ]">
                <span class="text-xl">{{ toast.type === 'success' ? '✓' : toast.type === 'error' ? '✕' : toast.type === 'warning' ? '⚠' : 'ℹ' }}</span>
                <span class="flex-1">{{ toast.message }}</span>
            </div>
        </Transition>

        <!-- Overlay -->
        <Transition name="fade">
            <div v-if="show"
                 @click="$emit('close')"
                 class="fixed inset-0 bg-black/50 z-[9998]"></div>
        </Transition>

        <!-- Side Panel -->
        <Transition name="slide-right">
            <div v-if="show && currentReservation"
                 class="fixed top-0 right-0 h-full w-[480px] bg-[#1a1f2e] border-l border-gray-800 shadow-2xl z-[9999] flex flex-col">

                <!-- Header: Table + Zone + Actions -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                    <div class="flex items-center gap-3">
                        <!-- Table icon + number -->
                        <div class="flex items-center gap-2 text-white">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                            <span class="font-semibold">Стол {{ tableDisplayName }}</span>
                            <span class="text-gray-500">{{ table?.zone_name || '' }}</span>
                        </div>
                        <!-- Reservation ID icon with tooltip -->
                        <div class="relative group">
                            <div class="w-6 h-6 flex items-center justify-center rounded-full bg-gray-700 text-gray-400 cursor-help">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                </svg>
                            </div>
                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-2 px-3 py-1.5 bg-gray-900 text-white text-xs rounded-lg whitespace-nowrap opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 shadow-lg border border-gray-700">
                                Бронь #{{ currentReservation?.id }}
                                <div class="absolute left-1/2 -translate-x-1/2 -top-1 w-2 h-2 bg-gray-900 border-l border-t border-gray-700 rotate-45"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <!-- Сохранить (зелёная галочка) -->
                        <button @click="saveReservation"
                                :disabled="saving || !canSave"
                                :class="[
                                    'w-9 h-9 flex items-center justify-center rounded-lg transition-colors',
                                    canSave
                                        ? 'bg-green-500 hover:bg-green-600 text-white'
                                        : 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                ]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                        <!-- Закрыть -->
                        <button @click="$emit('close')"
                                class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Reservation switcher (if multiple) -->
                <div v-if="allReservations.length > 1" class="flex items-center justify-center gap-2 py-2 border-b border-gray-800">
                    <button @click="switchReservation(-1)"
                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-[#252a3a] hover:bg-[#2d3348] text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-1">
                        <button v-for="(res, idx) in allReservations" :key="res.id"
                                @click="currentIndex = idx"
                                :class="['w-2 h-2 rounded-full transition-all',
                                         idx === currentIndex ? 'bg-blue-400 w-4' : 'bg-gray-600 hover:bg-gray-500']">
                        </button>
                    </div>
                    <button @click="switchReservation(1)"
                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-[#252a3a] hover:bg-[#2d3348] text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <span class="text-xs text-gray-500 ml-1">{{ currentIndex + 1 }}/{{ allReservations.length }}</span>
                </div>

                <!-- Compact Date / Time / Guests row -->
                <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-800">
                    <!-- Date button -->
                    <button @click="openOverlay('date')"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-[#252a3a] hover:bg-[#2d3348] rounded-lg text-sm transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-white">{{ dateBadgeText }}</span>
                    </button>

                    <!-- Time button -->
                    <button @click="openOverlay('time')"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-[#252a3a] hover:bg-[#2d3348] rounded-lg text-sm transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-white">{{ formatTime(editData.time_from) }}–{{ formatTime(editData.time_to) }}</span>
                    </button>

                    <!-- Guests button -->
                    <button @click="openOverlay('guests')"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-[#252a3a] hover:bg-[#2d3348] rounded-lg text-sm transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-white">{{ editData.guests_count }}</span>
                    </button>
                </div>

                <!-- Main Content -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Guest info fields with background -->
                    <div class="px-4 py-3 space-y-2 bg-dark-900/50">
                    <!-- Row 1: Phone + Name -->
                    <div class="flex gap-2 relative">
                        <div class="relative">
                            <input
                                :value="editData.guest_phone"
                                type="tel"
                                placeholder="+7 (___) __-__-__"
                                @input="onPhoneInput"
                                @focus="editData.guest_phone?.length >= 3 && foundCustomers.length > 0 && (showCustomerDropdown = true)"
                                @blur="setTimeout(() => showCustomerDropdown = false, 200)"
                                class="w-44 bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                            />
                            <div v-if="searchingCustomer" class="absolute right-2 top-1/2 -translate-y-1/2">
                                <svg class="w-4 h-4 animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 relative">
                            <!-- Если клиент выбран - компактное отображение -->
                            <div v-if="selectedCustomer" class="flex items-center gap-2 bg-dark-800 rounded-lg px-3 py-2">
                                <button
                                    @click="openCustomerCard"
                                    class="flex items-center gap-2 group"
                                >
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-xs font-semibold">{{ (editData.guest_name || 'К')[0].toUpperCase() }}</span>
                                    </div>
                                    <span class="text-white text-sm font-medium transition-colors group-hover:text-gray-300">{{ editData.guest_name }}</span>
                                    <svg class="w-4 h-4 text-gray-500 transition-all group-hover:translate-x-1 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                                <button
                                    @click="openCustomerList"
                                    class="ml-auto text-gray-500 hover:text-white transition-colors"
                                    title="Выбрать из списка"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Если клиент не выбран - показываем input -->
                            <template v-else>
                                <input
                                    v-model="editData.guest_name"
                                    type="text"
                                    placeholder="Введите ФИО"
                                    @input="onNameInput"
                                    @focus="editData.guest_name?.length >= 2 && foundCustomers.length > 0 && (showCustomerDropdown = true)"
                                    @blur="setTimeout(() => showCustomerDropdown = false, 200)"
                                    class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                                />
                                <button
                                    @click="openCustomerList"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                                    title="Выбрать из списка"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                </button>
                            </template>
                        </div>

                        <!-- Customer search dropdown -->
                        <Transition name="dropdown">
                            <div
                                v-if="showCustomerDropdown && foundCustomers.length > 0"
                                class="absolute top-full left-0 right-0 mt-1 bg-dark-800 border border-dark-600 rounded-lg shadow-xl z-50 max-h-48 overflow-y-auto"
                            >
                                <button
                                    v-for="customer in foundCustomers"
                                    :key="customer.id"
                                    @mousedown.prevent="selectCustomer(customer)"
                                    class="w-full flex items-center gap-3 px-3 py-2 hover:bg-dark-700 transition-colors text-left"
                                >
                                    <div class="w-8 h-8 bg-accent/20 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-accent text-sm font-medium">{{ (customer.name || 'К')[0].toUpperCase() }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white text-sm font-medium truncate">{{ customer.name || 'Без имени' }}</p>
                                        <p class="text-gray-400 text-xs">{{ formatPhoneDisplay(customer.phone) }}</p>
                                    </div>
                                    <div v-if="customer.orders_count" class="text-xs text-gray-500">
                                        {{ customer.orders_count }} заказов
                                    </div>
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <!-- Row 2: Comment -->
                    <input
                        v-model="editData.notes"
                        type="text"
                        placeholder="Комментарий"
                        class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                    />
                    </div>

                    <!-- Preorder button -->
                    <div class="px-4 py-2 space-y-2">
                    <button @click="openOverlay('preorder')"
                            class="w-full flex items-center justify-between py-2.5 px-3 bg-[#252a3a] hover:bg-[#2d3348] rounded-lg text-sm transition-colors">
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <span class="text-white">{{ preorderItemsLocal.length > 0 ? 'Предзаказ' : 'Добавить предзаказ' }}</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <!-- Preorder items list -->
                    <div v-if="preorderItemsLocal.length > 0" class="mt-3">
                        <div v-for="item in preorderItemsLocal" :key="item.id"
                             class="border-b border-white/5">
                            <div class="px-3 py-2 hover:bg-gray-800/20 flex items-center gap-2"
                                 @mouseenter="hoveredItemId = item.id"
                                 @mouseleave="hoveredItemId = null">
                                <span class="w-2 h-2 rounded-full flex-shrink-0 bg-blue-500"></span>
                                <span class="text-gray-200 text-base flex-1 truncate">{{ item.name }}</span>

                                <!-- Price/buttons container - no layout shift -->
                                <div class="relative flex-shrink-0">
                                    <!-- Price info (always rendered, hidden on hover) -->
                                    <div class="flex items-center gap-2"
                                         :class="hoveredItemId === item.id ? 'invisible' : 'visible'">
                                        <span class="text-gray-500 text-sm">{{ formatPrice(item.price) }}</span>
                                        <span class="text-gray-500 text-sm">×</span>
                                        <span class="text-gray-400 text-sm">{{ item.quantity }} шт</span>
                                        <span class="text-gray-300 text-[14px] font-semibold w-20 text-right">{{ formatPrice(getItemTotal(item)) }}</span>
                                    </div>

                                    <!-- Inline action buttons (on hover) -->
                                    <div class="absolute inset-0 flex items-center justify-end gap-1 bg-[#1a1f2e]"
                                         :class="hoveredItemId === item.id ? 'visible' : 'invisible'">
                                        <div class="flex items-center gap-0.5">
                                            <button @click.stop="updatePreorderQuantity(item, -1)"
                                                    class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">−</button>
                                            <span class="text-gray-300 text-sm w-6 text-center">{{ item.quantity }}</span>
                                            <button @click.stop="updatePreorderQuantity(item, 1)"
                                                    class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">+</button>
                                        </div>

                                        <button @click.stop="openPreorderComment(item)"
                                                :class="item.comment ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                                class="w-7 h-7 rounded flex items-center justify-center"
                                                title="Комментарий">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                            </svg>
                                        </button>

                                        <button @click.stop="removeFromPreorder(item.id)"
                                                class="w-7 h-7 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                                title="Удалить">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Modifiers display -->
                            <div v-if="item.modifiers?.length" class="px-3 -mt-1 pb-1">
                                <div v-for="mod in sortModifiers(item.modifiers)" :key="mod.id || mod.option_id"
                                     class="flex items-center gap-2 text-[11px] text-gray-500 pl-4 leading-tight">
                                    <span class="flex-1 truncate">+ {{ mod.option_name || mod.name }}</span>
                                    <span v-if="mod.price > 0" class="text-gray-600">{{ mod.price }}₽</span>
                                </div>
                            </div>
                            <!-- Comment -->
                            <div v-if="item.comment" class="px-3 pb-2 text-yellow-500 text-xs italic">
                                💬 {{ item.comment }}
                            </div>
                        </div>
                        <!-- Total -->
                        <div class="flex items-center justify-between pt-2 mt-2 border-t border-gray-700/50 px-3">
                            <span class="text-gray-400 text-sm">Итого:</span>
                            <span class="text-blue-400 font-bold">{{ preorderTotal }}₽</span>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Action buttons - fixed at bottom -->
                <div class="flex-shrink-0 p-3 border-t border-gray-800">
                    <!-- Unified Payment Modal for deposit -->
                    <UnifiedPaymentModal
                        v-model="showDepositPaymentModal"
                        :total="currentDepositAmount"
                        :initialAmount="currentDepositAmount > 0 ? currentDepositAmount : ''"
                        mode="deposit"
                        :bottomSheet="true"
                        :rightAligned="true"
                        :roundAmounts="roundAmounts"
                        @confirm="handleDepositPaymentConfirm"
                    />

                    <!-- Main action buttons row -->
                    <div class="flex items-stretch gap-1 bg-[#1e2330] rounded-lg p-1">
                        <!-- Deposit button with dropdown (только для сохранённых броней) -->
                        <div v-if="currentReservation?.id" class="relative">
                            <button @click="handleDepositButtonClick"
                                    class="flex flex-col items-center justify-center px-3 py-1 bg-[#252a3a] hover:bg-[#2d3348] rounded-md transition-colors min-w-[100px] h-full">
                                <svg class="w-4 h-4 mb-0.5" :class="currentReservation?.deposit_status === 'paid' ? 'text-green-400' : (currentDepositAmount > 0 ? 'text-yellow-400' : 'text-gray-400')" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <span v-if="currentReservation?.deposit_status === 'paid'" class="text-[11px] text-green-400 font-bold">{{ formatPrice(currentDepositAmount) }}</span>
                                <span v-else class="text-[9px] text-gray-400">Депозит</span>
                            </button>

                            <!-- Deposit menu dropdown -->
                            <Transition name="dropdown">
                                <div v-if="showDepositMenu"
                                     class="absolute bottom-full left-0 mb-2 bg-[#252a3a] border border-gray-700 rounded-lg shadow-xl z-50 min-w-[160px] overflow-hidden">
                                    <button @click="handleAddMoreDeposit"
                                            class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-white hover:bg-[#2d3348] transition-colors">
                                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Добавить оплату
                                    </button>
                                    <button @click="handleRefundFromMenu"
                                            class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-white hover:bg-[#2d3348] transition-colors border-t border-gray-700">
                                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                        </svg>
                                        Вернуть депозит
                                    </button>
                                </div>
                            </Transition>
                            <!-- Backdrop to close menu -->
                            <div v-if="showDepositMenu" @click="showDepositMenu = false" class="fixed inset-0 z-40"></div>
                        </div>

                        <!-- Seat/Unseat button - только для бронирований на сегодня -->
                        <template v-if="isReservationToday">
                            <button v-if="currentReservation?.status === 'seated'"
                                    @click="handleUnseatGuests"
                                    :disabled="seatingGuests"
                                    class="flex-1 flex flex-col items-center justify-center px-3 py-1 bg-amber-500 hover:bg-amber-600 rounded-md text-white transition-colors">
                                <svg v-if="seatingGuests" class="w-4 h-4 mb-0.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <span class="text-[9px]">Снять</span>
                            </button>
                            <button v-else
                                    @click="handleSeatGuests"
                                    :disabled="seatingGuests"
                                    class="flex-1 flex flex-col items-center justify-center px-3 py-1 bg-blue-500 hover:bg-blue-600 rounded-md text-white transition-colors">
                                <svg v-if="seatingGuests" class="w-4 h-4 mb-0.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-[9px]">Посадить</span>
                            </button>
                        </template>

                        <!-- Print button (только для сохранённых броней с предзаказом) -->
                        <button v-if="currentReservation?.id"
                                @click="printPreorder"
                                :disabled="printing || preorderItemsLocal.length === 0"
                                :class="[
                                    'flex flex-col items-center justify-center px-2.5 py-1 rounded-md transition-colors min-w-[46px]',
                                    preorderItemsLocal.length > 0
                                        ? 'bg-[#252a3a] hover:bg-[#2d3348] text-gray-400 hover:text-cyan-400'
                                        : 'bg-[#252a3a] text-gray-600 cursor-not-allowed'
                                ]">
                            <svg v-if="printing" class="w-4 h-4 mb-0.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span class="text-[9px]">Печать</span>
                        </button>

                        <!-- Cancel/Delete button -->
                        <button @click="$emit('cancel', currentReservation)"
                                class="flex flex-col items-center justify-center px-2 py-1 bg-[#252a3a] hover:bg-red-500/20 rounded-md text-gray-400 hover:text-red-400 transition-colors min-w-[36px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ========== OVERLAYS ========== -->

                <!-- Date Picker Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'date'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                            <span class="text-white font-medium">Выберите дату</span>
                            <button @click="closeOverlay" class="text-gray-400 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4">
                            <InlineCalendar
                                v-model="editData.date"
                                :minDate="todayDate"
                                @update:modelValue="closeOverlay"
                            />
                        </div>
                    </div>
                </Transition>

                <!-- Time Picker Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'time'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <TimelineTimePicker
                            v-model="timePickerData"
                            :existingReservations="otherReservations"
                            :selectedDate="editData.date"
                            :embedded="true"
                            @close="closeOverlay"
                        />
                    </div>
                </Transition>

                <!-- Guests Picker Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'guests'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <GuestCountPicker
                            v-model="editData.guests_count"
                            :tableSeats="table?.seats || 4"
                            :embedded="true"
                            @close="closeOverlay"
                        />
                    </div>
                </Transition>

                <!-- Deposit Picker Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'deposit'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <DepositPicker
                            v-model="editData.deposit"
                            v-model:paymentMethod="editData.deposit_payment_method"
                            :embedded="true"
                            @close="handleDepositConfirm"
                        />
                    </div>
                </Transition>

                <!-- Preorder Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'preorder'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                            <span class="text-white font-medium">Предзаказ</span>
                            <button @click="closeOverlay" class="text-gray-400 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Search -->
                        <div class="p-3 border-b border-gray-800">
                            <div class="relative">
                                <input type="text"
                                       v-model="menuSearch"
                                       placeholder="Поиск блюда..."
                                       class="w-full bg-[#252a3a] text-white text-sm px-4 py-2 pl-10 rounded-lg border border-gray-700 focus:border-blue-500 outline-none">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="flex gap-2 p-3 overflow-x-auto border-b border-gray-800 scrollbar-hide">
                            <button @click="selectedCategory = null"
                                    :class="['px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors',
                                             !selectedCategory ? 'bg-blue-500 text-white' : 'bg-[#252a3a] text-gray-400 hover:bg-[#2d3348]']">
                                Все
                            </button>
                            <button v-for="cat in categories" :key="cat.id"
                                    @click="selectedCategory = cat.id"
                                    :class="['px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors',
                                             selectedCategory === cat.id ? 'bg-blue-500 text-white' : 'bg-[#252a3a] text-gray-400 hover:bg-[#2d3348]']">
                                {{ cat.name }}
                            </button>
                        </div>

                        <!-- Menu items -->
                        <div class="flex-1 overflow-y-auto p-3">
                            <div v-if="loadingMenu" class="text-center text-gray-500 py-8">
                                Загрузка меню...
                            </div>
                            <div v-else-if="filteredDishes.length === 0" class="text-center text-gray-500 py-8">
                                Блюда не найдены
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="dish in filteredDishes" :key="dish.id"
                                     @click="openVariantModal(dish)"
                                     class="flex items-center gap-3 bg-[#252a3a] rounded-xl p-3 cursor-pointer hover:bg-[#2d3348] transition-colors">
                                    <div v-if="dish.image_url"
                                         class="w-12 h-12 rounded-lg bg-cover bg-center flex-shrink-0"
                                         :style="{ backgroundImage: `url(${dish.image_url})` }">
                                    </div>
                                    <div v-else class="w-12 h-12 rounded-lg bg-gray-700 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-white text-sm font-medium truncate">{{ dish.name }}</div>
                                        <div class="text-gray-500 text-xs">{{ dish.weight || '' }}</div>
                                        <!-- Бейджи если есть варианты/модификаторы -->
                                        <div v-if="dish.variants?.length || dish.modifiers?.length" class="flex gap-1 mt-1">
                                            <span v-if="dish.variants?.length" class="text-[10px] px-1.5 py-0.5 bg-blue-500/20 text-blue-400 rounded">
                                                {{ dish.variants.length }} размер{{ dish.variants.length > 1 ? 'а' : '' }}
                                            </span>
                                            <span v-if="dish.modifiers?.length" class="text-[10px] px-1.5 py-0.5 bg-green-500/20 text-green-400 rounded">
                                                + добавки
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-blue-400 font-bold">
                                        {{ dish.variants?.length ? 'от ' + dish.variants[0]?.price : dish.price }}₽
                                    </div>
                                    <div class="w-8 h-8 bg-blue-500 hover:bg-blue-600 rounded-lg flex items-center justify-center text-white">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Variant/Modifier Modal -->
                        <Transition name="fade">
                            <div v-if="variantModal.show" class="absolute inset-0 bg-black/60 z-20 flex items-end">
                                <div class="w-full bg-[#1a1f2e] rounded-t-2xl max-h-[70vh] flex flex-col">
                                    <!-- Header -->
                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                                        <div>
                                            <span class="text-white font-medium">{{ variantModal.dish?.name }}</span>
                                            <div v-if="variantModal.dish?.weight" class="text-gray-500 text-xs">{{ variantModal.dish.weight }}</div>
                                        </div>
                                        <button @click="closeVariantModal" class="text-gray-400 hover:text-white">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 overflow-y-auto p-4">
                                        <!-- Variants selection -->
                                        <div v-if="variantModal.dish?.variants?.length" class="mb-4">
                                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Размер</div>
                                            <div class="flex gap-2">
                                                <button v-for="variant in variantModal.dish.variants" :key="variant.id"
                                                        @click="variantModal.selectedVariant = variant"
                                                        :class="[
                                                            'flex-1 px-3 py-2 rounded-lg text-sm transition-all',
                                                            variantModal.selectedVariant?.id === variant.id
                                                                ? 'bg-blue-500 text-white'
                                                                : 'bg-[#252a3a] text-white hover:bg-[#2d3348]'
                                                        ]">
                                                    <div class="font-medium">{{ variant.variant_name }}</div>
                                                    <div class="text-xs mt-0.5" :class="variantModal.selectedVariant?.id === variant.id ? 'text-blue-200' : 'text-gray-400'">
                                                        {{ variant.price }}₽
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Modifiers -->
                                        <div v-if="variantModal.dish?.modifiers?.length">
                                            <div class="space-y-3">
                                                <div v-for="modifier in variantModal.dish.modifiers" :key="modifier.id">
                                                    <!-- Modifier header -->
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-gray-400 text-xs">{{ modifier.name }}</span>
                                                        <span v-if="modifier.is_required" class="text-[10px] text-orange-400">обязательно</span>
                                                        <span v-else-if="modifier.type === 'multiple' && modifier.max_selections" class="text-[10px] text-gray-600">
                                                            макс. {{ modifier.max_selections }}
                                                        </span>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button v-for="option in modifier.options" :key="option.id"
                                                                @click="toggleModifierOption(modifier, option)"
                                                                :class="[
                                                                    'px-3 py-1.5 rounded-lg text-sm transition-all',
                                                                    isModifierSelected(modifier.id, option.id)
                                                                        ? 'bg-green-500 text-white'
                                                                        : 'bg-[#252a3a] text-white hover:bg-[#2d3348]'
                                                                ]">
                                                            {{ option.name }}
                                                            <span v-if="option.price > 0" class="text-xs ml-1 opacity-70">+{{ option.price }}₽</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer -->
                                    <div class="p-4 border-t border-gray-800">
                                        <button @click="confirmVariantModal"
                                                :disabled="!canAddToPreorder"
                                                :class="[
                                                    'w-full py-3 rounded-xl font-medium transition-colors',
                                                    !canAddToPreorder
                                                        ? 'bg-gray-700 text-gray-500 cursor-not-allowed'
                                                        : 'bg-blue-500 hover:bg-blue-600 text-white'
                                                ]">
                                            Добавить за {{ calculateModalTotal }}₽
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </Transition>

                        <!-- Preorder summary -->
                        <div v-if="preorderItemsLocal.length > 0" class="border-t border-gray-800 bg-[#1d2230] max-h-[50vh] flex flex-col">
                            <div class="p-3 flex flex-col min-h-0">
                                <div class="text-xs text-gray-500 tracking-wider mb-2 flex-shrink-0">ПРЕДЗАКАЗ ({{ preorderItemsLocal.length }})</div>
                                <div class="overflow-y-auto flex-1">
                                    <div v-for="item in preorderItemsLocal" :key="item.id"
                                         class="border-b border-white/5">
                                        <div class="px-2 py-1.5 hover:bg-gray-800/20 flex items-center gap-2"
                                             @mouseenter="hoveredItemId = item.id"
                                             @mouseleave="hoveredItemId = null">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0 bg-blue-500"></span>
                                            <span class="text-gray-200 text-sm flex-1 truncate">{{ item.name }}</span>

                                            <!-- Price/buttons container - no layout shift -->
                                            <div class="relative flex-shrink-0">
                                                <!-- Price info (always rendered, hidden on hover) -->
                                                <div class="flex items-center gap-2"
                                                     :class="hoveredItemId === item.id ? 'invisible' : 'visible'">
                                                    <span class="text-gray-500 text-sm">{{ formatPrice(item.price) }}</span>
                                                    <span class="text-gray-500 text-sm">×</span>
                                                    <span class="text-gray-400 text-sm">{{ item.quantity }} шт</span>
                                                    <span class="text-gray-300 text-[14px] font-semibold w-16 text-right">{{ formatPrice(getItemTotal(item)) }}</span>
                                                </div>

                                                <!-- Inline action buttons (on hover) -->
                                                <div class="absolute inset-0 flex items-center justify-end gap-1 bg-[#1a1f2e]"
                                                     :class="hoveredItemId === item.id ? 'visible' : 'invisible'">
                                                    <div class="flex items-center gap-0.5">
                                                        <button @click.stop="updatePreorderQuantity(item, -1)"
                                                                class="w-6 h-6 bg-gray-700/50 text-gray-300 rounded text-sm hover:bg-gray-600 flex items-center justify-center">−</button>
                                                        <span class="text-gray-300 text-sm w-5 text-center">{{ item.quantity }}</span>
                                                        <button @click.stop="updatePreorderQuantity(item, 1)"
                                                                class="w-6 h-6 bg-gray-700/50 text-gray-300 rounded text-sm hover:bg-gray-600 flex items-center justify-center">+</button>
                                                    </div>

                                                    <button @click.stop="openPreorderComment(item)"
                                                            :class="item.comment ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                                            class="w-6 h-6 rounded flex items-center justify-center"
                                                            title="Комментарий">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                                        </svg>
                                                    </button>

                                                    <button @click.stop="removeFromPreorder(item.id)"
                                                            class="w-6 h-6 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                                            title="Удалить">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Modifiers display -->
                                        <div v-if="item.modifiers?.length" class="px-2 -mt-1 pb-1">
                                            <div v-for="mod in sortModifiers(item.modifiers)" :key="mod.id || mod.option_id"
                                                 class="flex items-center gap-2 text-[11px] text-gray-500 pl-4 leading-tight">
                                                <span class="flex-1 truncate">+ {{ mod.option_name || mod.name }}</span>
                                                <span v-if="mod.price > 0" class="text-gray-600">{{ mod.price }}₽</span>
                                            </div>
                                        </div>
                                        <!-- Comment -->
                                        <div v-if="item.comment" class="px-2 pb-1.5 text-yellow-500 text-xs italic">
                                            💬 {{ item.comment }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-700 flex-shrink-0 px-3">
                                    <span class="text-gray-400 text-sm">Итого:</span>
                                    <span class="text-blue-400 font-bold text-lg">{{ preorderTotal }}₽</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 border-t border-gray-800 flex-shrink-0">
                            <button @click="closeOverlay" class="w-full py-3 bg-blue-500 hover:bg-blue-600 rounded-xl text-white font-medium">
                                Готово
                            </button>
                        </div>
                    </div>
                </Transition>

                <!-- Customer List Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'customers'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                            <span class="text-white font-medium">Выберите клиента</span>
                            <button @click="closeOverlay" class="text-gray-400 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Search -->
                        <div class="p-3 border-b border-gray-800">
                            <div class="relative">
                                <input type="text"
                                       v-model="customerSearch"
                                       placeholder="Поиск по имени или телефону..."
                                       @input="onCustomerSearchInput"
                                       class="w-full bg-dark-800 text-white text-sm px-4 py-2.5 pl-10 rounded-lg border-0 focus:ring-1 focus:ring-accent outline-none">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <div v-if="loadingCustomerList" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="w-4 h-4 animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Customer List -->
                        <div class="flex-1 overflow-y-auto px-3">
                            <div v-if="customerListFiltered.length === 0 && !loadingCustomerList" class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <p>{{ customerSearch ? 'Клиенты не найдены' : 'Начните вводить для поиска' }}</p>
                            </div>
                            <div v-else class="space-y-1 pb-4">
                                <button
                                    v-for="customer in customerListFiltered"
                                    :key="customer.id"
                                    @click="selectCustomerFromList(customer)"
                                    class="w-full flex items-center gap-3 px-3 py-3 hover:bg-dark-800 rounded-lg transition-colors text-left"
                                >
                                    <div class="w-11 h-11 bg-accent/20 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-accent text-lg font-medium">{{ (customer.name || 'К')[0].toUpperCase() }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white font-medium truncate">{{ customer.name || 'Без имени' }}</p>
                                        <p class="text-gray-400 text-sm">{{ formatPhoneDisplay(customer.phone) }}</p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p v-if="customer.orders_count" class="text-sm text-gray-500">{{ customer.orders_count }} заказов</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Comment Modal -->
                <Teleport to="body">
                    <div v-if="commentModal.show"
                         class="fixed inset-0 bg-black/70 z-[10000] flex items-center justify-center p-4"
                         @click.self="commentModal.show = false">
                        <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden">
                            <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                                <h3 class="text-white text-lg font-semibold">💬 Комментарий для кухни</h3>
                                <button @click="commentModal.show = false" class="text-gray-500 hover:text-white text-xl">✕</button>
                            </div>
                            <div class="p-4">
                                <p class="text-gray-400 text-sm mb-2">{{ commentModal.item?.name }}</p>
                                <textarea v-model="commentModal.text"
                                          placeholder="Например: без лука, поострее, не солить..."
                                          class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none resize-none"
                                          rows="3"></textarea>

                                <!-- Quick buttons -->
                                <div class="flex flex-wrap gap-2 mt-3">
                                    <button v-for="quick in quickCommentOptions"
                                            :key="quick"
                                            @click="addQuickCommentOption(quick)"
                                            class="px-3 py-1.5 bg-gray-800 text-gray-400 rounded-lg text-sm hover:bg-gray-700 hover:text-white">
                                        {{ quick }}
                                    </button>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-800 flex gap-3">
                                <button @click="commentModal.show = false"
                                        class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600">
                                    Отмена
                                </button>
                                <button @click="savePreorderComment"
                                        class="flex-1 py-3 bg-blue-500 text-white rounded-xl font-medium hover:bg-blue-600">
                                    Сохранить
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>

                <!-- Success Animation Modal -->
                <Teleport to="body">
                    <Transition name="success-fade">
                        <div v-if="showSuccessAnimation"
                             class="fixed inset-0 bg-black/70 z-[10001] flex items-center justify-center">
                            <div class="success-animation-container text-center">
                                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                    <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                                    <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                                </svg>
                                <div class="success-text">
                                    <div class="text-white text-xl font-bold mt-6">Депозит оплачен</div>
                                    <div class="text-green-400 text-3xl font-bold mt-2">{{ formatPrice(successAnimationAmount) }}</div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>

                <!-- Refund Animation Modal -->
                <Teleport to="body">
                    <Transition name="success-fade">
                        <div v-if="showRefundAnimation"
                             class="fixed inset-0 bg-black/70 z-[10001] flex items-center justify-center">
                            <div class="success-animation-container text-center">
                                <svg class="refund-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                    <circle class="refund-circle" cx="26" cy="26" r="25" fill="none"/>
                                    <path class="refund-arrow" fill="none" d="M15 26h17m-17 0l7-7m-7 7l7 7"/>
                                </svg>
                                <div class="success-text">
                                    <div class="text-white text-xl font-bold mt-6">Возврат выполнен</div>
                                    <div class="text-red-400 text-3xl font-bold mt-2">-{{ formatPrice(refundAnimationAmount) }}</div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>

                <!-- Refund Confirmation Modal -->
                <Teleport to="body">
                    <div v-if="refundModal.show"
                         class="fixed inset-0 bg-black/70 z-[10000] flex items-center justify-center p-4"
                         @click.self="refundModal.show = false">
                        <div class="bg-gray-900 rounded-2xl w-full max-w-sm overflow-hidden">
                            <!-- Header -->
                            <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-white text-lg font-semibold">Возврат депозита</h3>
                                </div>
                                <button @click="refundModal.show = false" class="text-gray-500 hover:text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Content -->
                            <div class="p-4">
                                <!-- Amount -->
                                <div class="text-center mb-4">
                                    <div class="text-3xl font-bold text-red-400 mb-1">-{{ formatPrice(refundModal.amount) }}</div>
                                    <div class="text-gray-400 text-sm">Сумма возврата</div>
                                </div>

                                <!-- Payment info -->
                                <div class="bg-[#252a3a] rounded-xl p-3 mb-4">
                                    <div class="flex items-center justify-between text-sm mb-2">
                                        <span class="text-gray-400">Оплачено:</span>
                                        <span class="text-white">{{ formatRefundPaymentDate }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-400">Способ оплаты:</span>
                                        <span class="text-white flex items-center gap-1.5">
                                            <svg v-if="refundModal.paymentMethod === 'cash'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <svg v-else class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                            </svg>
                                            {{ refundModal.paymentMethod === 'cash' ? 'Наличные' : 'Карта' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Reason input -->
                                <div class="mb-4">
                                    <label class="text-gray-400 text-sm mb-2 block">Причина возврата</label>
                                    <textarea v-model="refundModal.reason"
                                              placeholder="Укажите причину (необязательно)"
                                              rows="2"
                                              class="w-full bg-[#252a3a] border border-gray-700 rounded-xl px-3 py-2 text-white text-sm placeholder-gray-500 focus:border-red-500 focus:outline-none resize-none"></textarea>
                                </div>

                                <!-- Quick reasons -->
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="reason in quickRefundReasons" :key="reason"
                                            @click="refundModal.reason = reason"
                                            :class="['px-3 py-1.5 rounded-lg text-xs transition-colors',
                                                     refundModal.reason === reason
                                                        ? 'bg-red-500/20 text-red-400 ring-1 ring-red-500/50'
                                                        : 'bg-gray-800 text-gray-400 hover:bg-gray-700']">
                                        {{ reason }}
                                    </button>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="p-4 border-t border-gray-800 space-y-3">
                                <button @click="confirmRefund"
                                        :disabled="processingDepositLocal"
                                        class="w-full py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl font-medium transition-colors flex items-center justify-center gap-2">
                                    <svg v-if="processingDepositLocal" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    </svg>
                                    {{ processingDepositLocal ? 'Обработка...' : 'Подтвердить возврат' }}
                                </button>
                                <button @click="refundModal.show = false"
                                        class="w-full py-2.5 text-gray-400 hover:text-white text-sm transition-colors">
                                    Отмена
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>

            </div>
        </Transition>
    </Teleport>

    <!-- Customer Info Card -->
    <CustomerInfoCard
        :show="showCustomerCard"
        :customer="selectedCustomer"
        :anchor-el="customerNameRef"
        @close="showCustomerCard = false"
        @update="handleCustomerUpdate"
    />

    <!-- Clear Preorder Confirm Modal -->
    <ConfirmModal
        v-model="showClearPreorderConfirm"
        title="Очистить предзаказ"
        message="Все позиции предзаказа будут удалены. Продолжить?"
        confirm-text="Очистить"
        cancel-text="Отмена"
        type="warning"
        icon="🗑️"
        :loading="clearingPreorder"
        @confirm="confirmClearPreorder"
    />
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import TimelineTimePicker from './TimelineTimePicker.vue';
import InlineCalendar from './InlineCalendar.vue';
import GuestCountPicker from './GuestCountPicker.vue';
import DepositPicker from './DepositPicker.vue';
import UnifiedPaymentModal from '../../../components/UnifiedPaymentModal.vue';
import CustomerInfoCard from '../../../components/CustomerInfoCard.vue';
import ConfirmModal from '../modals/ConfirmModal.vue';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const props = defineProps({
    show: Boolean,
    table: Object,
    reservation: Object,
    allReservations: {
        type: Array,
        default: () => []
    },
    preorderItems: {
        type: Array,
        default: () => []
    },
    loadingPreorder: Boolean,
    seatingGuests: Boolean,
    processingDeposit: Boolean,
    roundAmounts: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'update', 'saved', 'seatGuests', 'unseatGuests', 'cancel', 'switchReservation', 'payDeposit', 'refundDeposit', 'print']);

const saving = ref(false);
const printing = ref(false);
const currentIndex = ref(0);

// Toast
const toast = ref({ show: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};
const activeOverlay = ref(null); // 'date', 'time', 'guests', 'deposit', 'preorder'
const hoveredItemId = ref(null);
const processingDepositLocal = ref(false);

// Deposit payment modal (using UnifiedPaymentModal)
const showDepositPaymentModal = ref(false);
const showDepositMenu = ref(false);
const showSuccessAnimation = ref(false);
const successAnimationAmount = ref(0);
const showRefundAnimation = ref(false);
const refundAnimationAmount = ref(0);

// Refund confirmation modal
const refundModal = ref({
    show: false,
    amount: 0,
    paymentMethod: 'cash',
    paidAt: null,
    reason: ''
});
const quickRefundReasons = ['Отмена брони', 'По просьбе гостя', 'Изменение планов', 'Другое'];

// Comment modal state
const commentModal = ref({
    show: false,
    item: null,
    text: ''
});
const quickCommentOptions = ['Без лука', 'Поострее', 'Не солить', 'Без соуса', 'На вынос'];

// Clear preorder confirm modal
const showClearPreorderConfirm = ref(false);
const clearingPreorder = ref(false);

// Edit data - always in edit mode for simplicity
const editData = ref({
    date: '',
    time_from: '19:00',
    time_to: '21:00',
    guests_count: 2,
    guest_name: '',
    guest_phone: '',
    notes: '',
    deposit: 0,
    deposit_payment_method: 'cash'
});

// Оригинальные данные для отслеживания изменений
const originalData = ref(null);

// Menu state
const loadingMenu = ref(false);
const menuLoaded = ref(false);
const categories = ref([]);
const dishes = ref([]);
const selectedCategory = ref(null);
const menuSearch = ref('');

// Variant/Modifier modal state
const variantModal = ref({
    show: false,
    dish: null,
    selectedVariant: null,
    selectedModifiers: {} // { modifierId: [optionId, optionId, ...] }
});

// Local preorder items
const preorderItemsLocal = ref([]);

// Customer search
const showCustomerDropdown = ref(false);
const foundCustomers = ref([]);
const searchingCustomer = ref(false);
const customerSearch = ref('');
const customerList = ref([]);
const loadingCustomerList = ref(false);
let searchTimeout = null;
let customerSearchTimeout = null;

// Customer info card
const showCustomerCard = ref(false);
const selectedCustomer = ref(null);
const customerNameRef = ref(null);

// Computed
const currentReservation = computed(() => {
    if (props.allReservations.length > 0) {
        return props.allReservations[currentIndex.value] || props.reservation;
    }
    return props.reservation;
});

// Проверка - дата брони сегодня?
const isReservationToday = computed(() => {
    const res = currentReservation.value;
    if (!res?.date) return false;

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const todayStr = `${year}-${month}-${day}`;

    return res.date === todayStr;
});

// Отображение названия столов (с учётом связанных)
const tableDisplayName = computed(() => {
    const res = currentReservation.value;
    if (!res) return props.table?.number || '';

    // Если есть загруженные связанные столы
    if (res.tables && res.tables.length > 0) {
        return res.tables.map(t => t.number).join(' + ');
    }

    // Если есть linked_table_ids - показываем основной стол + количество связанных
    if (res.linked_table_ids && res.linked_table_ids.length > 0) {
        const mainTable = props.table?.number || res.table_id;
        return `${mainTable} + ещё ${res.linked_table_ids.length}`;
    }

    return props.table?.number || '';
});

const timePickerData = computed({
    get: () => ({
        time_from: editData.value.time_from || '19:00',
        time_to: editData.value.time_to || '21:00'
    }),
    set: (val) => {
        editData.value.time_from = val.time_from;
        editData.value.time_to = val.time_to;
    }
});

// Брони загруженные для выбранной даты
const dateReservations = ref([]);
const loadingDateReservations = ref(false);

// Загрузка броней для выбранной даты и стола
const loadDateReservations = async (date) => {
    if (!props.table?.id || !date) return;

    loadingDateReservations.value = true;
    try {
        const response = await fetch(`/api/reservations?table_id=${props.table.id}&date=${date}`);
        const data = await response.json();
        if (data.success) {
            dateReservations.value = data.data || [];
        }
    } catch (e) {
        console.error('Failed to load date reservations', e);
        dateReservations.value = [];
    } finally {
        loadingDateReservations.value = false;
    }
};

// Наблюдение за изменением даты
watch(() => editData.value.date, (newDate, oldDate) => {
    if (newDate && newDate !== oldDate) {
        loadDateReservations(newDate);
    }
});

// Загружаем брони при изменении стола
watch(() => props.table?.id, (newId) => {
    if (newId && editData.value.date) {
        loadDateReservations(editData.value.date);
    }
}, { immediate: true });

const otherReservations = computed(() => {
    // Если есть загруженные брони для даты - используем их
    const reservations = dateReservations.value.length > 0
        ? dateReservations.value
        : props.allReservations;

    if (!currentReservation.value) return reservations;
    return reservations.filter(r => r.id !== currentReservation.value.id);
});

const todayDate = computed(() => getLocalDateString());

// Проверка наличия изменений
const hasChanges = computed(() => {
    if (!originalData.value) return false;
    return JSON.stringify(editData.value) !== JSON.stringify(originalData.value);
});

// Валидация формы - нельзя сохранить без времени, имени и телефона
const isFormValid = computed(() => {
    return editData.value.time_from &&
           editData.value.time_to &&
           editData.value.guest_name?.trim() &&
           editData.value.guest_phone?.trim();
});

// Можно сохранить только если форма валидна И есть изменения
const canSave = computed(() => {
    return isFormValid.value && hasChanges.value;
});

const dateBadgeText = computed(() => {
    const date = editData.value.date;
    if (!date) return 'Сегодня';
    const today = getLocalDateString();
    if (date === today) return 'Сегодня';
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    if (date === getLocalDateString(tomorrow)) return 'Завтра';
    const d = new Date(date);
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${d.getDate()} ${months[d.getMonth()]}`;
});

const currentDepositAmount = computed(() => {
    return editData.value.deposit || currentReservation.value?.deposit || 0;
});

const depositStatusClass = computed(() => {
    const status = currentReservation.value?.deposit_status || 'pending';
    switch (status) {
        case 'paid': return 'bg-green-500/20 text-green-400';
        case 'refunded': return 'bg-red-500/20 text-red-400';
        case 'transferred': return 'bg-blue-500/20 text-blue-400';
        default: return 'bg-yellow-500/20 text-yellow-400';
    }
});

const depositStatusText = computed(() => {
    const status = currentReservation.value?.deposit_status || 'pending';
    switch (status) {
        case 'paid': return 'оплачен';
        case 'refunded': return 'возврат';
        case 'transferred': return 'в заказе';
        default: return 'ожидает';
    }
});

const preorderTotal = computed(() => {
    return preorderItemsLocal.value.reduce((sum, item) => {
        const basePrice = parseFloat(item.price) || 0;
        const modifiersPrice = (item.modifiers || []).reduce((mSum, mod) => mSum + (parseFloat(mod.price) || 0), 0);
        const itemTotal = (basePrice + modifiersPrice) * (item.quantity || 1);
        return sum + itemTotal;
    }, 0);
});

const formatRefundPaymentDate = computed(() => {
    const paidAt = refundModal.value.paidAt || currentReservation.value?.deposit_paid_at;
    if (!paidAt) return 'Неизвестно';
    const date = new Date(paidAt);
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${day}.${month}.${year} в ${hours}:${minutes}`;
});

const filteredDishes = computed(() => {
    let result = dishes.value;
    if (selectedCategory.value) {
        result = result.filter(d => d.category_id === selectedCategory.value);
    }
    if (menuSearch.value.trim()) {
        const search = menuSearch.value.toLowerCase().trim();
        result = result.filter(d => d.name.toLowerCase().includes(search));
    }
    return result;
});

// Watchers
watch(() => props.reservation, (newRes) => {
    if (newRes && props.allReservations.length > 0) {
        const idx = props.allReservations.findIndex(r => r.id === newRes.id);
        if (idx >= 0) {
            currentIndex.value = idx;
        }
    }
}, { deep: true });

watch(() => props.show, (val) => {
    if (val) {
        if (props.reservation && props.allReservations.length > 0) {
            const idx = props.allReservations.findIndex(r => r.id === props.reservation.id);
            currentIndex.value = idx >= 0 ? idx : 0;
        } else {
            currentIndex.value = 0;
        }
        activeOverlay.value = null;
        menuSearch.value = '';
        selectedCategory.value = null;
        initEditData();
        loadPreorderItems();
    }
});

watch(currentIndex, () => {
    initEditData();
    loadPreorderItems();
});

// Methods
const initEditData = () => {
    const res = currentReservation.value;
    // Нормализуем дату (убираем время если есть)
    const normalizeDate = (d) => d ? d.substring(0, 10) : null;

    // Сбрасываем загруженные брони для даты (будут загружены заново при изменении даты)
    dateReservations.value = [];

    const dateValue = normalizeDate(res?.date) || getLocalDateString();
    const newData = {
        date: dateValue,
        time_from: res?.time_from?.substring(0, 5) || '19:00',
        time_to: res?.time_to?.substring(0, 5) || '21:00',
        guests_count: res?.guests_count || 2,
        guest_name: res?.guest_name || '',
        guest_phone: res?.guest_phone || '',
        notes: res?.notes || '',
        deposit: res?.deposit || 0,
        deposit_payment_method: res?.deposit_payment_method || 'cash'
    };

    editData.value = newData;
    // Сохраняем копию для отслеживания изменений
    originalData.value = JSON.parse(JSON.stringify(newData));

    // Загружаем брони для выбранной даты
    loadDateReservations(dateValue);

    // Установить selectedCustomer из данных бронирования
    if (res?.customer) {
        selectedCustomer.value = res.customer;
    } else {
        selectedCustomer.value = null;
    }
};

const openOverlay = (type) => {
    activeOverlay.value = type;
    if (type === 'preorder') {
        loadMenuIfNeeded();
    }
};

const closeOverlay = () => {
    activeOverlay.value = null;
};

const switchReservation = (delta) => {
    const newIndex = currentIndex.value + delta;
    if (newIndex >= 0 && newIndex < props.allReservations.length) {
        currentIndex.value = newIndex;
        emit('switchReservation', props.allReservations[newIndex]);
    }
};

// Печать предзаказа на кухню
const printPreorder = async () => {
    if (printing.value) return;

    const reservation = currentReservation.value;

    // Проверяем что бронь существует
    if (!reservation?.id) {
        showToast('Бронь не найдена', 'error');
        return;
    }

    // Проверяем что есть позиции
    if (preorderItemsLocal.value.length === 0) {
        showToast('Добавьте позиции в предзаказ', 'warning');
        return;
    }

    printing.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/api/reservations/${reservation.id}/print-preorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        const data = await response.json();

        if (data.success) {
            showToast('Предзаказ отправлен на кухню', 'success');
        } else {
            showToast(data.message || 'Ошибка печати', 'error');
        }
    } catch (e) {
        console.error('Print error:', e);
        showToast('Ошибка: ' + e.message, 'error');
    } finally {
        printing.value = false;
    }
};

const saveReservation = async (options = {}) => {
    const { closeAfterSave = true } = options;
    saving.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/api/reservations/${currentReservation.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(editData.value)
        });
        const data = await response.json();
        if (data.success) {
            emit('update', data.reservation || data.data);
            if (closeAfterSave) {
                emit('close'); // Закрываем окно после сохранения
            }
            return true;
        } else {
            // Показываем ошибку валидации
            const errorMsg = data.message || data.errors?.join(', ') || 'Ошибка сохранения';
            console.error('Validation error:', data);
            alert(errorMsg);
        }
    } catch (e) {
        console.error('Failed to save reservation', e);
    } finally {
        saving.value = false;
    }
    return false;
};

// Auto-save and then seat guests
const handleSeatGuests = async () => {
    await saveReservation();
    emit('seatGuests', currentReservation.value, props.table);
};

// Unseat guests - return reservation to confirmed status
const handleUnseatGuests = () => {
    emit('unseatGuests', currentReservation.value, props.table);
};

// Customer search functions
const searchCustomers = async (query) => {
    if (!query || query.length < 2) {
        foundCustomers.value = [];
        return;
    }

    searchingCustomer.value = true;
    try {
        const response = await axios.get('/api/customers/search', {
            params: { q: query, limit: 5 }
        });
        foundCustomers.value = response.data.data || response.data || [];
    } catch (e) {
        foundCustomers.value = [];
    } finally {
        searchingCustomer.value = false;
    }
};

const onNameInput = (e) => {
    const value = e.target.value;
    editData.value.guest_name = value;

    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchCustomers(value);
        if (value.length >= 2) {
            showCustomerDropdown.value = true;
        }
    }, 300);
};

const onPhoneInput = (e) => {
    let value = e.target.value.replace(/\D/g, '');

    // Ensure starts with 7
    if (value.length > 0 && value[0] !== '7') {
        if (value[0] === '8') {
            value = '7' + value.slice(1);
        } else {
            value = '7' + value;
        }
    }

    // Format phone
    let formatted = '';
    if (value.length > 0) {
        formatted = '+' + value[0];
        if (value.length > 1) formatted += ' (' + value.slice(1, 4);
        if (value.length > 4) formatted += ') ' + value.slice(4, 7);
        if (value.length > 7) formatted += '-' + value.slice(7, 9);
        if (value.length > 9) formatted += '-' + value.slice(9, 11);
    }

    editData.value.guest_phone = formatted;

    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (value.length >= 4) {
            searchCustomers(value);
            showCustomerDropdown.value = true;
        }
    }, 300);
};

const selectCustomer = (customer) => {
    editData.value.guest_name = customer.name || '';
    editData.value.guest_phone = formatPhoneDisplay(customer.phone) || '';
    selectedCustomer.value = customer;
    showCustomerDropdown.value = false;
    foundCustomers.value = [];
};

const openCustomerList = () => {
    customerSearch.value = '';
    customerList.value = [];
    activeOverlay.value = 'customers';
    loadCustomerList();
};

const loadCustomerList = async (query = '') => {
    loadingCustomerList.value = true;
    try {
        const params = { per_page: 50, sort: '-created_at' };
        if (query && query.length >= 2) {
            params.search = query;
        }
        const response = await axios.get('/api/customers', { params });
        customerList.value = response.data.data || response.data || [];
    } catch (e) {
        customerList.value = [];
    } finally {
        loadingCustomerList.value = false;
    }
};

const onCustomerSearchInput = () => {
    if (customerSearchTimeout) clearTimeout(customerSearchTimeout);
    customerSearchTimeout = setTimeout(() => {
        loadCustomerList(customerSearch.value);
    }, 300);
};

const customerListFiltered = computed(() => {
    return customerList.value;
});

const selectCustomerFromList = (customer) => {
    editData.value.guest_name = customer.name || '';
    editData.value.guest_phone = formatPhoneDisplay(customer.phone) || '';
    selectedCustomer.value = customer;
    closeOverlay();
};

// Customer card methods
const openCustomerCard = (e) => {
    if (selectedCustomer.value) {
        customerNameRef.value = e.currentTarget;
        showCustomerCard.value = true;
    }
};

const handleCustomerUpdate = (updatedCustomer) => {
    selectedCustomer.value = updatedCustomer;
};

// Check if birthday is within promotion range (3 days before, 15 days after)
const isBirthdaySoon = (birthDate) => {
    if (!birthDate) return false;
    const today = new Date();
    const birth = new Date(birthDate);
    birth.setFullYear(today.getFullYear());

    const diffDays = Math.ceil((birth - today) / (1000 * 60 * 60 * 24));

    // 3 дня до и 15 дней после
    return diffDays >= -15 && diffDays <= 3;
};

const formatPhoneDisplay = (phone) => {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 11) return phone;
    return `+${digits[0]} (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
};

const loadPreorderItems = async () => {
    const res = currentReservation.value;
    if (!res?.id) {
        preorderItemsLocal.value = [];
        return;
    }

    try {
        const response = await axios.get(`/api/reservations/${res.id}/preorder-items`);
        preorderItemsLocal.value = response.data.items || [];
    } catch (e) {
        preorderItemsLocal.value = [];
    }
};

const loadMenuIfNeeded = async () => {
    if (menuLoaded.value) return;
    loadingMenu.value = true;

    try {
        const [catRes, dishRes] = await Promise.all([
            axios.get('/api/categories'),
            axios.get('/api/dishes')
        ]);
        categories.value = catRes.data.data || catRes.data || [];
        dishes.value = (dishRes.data.data || dishRes.data || []).filter(d => d.is_active !== false);
        menuLoaded.value = true;
    } catch (e) {
        console.error('Failed to load menu:', e);
    } finally {
        loadingMenu.value = false;
    }
};

const addToPreorder = async (dish, variant = null, modifiers = []) => {
    const res = currentReservation.value;
    if (!res?.id) {
        alert('Сначала сохраните бронь');
        return;
    }

    try {
        const response = await axios.post(`/api/reservations/${res.id}/preorder-items`, {
            dish_id: variant ? variant.id : dish.id,
            quantity: 1,
            modifiers: modifiers
        });
        if (response.data.success) {
            await loadPreorderItems();
        }
    } catch (e) {
        console.error('Failed to add item:', e);
        alert('Ошибка добавления: ' + (e.response?.data?.message || e.message));
    }
};

// Open variant/modifier modal
const openVariantModal = (dish) => {
    // Если нет вариантов и модификаторов - добавляем сразу
    if (!dish.variants?.length && !dish.modifiers?.length) {
        addToPreorder(dish, null, []);
        return;
    }

    variantModal.value = {
        show: true,
        dish: dish,
        selectedVariant: dish.variants?.length ? dish.variants[0] : null,
        selectedModifiers: {}
    };
};

const closeVariantModal = () => {
    variantModal.value.show = false;
};

// Toggle modifier option selection
const toggleModifierOption = (modifier, option) => {
    const modifierId = modifier.id;

    if (modifier.type === 'single') {
        // Single type - только один выбор
        const current = variantModal.value.selectedModifiers[modifierId] || [];
        if (current.some(o => o.id === option.id)) {
            // Отменяем выбор только если не обязательный
            if (!modifier.is_required) {
                variantModal.value.selectedModifiers[modifierId] = [];
            }
        } else {
            variantModal.value.selectedModifiers[modifierId] = [option];
        }
    } else {
        // Multiple type
        const current = variantModal.value.selectedModifiers[modifierId] || [];
        const idx = current.findIndex(o => o.id === option.id);

        if (idx >= 0) {
            // Отменяем выбор
            current.splice(idx, 1);
            variantModal.value.selectedModifiers[modifierId] = [...current];
        } else {
            // Добавляем если не превышен лимит
            if (!modifier.max_selections || current.length < modifier.max_selections) {
                variantModal.value.selectedModifiers[modifierId] = [...current, option];
            }
        }
    }
};

// Check if modifier option is selected
const isModifierSelected = (modifierId, optionId) => {
    const selected = variantModal.value.selectedModifiers[modifierId] || [];
    return selected.some(o => o.id === optionId);
};

// Check if can add to preorder (all required modifiers selected, variant selected if needed)
const canAddToPreorder = computed(() => {
    const dish = variantModal.value.dish;
    if (!dish) return false;

    // Check variant selection
    if (dish.variants?.length && !variantModal.value.selectedVariant) {
        return false;
    }

    // Check required modifiers
    const modifiers = dish.modifiers || [];
    for (const modifier of modifiers) {
        if (modifier.is_required) {
            const selected = variantModal.value.selectedModifiers[modifier.id] || [];
            if (selected.length === 0) {
                return false;
            }
        }
    }

    return true;
});

// Calculate total for modal
const calculateModalTotal = computed(() => {
    const dish = variantModal.value.dish;
    if (!dish) return 0;

    let total = parseFloat(variantModal.value.selectedVariant?.price) || parseFloat(dish.price) || 0;

    // Add modifiers prices
    Object.values(variantModal.value.selectedModifiers).forEach(options => {
        options.forEach(opt => {
            total += parseFloat(opt.price) || 0;
        });
    });

    return Math.round(total);
});

// Confirm modal and add to preorder
const confirmVariantModal = async () => {
    const dish = variantModal.value.dish;
    const variant = variantModal.value.selectedVariant;

    // Collect modifiers
    const modifiers = [];
    Object.entries(variantModal.value.selectedModifiers).forEach(([modifierId, options]) => {
        options.forEach(opt => {
            modifiers.push({
                modifier_id: parseInt(modifierId),
                option_id: opt.id,
                option_name: opt.name,
                price: opt.price || 0
            });
        });
    });

    await addToPreorder(dish, variant, modifiers);
    closeVariantModal();
};

const removeFromPreorder = async (itemId) => {
    const res = currentReservation.value;
    if (!res?.id) return;

    try {
        await axios.delete(`/api/reservations/${res.id}/preorder-items/${itemId}`);
        await loadPreorderItems();
    } catch (e) {
        console.error('Failed to remove item:', e);
    }
};

const updatePreorderQuantity = async (item, delta) => {
    const res = currentReservation.value;
    if (!res?.id) return;

    const newQuantity = item.quantity + delta;
    if (newQuantity <= 0) {
        await removeFromPreorder(item.id);
        return;
    }

    try {
        await axios.patch(`/api/reservations/${res.id}/preorder-items/${item.id}`, {
            quantity: newQuantity
        });
        await loadPreorderItems();
    } catch (e) {
        console.error('Failed to update quantity:', e);
    }
};

const clearPreorder = () => {
    const res = currentReservation.value;
    if (!res?.id || !preorderItemsLocal.value.length) return;
    showClearPreorderConfirm.value = true;
};

const confirmClearPreorder = async () => {
    const res = currentReservation.value;
    if (!res?.id) return;

    clearingPreorder.value = true;
    try {
        await Promise.all(
            preorderItemsLocal.value.map(item =>
                axios.delete(`/api/reservations/${res.id}/preorder-items/${item.id}`)
            )
        );
        await loadPreorderItems();
        showClearPreorderConfirm.value = false;
    } catch (e) {
        console.error('Failed to clear preorder:', e);
    } finally {
        clearingPreorder.value = false;
    }
};

const openPreorderComment = (item) => {
    commentModal.value = {
        show: true,
        item: item,
        text: item.comment || ''
    };
};

const addQuickCommentOption = (option) => {
    const current = commentModal.value.text || '';
    commentModal.value.text = current ? current + ', ' + option.toLowerCase() : option.toLowerCase();
};

const savePreorderComment = async () => {
    const res = currentReservation.value;
    const item = commentModal.value.item;
    if (!item || !res?.id) return;

    try {
        await axios.patch(`/api/reservations/${res.id}/preorder-items/${item.id}`, {
            quantity: item.quantity,
            comment: commentModal.value.text
        });
        await loadPreorderItems();
        commentModal.value.show = false;
    } catch (e) {
        console.error('Failed to update comment:', e);
    }
};

const formatTime = (time) => {
    if (!time) return '--:--';
    return time.substring(0, 5);
};

const formatPrice = (amount) => {
    if (!amount) return '0 ₽';
    return amount.toLocaleString('ru-RU') + ' ₽';
};

// Sort modifiers - "Тесто" first
const sortModifiers = (modifiers) => {
    if (!modifiers?.length) return [];
    return [...modifiers].sort((a, b) => {
        const nameA = (a.option_name || a.name || '').toLowerCase();
        const nameB = (b.option_name || b.name || '').toLowerCase();
        // "Тесто" или "тонкое" первым
        const isTestoA = nameA.includes('тесто') || nameA.includes('тонкое');
        const isTestoB = nameB.includes('тесто') || nameB.includes('тонкое');
        if (isTestoA && !isTestoB) return -1;
        if (!isTestoA && isTestoB) return 1;
        return 0;
    });
};

// Calculate item total with modifiers
const getItemTotal = (item) => {
    const basePrice = parseFloat(item.price) || 0;
    const modifiersPrice = (item.modifiers || []).reduce((sum, mod) => sum + (parseFloat(mod.price) || 0), 0);
    return (basePrice + modifiersPrice) * (item.quantity || 1);
};

// Handle deposit button click - open payment modal or show menu if paid
const handleDepositButtonClick = () => {
    const depositStatus = currentReservation.value?.deposit_status;

    // If deposit already paid - show menu with options
    if (depositStatus === 'paid') {
        showDepositMenu.value = !showDepositMenu.value;
        return;
    }

    // Otherwise open payment modal
    showDepositPaymentModal.value = true;
};

// Add more deposit
const handleAddMoreDeposit = () => {
    showDepositMenu.value = false;
    showDepositPaymentModal.value = true;
};

// Refund deposit from menu
const handleRefundFromMenu = () => {
    showDepositMenu.value = false;
    handleRefundDeposit();
};

// Deposit confirmation handler (when DepositPicker closes)
const handleDepositConfirm = () => {
    const newAmount = editData.value.deposit || 0;
    const currentStatus = currentReservation.value?.deposit_status;

    // Close the overlay first
    closeOverlay();

    // If deposit amount is set and status is pending (or no status yet), show payment modal
    if (newAmount > 0 && (!currentStatus || currentStatus === 'pending')) {
        showDepositPaymentModal.value = true;
    }
};

// Show success animation
const showSuccess = (amount) => {
    // Small delay to ensure modal is closed first
    setTimeout(() => {
        successAnimationAmount.value = amount;
        showSuccessAnimation.value = true;
        setTimeout(() => {
            showSuccessAnimation.value = false;
        }, 2000);
    }, 100);
};

// Show refund animation
const showRefund = (amount) => {
    // Small delay to ensure modal is closed first
    setTimeout(() => {
        refundAnimationAmount.value = amount;
        showRefundAnimation.value = true;
        setTimeout(() => {
            showRefundAnimation.value = false;
        }, 2000);
    }, 100);
};

// Handle deposit payment from UnifiedPaymentModal
const handleDepositPaymentConfirm = async ({ amount, method }) => {
    showDepositPaymentModal.value = false;

    const res = currentReservation.value;

    // If no reservation yet (new) - just save locally, payment will be on save
    if (!res?.id) {
        editData.value.deposit = amount;
        editData.value.deposit_payment_method = method;
        return;
    }

    const isAddingMore = res.deposit_status === 'paid';

    // For existing reservation - process payment
    processingDepositLocal.value = true;

    try {
        // If adding more to paid deposit - just call API with amount
        // If new deposit - save reservation first, then pay
        if (!isAddingMore) {
            editData.value.deposit = amount;
            editData.value.deposit_payment_method = method;
            await saveReservation({ closeAfterSave: false });
        }

        // Process the payment
        const response = await axios.post(`/api/reservations/${res.id}/deposit/pay`, {
            method: method,
            amount: amount  // Send amount for adding more deposits
        });

        // For adding more - use new_total from response, otherwise use amount
        const newTotal = response.data.new_total || response.data.data?.reservation?.deposit || amount;

        if (currentReservation.value) {
            currentReservation.value.deposit = newTotal;
            currentReservation.value.deposit_status = 'paid';
            currentReservation.value.deposit_payment_method = method;
            editData.value.deposit = newTotal;
        }

        // Show success animation
        showSuccess(amount);

        emit('update', response.data.data?.reservation);
    } catch (e) {
        console.error('Failed to process deposit:', e);
        alert(e.response?.data?.message || 'Ошибка при оплате депозита');
    } finally {
        processingDepositLocal.value = false;
    }
};

// Refund deposit handler
const handleRefundDeposit = () => {
    const res = currentReservation.value;
    if (!res?.id || processingDepositLocal.value) return;

    // Open refund modal with current deposit info
    refundModal.value = {
        show: true,
        amount: res.deposit || 0,
        paymentMethod: res.deposit_payment_method || 'cash',
        paidAt: res.deposit_paid_at,
        reason: ''
    };
};

const confirmRefund = async () => {
    const res = currentReservation.value;
    if (!res?.id || processingDepositLocal.value) return;

    const refundAmount = res.deposit || 0;

    processingDepositLocal.value = true;
    try {
        const response = await axios.post(`/api/reservations/${res.id}/deposit/refund`, {
            reason: refundModal.value.reason || null
        });

        if (response.data.success) {
            // Update local reservation data
            if (currentReservation.value) {
                currentReservation.value.deposit_status = 'refunded';
            }
            refundModal.value.show = false;

            // Show refund animation
            showRefund(refundAmount);

            emit('update', response.data.data?.reservation);
        }
    } catch (e) {
        console.error('Failed to refund deposit:', e);
        alert(e.response?.data?.message || 'Ошибка при возврате депозита');
    } finally {
        processingDepositLocal.value = false;
    }
};
</script>

<style scoped>
/* Toast animation */
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s ease;
}
.toast-slide-enter-from {
    opacity: 0;
    transform: translateX(100%);
}
.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.25s ease-out;
}
.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(100%);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.25s ease-out, opacity 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(20px);
    opacity: 0;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* Success Animation */
.success-fade-enter-active {
    transition: opacity 0.3s ease;
}
.success-fade-leave-active {
    transition: opacity 0.4s ease;
}
.success-fade-enter-from,
.success-fade-leave-to {
    opacity: 0;
}

.success-animation-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.checkmark {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #22c55e;
    stroke-miterlimit: 10;
    animation: checkmark-scale 0.4s ease-in-out 0.4s both, checkmark-glow 1.5s ease-in-out 0.6s;
}

.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #22c55e;
    fill: none;
    animation: checkmark-stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    animation: checkmark-stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.5s forwards;
}

@keyframes checkmark-stroke {
    100% {
        stroke-dashoffset: 0;
    }
}

@keyframes checkmark-scale {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

@keyframes checkmark-glow {
    0% {
        filter: drop-shadow(0 0 0 rgba(34, 197, 94, 0));
    }
    50% {
        filter: drop-shadow(0 0 20px rgba(34, 197, 94, 0.6));
    }
    100% {
        filter: drop-shadow(0 0 0 rgba(34, 197, 94, 0));
    }
}

.success-text {
    text-align: center;
    animation: success-text-appear 0.4s ease-out 0.6s both;
}

@keyframes success-text-appear {
    0% {
        opacity: 0;
        transform: translateY(15px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Refund Animation */
.refund-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #ef4444;
    stroke-miterlimit: 10;
    animation: checkmark-scale 0.4s ease-in-out 0.4s both, refund-glow 1.5s ease-in-out 0.6s;
}

.refund-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #ef4444;
    fill: none;
    animation: checkmark-stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.refund-arrow {
    transform-origin: 50% 50%;
    stroke-dasharray: 60;
    stroke-dashoffset: 60;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
    animation: checkmark-stroke 0.4s cubic-bezier(0.65, 0, 0.45, 1) 0.5s forwards;
}

@keyframes refund-glow {
    0% {
        filter: drop-shadow(0 0 0 rgba(239, 68, 68, 0));
    }
    50% {
        filter: drop-shadow(0 0 20px rgba(239, 68, 68, 0.6));
    }
    100% {
        filter: drop-shadow(0 0 0 rgba(239, 68, 68, 0));
    }
}
</style>
