<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
        $linkedTableNumbers = '';
        if (!empty($linkedTableIds)) {
            $linkedTables = \App\Models\Table::whereIn('id', $linkedTableIds)->orderBy('number')->pluck('number')->toArray();
            $linkedTableNumbers = implode(' + ', $linkedTables);
        } else {
            $linkedTableNumbers = $table->number;
        }
    @endphp
    <title>MenuLab - Стол {{ $linkedTableNumbers }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <style>
        body { background: #0a0a0f; font-family: 'Inter', system-ui, sans-serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
        .product-card:hover { background: #1f2937; }
        .product-card:hover .product-icon { transform: scale(1.05); }
        .cart-item .item-actions { opacity: 0; transition: opacity 0.2s; }
        .cart-item:hover .item-actions { opacity: 1; }
        .cart-item:hover .item-qty { opacity: 0; }
        .cart-item.pending .item-actions { opacity: 1; }
        .cart-item.pending:hover .item-qty { opacity: 0; }
        .guest-section.collapsed .guest-items { display: none; }
        .guest-section.collapsed .collapse-icon { transform: rotate(-90deg); }
        .guest-header.active { background: rgba(249, 115, 22, 0.1); }
        .guest-section.selected { background: rgba(249, 115, 22, 0.08); }
    </style>
</head>
<body class="h-screen overflow-hidden">
    <div id="app" class="flex flex-col h-full">

        <!-- ШАПКА НА ВСЮ ШИРИНУ -->
        <div class="h-14 bg-[#1e2430] border-b border-gray-800/50 flex items-center px-4 gap-4 flex-shrink-0">
            <!-- Кнопка Заказ (назад) -->
            <a href="/pos-vue#hall" class="px-4 py-2 bg-[#2a3142] text-gray-300 hover:bg-gray-600 rounded-lg text-sm font-medium">
                ← Заказ
            </a>

            <!-- Стол с dropdown -->
            <button class="px-4 py-2 bg-[#2a3142] border border-blue-500/50 text-white rounded-lg text-sm font-medium flex items-center gap-2 hover:bg-[#343d52]">
                {{ $linkedTableNumbers ? "Стол " . $linkedTableNumbers : ($table->name ?: "Стол " . $table->number) }}
                <span class="text-gray-400">▼</span>
            </button>

            @if($reservation ?? false)
            <!-- Инфо о брони -->
            <div class="flex items-center gap-1.5 bg-teal-500/10 px-3 py-1.5 rounded-lg border border-teal-500/30">
                <span class="text-teal-400 font-medium text-sm">Сегодня</span>
                <span class="text-teal-300 bg-teal-500/20 px-2 py-0.5 rounded text-xs flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ \Carbon\Carbon::parse($reservation->time_from)->format('H:i') }}
                </span>
            </div>
            @endif

            <!-- Индикатор предзаказа -->
            <div v-if="currentOrder?.type === 'preorder'" class="flex items-center gap-1.5 bg-purple-500/20 px-3 py-1.5 rounded-lg border border-purple-500/40">
                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-purple-300 font-semibold text-sm">ПРЕДЗАКАЗ</span>
            </div>

            <!-- Табы заказов (скрыты для предзаказов) -->
            <div v-if="!orders.every(o => o.type === 'preorder')" class="flex items-center gap-1.5 relative">
                <template v-for="(order, index) in orders.slice(0, 4)" :key="order.id">
                    <button @click="currentOrderIndex = index"
                        :class="currentOrderIndex === index ? 'bg-blue-500 text-white' : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'"
                        class="w-8 h-8 rounded-lg text-sm font-bold transition-all">
                        @{{ index + 1 }}
                    </button>
                </template>

                <!-- Три точки / + если заказов больше 4 -->
                <div v-if="orders.length > 4" class="relative">
                    <button @click="showOrdersDropdown = !showOrdersDropdown"
                        :class="currentOrderIndex >= 4 ? 'bg-blue-500 text-white' : 'bg-[#2a3142] text-gray-400 hover:bg-gray-600'"
                        class="w-8 h-8 rounded-lg text-sm font-bold transition-all">
                        <span v-if="currentOrderIndex >= 4">@{{ currentOrderIndex + 1 }}</span>
                        <span v-else>...</span>
                    </button>
                    <!-- Dropdown -->
                    <div v-if="showOrdersDropdown"
                         class="absolute top-10 left-0 bg-[#2a3142] border border-gray-700 rounded-lg shadow-xl z-50 py-1 min-w-[140px]">
                        <button v-for="(order, index) in orders" :key="'drop-' + order.id"
                            @click="currentOrderIndex = index; showOrdersDropdown = false"
                            :class="currentOrderIndex === index ? 'bg-blue-500/20 text-blue-400' : 'text-gray-300 hover:bg-gray-700'"
                            class="w-full px-3 py-2 text-sm text-left flex items-center gap-2">
                            <span class="w-6 h-6 rounded flex items-center justify-center text-xs font-bold"
                                :class="currentOrderIndex === index ? 'bg-blue-500 text-white' : 'bg-gray-600'">@{{ index + 1 }}</span>
                            <span>Заказ @{{ index + 1 }}</span>
                        </button>
                    </div>
                </div>

                <button v-if="currentOrder?.type !== 'preorder'" @click="createNewOrder" class="w-8 h-8 rounded-lg bg-[#2a3142] text-gray-400 hover:bg-gray-600 hover:text-white text-sm font-bold">+</button>
            </div>
            <!-- Закрытие dropdown -->
            <div v-if="showOrdersDropdown" @click="showOrdersDropdown = false" class="fixed inset-0 z-40"></div>

            <!-- Итого -->
            <div class="flex items-center gap-2">
                <span class="text-gray-500 text-sm">Итого</span>
                <span class="text-blue-500 font-bold text-lg">@{{ formatPrice(orderTotal) }}</span>
            </div>


            <!-- Поиск -->
            <div class="relative">
                <input type="text" v-model="searchQuery" placeholder="Найти..."
                    class="w-40 bg-[#2a3142] border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none">
            </div>

            <!-- Кнопки вида -->
            <div class="flex items-center gap-1">
                <button class="w-9 h-9 bg-[#2a3142] text-gray-400 hover:bg-gray-600 hover:text-white rounded-lg flex items-center justify-center" title="Сетка">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/></svg>
                </button>
                <button class="w-9 h-9 bg-[#2a3142] text-gray-400 hover:bg-gray-600 hover:text-white rounded-lg flex items-center justify-center" title="Список">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/></svg>
                </button>
            </div>
        </div>

        <!-- ОСНОВНОЙ КОНТЕНТ -->
        <div class="flex flex-1 overflow-hidden">

            <!-- ЛЕВАЯ ПАНЕЛЬ: Гости -->
            
            <div class="w-[440px] bg-[#151921] flex flex-col border-r border-gray-800/50">

                <!-- Панель информации о брони -->
                @if($reservation ?? false)
                <div class="bg-[#1a1f2e] border-b border-gray-700/50 px-3 py-2 flex-shrink-0">
                    <div class="flex items-center gap-2 text-gray-400 text-sm mb-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span>{{ $reservation->guest_name ?? 'Клиент' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400 text-sm mb-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <span>{{ $reservation->guest_phone ?? 'Телефон' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        <span class="truncate">{{ $reservation->notes ?? 'Комментарий' }}</span>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <button class="px-3 py-1 bg-orange-500 hover:bg-orange-600 text-white text-sm rounded-lg font-medium">
                            OK
                        </button>
                    </div>
                </div>
                @endif


                <!-- Гости со списком товаров -->
            <div class="flex-1 overflow-y-auto">
                <div v-for="guest in currentGuests" :key="guest.number"
                     class="guest-section border-b border-white/10"
                     :class="{ collapsed: guest.collapsed }">

                    <!-- Заголовок гостя -->
                    <div class="px-3 py-2 flex items-center gap-2 cursor-pointer hover:bg-gray-800/30 transition-colors group"
                         :class="{ 'bg-blue-500/10 border-l-2 border-blue-500': selectedGuest === guest.number }"
                         @click="selectGuest(guest.number)">
                        <span class="collapse-icon text-gray-600 text-xs transition-transform duration-200 w-3"
                              @click.stop="guest.collapsed = !guest.collapsed">▼</span>
                        <span class="text-gray-200 text-base font-medium">Гость @{{ guest.number }}</span>

                        <!-- Бейдж новых позиций -->
                        <span v-if="getGuestPendingCount(guest) > 0"
                              class="bg-blue-500 text-white text-[10px] px-2 py-0.5 rounded font-medium">
                            новые @{{ getGuestPendingCount(guest) }}
                        </span>

                        <!-- Бейдж готовых к подаче -->
                        <span v-if="getGuestReadyCount(guest) > 0"
                              class="bg-green-500 text-white text-[10px] px-2 py-0.5 rounded font-medium">
                            🍽️ подать @{{ getGuestReadyCount(guest) }}
                        </span>

                        <!-- Кнопка выбора (появляется при hover) -->
                        <button v-if="!selectMode && guest.items.length > 0 && currentGuests.length > 1"
                                @click.stop="startSelectMode(guest.number)"
                                class="opacity-0 group-hover:opacity-100 px-2 py-0.5 text-gray-500 hover:text-blue-400 text-xs transition-all"
                                title="Выбрать для переноса">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </button>

                        <span class="text-white text-base ml-auto font-bold">@{{ formatPrice(guest.total) }}</span>
                    </div>

                    <!-- Панель мультивыбора -->
                    <div v-if="selectMode && selectModeGuest === guest.number"
                         class="px-3 py-2 bg-blue-500/10 border-b border-blue-500/30 flex items-center gap-2">
                        <button @click="selectAllGuestItems(guest)"
                                class="px-2 py-1 text-xs text-blue-400 hover:bg-blue-500/20 rounded transition-colors">
                            Все
                        </button>
                        <button @click="deselectAllItems"
                                class="px-2 py-1 text-xs text-gray-400 hover:bg-gray-500/20 rounded transition-colors">
                            Сбросить
                        </button>
                        <span class="text-gray-500 text-xs">|</span>
                        <span class="text-gray-400 text-xs">Выбрано: @{{ selectedItems.length }}</span>
                        <div class="ml-auto flex items-center gap-2">
                            <button v-if="selectedItems.length > 0"
                                    @click="openBulkMoveModal"
                                    class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition-colors">
                                Перенести
                            </button>
                            <button @click="cancelSelectMode"
                                    class="px-2 py-1 text-gray-500 hover:text-white text-xs transition-colors">
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- Товары гостя -->
                    <div class="guest-items">
                        <div v-if="guest.items.length === 0" class="px-4 py-3 text-center">
                            <p class="text-gray-600 text-sm">Нет позиций</p>
                        </div>

                        <div v-for="item in guest.items" :key="item.id"
                             class="px-3 py-2 hover:bg-gray-800/20 group transition-colors border-b border-white/5"
                             :class="{ 'opacity-50': ['cancelled', 'voided'].includes(item.status), 'bg-blue-500/10': selectMode && selectModeGuest === guest.number && selectedItems.includes(item.id), 'cursor-pointer': selectMode && selectModeGuest === guest.number }"
                             @click="selectMode && selectModeGuest === guest.number ? toggleItemSelection(item.id) : null">
                            <!-- Первая строка: название и цена -->
                            <div class="flex items-center gap-2">
                                <!-- Чекбокс в режиме выбора -->
                                <label v-if="selectMode && selectModeGuest === guest.number"
                                       class="flex items-center cursor-pointer"
                                       @click.stop>
                                    <input type="checkbox"
                                           :checked="selectedItems.includes(item.id)"
                                           @change="toggleItemSelection(item.id)"
                                           class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-0 cursor-pointer">
                                </label>

                                <!-- Точка статуса -->
                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                      :class="{
                                          'bg-blue-500': item.status === 'pending',
                                          'bg-orange-500': item.status === 'cooking',
                                          'bg-green-500': item.status === 'ready',
                                          'bg-purple-500': item.status === 'served',
                                          'bg-gray-500': ['cancelled', 'voided'].includes(item.status)
                                      }"></span>

                                <span class="text-gray-200 text-base flex-1 truncate"
                                      :class="{ 'line-through text-gray-500': ['cancelled', 'voided'].includes(item.status) }">
                                    @{{ item.name || item.dish?.name }}
                                </span>
                                <span class="text-gray-500 text-sm">@{{ formatPrice(item.price) }}</span>
                                <span class="text-gray-500 text-sm">×</span>
                                <span class="text-gray-400 text-sm">@{{ item.quantity }} шт</span>
                                <span class="text-gray-300 text-[14px] font-semibold w-20 text-right">@{{ formatPrice(item.price * item.quantity) }}</span>
                            </div>

                            <!-- Комментарий к блюду (если есть) -->
                            <div v-if="item.comment" class="text-yellow-500 text-xs mt-0.5 italic">
                                💬 @{{ item.comment }}
                            </div>

                            <!-- Вторая строка: кнопки управления (при наведении) -->
                            <div v-if="['pending', 'saved'].includes(item.status) && !selectMode" class="flex items-center gap-2 mt-1 h-0 overflow-hidden group-hover:h-9 transition-all">
                                <!-- Кнопки +/- -->
                                <button @click.stop="updateItemQuantity(item, -1)"
                                        class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">−</button>
                                <span class="text-gray-300 text-base w-5 text-center">@{{ item.quantity }}</span>
                                <button @click.stop="updateItemQuantity(item, 1)"
                                        class="w-7 h-7 bg-gray-700/50 text-gray-300 rounded text-base hover:bg-gray-600 flex items-center justify-center">+</button>

                                <div class="flex-1"></div>

                                <!-- Кнопка отправки на кухню -->
                                <button @click.stop="sendItemToKitchen(item)"
                                        class="w-8 h-8 text-gray-400 hover:text-blue-500 rounded flex items-center justify-center"
                                        title="Отправить на кухню">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"/></svg>
                                </button>

                                <!-- Кнопка комментария -->
                                <button @click.stop="openCommentModal(item)"
                                        :class="item.comment ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'"
                                        class="w-8 h-8 rounded flex items-center justify-center"
                                        title="Комментарий для кухни">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                </button>

                                <!-- Кнопка переноса к другому гостю -->
                                <button v-if="currentGuests.length > 1" @click.stop="openMoveModal(item, guest)"
                                        class="w-8 h-8 text-gray-400 hover:text-blue-500 rounded flex items-center justify-center"
                                        title="Перенести к другому гостю">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                </button>

                                <!-- Кнопка удаления -->
                                <button @click.stop="alert('PENDING: ' + item.status); removeItem(item)"
                                        class="w-8 h-8 text-gray-400 hover:text-red-500 rounded flex items-center justify-center"
                                        title="Удалить">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            <!-- Кнопки для позиций на кухне (cooking/ready) -->
                            <div v-if="['cooking', 'ready'].includes(item.status) && !selectMode" class="flex items-center gap-2 mt-1.5">
                                <!-- Кнопка подать для готовых блюд -->
                                <button v-if="item.status === 'ready'" @click.stop="markItemServed(item)"
                                        class="flex-1 py-2 bg-gradient-to-r from-green-500/10 to-green-400/5 border border-green-500/30 text-green-400 rounded-lg text-sm font-medium hover:from-green-500/20 hover:to-green-400/10 hover:border-green-400/50 transition-all duration-200 flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Подать гостю
                                </button>

                                <!-- Кнопка отмены для позиций на кухне -->
                                <button @click.stop="alert('KITCHEN: ' + item.status); removeItem(item)"
                                        class="w-10 h-10 bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 hover:border-red-500/50 rounded-lg flex items-center justify-center transition-all"
                                        title="Отменить позицию">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Кнопка добавить гостя -->
                <button @click="addGuest"
                        class="w-full px-3 py-2.5 text-gray-500 hover:text-gray-300 hover:bg-gray-800/30 text-sm flex items-center justify-center gap-1 transition-all">
                    <span>+ Гость</span>
                </button>
            </div>

            <!-- Кнопки действий -->
            <div class="p-2 border-t border-gray-800/50 space-y-1.5">
                <!-- Для предзаказов: красивые кнопки -->
                <template v-if="currentOrder?.type === 'preorder'">
                    <!-- Сохранить предзаказ -->
                    <button v-if="pendingItems > 0" @click="savePreorder()"
                            class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 transition-all duration-300 flex items-center justify-center gap-2 group">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span>Сохранить предзаказ</span>
                        <span class="bg-white/20 px-2 py-0.5 rounded-full text-xs">@{{ pendingItems }}</span>
                    </button>

                    <!-- Предоплата -->
                    <button @click="showPrepaymentModal = true"
                            class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 flex items-center justify-center gap-2 group">
                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>Внести предоплату</span>
                    </button>

                    <!-- Информация о предоплате -->
                    <div v-if="currentOrder?.prepayment > 0" class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-emerald-400">Предоплата внесена:</span>
                            <span class="text-emerald-300 font-bold">@{{ formatPrice(currentOrder.prepayment) }}</span>
                        </div>
                    </div>

                    <!-- Закрыть предзаказ -->
                    <button @click="closePreorderPage()"
                            class="w-full py-2.5 bg-gray-800/50 hover:bg-gray-700/50 text-gray-400 hover:text-gray-300 rounded-xl text-sm font-medium transition-all duration-200 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Вернуться к бронированиям</span>
                    </button>
                </template>

                <!-- Для обычных заказов: стандартные кнопки -->
                <template v-else>
                    <!-- Отправить на кухню -->
                    <button v-if="pendingItems > 0" @click="sendAllToKitchen()"
                            class="w-full py-2 bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 rounded-lg text-xs font-medium flex items-center justify-center gap-1">
                        <span>🔥 На кухню (@{{ pendingItems }})</span>
                    </button>

                    <!-- Подать все готовые -->
                    <button v-if="readyItems > 0" @click="serveAllReady"
                            class="w-full py-2.5 bg-gradient-to-r from-green-500/10 to-green-400/5 border border-green-500/30 text-green-400 rounded-lg text-sm font-medium hover:from-green-500/20 hover:to-green-400/10 hover:border-green-400/50 transition-all duration-200 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Подать (@{{ readyItems }})
                    </button>

                    <!-- Оплата -->
                    <div class="grid grid-cols-2 gap-1.5">
                        <button @click="showSplitPayment = true"
                                class="py-2 bg-gray-700/50 text-gray-400 rounded-lg text-xs hover:bg-gray-600">
                            Раздельно
                        </button>
                        <button @click="showPaymentModal = true"
                                class="py-2 bg-blue-500 text-white rounded-lg text-xs font-bold hover:bg-blue-600">
                            Оплата
                        </button>
                    </div>
                </template>
            </div>
            </div>

            <!-- МЕНЮ -->
            <div class="flex-1 flex flex-col bg-gray-950">

            <!-- Категории -->
            <div class="px-4 py-3 flex gap-2 overflow-x-auto border-b border-gray-800">
                <button @click="selectedCategory = null"
                    :class="selectedCategory === null ? 'bg-blue-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'"
                    class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-medium">Все</button>
                <button v-for="category in categories" :key="category.id"
                    @click="selectedCategory = category.id"
                    :class="selectedCategory === category.id ? 'bg-blue-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'"
                    class="flex-shrink-0 px-4 py-2 rounded-xl text-sm">
                    @{{ category.icon }} @{{ category.name }}
                </button>
            </div>

            <!-- Товары -->
            <div class="flex-1 overflow-y-auto p-4">
                <div class="grid grid-cols-5 gap-3">
                    <div v-for="product in filteredProducts" :key="product.id"
                        @click="product.is_available && addItem(product)"
                        class="product-card rounded-xl p-3 cursor-pointer transition-all relative"
                        :class="product.is_available ? 'bg-gray-800/50' : 'bg-gray-800/30 opacity-50 cursor-not-allowed'">
                        <div v-if="!product.is_available" class="absolute top-1 right-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded text-[10px]">СТОП</div>
                        <div class="aspect-square rounded-lg mb-2 flex items-center justify-center"
                            :class="product.is_available ? product.gradient : 'bg-gradient-to-br from-gray-500 to-gray-600'">
                            <span class="product-icon text-4xl transition-transform" :class="{ grayscale: !product.is_available }">@{{ product.icon }}</span>
                        </div>
                        <h4 class="text-sm font-medium truncate" :class="product.is_available ? 'text-white' : 'text-gray-500 line-through'">@{{ product.name }}</h4>
                        <p class="font-bold text-sm" :class="product.is_available ? 'text-blue-500' : 'text-gray-500 line-through'">@{{ formatPrice(product.price) }}</p>
                    </div>
                </div>

                <div v-if="filteredProducts.length === 0" class="text-center py-12 text-gray-500">
                    <p class="text-4xl mb-2">&#x1F50D;</p>
                    <p>Ничего не найдено</p>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА РАЗДЕЛЬНОЙ ОПЛАТЫ -->
        <div v-if="showSplitPayment" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="showSplitPayment = false">
            <div class="bg-gray-900 rounded-2xl w-full max-w-lg overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">&#x1F4B3; Раздельная оплата</h3>
                    <button @click="showSplitPayment = false" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <div v-for="guest in currentGuests" :key="guest.number" class="border-b border-gray-800" :class="{ 'opacity-60': !selectedGuestsForPayment.includes(guest.number) }">
                        <div class="px-4 py-3 flex items-center gap-3 bg-gray-800/30">
                            <input type="checkbox" :value="guest.number" v-model="selectedGuestsForPayment"
                                class="w-5 h-5 rounded bg-gray-700 border-gray-600 text-blue-500 cursor-pointer">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                :class="guestColors[guest.number % guestColors.length]">@{{ guest.number }}</div>
                            <span class="text-white font-medium flex-1">Гость @{{ guest.number }}</span>
                            <span :class="selectedGuestsForPayment.includes(guest.number) ? 'text-blue-500' : 'text-gray-400'" class="font-bold">@{{ formatPrice(guest.total) }}</span>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 border-t border-gray-800">
                    <p class="text-gray-400 text-sm mb-2">Чаевые</p>
                    <div class="flex gap-2">
                        <button v-for="tip in [0, 5, 10, 15]" :key="tip" @click="tipsPercent = tip"
                            :class="tipsPercent === tip ? 'bg-blue-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                            class="flex-1 py-2 rounded-lg text-sm">@{{ tip === 0 ? 'Без' : tip + '%' }}</button>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-800">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Выбрано:</span>
                        <span class="text-white">@{{ formatPrice(selectedGuestsTotal) }}</span>
                    </div>
                    <div class="flex justify-between text-sm mb-3">
                        <span class="text-gray-500">Чаевые @{{ tipsPercent }}%:</span>
                        <span class="text-green-500">+@{{ formatPrice(tipsAmount) }}</span>
                    </div>
                    <div class="flex justify-between mb-4">
                        <span class="text-white font-medium">К оплате:</span>
                        <span class="text-blue-500 font-bold text-xl">@{{ formatPrice(selectedGuestsTotal + tipsAmount) }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="processSplitPayment('cash')" class="py-3 bg-green-600 text-white rounded-xl font-medium hover:bg-green-700">&#x1F4B5; Нал</button>
                        <button @click="processSplitPayment('card')" class="py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700">&#x1F4B3; Карта</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА БРОНИРОВАНИЯ -->
        <div v-if="showReservation" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="showReservation = false">
            <div class="bg-gray-900 rounded-2xl w-[400px] max-h-[90vh] overflow-y-auto">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between sticky top-0 bg-gray-900 z-10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-500/20 rounded-xl flex items-center justify-center"><span class="text-xl">&#x1F4C5;</span></div>
                        <h3 class="text-white font-semibold">Бронь стола {{ $table->number }}</h3>
                    </div>
                    <button @click="showReservation = false" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>

                <div class="px-4 pt-4 flex gap-2">
                    <div class="flex-1 bg-gray-800/50 rounded-xl p-3 text-center border border-gray-700">
                        <p class="text-gray-500 text-xs">Вместимость</p>
                        <p class="text-white font-bold">{{ $table->min_seats ?? 2 }}-{{ $table->seats ?? 6 }}</p>
                    </div>
                    <div class="flex-1 bg-gray-800/50 rounded-xl p-3 text-center border border-gray-700">
                        <p class="text-gray-500 text-xs">Депозит</p>
                        <div class="flex items-center justify-center gap-1">
                            <input type="number" v-model="reservation.deposit" class="w-16 bg-transparent text-white font-bold text-center focus:outline-none">
                            <span class="text-white font-bold">&#x20BD;</span>
                        </div>
                    </div>
                </div>

                <div class="p-4 space-y-4">
                    <!-- Дата -->
                    <div>
                        <label class="text-gray-400 text-sm mb-2 block">Дата</label>
                        <div class="flex gap-2 mb-3">
                            <button v-for="(day, index) in quickDates" :key="index"
                                @click="reservation.date = day.date"
                                :class="reservation.date === day.date ? 'bg-purple-600 text-white border-purple-500' : 'bg-gray-800 text-gray-300 border-gray-700'"
                                class="flex-1 py-2 rounded-lg text-sm border transition-all">
                                <span class="block text-xs opacity-70">@{{ day.label }}</span>
                                <span class="font-semibold">@{{ day.display }}</span>
                            </button>
                        </div>
                        <input type="date" v-model="reservation.date" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm focus:border-purple-500 focus:outline-none">
                    </div>

                    <!-- Время и гости -->
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="text-gray-400 text-sm mb-2 block">Время</label>
                            <div class="grid grid-cols-4 gap-1.5">
                                <button v-for="slot in timeSlots" :key="slot.time"
                                    @click="slot.available && (reservation.time = slot.time)"
                                    :class="[
                                        reservation.time === slot.time ? 'bg-purple-600 text-white border-purple-500' : 'bg-gray-800 text-gray-300 border-gray-700',
                                        !slot.available ? 'opacity-30 pointer-events-none line-through' : ''
                                    ]"
                                    class="py-2 rounded-lg text-xs border transition-all">
                                    @{{ slot.time }}
                                </button>
                            </div>
                        </div>
                        <div class="w-24">
                            <label class="text-gray-400 text-sm mb-2 block">Гостей</label>
                            <div class="bg-gray-800 border border-gray-700 rounded-xl p-2">
                                <div class="flex items-center justify-between">
                                    <button @click="reservation.guests_count = Math.max(1, reservation.guests_count - 1)" class="w-8 h-8 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600">&#x2212;</button>
                                    <input type="number" v-model="reservation.guests_count" min="1" max="20" class="w-10 bg-transparent text-white font-bold text-center text-xl focus:outline-none">
                                    <button @click="reservation.guests_count = Math.min(20, reservation.guests_count + 1)" class="w-8 h-8 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600">+</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Контакты -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-gray-400 text-sm mb-1 block">Имя</label>
                            <input type="text" v-model="reservation.guest_name" placeholder="Имя гостя" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-gray-400 text-sm mb-1 block">Телефон</label>
                            <input type="tel" v-model="reservation.guest_phone" placeholder="+7" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                    </div>

                    <!-- Пожелания -->
                    <div>
                        <label class="text-gray-400 text-sm mb-2 block">Пожелания</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="wish in wishOptions" :key="wish.id"
                                @click="toggleWish(wish.id)"
                                :class="reservation.wishes.includes(wish.id) ? 'bg-purple-500/20 border-purple-500 text-purple-400' : 'bg-gray-800 text-gray-400 border-gray-700'"
                                class="px-3 py-1.5 rounded-full text-xs border transition-all flex items-center gap-1">
                                <span>@{{ wish.icon }}</span> @{{ wish.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Комментарий -->
                    <textarea v-model="reservation.comment" placeholder="Дополнительные пожелания..." rows="2"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:border-purple-500 focus:outline-none resize-none"></textarea>
                </div>

                <div class="mx-4 mb-4 p-3 bg-purple-500/10 border border-purple-500/30 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-purple-400">&#x1F4B0;</span>
                        <div>
                            <p class="text-white font-semibold">@{{ formatPrice(reservation.deposit) }}</p>
                            <p class="text-purple-400 text-xs">Депозит</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-xs text-right">Списывается<br>при заказе</p>
                </div>

                <div class="p-4 border-t border-gray-800 flex gap-3 sticky bottom-0 bg-gray-900">
                    <button @click="showReservation = false" class="flex-1 py-3 bg-gray-800 text-gray-300 rounded-xl hover:bg-gray-700">Отмена</button>
                    <button @click="submitReservation" class="flex-1 py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700">Забронировать</button>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА ОТМЕНЫ ПОЗИЦИИ -->
        <div v-if="showCancelModal" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4" @click.self="closeCancelModal">
            <div class="bg-gray-900 rounded-2xl w-[420px] max-h-[90vh] overflow-y-auto">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between sticky top-0 bg-gray-900 z-10">
                    <div class="flex items-center gap-3">
                        <button v-if="cancelMode && !canCancelItems" @click="cancelMode = null" class="text-gray-400 hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div class="w-10 h-10 bg-red-500/20 rounded-xl flex items-center justify-center"><span class="text-xl">&#x26D4;</span></div>
                        <h3 class="text-white font-semibold">Отмена позиции</h3>
                    </div>
                    <button @click="closeCancelModal" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>

                <!-- Информация о позиции -->
                <div class="p-4 bg-red-500/10 border-b border-red-500/30">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white font-semibold text-lg">@{{ cancelItem?.name }}</span>
                        <span class="text-blue-500 font-bold">@{{ formatPrice(cancelItem?.price * cancelItem?.quantity) }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-gray-400">Кол-во: <span class="text-white">@{{ cancelItem?.quantity }}</span></span>
                        <span class="px-2 py-0.5 rounded text-xs"
                            :class="cancelItem?.status === 'cooking' ? 'bg-yellow-500/20 text-yellow-500' : 'bg-green-500/20 text-green-500'">
                            @{{ cancelItem?.status === 'cooking' ? '&#x1F373; Готовится' : '&#x2705; Готово' }}
                        </span>
                    </div>
                    <p v-if="cancelItem?.status !== 'pending'" class="text-red-400 text-xs mt-2">
                        &#x26A0; Блюдо уже на кухне! Продукты будут списаны.
                    </p>
                </div>

                <!-- Выбор режима (для не-менеджеров) -->
                <div v-if="cancelMode === null" class="p-4 space-y-3">
                    <p class="text-gray-400 text-sm">Выберите способ отмены:</p>
                    <button @click="selectCancelMode('pin')"
                        class="w-full p-4 bg-gray-800 hover:bg-gray-700 rounded-xl text-left transition-colors border border-gray-700 hover:border-orange-500">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                                <span class="text-xl">&#x1F512;</span>
                            </div>
                            <div>
                                <div class="text-white font-medium">Ввести PIN менеджера</div>
                                <div class="text-gray-500 text-sm">Отмена будет выполнена сразу</div>
                            </div>
                        </div>
                    </button>
                    <button @click="selectCancelMode('request')"
                        class="w-full p-4 bg-gray-800 hover:bg-gray-700 rounded-xl text-left transition-colors border border-gray-700 hover:border-blue-500">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
                                <span class="text-xl">&#x1F4DD;</span>
                            </div>
                            <div>
                                <div class="text-white font-medium">Отправить заявку</div>
                                <div class="text-gray-500 text-sm">После одобрения менеджером</div>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Режим PIN -->
                <template v-if="cancelMode === 'pin'">
                    <div class="p-4">
                        <label class="text-gray-400 text-sm mb-2 block">PIN менеджера</label>
                        <input v-model="cancelManagerPin" type="password" maxlength="4" placeholder="****"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white text-center text-2xl tracking-widest focus:border-orange-500 focus:outline-none"
                            :class="cancelPinError ? 'border-red-500' : ''" />
                        <p v-if="cancelPinError" class="text-red-400 text-sm mt-1">@{{ cancelPinError }}</p>
                    </div>
                </template>

                <!-- Выбор причины (для режимов pin, direct, request) -->
                <div v-if="cancelMode" class="p-4">
                    <label class="text-gray-400 text-sm mb-3 block">Причина отмены <span class="text-red-500">*</span></label>
                    <div class="space-y-2">
                        <button v-for="reason in cancelReasons" :key="reason.value"
                            @click="cancelReason = reason.value"
                            :class="cancelReason === reason.value ? 'bg-red-500/20 border-red-500 text-red-400' : 'bg-gray-800 text-gray-300 border-gray-700 hover:border-gray-600'"
                            class="w-full p-3 rounded-xl text-left border transition-all flex items-center gap-3">
                            <span class="text-xl">@{{ reason.icon }}</span>
                            <span>@{{ reason.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Комментарий -->
                <div v-if="cancelMode" class="px-4 pb-4">
                    <label class="text-gray-400 text-sm mb-2 block">Комментарий (необязательно)</label>
                    <textarea v-model="cancelComment" placeholder="Дополнительная информация..."
                        rows="2" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:border-red-500 focus:outline-none resize-none"></textarea>
                </div>

                <!-- Кнопки -->
                <div class="p-4 border-t border-gray-800 flex gap-3 sticky bottom-0 bg-gray-900">
                    <button @click="closeCancelModal" class="flex-1 py-3 bg-gray-800 text-gray-300 rounded-xl hover:bg-gray-700">Закрыть</button>
                    <button v-if="cancelMode" @click="submitCancellation"
                        :disabled="!cancelReason || cancelLoading || (cancelMode === 'pin' && cancelManagerPin.length < 4)"
                        :class="cancelReason && !cancelLoading && (cancelMode !== 'pin' || cancelManagerPin.length >= 4) ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-700 cursor-not-allowed'"
                        class="flex-1 py-3 text-white rounded-xl font-semibold flex items-center justify-center gap-2">
                        <span v-if="cancelLoading" class="animate-spin">&#x23F3;</span>
                        <span v-else-if="cancelMode === 'request'">Отправить заявку</span>
                        <span v-else>Отменить позицию</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- TOAST -->
        <div v-if="toast.show" class="fixed top-4 right-4 px-6 py-3 rounded-xl shadow-lg z-50 transition-all"
            :class="toast.type === 'success' ? 'bg-green-500' : toast.type === 'error' ? 'bg-red-500' : 'bg-blue-500'"
            class="text-white">
            @{{ toast.message }}
        </div>

        <!-- МОДАЛКА МАССОВОГО ПЕРЕНОСА -->
        <div v-if="bulkMoveModal.show" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="bulkMoveModal.show = false">
            <div class="bg-gray-900 rounded-2xl w-full max-w-xs overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">Перенести @{{ selectedItems.length }} поз.</h3>
                    <button @click="bulkMoveModal.show = false" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>
                <div class="p-4">
                    <p class="text-gray-500 text-xs mb-3">Выберите гостя:</p>
                    <div class="flex flex-col gap-2">
                        <template v-for="g in currentGuests" :key="g.number">
                            <button v-if="g.number !== selectModeGuest"
                                    @click="bulkMoveToGuest(g.number)"
                                    class="w-full py-3 bg-gray-800 hover:bg-blue-500/20 hover:border-blue-500/50 border border-gray-700 text-gray-300 hover:text-blue-400 rounded-xl text-sm font-medium transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Гость @{{ g.number }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА ПЕРЕНОСА К ДРУГОМУ ГОСТЮ -->
        <div v-if="moveModal.show" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="moveModal.show = false">
            <div class="bg-gray-900 rounded-2xl w-full max-w-xs overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">Перенести блюдо</h3>
                    <button @click="moveModal.show = false" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>
                <div class="p-4">
                    <p class="text-gray-400 text-sm mb-3">@{{ moveModal.item?.name }}</p>
                    <p class="text-gray-500 text-xs mb-3">Выберите гостя:</p>
                    <div class="flex flex-col gap-2">
                        <template v-for="g in currentGuests" :key="g.number">
                            <button v-if="g.number !== moveModal.fromGuest"
                                    @click="moveItemToGuest(g.number)"
                                    class="w-full py-3 bg-gray-800 hover:bg-blue-500/20 hover:border-blue-500/50 border border-gray-700 text-gray-300 hover:text-blue-400 rounded-xl text-sm font-medium transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Гость @{{ g.number }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА КОММЕНТАРИЯ К БЛЮДУ -->
        <div v-if="commentModal.show" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="commentModal.show = false">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">💬 Комментарий для кухни</h3>
                    <button @click="commentModal.show = false" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>
                <div class="p-4">
                    <p class="text-gray-400 text-sm mb-2">@{{ commentModal.item?.name }}</p>
                    <textarea v-model="commentModal.text"
                              placeholder="Например: без лука, поострее, не солить..."
                              class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500 focus:outline-none resize-none"
                              rows="3"
                              ref="commentInput"></textarea>

                    <!-- Быстрые кнопки -->
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button v-for="quick in ['Без лука', 'Поострее', 'Не солить', 'Без соуса', 'На вынос']"
                                :key="quick"
                                @click="commentModal.text = commentModal.text ? commentModal.text + ', ' + quick.toLowerCase() : quick.toLowerCase()"
                                class="px-3 py-1.5 bg-gray-800 text-gray-400 rounded-lg text-sm hover:bg-gray-700 hover:text-white">
                            @{{ quick }}
                        </button>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-800 flex gap-3">
                    <button @click="commentModal.show = false"
                            class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600">
                        Отмена
                    </button>
                    <button @click="saveItemComment"
                            class="flex-1 py-3 bg-blue-500 text-white rounded-xl font-medium hover:bg-blue-600">
                        Сохранить
                    </button>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА ОПЛАТЫ -->
        <!-- МОДАЛКА ПРЕДОПЛАТЫ -->
        <div v-if="showPrepaymentModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="showPrepaymentModal = false">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between bg-gradient-to-r from-emerald-900/50 to-teal-900/50">
                    <h3 class="text-white text-lg font-semibold flex items-center gap-2">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Предоплата за бронь
                    </h3>
                    <button @click="showPrepaymentModal = false" class="text-gray-500 hover:text-white text-xl">&times;</button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Информация о заказе -->
                    <div class="bg-gray-800/50 rounded-xl p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-400">Сумма предзаказа:</span>
                            <span class="text-white font-bold">@{{ formatPrice(orderTotal) }}</span>
                        </div>
                        <div v-if="currentOrder?.prepayment > 0" class="flex justify-between items-center">
                            <span class="text-emerald-400">Уже внесено:</span>
                            <span class="text-emerald-300 font-bold">@{{ formatPrice(currentOrder.prepayment) }}</span>
                        </div>
                    </div>

                    <!-- Сумма предоплаты -->
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Сумма предоплаты</label>
                        <div class="relative">
                            <input type="number" v-model="prepaymentAmount"
                                   class="w-full bg-gray-800 text-white text-2xl font-bold rounded-xl px-4 py-4 border-2 border-gray-700 focus:border-emerald-500 focus:outline-none transition-colors"
                                   placeholder="0">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-lg">₽</span>
                        </div>
                    </div>

                    <!-- Быстрые суммы -->
                    <div class="grid grid-cols-4 gap-2">
                        <button @click="prepaymentAmount = 500" class="py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors">500</button>
                        <button @click="prepaymentAmount = 1000" class="py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors">1000</button>
                        <button @click="prepaymentAmount = 2000" class="py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors">2000</button>
                        <button @click="prepaymentAmount = orderTotal" class="py-2 bg-emerald-600/30 hover:bg-emerald-600/50 text-emerald-400 rounded-lg text-sm font-medium transition-colors">100%</button>
                    </div>

                    <!-- Способ оплаты -->
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Способ оплаты</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="prepaymentMethod = 'cash'"
                                    :class="prepaymentMethod === 'cash' ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-600'"
                                    class="py-3 rounded-xl border-2 font-medium flex items-center justify-center gap-2 transition-all">
                                <span class="text-xl">💵</span> Наличные
                            </button>
                            <button @click="prepaymentMethod = 'card'"
                                    :class="prepaymentMethod === 'card' ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-600'"
                                    class="py-3 rounded-xl border-2 font-medium flex items-center justify-center gap-2 transition-all">
                                <span class="text-xl">💳</span> Карта
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-800 flex gap-3">
                    <button @click="showPrepaymentModal = false"
                            class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600 transition-colors">
                        Отмена
                    </button>
                    <button @click="processPrepayment()"
                            class="flex-1 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-xl font-bold hover:from-emerald-500 hover:to-teal-500 transition-all shadow-lg shadow-emerald-500/25">
                        Принять предоплату
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showPaymentModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="showPaymentModal = false">
            <div class="bg-gray-900 rounded-2xl w-full max-w-md overflow-hidden">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 class="text-white text-lg font-semibold">💰 Оплата заказа</h3>
                    <button @click="showPaymentModal = false" class="text-gray-500 hover:text-white text-xl">&#x2715;</button>
                </div>
                <div class="p-4">
                    <!-- Сумма -->
                    <div class="bg-gray-800 rounded-xl p-4 mb-4 text-center">
                        <p class="text-gray-400 text-sm mb-1">Итого к оплате</p>
                        <p class="text-3xl font-bold text-blue-500">@{{ formatPrice(orderTotal) }}</p>
                    </div>

                    <!-- Способ оплаты -->
                    <p class="text-gray-400 text-sm mb-3">Способ оплаты:</p>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <button @click="selectedPaymentMethod = 'cash'"
                            :class="selectedPaymentMethod === 'cash' ? 'border-green-500 bg-green-500/20' : 'border-gray-700 bg-gray-800'"
                            class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all">
                            <span class="text-3xl">💵</span>
                            <span class="text-white font-medium">Наличные</span>
                        </button>
                        <button @click="selectedPaymentMethod = 'card'"
                            :class="selectedPaymentMethod === 'card' ? 'border-blue-500 bg-blue-500/20' : 'border-gray-700 bg-gray-800'"
                            class="p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all">
                            <span class="text-3xl">💳</span>
                            <span class="text-white font-medium">Картой</span>
                        </button>
                    </div>

                    <!-- Кнопки -->
                    <div class="flex gap-3">
                        <button @click="showPaymentModal = false" class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl font-medium hover:bg-gray-600">
                            Отмена
                        </button>
                        <button @click="confirmPayment" class="flex-1 py-3 bg-green-500 text-white rounded-xl font-bold hover:bg-green-600">
                            ✓ Принять оплату
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
    const { createApp, ref, computed, onMounted, watch } = Vue;

    createApp({
        setup() {
            // Данные
            const tableId = {{ $table->id }};
            const initialGuests = {{ $initialGuests ?? 'null' }};
            const orders = ref(@json($orders));
            const categories = ref(@json($categories));
            const currentOrderIndex = ref(0);
            const selectedGuest = ref(1);
            const maxVisibleTabs = 4;
            const showOrdersDropdown = ref(false);
            // Создаём гостей на основе initialGuests
            const createdGuests = ref(initialGuests ? Array.from({length: initialGuests}, (_, i) => i + 1) : [1]);
            const searchQuery = ref('');
            const selectedCategory = ref(null);
            const showSplitPayment = ref(false);
            const showPaymentModal = ref(false);
            const selectedPaymentMethod = ref('cash');

            // Предоплата
            const showPrepaymentModal = ref(false);
            const prepaymentAmount = ref('');
            const prepaymentMethod = ref('cash');
            const showReservation = ref(false);

            // Комментарий к блюду
            const commentModal = ref({ show: false, item: null, text: '' });

            // Перенос блюда к другому гостю
            const moveModal = ref({ show: false, item: null, fromGuest: null });

            // Мультивыбор для переноса нескольких блюд
            const selectMode = ref(false);
            const selectModeGuest = ref(null);
            const selectedItems = ref([]);
            const bulkMoveModal = ref({ show: false });
            const selectedGuestsForPayment = ref([1]);
            const tipsPercent = ref(10);
            const orderStartTime = ref(Date.now());

            // Toast
            const toast = ref({ show: false, message: '', type: 'info' });

            // Отмена позиции
            const showCancelModal = ref(false);
            const cancelItem = ref(null);
            const cancelReason = ref('');
            const cancelComment = ref('');
            const cancelLoading = ref(false);
            const cancelMode = ref(null); // null = выбор, 'pin' = ввод PIN, 'request' = заявка, 'direct' = сразу
            const cancelManagerPin = ref('');
            const cancelPinError = ref('');

            // Проверка роли пользователя (из localStorage)
            const currentUserRole = localStorage.getItem('pos_user_role') || 'waiter';
            const canCancelItems = ['super_admin', 'owner', 'admin', 'manager'].includes(currentUserRole);

            const cancelReasons = [
                { value: 'guest_refused', icon: '🙅', label: 'Гость отказался' },
                { value: 'guest_changed_mind', icon: '🤔', label: 'Гость передумал' },
                { value: 'wrong_order', icon: '❌', label: 'Ошибка официанта' },
                { value: 'out_of_stock', icon: '📦', label: 'Закончился товар' },
                { value: 'quality_issue', icon: '⚠️', label: 'Проблема с качеством' },
                { value: 'long_wait', icon: '⏰', label: 'Долгое ожидание' },
                { value: 'duplicate', icon: '📋', label: 'Дубликат заказа' },
                { value: 'other', icon: '💬', label: 'Другое' },
            ];

            const showToast = (message, type = 'info') => {
                toast.value = { show: true, message, type };
                setTimeout(() => toast.value.show = false, 3000);
            };

            // CSRF
            axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;

            // Цвета гостей
            const guestColors = [
                'bg-gradient-to-br from-blue-400 to-blue-600',
                'bg-gradient-to-br from-pink-400 to-pink-600',
                'bg-gradient-to-br from-green-400 to-green-600',
                'bg-gradient-to-br from-purple-400 to-purple-600',
                'bg-gradient-to-br from-yellow-400 to-yellow-600',
            ];

            const statusColors = {
                pending: 'bg-blue-500',
                cooking: 'bg-yellow-500',
                ready: 'bg-green-500',
                served: 'bg-purple-500',
                cancelled: 'bg-red-500',
                voided: 'bg-red-800',
                pending_cancel: 'bg-blue-600',
            };

            // Бронирование
            const reservation = ref({
                date: new Date().toISOString().split('T')[0],
                time: '19:00',
                guests_count: initialGuests || 3,
                guest_name: '',
                guest_phone: '',
                deposit: 2000,
                wishes: [],
                comment: '',
            });

            const wishOptions = [
                { id: 'birthday', icon: '🎂', label: 'День рождения' },
                { id: 'baby_chair', icon: '👶', label: 'Детский стул' },
                { id: 'flowers', icon: '🌸', label: 'Цветы' },
                { id: 'cake', icon: '🍰', label: 'Торт' },
                { id: 'balloons', icon: '🎈', label: 'Шары' },
            ];

            const timeSlots = ref([
                { time: '12:00', available: true },
                { time: '13:00', available: true },
                { time: '14:00', available: true },
                { time: '15:00', available: true },
                { time: '16:00', available: true },
                { time: '17:00', available: true },
                { time: '18:00', available: true },
                { time: '19:00', available: true },
                { time: '20:00', available: true },
                { time: '21:00', available: true },
                { time: '22:00', available: true },
                { time: '23:00', available: true },
            ]);

            // Загрузка слотов при изменении даты
            watch(() => reservation.value.date, async (newDate) => {
                try {
                    const response = await axios.get(`/pos/table/${tableId}/reservation/slots?date=${newDate}`);
                    timeSlots.value = response.data;
                } catch (e) {
                    console.error(e);
                }
            });

            // Сброс гостей при переключении заказа
            watch(() => currentOrderIndex.value, () => {
                // Собираем уникальные номера гостей из позиций заказа
                const guestNumbers = new Set([1]);
                if (currentOrder.value?.items) {
                    currentOrder.value.items.forEach(item => {
                        guestNumbers.add(item.guest_number || 1);
                    });
                }
                createdGuests.value = [...guestNumbers].sort((a, b) => a - b);
                selectedGuest.value = 1;
            });

            // Быстрые даты
            const quickDates = computed(() => {
                const dates = [];
                const today = new Date();
                const days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
                const months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

                for (let i = 0; i < 4; i++) {
                    const d = new Date(today);
                    d.setDate(d.getDate() + i);
                    dates.push({
                        date: d.toISOString().split('T')[0],
                        label: i === 0 ? 'Сегодня' : i === 1 ? 'Завтра' : days[d.getDay()],
                        display: d.getDate() + ' ' + months[d.getMonth()],
                    });
                }
                return dates;
            });

            // Вычисляемые свойства
            const currentOrder = computed(() => orders.value[currentOrderIndex.value] || null);

            // Видимые табы (первые N)
            const visibleOrders = computed(() => orders.value.slice(0, maxVisibleTabs));

            const currentGuests = computed(() => {
                if (!currentOrder.value) return [];
                const guests = {};

                // Сначала добавляем всех созданных гостей (даже пустых)
                createdGuests.value.forEach(guestNum => {
                    guests[guestNum] = { number: guestNum, items: [], total: 0, collapsed: false };
                });

                // Заполняем товарами
                (currentOrder.value.items || []).forEach(item => {
                    const g = item.guest_number || 1;
                    if (!guests[g]) {
                        guests[g] = { number: g, items: [], total: 0, collapsed: false };
                        // Добавляем в createdGuests если не было
                        if (!createdGuests.value.includes(g)) {
                            createdGuests.value.push(g);
                        }
                    }
                    guests[g].items.push(item);
                    // Не учитываем отменённые/списанные/ожидающие отмены позиции в сумме
                    if (!['cancelled', 'voided', 'pending_cancel'].includes(item.status)) {
                        guests[g].total += parseFloat(item.price) * item.quantity;
                    }
                });

                return Object.values(guests).sort((a, b) => a.number - b.number);
            });

            const orderTotal = computed(() => {
                return currentGuests.value.reduce((sum, g) => sum + g.total, 0);
            });

            const totalItems = computed(() => {
                return currentGuests.value.reduce((sum, g) =>
                    sum + g.items.filter(i => !['cancelled', 'voided', 'pending_cancel'].includes(i.status)).length, 0);
            });

            const readyItems = computed(() => {
                return currentGuests.value.reduce((sum, g) =>
                    sum + g.items.filter(i => i.status === 'ready').length, 0);
            });

            const pendingItems = computed(() => {
                return currentGuests.value.reduce((sum, g) =>
                    sum + g.items.filter(i => i.status === 'pending').length, 0);
            });

            const progressPercent = computed(() => {
                return totalItems.value ? Math.round(readyItems.value / totalItems.value * 100) : 0;
            });

            const filteredProducts = computed(() => {
                let products = [];
                categories.value.forEach(cat => {
                    if (!selectedCategory.value || cat.id === selectedCategory.value) {
                        products.push(...(cat.products || []));
                    }
                });
                if (searchQuery.value) {
                    const q = searchQuery.value.toLowerCase();
                    products = products.filter(p => p.name.toLowerCase().includes(q));
                }
                return products;
            });

            const selectedGuestsTotal = computed(() => {
                return currentGuests.value
                    .filter(g => selectedGuestsForPayment.value.includes(g.number))
                    .reduce((sum, g) => sum + g.total, 0);
            });

            const tipsAmount = computed(() => {
                return Math.round(selectedGuestsTotal.value * tipsPercent.value / 100);
            });

            const orderDuration = computed(() => {
                const diff = Math.floor((Date.now() - orderStartTime.value) / 1000 / 60);
                if (diff < 60) return diff + ' мин';
                return Math.floor(diff / 60) + 'ч ' + (diff % 60) + 'м';
            });

            // Методы
            const formatPrice = (price) => {
                return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
            };

            // Количество новых (pending) позиций у гостя
            const getGuestPendingCount = (guest) => {
                return guest.items.filter(item => item.status === 'pending').length;
            };

            // Количество готовых к подаче (ready) позиций у гостя
            const getGuestReadyCount = (guest) => {
                return guest.items.filter(item => item.status === 'ready').length;
            };

            const selectGuest = (number) => {
                selectedGuest.value = number;
            };

            const addGuest = () => {
                const maxGuest = Math.max(...createdGuests.value, 0);
                const newGuestNumber = maxGuest + 1;
                createdGuests.value.push(newGuestNumber);
                selectedGuest.value = newGuestNumber;
                showToast(`Гость ${newGuestNumber} добавлен. Добавляйте блюда!`, 'success');
            };

            const removeGuest = (guestNumber) => {
                // Нельзя удалить гостя 1 или гостя с товарами
                if (guestNumber === 1) return;
                const guest = currentGuests.value.find(g => g.number === guestNumber);
                if (guest && guest.items.length > 0) {
                    showToast('Сначала удалите товары гостя', 'error');
                    return;
                }
                // Удаляем из списка созданных гостей
                createdGuests.value = createdGuests.value.filter(n => n !== guestNumber);
                // Если удалили выбранного гостя - переключаемся на первого
                if (selectedGuest.value === guestNumber) {
                    selectedGuest.value = 1;
                }
                showToast(`Гость ${guestNumber} удалён`, 'success');
            };

            const createNewOrder = async () => {
                try {
                    const response = await axios.post(`/pos/table/${tableId}/order`);
                    if (response.data.success) {
                        orders.value.push(response.data.order);
                        currentOrderIndex.value = orders.value.length - 1;
                        // Сбрасываем гостей для нового заказа
                        createdGuests.value = [1];
                        selectedGuest.value = 1;
                        showToast('Новый заказ создан', 'success');
                    }
                } catch (error) {
                    showToast('Ошибка создания заказа', 'error');
                }
            };

            const closeEmptyOrder = async (order, index) => {
                if (order.items.length > 0) {
                    showToast('Нельзя закрыть заказ с позициями', 'error');
                    return;
                }
                if (orders.value.length <= 1) {
                    showToast('Нельзя закрыть единственный заказ', 'error');
                    return;
                }
                try {
                    const response = await axios.delete(`/pos/table/${tableId}/order/${order.id}`);
                    if (response.data.success) {
                        orders.value.splice(index, 1);
                        // Переключаемся на предыдущий заказ если закрыли текущий
                        if (currentOrderIndex.value >= orders.value.length) {
                            currentOrderIndex.value = orders.value.length - 1;
                        }
                        showToast('Пустой заказ закрыт', 'success');
                    }
                } catch (error) {
                    showToast(error.response?.data?.message || 'Ошибка', 'error');
                }
            };

            const addItem = async (product) => {
                if (!product.is_available || !currentOrder.value) return;

                try {
                    const response = await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/item`, {
                        product_id: product.id,
                        guest_id: selectedGuest.value,
                        quantity: 1,
                    });
                    if (response.data.success) {
                        currentOrder.value.items.push(response.data.item);
                        showToast(`${product.name} → Гость ${selectedGuest.value}`, 'success');
                    }
                } catch (error) {
                    showToast(error.response?.data?.message || 'Ошибка', 'error');
                }
            };

            const removeItem = async (item) => {
                if (!currentOrder.value) return;

                console.log('removeItem called, item.status:', item.status, 'item:', item);

                // Если позиция не отправлена на кухню - удаляем сразу
                if (['pending', 'saved'].includes(item.status)) {
                    try {
                        const response = await axios.delete(`/pos/table/${tableId}/order/${currentOrder.value.id}/item/${item.id}`);
                        if (response.data.success) {
                            const index = currentOrder.value.items.findIndex(i => i.id === item.id);
                            if (index > -1) currentOrder.value.items.splice(index, 1);
                            showToast('Позиция удалена', 'success');
                        }
                    } catch (error) {
                        showToast('Ошибка удаления', 'error');
                    }
                } else {
                    // Позиция на кухне - показываем модалку отмены (PIN или заявка)
                    openCancelModal(item);
                }
            };

            const updateItemQuantity = async (item, delta) => {
                if (!currentOrder.value) return;
                if (!['pending', 'saved'].includes(item.status)) {
                    showToast('Нельзя изменить - уже на кухне', 'error');
                    return;
                }

                const newQuantity = item.quantity + delta;

                // Если количество становится 0 или меньше - удаляем позицию
                if (newQuantity <= 0) {
                    await removeItem(item);
                    return;
                }

                try {
                    const response = await axios.patch(`/pos/table/${tableId}/order/${currentOrder.value.id}/item/${item.id}`, {
                        quantity: newQuantity
                    });
                    if (response.data.success) {
                        item.quantity = newQuantity;
                        item.total = item.price * newQuantity;
                    }
                } catch (error) {
                    showToast('Ошибка изменения', 'error');
                }
            };

            // Установить количество напрямую
            const setItemQuantity = async (item, quantity) => {
                if (!currentOrder.value) return;
                if (!['pending', 'saved'].includes(item.status)) {
                    showToast('Нельзя изменить - уже на кухне', 'error');
                    return;
                }
                if (quantity <= 0) {
                    await removeItem(item);
                    return;
                }
                try {
                    const response = await axios.patch(`/pos/table/${tableId}/order/${currentOrder.value.id}/item/${item.id}`, {
                        quantity: quantity
                    });
                    if (response.data.success) {
                        item.quantity = quantity;
                        item.total = item.price * quantity;
                    }
                } catch (error) {
                    showToast('Ошибка изменения', 'error');
                }
            };

            // Редактирование количества (модальное окно)
            const editItemQuantity = (item) => {
                const newQty = prompt('Введите количество:', item.quantity);
                if (newQty !== null && !isNaN(newQty) && parseInt(newQty) > 0) {
                    setItemQuantity(item, parseInt(newQty));
                }
            };

            // Открыть модалку комментария
            const openCommentModal = (item) => {
                commentModal.value = {
                    show: true,
                    item: item,
                    text: item.comment || ''
                };
            };

            // Сохранить комментарий к блюду
            const saveItemComment = async () => {
                if (!currentOrder.value || !commentModal.value.item) return;

                try {
                    const response = await axios.patch(`/pos/table/${tableId}/order/${currentOrder.value.id}/item/${commentModal.value.item.id}`, {
                        comment: commentModal.value.text
                    });
                    if (response.data.success) {
                        commentModal.value.item.comment = commentModal.value.text;
                        commentModal.value.show = false;
                        showToast('Комментарий сохранён', 'success');
                    }
                } catch (error) {
                    showToast('Ошибка сохранения', 'error');
                }
            };

            // Открыть модалку переноса блюда
            const openMoveModal = (item, fromGuest) => {
                moveModal.value = {
                    show: true,
                    item: item,
                    fromGuest: fromGuest.number
                };
            };

            // Перенести блюдо к другому гостю
            const moveItemToGuest = async (toGuestNumber) => {
                if (!currentOrder.value || !moveModal.value.item) return;

                try {
                    const response = await axios.patch(`/pos/table/${tableId}/order/${currentOrder.value.id}/item/${moveModal.value.item.id}`, {
                        guest_number: toGuestNumber
                    });
                    if (response.data.success) {
                        // Найти и обновить элемент в массиве items заказа
                        const itemInOrder = currentOrder.value.items.find(i => i.id === moveModal.value.item.id);
                        if (itemInOrder) {
                            itemInOrder.guest_number = toGuestNumber;
                        }
                        moveModal.value.show = false;
                        showToast(`Блюдо перенесено к Гостю ${toGuestNumber}`, 'success');
                    }
                } catch (error) {
                    showToast('Ошибка переноса', 'error');
                }
            };

            // Функции мультивыбора
            const startSelectMode = (guestNumber) => {
                selectMode.value = true;
                selectModeGuest.value = guestNumber;
                selectedItems.value = [];
            };

            const cancelSelectMode = () => {
                selectMode.value = false;
                selectModeGuest.value = null;
                selectedItems.value = [];
            };

            const toggleItemSelection = (itemId) => {
                const index = selectedItems.value.indexOf(itemId);
                if (index === -1) {
                    selectedItems.value.push(itemId);
                } else {
                    selectedItems.value.splice(index, 1);
                }
            };

            const selectAllGuestItems = (guest) => {
                const pendingIds = guest.items
                    .filter(item => item.status === 'pending')
                    .map(item => item.id);
                selectedItems.value = [...pendingIds];
            };

            const deselectAllItems = () => {
                selectedItems.value = [];
            };

            const openBulkMoveModal = () => {
                bulkMoveModal.value.show = true;
            };

            const bulkMoveToGuest = async (toGuestNumber) => {
                if (!currentOrder.value || selectedItems.value.length === 0) return;

                try {
                    for (const itemId of selectedItems.value) {
                        await axios.patch(`/pos/table/${tableId}/order/${currentOrder.value.id}/item/${itemId}`, {
                            guest_number: toGuestNumber
                        });
                        const itemInOrder = currentOrder.value.items.find(i => i.id === itemId);
                        if (itemInOrder) {
                            itemInOrder.guest_number = toGuestNumber;
                        }
                    }
                    bulkMoveModal.value.show = false;
                    showToast(`Перенесено ${selectedItems.value.length} поз. к Гостю ${toGuestNumber}`, 'success');
                    cancelSelectMode();
                } catch (error) {
                    console.error('Bulk move error:', error);
                    showToast('Ошибка переноса', 'error');
                }
            };

            const markItemServed = async (item) => {
                if (!currentOrder.value) return;
                try {
                    const apiUrl = `${window.location.origin}/api/orders/${currentOrder.value.id}/items/${item.id}/status`;
                    await axios.patch(apiUrl, { status: 'served' });
                    item.status = 'served';
                    showToast('Отмечено как поданное', 'success');
                } catch (error) {
                    console.error('markItemServed error:', error);
                    showToast('Ошибка', 'error');
                }
            };

            // Подать все готовые блюда
            const serveAllReady = async () => {
                if (!currentOrder.value) return;
                const readyItemsList = currentOrder.value.items.filter(i => i.status === 'ready');
                if (readyItemsList.length === 0) return;

                let served = 0;
                for (const item of readyItemsList) {
                    try {
                        const apiUrl = `${window.location.origin}/api/orders/${currentOrder.value.id}/items/${item.id}/status`;
                        await axios.patch(apiUrl, { status: 'served' });
                        item.status = 'served';
                        served++;
                    } catch (error) {
                        console.error('serveAllReady error:', error);
                    }
                }
                if (served > 0) {
                    showToast(`Подано ${served} блюд`, 'success');
                }
            };

            const sendItemToKitchen = async (item) => {
                if (!currentOrder.value) return;

                const payload = { item_ids: [item.id] };
                console.log('Sending to kitchen:', payload);

                try {
                    const response = await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/send-kitchen`, payload);
                    console.log('Response:', response.data);
                    item.status = 'cooking';
                    showToast('Отправлено на кухню', 'success');
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Ошибка', 'error');
                }
            };

            const sendAllToKitchen = async () => {
                if (!currentOrder.value) return;
                const count = pendingItems.value;
                if (count === 0) {
                    showToast('Нет позиций для отправки', 'error');
                    return;
                }

                try {
                    await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/send-kitchen`);
                    currentGuests.value.forEach(g => {
                        g.items.filter(i => i.status === 'pending').forEach(i => i.status = 'cooking');
                    });
                    showToast(`${count} поз. отправлено на кухню`, 'success');
                } catch (error) {
                    showToast('Ошибка', 'error');
                }
            };

            // Сохранить предзаказ (без отправки на кухню)
            const savePreorder = async () => {
                if (!currentOrder.value) return;
                const count = pendingItems.value;
                if (count === 0) {
                    showToast('Нет позиций для сохранения', 'error');
                    return;
                }

                try {
                    // Просто сохраняем позиции как 'saved' без отправки на печать
                    await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/save-preorder`);
                    currentGuests.value.forEach(g => {
                        g.items.filter(i => i.status === 'pending').forEach(i => i.status = 'saved');
                    });
                    showToast(`Предзаказ сохранён (${count} поз.)`, 'success');
                    setTimeout(() => {
                        window.location.href = '/pos-vue#hall';
                    }, 1000);
                } catch (error) {
                    showToast('Ошибка сохранения', 'error');
                }
            };

            const closePreorderPage = () => {
                // Возвращаемся на страницу бронирований
                window.location.href = "/pos?tab=reservations";
            };

            const processPrepayment = async () => {
                const amount = parseFloat(prepaymentAmount.value);
                if (!amount || amount <= 0) {
                    showToast("Введите сумму предоплаты", "error");
                    return;
                }

                try {
                    const response = await axios.post(`/api/reservations/${currentOrder.value.reservation_id}/prepayment`, {
                        amount: amount,
                        method: prepaymentMethod.value,
                        order_id: currentOrder.value.id
                    });

                    if (response.data.success) {
                        currentOrder.value.prepayment = (currentOrder.value.prepayment || 0) + amount;
                        showPrepaymentModal.value = false;
                        prepaymentAmount.value = "";
                        showToast("Предоплата принята: " + formatPrice(amount), "success");
                    }
                } catch (error) {
                    console.error("Prepayment error:", error);
                    showToast("Ошибка при внесении предоплаты", "error");
                }
            };

            const processPayment = async (method) => {
                if (!currentOrder.value) return;

                try {
                    await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/payment`, {
                        payment_method: method
                    });
                    showToast('Оплата принята!', 'success');
                    setTimeout(() => {
                        window.location.href = '/pos-vue#hall';
                    }, 1500);
                } catch (error) {
                    showToast('Ошибка оплаты', 'error');
                }
            };

            // Хелпер для продления сессии
            const extendPosSession = () => {
                const SESSION_KEY = 'menulab_session';
                const ACTIVITY_EXTEND = 30 * 60 * 1000;
                try {
                    const session = JSON.parse(localStorage.getItem(SESSION_KEY));
                    if (session) {
                        session.lastActivity = Date.now();
                        session.expiresAt = Date.now() + ACTIVITY_EXTEND;
                        localStorage.setItem(SESSION_KEY, JSON.stringify(session));
                    }
                } catch {}
            };

            // Оплата через модалку
            const confirmPayment = async () => {
                if (!currentOrder.value) return;

                try {
                    const response = await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/payment`, {
                        payment_method: selectedPaymentMethod.value
                    });
                    showPaymentModal.value = false;
                    showToast('Оплата принята!', 'success');
                    extendPosSession();
                    setTimeout(() => {
                        window.location.href = '/pos-vue#hall';
                    }, 1500);
                } catch (error) {
                    const msg = error.response?.data?.message || 'Ошибка оплаты';
                    showToast(msg, 'error');
                    // Если касса закрыта - закрываем модалку
                    if (error.response?.data?.error_code === 'SHIFT_CLOSED') {
                        showPaymentModal.value = false;
                    }
                }
            };

            const processSplitPayment = async (method) => {
                if (!currentOrder.value) return;

                try {
                    const response = await axios.post(`/pos/table/${tableId}/order/${currentOrder.value.id}/payment`, {
                        payment_method: 'split',
                        guest_ids: selectedGuestsForPayment.value,
                        tips_percent: tipsPercent.value
                    });
                    if (response.data.success) {
                        showSplitPayment.value = false;
                        if (!response.data.remaining) {
                            showToast('Заказ полностью оплачен!', 'success');
                            extendPosSession();
                            setTimeout(() => {
                                window.location.href = '/pos-vue#hall';
                            }, 1500);
                        } else {
                            showToast('Оплата принята', 'success');
                            // Помечаем оплаченных гостей
                            selectedGuestsForPayment.value.forEach(guestNum => {
                                const guest = currentGuests.value.find(g => g.number === guestNum);
                                if (guest) {
                                    guest.items.forEach(item => item.is_paid = true);
                                }
                            });
                            selectedGuestsForPayment.value = [];
                        }
                    }
                } catch (error) {
                    const msg = error.response?.data?.message || 'Ошибка оплаты';
                    showToast(msg, 'error');
                }
            };

            const toggleWish = (wishId) => {
                const index = reservation.value.wishes.indexOf(wishId);
                if (index > -1) {
                    reservation.value.wishes.splice(index, 1);
                } else {
                    reservation.value.wishes.push(wishId);
                }
            };

            const submitReservation = async () => {
                if (!reservation.value.guest_name || !reservation.value.guest_phone) {
                    showToast('Заполните имя и телефон', 'error');
                    return;
                }

                try {
                    const response = await axios.post(`/pos/table/${tableId}/reservation`, reservation.value);
                    if (response.data.success) {
                        showReservation.value = false;
                        showToast('Бронь создана!', 'success');
                        // Сброс формы
                        reservation.value = {
                            date: new Date().toISOString().split('T')[0],
                            time: '19:00',
                            guests_count: 3,
                            guest_name: '',
                            guest_phone: '',
                            deposit: 2000,
                            wishes: [],
                            comment: '',
                        };
                    } else {
                        showToast(response.data.message || 'Ошибка', 'error');
                    }
                } catch (error) {
                    showToast(error.response?.data?.message || 'Ошибка создания брони', 'error');
                }
            };

            // Открытие модалки отмены
            const openCancelModal = (item) => {
                cancelItem.value = {
                    ...item,
                    total: item.price * item.quantity,
                    name: item.name || item.dish?.name,
                };
                cancelReason.value = '';
                cancelComment.value = '';
                cancelManagerPin.value = '';
                cancelPinError.value = '';
                // Если менеджер - сразу показываем форму отмены
                cancelMode.value = canCancelItems ? 'direct' : null;
                showCancelModal.value = true;
            };

            const selectCancelMode = (mode) => {
                cancelMode.value = mode;
                cancelPinError.value = '';
            };

            const closeCancelModal = () => {
                showCancelModal.value = false;
                cancelMode.value = null;
                cancelManagerPin.value = '';
                cancelPinError.value = '';
            };

            // Отправка отмены
            const submitCancellation = async () => {
                if (!cancelItem.value || !cancelReason.value) return;

                // Режим заявки - отправляем на одобрение
                if (cancelMode.value === 'request') {
                    cancelLoading.value = true;
                    try {
                        const response = await axios.post(`/api/orders/${currentOrder.value.id}/request-cancellation`, {
                            reason: `Удаление позиции "${cancelItem.value.name}": ${cancelReasons.find(r => r.value === cancelReason.value)?.label || cancelReason.value}${cancelComment.value ? ' - ' + cancelComment.value : ''}`,
                            requested_by: null
                        });

                        if (response.data.success) {
                            closeCancelModal();
                            showToast('Заявка на отмену отправлена', 'info');
                        }
                    } catch (error) {
                        showToast(error.response?.data?.message || 'Ошибка отправки заявки', 'error');
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
                        const authResult = await axios.post('/api/auth/login-pin', { pin: cancelManagerPin.value });
                        const managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
                        const userRole = authResult.data?.data?.user?.role;
                        if (!authResult.data.success || !managerRoles.includes(userRole)) {
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

                // Режим direct или после проверки PIN - отменяем позицию
                cancelLoading.value = true;
                try {
                    const apiUrl = `${window.location.origin}/api/order-items/${cancelItem.value.id}/cancel`;

                    const response = await axios.post(apiUrl, {
                        reason_type: cancelReason.value,
                        reason_comment: cancelComment.value || null,
                    });

                    if (response.data.success) {
                        // Обновляем статус позиции локально
                        const item = currentOrder.value.items.find(i => i.id === cancelItem.value.id);
                        if (item) {
                            item.status = response.data.new_status || 'cancelled';
                            item.cancelled_at = new Date().toISOString();
                        }

                        closeCancelModal();

                        if (response.data.requires_approval) {
                            showToast('Отправлено на подтверждение менеджеру', 'info');
                        } else {
                            showToast('Позиция отменена', 'success');
                        }
                    }
                } catch (error) {
                    console.error('Cancel error:', error.response || error);
                    showToast(error.response?.data?.message || 'Ошибка отмены', 'error');
                } finally {
                    cancelLoading.value = false;
                }
            };

            // Обновление меню в реальном времени
            const refreshMenu = async () => {
                try {
                    const response = await axios.get(`/pos/table/${tableId}/menu`);
                    categories.value = response.data;
                } catch (error) {
                    console.error('Ошибка обновления меню:', error);
                }
            };

            // Обновление данных заказа с сервера
            const refreshOrder = async () => {
                if (!currentOrder.value) return;
                try {
                    const apiUrl = `${window.location.origin}/api/orders/${currentOrder.value.id}`;
                    const response = await axios.get(apiUrl);
                    if (response.data.success) {
                        const freshOrder = response.data.data;
                        // Обновляем статусы позиций
                        (freshOrder.items || []).forEach(freshItem => {
                            const localItem = currentOrder.value.items.find(i => i.id === freshItem.id);
                            if (localItem && localItem.status !== freshItem.status) {
                                // Не перезаписываем pending_cancel - это локальный статус ожидания подтверждения
                                if (localItem.status === 'pending_cancel') {
                                    // Проверяем если с сервера пришёл cancelled/voided - значит менеджер подтвердил
                                    if (['cancelled', 'voided'].includes(freshItem.status)) {
                                        localItem.status = freshItem.status;
                                        showToast(`Отмена подтверждена: ${freshItem.name}`, 'success');
                                    }
                                    return;
                                }
                                console.log(`Item ${freshItem.name}: ${localItem.status} -> ${freshItem.status}`);
                                const oldStatus = localItem.status;
                                localItem.status = freshItem.status;
                                // Уведомляем если позиция готова
                                if (freshItem.status === 'ready' && oldStatus !== 'ready') {
                                    showToast(`🍽️ ${freshItem.name} готово!`, 'success');
                                }
                            }
                        });
                        // Обновляем статус заказа
                        if (currentOrder.value.status !== freshOrder.status) {
                            currentOrder.value.status = freshOrder.status;
                        }
                    }
                } catch (error) {
                    console.error('Ошибка обновления заказа:', error);
                }
            };

            // Очистка пустых заказов при закрытии страницы
            const cleanupEmptyOrders = () => {
                const url = `/pos/table/${tableId}/cleanup`;
                const data = new FormData();
                data.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                navigator.sendBeacon(url, data);
            };

            // Автообновление меню и заказа
            onMounted(() => {
                setInterval(refreshMenu, 30000);
                setInterval(refreshOrder, 5000); // Проверяем статус каждые 5 сек

                // Очистка пустых заказов при уходе со страницы
                // Автоочистка отключена - мешает работе
                // window.addEventListener('pagehide', cleanupEmptyOrders);
                // beforeunload убран - мешал при F5
            });

            return {
                orders, categories, currentOrderIndex, selectedGuest, searchQuery, selectedCategory,
                maxVisibleTabs, showOrdersDropdown, visibleOrders,
                showSplitPayment, showReservation, selectedGuestsForPayment, tipsPercent,
                showPaymentModal, selectedPaymentMethod,
                showPrepaymentModal, prepaymentAmount, prepaymentMethod, processPrepayment, closePreorderPage,
                commentModal, openCommentModal, saveItemComment,
                moveModal, openMoveModal, moveItemToGuest,
                selectMode, selectModeGuest, selectedItems, bulkMoveModal,
                startSelectMode, cancelSelectMode, toggleItemSelection, selectAllGuestItems, deselectAllItems, openBulkMoveModal, bulkMoveToGuest,
                guestColors, statusColors, reservation, wishOptions, timeSlots, quickDates,
                currentOrder, currentGuests, orderTotal, totalItems, readyItems, pendingItems, progressPercent,
                filteredProducts, selectedGuestsTotal, tipsAmount, orderDuration,
                formatPrice, getGuestPendingCount, getGuestReadyCount, selectGuest, addGuest, removeGuest, createNewOrder, closeEmptyOrder, addItem, removeItem,
                sendItemToKitchen, sendAllToKitchen, savePreorder, updateItemQuantity, markItemServed, serveAllReady, processPayment, processSplitPayment,
                confirmPayment,
                toggleWish, submitReservation, toast, showToast, refreshMenu,
                // Отмена позиции
                showCancelModal, cancelItem, cancelReason, cancelComment, cancelLoading, cancelReasons, cancelMode, cancelManagerPin, cancelPinError, canCancelItems,
                selectCancelMode, closeCancelModal,
                openCancelModal, submitCancellation,
            };
        },
    }).mount('#app');
    </script>
</body>
</html>
