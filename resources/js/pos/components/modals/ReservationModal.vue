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
        <transition name="fade">
            <div v-if="modelValue"
                 @click="close"
                 class="fixed inset-0 bg-black/50 z-[9998]"></div>
        </transition>

        <!-- Side Panel -->
        <transition name="slide-right">
            <div v-if="modelValue"
                 class="fixed top-0 right-0 h-full w-[480px] bg-[#1a1f2e] border-l border-gray-800 shadow-2xl z-[9999] flex flex-col">

                <!-- Header: Table + Zone -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 text-white">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                            <span class="font-semibold">{{ tablesDisplay }}</span>
                            <span v-if="allTables.length === 1" class="text-gray-500">{{ allTables[0]?.zone_name || '' }}</span>
                            <span v-else class="text-gray-500">{{ totalSeats }} мест</span>
                        </div>
                    </div>
                    <span class="text-gray-400 text-sm">{{ reservation?.id ? 'Редактирование' : 'Новая бронь' }}</span>
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
                            :class="[
                                'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors',
                                editForm.time_from && editForm.time_to
                                    ? 'bg-[#252a3a] hover:bg-[#2d3348]'
                                    : 'bg-orange-500/20 hover:bg-orange-500/30 border border-orange-500/50'
                            ]">
                        <svg :class="['w-4 h-4', editForm.time_from && editForm.time_to ? 'text-gray-400' : 'text-orange-400']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span :class="editForm.time_from && editForm.time_to ? 'text-white' : 'text-orange-400'">
                            {{ editForm.time_from && editForm.time_to ? `${formatTime(editForm.time_from)}–${formatTime(editForm.time_to)}` : 'Выберите время' }}
                        </span>
                    </button>

                    <!-- Guests button -->
                    <button @click="openOverlay('guests')"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-[#252a3a] hover:bg-[#2d3348] rounded-lg text-sm transition-colors">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-white">{{ editForm.guests_count }}</span>
                    </button>
                </div>

                <!-- Main Content -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Guest info fields with background -->
                    <div class="px-4 py-3 space-y-2 bg-dark-900/50 relative">
                        <!-- Row 1: Phone + Name -->
                        <div class="flex gap-2 relative">
                            <div class="flex flex-col">
                                <div class="relative">
                                    <input
                                        :value="editForm.guest_phone"
                                        type="tel"
                                        inputmode="numeric"
                                        placeholder="+7 (___) __-__-__"
                                        @input="onPhoneInput"
                                        @keypress="onlyDigits"
                                        @focus="editForm.guest_phone?.length >= 3 && foundCustomers.length > 0 && (showCustomerDropdown = true)"
                                        @blur="onPhoneBlur"
                                        :class="[
                                            'w-44 bg-dark-800 rounded-lg px-3 pr-8 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:outline-none transition-colors',
                                            editForm.guest_phone && !isPhoneValid ? 'border border-red-500 focus:ring-red-500' : 'border border-transparent focus:ring-accent',
                                            editForm.guest_phone && isPhoneValid ? 'border-green-500' : ''
                                        ]"
                                    />
                                    <!-- Status icon -->
                                    <div class="absolute right-2 top-1/2 -translate-y-1/2">
                                        <svg v-if="searchingCustomer" class="w-4 h-4 animate-spin text-accent" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else-if="editForm.guest_phone && isPhoneValid" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <svg v-else-if="editForm.guest_phone && !isPhoneValid" class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <!-- Hint text -->
                                <p v-if="editForm.guest_phone && !isPhoneValid" class="text-red-400 text-xs mt-1">
                                    Ещё {{ phoneDigitsRemaining }} {{ phoneDigitsRemaining === 1 ? 'цифра' : phoneDigitsRemaining < 5 ? 'цифры' : 'цифр' }}
                                </p>
                            </div>
                            <div class="flex-1 relative">
                                <!-- Если клиент выбран - компактное отображение -->
                                <div v-if="selectedCustomer" class="flex items-center gap-2 bg-dark-800 rounded-lg px-3 py-2">
                                    <button
                                        @click="openCustomerCard"
                                        class="flex items-center gap-2 group"
                                    >
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-purple-500 flex items-center justify-center flex-shrink-0">
                                            <span class="text-white text-xs font-semibold">{{ (selectedCustomer.name || 'К')[0].toUpperCase() }}</span>
                                        </div>
                                        <span class="text-white text-sm font-medium transition-colors group-hover:text-gray-300">{{ selectedCustomer.name }}</span>
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
                                        :value="editForm.guest_name"
                                        type="text"
                                        placeholder="Введите ФИО"
                                        @input="onNameInput"
                                        @focus="editForm.guest_name?.length >= 2 && foundCustomers.length > 0 && (showCustomerDropdown = true)"
                                        @blur="onNameBlur"
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
                            v-model="editForm.notes"
                            type="text"
                            placeholder="Комментарий"
                            class="w-full bg-dark-800 border-0 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:ring-1 focus:ring-accent focus:outline-none"
                        />
                    </div>

                    <!-- Preorder button -->
                    <div class="px-4 py-2">
                        <button @click="openOverlay('preorder')"
                                class="w-full flex items-center justify-between py-2.5 px-3 bg-[#252a3a] hover:bg-[#2d3348] rounded-lg text-sm transition-colors">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                <span class="text-white">{{ preorderItems.length > 0 ? 'Предзаказ' : 'Добавить предзаказ' }}</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Preorder items list -->
                    <div v-if="preorderItems.length > 0" class="px-4 mt-2">
                        <div v-for="item in preorderItems" :key="item.id"
                             class="border-b border-white/5">
                            <div class="px-3 py-2 hover:bg-gray-800/20 transition-colors"
                                 @mouseenter="hoveredItemId = item.id"
                                 @mouseleave="hoveredItemId = null">
                                <!-- First row: name and price -->
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0 bg-blue-500"></span>
                                    <span class="text-gray-200 text-base flex-1 truncate">{{ item.name }}</span>
                                    <span class="text-gray-500 text-sm">{{ formatPrice(item.price) }}</span>
                                    <span class="text-gray-500 text-sm">×</span>
                                    <span class="text-gray-400 text-sm">{{ item.quantity }} шт</span>
                                    <span class="text-gray-300 text-[14px] font-semibold w-20 text-right">{{ formatPrice(item.price * item.quantity) }}</span>
                                </div>
                                <!-- Comment -->
                                <div v-if="item.comment" class="text-yellow-500 text-xs mt-0.5 italic">
                                    💬 {{ item.comment }}
                                </div>
                                <!-- Action buttons (on hover) -->
                                <div v-if="hoveredItemId === item.id"
                                     class="flex items-center gap-2 mt-1 h-9 transition-all">
                                    <button @click.stop="updatePreorderQuantity(item, -1)"
                                            class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">−</button>
                                    <span class="text-gray-300 text-base w-5 text-center">{{ item.quantity }}</span>
                                    <button @click.stop="updatePreorderQuantity(item, 1)"
                                            class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">+</button>
                                    <div class="flex-1"></div>
                                    <button @click.stop="openPreorderComment(item)"
                                            :class="item.comment ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                            class="w-8 h-8 rounded flex items-center justify-center"
                                            title="Комментарий">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                        </svg>
                                    </button>
                                    <button @click.stop="removeFromPreorder(item.id)"
                                            class="w-8 h-8 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                            title="Удалить">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Total -->
                        <div class="flex items-center justify-between pt-2 mt-2 border-t border-gray-700/50 px-3">
                            <span class="text-gray-400 text-sm">Итого:</span>
                            <span class="text-blue-400 font-bold">{{ formatPrice(preorderTotal) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Action buttons - fixed at bottom -->
                <div class="flex-shrink-0 p-3 border-t border-gray-800">
                    <!-- Unified Payment Modal for deposit -->
                    <UnifiedPaymentModal
                        v-model="showDepositPaymentModal"
                        :total="editForm.deposit || 0"
                        :initialAmount="editForm.deposit > 0 ? editForm.deposit : ''"
                        mode="deposit"
                        :bottomSheet="true"
                        :rightAligned="true"
                        :roundAmounts="posStore.roundAmounts"
                        @confirm="handleDepositPaymentConfirm"
                    />

                    <!-- Main action buttons row -->
                    <div class="flex items-stretch gap-1 bg-[#1e2330] rounded-lg p-1">
                        <!-- Deposit button (только для сохранённых броней) -->
                        <button v-if="reservation?.id"
                                @click="handleDepositButtonClick"
                                class="flex flex-col items-center justify-center px-3 py-1 bg-[#252a3a] hover:bg-[#2d3348] rounded-md transition-colors min-w-[100px]">
                            <svg class="w-4 h-4 mb-0.5" :class="editForm.deposit > 0 ? 'text-green-400' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span v-if="editForm.deposit > 0" class="text-[11px] text-green-400 font-bold">{{ formatPrice(editForm.deposit) }}</span>
                            <span v-else class="text-[9px] text-gray-400">Депозит</span>
                        </button>

                        <!-- Save button -->
                        <button @click="saveReservation"
                                :disabled="saving || !canSave"
                                :class="[
                                    'flex-1 flex flex-col items-center justify-center px-3 py-1 rounded-md transition-colors',
                                    canSave
                                        ? 'bg-green-500 hover:bg-green-600 text-white'
                                        : 'bg-gray-600 text-gray-400 cursor-not-allowed'
                                ]">
                            <svg v-if="saving" class="w-4 h-4 mb-0.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-[9px]">Сохранить</span>
                        </button>

                        <!-- Print button (только для сохранённых броней с предзаказом) -->
                        <button v-if="reservation?.id"
                                @click="printPreorder"
                                :disabled="printing || preorderItems.length === 0"
                                :class="[
                                    'flex flex-col items-center justify-center px-2.5 py-1 rounded-md transition-colors min-w-[46px]',
                                    preorderItems.length > 0
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

                        <!-- Close button -->
                        <button @click="close"
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
                                v-model="editForm.date"
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
                            :existingReservations="tableReservations"
                            :selectedDate="editForm.date"
                            :embedded="true"
                            @close="closeOverlay"
                        />
                    </div>
                </Transition>

                <!-- Guests Picker Overlay -->
                <Transition name="slide-up">
                    <div v-if="activeOverlay === 'guests'" class="absolute inset-0 bg-[#1a1f2e] flex flex-col z-10">
                        <GuestCountPicker
                            v-model="editForm.guests_count"
                            :tableSeats="table?.seats || 4"
                            :embedded="true"
                            @close="closeOverlay"
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
                                     @click="addToPreorder(dish)"
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
                                    </div>
                                    <div class="text-blue-400 font-bold">{{ dish.price }}₽</div>
                                    <button class="w-8 h-8 bg-blue-500 hover:bg-blue-600 rounded-lg flex items-center justify-center text-white">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Preorder summary -->
                        <div v-if="preorderItems.length > 0" class="border-t border-gray-800 bg-[#1d2230] max-h-[50vh] flex flex-col">
                            <div class="p-3 flex flex-col min-h-0">
                                <div class="text-xs text-gray-500 tracking-wider mb-2 flex-shrink-0">ПРЕДЗАКАЗ ({{ preorderItems.length }})</div>
                                <div class="overflow-y-auto flex-1">
                                    <div v-for="item in preorderItems" :key="item.id"
                                         class="border-b border-white/5">
                                        <div class="px-3 py-2 hover:bg-gray-800/20 transition-colors"
                                             @mouseenter="hoveredItemId = item.id"
                                             @mouseleave="hoveredItemId = null">
                                            <!-- First row: name and price -->
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full flex-shrink-0 bg-blue-500"></span>
                                                <span class="text-gray-200 text-base flex-1 truncate">{{ item.name }}</span>
                                                <span class="text-gray-500 text-sm">{{ formatPrice(item.price) }}</span>
                                                <span class="text-gray-500 text-sm">×</span>
                                                <span class="text-gray-400 text-sm">{{ item.quantity }} шт</span>
                                                <span class="text-gray-300 text-[14px] font-semibold w-20 text-right">{{ formatPrice(item.price * item.quantity) }}</span>
                                            </div>
                                            <!-- Comment -->
                                            <div v-if="item.comment" class="text-yellow-500 text-xs mt-0.5 italic">
                                                💬 {{ item.comment }}
                                            </div>
                                            <!-- Action buttons (on hover) -->
                                            <div v-if="hoveredItemId === item.id"
                                                 class="flex items-center gap-2 mt-1 h-9 transition-all">
                                                <button @click.stop="updatePreorderQuantity(item, -1)"
                                                        class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">−</button>
                                                <span class="text-gray-300 text-base w-5 text-center">{{ item.quantity }}</span>
                                                <button @click.stop="updatePreorderQuantity(item, 1)"
                                                        class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">+</button>
                                                <div class="flex-1"></div>
                                                <button @click.stop="openPreorderComment(item)"
                                                        :class="item.comment ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                                        class="w-8 h-8 rounded flex items-center justify-center"
                                                        title="Комментарий">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                                    </svg>
                                                </button>
                                                <button @click.stop="removeFromPreorder(item.id)"
                                                        class="w-8 h-8 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                                        title="Удалить">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-700 flex-shrink-0 px-3">
                                    <span class="text-gray-400 text-sm">Итого:</span>
                                    <span class="text-blue-400 font-bold text-lg">{{ formatPrice(preorderTotal) }}</span>
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

                <!-- Customers List Overlay -->
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
                                       @input="onCustomerSearchInput"
                                       placeholder="Поиск по имени или телефону..."
                                       class="w-full bg-[#252a3a] text-white text-sm px-4 py-2 pl-10 rounded-lg border border-gray-700 focus:border-blue-500 outline-none">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Customer list -->
                        <div class="flex-1 overflow-y-auto">
                            <div v-if="loadingCustomerList" class="text-center text-gray-500 py-8">
                                Загрузка клиентов...
                            </div>
                            <div v-else-if="customerList.length === 0" class="text-center text-gray-500 py-8">
                                Клиенты не найдены
                            </div>
                            <div v-else class="divide-y divide-gray-800">
                                <button
                                    v-for="customer in customerList"
                                    :key="customer.id"
                                    @click="selectCustomerFromList(customer)"
                                    class="w-full flex items-center gap-3 px-4 py-3 hover:bg-[#252a3a] transition-colors text-left"
                                >
                                    <div class="w-10 h-10 bg-accent/20 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-accent font-medium">{{ (customer.name || 'К')[0].toUpperCase() }}</span>
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


            </div>
        </transition>

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
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import InlineCalendar from '../floor/InlineCalendar.vue';
import TimelineTimePicker from '../floor/TimelineTimePicker.vue';
import GuestCountPicker from '../floor/GuestCountPicker.vue';
import UnifiedPaymentModal from '../../../components/UnifiedPaymentModal.vue';
import CustomerInfoCard from '../../../components/CustomerInfoCard.vue';
import ConfirmModal from './ConfirmModal.vue';
import { usePosStore } from '../../stores/pos';
import { getLocalDateString, getTimezone } from '../../../utils/timezone';

// Get current hour in configured timezone
const getCurrentHourInTimezone = () => {
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('en-US', {
        hour: 'numeric',
        hour12: false,
        timeZone: getTimezone()
    });
    return parseInt(formatter.format(now), 10);
};

const posStore = usePosStore();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    reservation: { type: Object, default: null },
    table: { type: Object, default: null },
    tables: { type: Array, default: () => [] }, // Для мультивыбора столов
    existingReservations: { type: Array, default: () => [] },
    initialDate: { type: String, default: '' }
});

