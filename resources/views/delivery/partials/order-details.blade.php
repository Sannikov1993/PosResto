{{-- Детали заказа --}}

<div class="p-4 border-b border-gray-800 flex items-center justify-between">
    <div>
        <h2 class="text-white text-lg font-semibold">Детали заказа</h2>
        <p class="text-gray-500 text-sm">{{ $order->order_number }}</p>
    </div>
    <div class="flex items-center gap-2">
        <button class="px-3 py-1.5 bg-gray-800 text-gray-300 rounded-lg text-sm hover:bg-gray-700 btn">🖨️ Печать</button>
        <button onclick="updateOrderStatus({{ $order->id }}, 'cancelled')" class="px-3 py-1.5 bg-red-500/20 text-red-500 rounded-lg text-sm hover:bg-red-500/30 btn">✕ Отменить</button>
    </div>
</div>

<div class="flex-1 overflow-y-auto p-4">
    <div class="grid grid-cols-2 gap-6">
        {{-- Левая колонка --}}
        <div class="space-y-4">
            {{-- Статус --}}
            <div class="bg-gray-800/50 rounded-xl p-4">
                <h3 class="text-gray-400 text-sm mb-3">Статус заказа</h3>
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex-1 h-2 bg-gray-700 rounded-full overflow-hidden">
                        @php
                            $progress = match($order->status) {
                                'new' => 20,
                                'cooking' => 40,
                                'ready' => 60,
                                'delivering' => 80,
                                'completed' => 100,
                                default => 0,
                            };
                        @endphp
                        <div class="h-full bg-{{ $order->status_color }}-500 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
                <select onchange="updateOrderStatus({{ $order->id }}, this.value)"
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:border-accent focus:outline-none">
                    <option value="new" {{ $order->status === 'new' ? 'selected' : '' }}>🔵 Новый</option>
                    <option value="cooking" {{ $order->status === 'cooking' ? 'selected' : '' }}>🟡 Готовится</option>
                    <option value="ready" {{ $order->status === 'ready' ? 'selected' : '' }}>🟢 Готов к выдаче</option>
                    <option value="delivering" {{ $order->status === 'delivering' ? 'selected' : '' }}>🟣 В пути</option>
                    <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>⚪ Доставлен</option>
                    <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>🔴 Отменён</option>
                </select>
            </div>

            {{-- Клиент --}}
            <div class="bg-gray-800/50 rounded-xl p-4">
                <h3 class="text-gray-400 text-sm mb-3">👤 Клиент</h3>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 bg-accent rounded-full flex items-center justify-center text-white font-bold">
                        {{ mb_strtoupper(mb_substr($order->customer_name, 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-white font-medium">{{ $order->customer_name }}</p>
                        @if($order->customer)
                            <p class="text-gray-500 text-sm">{{ $order->customer->orders_count ?? 0 }} заказов</p>
                        @endif
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-gray-500">📱</span>
                        <a href="tel:{{ $order->customer_phone }}" class="text-blue-400 hover:underline">{{ $order->customer_phone }}</a>
                    </div>
                    @if($order->customer_comment)
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500">💬</span>
                            <span class="text-gray-300">{{ $order->customer_comment }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Адрес --}}
            @if($order->type === 'delivery')
                <div class="bg-gray-800/50 rounded-xl p-4">
                    <h3 class="text-gray-400 text-sm mb-3">📍 Адрес доставки</h3>
                    <p class="text-white mb-2">{{ $order->address_street }}, {{ $order->address_house }}</p>
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <div class="bg-gray-700 rounded-lg p-2 text-center">
                            <p class="text-gray-500 text-xs">Кв.</p>
                            <p class="text-white">{{ $order->address_apartment ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-2 text-center">
                            <p class="text-gray-500 text-xs">Подъезд</p>
                            <p class="text-white">{{ $order->address_entrance ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-2 text-center">
                            <p class="text-gray-500 text-xs">Этаж</p>
                            <p class="text-white">{{ $order->address_floor ?: '—' }}</p>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-2 text-center">
                            <p class="text-gray-500 text-xs">Домофон</p>
                            <p class="text-white">{{ $order->address_intercom ?: '—' }}</p>
                        </div>
                    </div>
                    @if($order->address_comment)
                        <div class="mt-3 p-2 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                            <p class="text-yellow-500 text-sm">⚠️ {{ $order->address_comment }}</p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Курьер --}}
            @if($order->type === 'delivery')
                <div class="bg-gray-800/50 rounded-xl p-4">
                    <h3 class="text-gray-400 text-sm mb-3">🛵 Курьер</h3>
                    @if($order->courier)
                        <div class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg">
                            <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                {{ mb_strtoupper(mb_substr($order->courier->name, 0, 2)) }}
                            </div>
                            <div class="flex-1">
                                <p class="text-white">{{ $order->courier->name }}</p>
                                <p class="text-gray-500 text-sm">📱 {{ $order->courier->phone }}</p>
                            </div>
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        </div>
                    @else
                        <div class="space-y-2">
                            <p class="text-gray-500 text-xs mb-2">Доступные курьеры:</p>
                            @foreach($couriers->where('status', '!=', 'offline') as $courier)
                                <div onclick="assignCourier({{ $order->id }}, {{ $courier->id }})"
                                     class="flex items-center gap-3 p-2 bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-600 btn">
                                    <div class="w-8 h-8 bg-{{ $courier->status === 'available' ? 'green' : 'yellow' }}-500 rounded-full flex items-center justify-center text-white text-sm">
                                        {{ mb_strtoupper(mb_substr($courier->name, 0, 2)) }}
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-white text-sm">{{ $courier->name }}</p>
                                        <p class="text-gray-500 text-xs">
                                            {{ $courier->active_orders_count ?? 0 }} заказов
                                            @if($courier->estimated_free_time > 0)
                                                • ~{{ $courier->estimated_free_time }} мин
                                            @else
                                                • Свободен
                                            @endif
                                        </p>
                                    </div>
                                    <span class="w-2 h-2 bg-{{ $courier->status === 'available' ? 'green' : 'yellow' }}-500 rounded-full {{ $courier->status === 'available' ? 'animate-pulse' : '' }}"></span>
                                </div>
                            @endforeach
                            @if($couriers->where('status', '!=', 'offline')->isEmpty())
                                <p class="text-gray-500 text-sm text-center py-4">Нет доступных курьеров</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Правая колонка --}}
        <div class="space-y-4">
            {{-- Время --}}
            <div class="bg-gray-800/50 rounded-xl p-4">
                <h3 class="text-gray-400 text-sm mb-3">🕐 Время</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-500 text-xs">Создан</p>
                        <p class="text-white">{{ $order->created_at->format('H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs">Доставить к</p>
                        <p class="text-accent font-bold">{{ $order->deliver_at?->format('H:i') ?? 'ASAP' }}</p>
                    </div>
                    @if($order->time_remaining !== null)
                        <div>
                            <p class="text-gray-500 text-xs">Осталось</p>
                            <p class="{{ $order->time_remaining < 15 ? 'text-red-500' : 'text-white' }} font-bold">{{ $order->time_remaining }} мин</p>
                        </div>
                    @endif
                    @if($order->zone)
                        <div>
                            <p class="text-gray-500 text-xs">Зона доставки</p>
                            <p class="text-white">{{ $order->zone->name }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Состав заказа --}}
            <div class="bg-gray-800/50 rounded-xl p-4">
                <h3 class="text-gray-400 text-sm mb-3">🍕 Состав заказа</h3>
                <div class="space-y-3">
                    @foreach($order->items as $item)
                        <div class="flex items-start gap-3">
                            <span class="text-xl">{{ $item->dish?->category?->icon ?? '🍽' }}</span>
                            <div class="flex-1">
                                <p class="text-white">{{ $item->product_name }}</p>
                                @if(!empty($item->modifiers))
                                    @foreach($item->modifiers as $mod)
                                        <p class="text-accent text-xs">+ {{ $mod['name'] ?? '' }}</p>
                                    @endforeach
                                @endif
                                @if($item->comment)
                                    <p class="text-gray-500 text-xs">💬 {{ $item->comment }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-white">{{ number_format($item->total, 0, '', ' ') }} ₽</p>
                                <p class="text-gray-500 text-xs">×{{ $item->quantity }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Оплата --}}
            <div class="bg-gray-800/50 rounded-xl p-4">
                <h3 class="text-gray-400 text-sm mb-3">💳 Оплата</h3>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Товары:</span>
                        <span class="text-white">{{ number_format($order->subtotal, 0, '', ' ') }} ₽</span>
                    </div>
                    @if($order->delivery_cost > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Доставка:</span>
                            <span class="text-white">{{ number_format($order->delivery_cost, 0, '', ' ') }} ₽</span>
                        </div>
                    @endif
                    @if($order->discount > 0)
                        <div class="flex justify-between text-sm text-green-500">
                            <span>Скидка:</span>
                            <span>-{{ number_format($order->discount, 0, '', ' ') }} ₽</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-700">
                        <span class="text-white">Итого:</span>
                        <span class="text-accent">{{ number_format($order->total, 0, '', ' ') }} ₽</span>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    @php
                        $paymentIcons = ['cash' => '💵', 'card' => '💳', 'online' => '📱'];
                        $paymentLabels = ['cash' => 'Наличные', 'card' => 'Картой', 'online' => 'Онлайн'];
                    @endphp
                    <span class="px-3 py-1 bg-green-500/20 text-green-500 rounded-lg text-sm">
                        {{ $paymentIcons[$order->payment_method] ?? '' }} {{ $paymentLabels[$order->payment_method] ?? $order->payment_method }}
                    </span>
                    @if($order->change_from)
                        <span class="text-gray-500 text-sm">Сдача с {{ number_format($order->change_from, 0, '', ' ') }} ₽</span>
                    @endif
                    @if($order->is_paid)
                        <span class="px-2 py-1 bg-green-500 text-white rounded text-xs">Оплачено</span>
                    @endif
                </div>
            </div>

            {{-- История --}}
            <div class="bg-gray-800/50 rounded-xl p-4">
                <h3 class="text-gray-400 text-sm mb-3">📜 История</h3>
                <div class="space-y-2 text-sm max-h-40 overflow-y-auto">
                    @foreach($order->history as $event)
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-gray-500 rounded-full flex-shrink-0"></span>
                            <span class="text-gray-500">{{ $event->created_at->format('H:i') }}</span>
                            <span class="text-gray-300">
                                {{ $event->description }}
                                @if($event->user)
                                    <span class="text-gray-500">({{ $event->user->name }})</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Кнопки действий --}}
<div class="p-4 border-t border-gray-800 flex items-center gap-3">
    @if($order->status === 'new')
        <button onclick="updateOrderStatus({{ $order->id }}, 'cooking')" class="flex-1 py-3 bg-yellow-500 text-black rounded-xl font-semibold hover:bg-yellow-400 btn">
            👨‍🍳 Отправить на кухню
        </button>
    @endif

    @if($order->status === 'cooking')
        <button onclick="updateOrderStatus({{ $order->id }}, 'ready')" class="flex-1 py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 btn">
            ✓ Готов к выдаче
        </button>
    @endif

    @if($order->status === 'ready' && !$order->courier && $order->type === 'delivery')
        <div class="flex-1 py-3 bg-purple-500/20 text-purple-400 rounded-xl font-semibold text-center">
            🛵 Назначьте курьера
        </div>
    @endif

    @if($order->status === 'delivering')
        <button onclick="updateOrderStatus({{ $order->id }}, 'completed')" class="flex-1 py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 btn">
            ✓ Отметить доставленным
        </button>
    @endif

    @if($order->type === 'pickup' && $order->status === 'ready')
        <button onclick="updateOrderStatus({{ $order->id }}, 'completed')" class="flex-1 py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 btn">
            ✓ Выдан клиенту
        </button>
    @endif
</div>