const emit = defineEmits(['update:modelValue', 'save', 'cancel']);

// Computed: все столы (для мультивыбора или один стол)
const allTables = computed(() => {
    if (props.tables && props.tables.length > 0) {
        return props.tables;
    }
    return props.table ? [props.table] : [];
});

// Computed: отображение столов в заголовке
const tablesDisplay = computed(() => {
    if (allTables.value.length === 0) return '';
    if (allTables.value.length === 1) {
        return `Стол ${allTables.value[0].name || allTables.value[0].number}`;
    }
    const names = allTables.value.map(t => t.name || t.number).join(' + ');
    return `Столы ${names}`;
});

// Computed: суммарное количество мест
const totalSeats = computed(() => {
    return allTables.value.reduce((sum, t) => sum + (t.seats || 4), 0);
});

// State
const saving = ref(false);
const printing = ref(false);
const activeOverlay = ref(null);

// Toast
const toast = ref({ show: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};
const preorderItems = ref([]);
const hoveredItemId = ref(null);
const processingDeposit = ref(false);
const showDepositPaymentModal = ref(false);

// Customer search
const showCustomerDropdown = ref(false);
const foundCustomers = ref([]);
const searchingCustomer = ref(false);
const customerSearch = ref('');
const customerList = ref([]);
const loadingCustomerList = ref(false);
const selectedCustomer = ref(null);
const showCustomerCard = ref(false);
const customerNameRef = ref(null);
let searchTimeout = null;
let customerSearchTimeout = null;

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


const editForm = ref({
    date: '',
    time_from: '',
    time_to: '',
    guests_count: 2,
    guest_name: '',
    guest_phone: '',
    notes: '',
    deposit: 0,
    deposit_payment_method: 'cash'
});

// Menu state
const loadingMenu = ref(false);
const menuLoaded = ref(false);
const categories = ref([]);
const dishes = ref([]);
const selectedCategory = ref(null);
const menuSearch = ref('');

// Computed
const isNewReservation = computed(() => !props.reservation?.id);
const todayDate = computed(() => getLocalDateString());

// Получаем брони для этого стола из store (если не переданы через props)
const tableReservations = computed(() => {
    // Если переданы через props, используем их
    if (props.existingReservations && props.existingReservations.length > 0) {
        return props.existingReservations;
    }
    // Иначе берём из store для текущего стола
    if (props.table?.id) {
        return posStore.getTableReservations(props.table.id) || [];
    }
    return [];
});

// Валидация формы - нельзя сохранить без времени, имени и полного телефона
const isPhoneValid = computed(() => {
    const digits = (editForm.value.guest_phone || '').replace(/\D/g, '');
    return digits.length >= 11;
});

// Сколько цифр осталось ввести
const phoneDigitsRemaining = computed(() => {
    const digits = (editForm.value.guest_phone || '').replace(/\D/g, '');
    return Math.max(0, 11 - digits.length);
});

const canSave = computed(() => {
    return editForm.value.time_from &&
           editForm.value.time_to &&
           editForm.value.guest_name?.trim() &&
           editForm.value.guest_phone?.trim() &&
           isPhoneValid.value;
});

// Можно печатать только если есть предзаказ и бронь сохранена
const canPrint = computed(() => {
    return props.reservation?.id && preorderItems.value.length > 0;
});

const dateBadgeText = computed(() => {
    const date = editForm.value.date;
    if (!date) return 'Сегодня';
    const today = getLocalDateString();
    if (date === today) return 'Сегодня';
    // Calculate tomorrow in restaurant's timezone
    const todayParts = today.split('-').map(Number);
    const tomorrowDate = new Date(todayParts[0], todayParts[1] - 1, todayParts[2] + 1);
    const tomorrowStr = `${tomorrowDate.getFullYear()}-${String(tomorrowDate.getMonth() + 1).padStart(2, '0')}-${String(tomorrowDate.getDate()).padStart(2, '0')}`;
    if (date === tomorrowStr) return 'Завтра';
    const d = new Date(date);
    const months = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    return `${d.getDate()} ${months[d.getMonth()]}`;
});

const timePickerData = computed({
    get: () => ({
        time_from: editForm.value.time_from || '19:00',
        time_to: editForm.value.time_to || '21:00'
    }),
    set: (val) => {
        editForm.value.time_from = val.time_from;
        editForm.value.time_to = val.time_to;
    }
});

const preorderTotal = computed(() => {
    return preorderItems.value.reduce((sum, i) => {
        const price = parseFloat(i.price) || 0;
        const quantity = parseInt(i.quantity) || 0;
        const total = parseFloat(i.total) || (price * quantity);
        return sum + total;
    }, 0);
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

// Watch
watch(() => props.modelValue, (isOpen) => {
    if (isOpen) {
        activeOverlay.value = null;
        menuSearch.value = '';
        selectedCategory.value = null;
        initForm();
        loadPreorderItems();
    }
});

// Methods
const initForm = () => {
    const res = props.reservation;

    // Нормализуем дату (убираем время если есть)
    const normalizeDate = (d) => d ? d.substring(0, 10) : null;

    // Для новых бронирований время пустое - пользователь должен выбрать
    editForm.value = {
        date: normalizeDate(res?.date) || normalizeDate(props.initialDate) || todayDate.value,
        time_from: res?.time_from?.substring(0, 5) || '',
        time_to: res?.time_to?.substring(0, 5) || '',
        guests_count: res?.guests_count || 2,
        guest_name: res?.guest_name || '',
        guest_phone: res?.guest_phone || '',
        notes: res?.notes || '',
        deposit: res?.deposit || 0,
        deposit_payment_method: res?.deposit_payment_method || 'cash'
    };
};

const close = () => {
    emit('update:modelValue', false);
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

// Handle deposit button click - open payment modal directly
const handleDepositButtonClick = () => {
    showDepositPaymentModal.value = true;
};

// Handle deposit payment from UnifiedPaymentModal
const handleDepositPaymentConfirm = ({ amount, method }) => {
    showDepositPaymentModal.value = false;

    // Update deposit amount in editForm
    editForm.value.deposit = amount;
    editForm.value.deposit_payment_method = method;
};

// Save reservation and optionally process deposit payment
const saveWithDepositPayment = async (paymentMethod = null) => {
    if (saving.value) return;
    saving.value = true;
    processingDeposit.value = true;

    try {
        const formData = {
            table_id: props.table?.id,
            date: editForm.value.date,
            time_from: editForm.value.time_from,
            time_to: editForm.value.time_to,
            guest_count: editForm.value.guest_count,
            guest_name: editForm.value.guest_name,
            guest_phone: editForm.value.guest_phone,
            notes: editForm.value.notes,
            deposit: editForm.value.deposit || 0,
            deposit_payment_method: editForm.value.deposit_payment_method,
        };

        // Create the reservation
        const response = await axios.post('/api/reservations', formData);

        if (response.data.success && response.data.reservation) {
            const newReservation = response.data.reservation;

            // If payment method specified and deposit > 0, process payment
            if (paymentMethod && editForm.value.deposit > 0) {
                try {
                    await axios.post(`/api/reservations/${newReservation.id}/deposit/pay`, {
                        method: paymentMethod
                    });
                    newReservation.deposit_status = 'paid';
                    newReservation.deposit_payment_method = paymentMethod;
                } catch (payError) {
                    console.error('Failed to process deposit payment:', payError);
                    // Reservation is created but payment failed - notify user
                    alert('Бронь создана, но оплата депозита не прошла: ' + (payError.response?.data?.message || 'Ошибка'));
                }
            }

            emit('save', newReservation);
            emit('update:modelValue', false);
        }
    } catch (e) {
        console.error('Failed to create reservation:', e);
        alert(e.response?.data?.message || 'Ошибка при создании брони');
    } finally {
        saving.value = false;
        processingDeposit.value = false;
    }
};

const saveReservation = async () => {
    if (saving.value) return;

    // Валидация: время должно быть выбрано
    if (!editForm.value.time_from || !editForm.value.time_to) {
        alert('Выберите время бронирования');
        openOverlay('time');
        return;
    }

    saving.value = true;

    try {
        let response;
        let newReservationId = null;

        if (props.reservation?.id) {
            response = await axios.put(`/api/reservations/${props.reservation.id}`, editForm.value);
        } else {
            // Получаем ID столов
            const tableIds = allTables.value.map(t => t.id);
            const createData = {
                ...editForm.value,
                table_id: tableIds[0], // Основной стол
                table_ids: tableIds.length > 1 ? tableIds : undefined // Все столы для мультивыбора
                // restaurant_id определяется на бэкенде из авторизации
            };
            response = await axios.post('/api/reservations', createData);
            newReservationId = response.data.reservation?.id || response.data.data?.id;
        }

        if (response.data.success) {
            // Save local preorder items for new reservation
            if (newReservationId && preorderItems.value.length > 0) {
                const localItems = preorderItems.value.filter(item => item.isLocal);
                for (const item of localItems) {
                    try {
                        await axios.post(`/api/reservations/${newReservationId}/preorder-items`, {
                            dish_id: item.dish_id,
                            quantity: item.quantity,
                            comment: item.comment || ''
                        });
                    } catch (e) {
                        console.error('Failed to save preorder item:', e);
                    }
                }
            }

            emit('save', response.data.reservation || response.data.data);
            close();
        } else {
            alert(response.data.message || 'Ошибка сохранения');
        }
    } catch (e) {
        console.error('Save error:', e);
        alert('Ошибка: ' + (e.response?.data?.message || e.message));
    } finally {
        saving.value = false;
    }
};

// Save and close for existing reservations
const closeAndSave = async () => {
    if (saving.value) return;
    saving.value = true;

    try {
        const response = await axios.put(`/api/reservations/${props.reservation.id}`, editForm.value);
        if (response.data.success) {
            emit('save', response.data.reservation || response.data.data);
        }
    } catch (e) {
        console.error('Save error:', e);
    } finally {
        saving.value = false;
        close();
    }
};

// Печать предзаказа на кухню
const printPreorder = async () => {
    if (printing.value) return;

    // Проверяем что бронь сохранена
    if (!props.reservation?.id) {
        showToast('Сначала сохраните бронь', 'warning');
        return;
    }

    // Проверяем что есть позиции
    if (preorderItems.value.length === 0) {
        showToast('Добавьте позиции в предзаказ', 'warning');
        return;
    }

    printing.value = true;

    try {
        const response = await axios.post(`/api/reservations/${props.reservation.id}/print-preorder`);

        if (response.data.success) {
            showToast('Предзаказ отправлен на кухню', 'success');
        } else {
            showToast(response.data.message || 'Ошибка печати', 'error');
        }
    } catch (e) {
        console.error('Print error:', e);
        showToast('Ошибка: ' + (e.response?.data?.message || e.message), 'error');
    } finally {
        printing.value = false;
    }
};

const loadPreorderItems = async () => {
    if (!props.reservation?.id) {
        preorderItems.value = [];
        return;
    }

    try {
        const response = await axios.get(`/api/reservations/${props.reservation.id}/preorder-items`);
        preorderItems.value = response.data.items || [];
    } catch (e) {
        preorderItems.value = [];
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

// Local ID counter for new preorder items
let localItemId = 1;

const addToPreorder = async (dish) => {
    // For new reservations - add locally
    if (!props.reservation?.id) {
        const existingItem = preorderItems.value.find(item => item.dish_id === dish.id);
        if (existingItem) {
            existingItem.quantity += 1;
            existingItem.total = existingItem.price * existingItem.quantity;
        } else {
            preorderItems.value.push({
                id: `local_${localItemId++}`,
                dish_id: dish.id,
                name: dish.name,
                price: dish.price,
                quantity: 1,
                total: dish.price,
                comment: '',
                isLocal: true
            });
        }
        return;
    }

    // For existing reservations - save via API
    try {
        const response = await axios.post(`/api/reservations/${props.reservation.id}/preorder-items`, {
            dish_id: dish.id,
            quantity: 1
        });
        if (response.data.success) {
            await loadPreorderItems();
        }
    } catch (e) {
        console.error('Failed to add item:', e);
        alert('Ошибка добавления: ' + (e.response?.data?.message || e.message));
    }
};

const removeFromPreorder = async (itemId) => {
    // For new reservations - remove locally
    if (!props.reservation?.id) {
        preorderItems.value = preorderItems.value.filter(item => item.id !== itemId);
        return;
    }

    // For existing reservations - delete via API
    try {
        await axios.delete(`/api/reservations/${props.reservation.id}/preorder-items/${itemId}`);
        await loadPreorderItems();
    } catch (e) {
        console.error('Failed to remove item:', e);
    }
};

const updatePreorderQuantity = async (item, delta) => {
    const newQuantity = item.quantity + delta;
    if (newQuantity <= 0) {
        await removeFromPreorder(item.id);
        return;
    }

    // For new reservations - update locally
    if (!props.reservation?.id) {
        item.quantity = newQuantity;
        item.total = item.price * newQuantity;
        return;
    }

    // For existing reservations - update via API
    try {
        await axios.patch(`/api/reservations/${props.reservation.id}/preorder-items/${item.id}`, {
            quantity: newQuantity
        });
        await loadPreorderItems();
    } catch (e) {
        console.error('Failed to update quantity:', e);
    }
};

const clearPreorder = () => {
    if (!preorderItems.value.length) return;
    showClearPreorderConfirm.value = true;
};

const confirmClearPreorder = async () => {
    clearingPreorder.value = true;

    // For new reservations - clear locally
    if (!props.reservation?.id) {
        preorderItems.value = [];
        showClearPreorderConfirm.value = false;
        clearingPreorder.value = false;
        return;
    }

    // For existing reservations - delete via API
    try {
        await Promise.all(
            preorderItems.value.map(item =>
                axios.delete(`/api/reservations/${props.reservation.id}/preorder-items/${item.id}`)
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
    const item = commentModal.value.item;
    if (!item) return;

    // For new reservations - update locally
    if (!props.reservation?.id) {
        const localItem = preorderItems.value.find(i => i.id === item.id);
        if (localItem) {
            localItem.comment = commentModal.value.text;
        }
        commentModal.value.show = false;
        return;
    }

    // For existing reservations - update via API
    try {
        await axios.patch(`/api/reservations/${props.reservation.id}/preorder-items/${item.id}`, {
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
    const num = parseFloat(amount) || 0;
    return Math.round(num).toLocaleString('ru-RU') + ' ₽';
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
    editForm.value.guest_name = value;

    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchCustomers(value);
        if (value.length >= 2) {
            showCustomerDropdown.value = true;
        }
    }, 300);
};

// Скрытие dropdown при потере фокуса телефона
const onPhoneBlur = () => {
    setTimeout(() => showCustomerDropdown.value = false, 200);
};

// Форматирование имени при потере фокуса
const onNameBlur = () => {
    setTimeout(() => showCustomerDropdown.value = false, 200);

    // Форматируем имя: первая буква каждого слова заглавная
    if (editForm.value.guest_name) {
        const words = editForm.value.guest_name.trim().replace(/\s+/g, ' ').split(' ');
        editForm.value.guest_name = words.map(word => {
            if (!word) return '';
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }).join(' ');
    }
};

// Блокировка ввода букв - только цифры
const onlyDigits = (e) => {
    const char = String.fromCharCode(e.which || e.keyCode);
    if (!/[\d]/.test(char)) {
        e.preventDefault();
    }
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

    editForm.value.guest_phone = formatted;

    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (value.length >= 4) {
            searchCustomers(value);
            showCustomerDropdown.value = true;
        }
    }, 300);
};

const selectCustomer = (customer) => {
    editForm.value.guest_name = customer.name || '';
    editForm.value.guest_phone = formatPhoneDisplay(customer.phone) || '';
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

const selectCustomerFromList = (customer) => {
    editForm.value.guest_name = customer.name || '';
    editForm.value.guest_phone = formatPhoneDisplay(customer.phone) || '';
    selectedCustomer.value = customer;
    closeOverlay();
};

const formatPhoneDisplay = (phone) => {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 11) return phone;
    return `+${digits[0]} (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
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
</style>
