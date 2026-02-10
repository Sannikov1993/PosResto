<template>
    <div>
        <!-- Settings Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6">
            <div class="flex border-b overflow-x-auto">
                <button @click="subTab = 'general'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'general' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Основные
                </button>
                <button @click="subTab = 'integrations'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'integrations' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Интеграции
                </button>
                <button @click="subTab = 'printers'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'printers' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Принтеры
                </button>
                <button @click="subTab = 'notifications'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'notifications' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Уведомления
                </button>
                <button @click="subTab = 'receipts'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'receipts' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    🧾 Чеки
                </button>
                <button @click="subTab = 'stations'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'stations' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Цеха кухни
                </button>
                <button @click="subTab = 'devices'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'devices' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Устройства кухни
                </button>
                <button @click="subTab = 'subscription'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'subscription' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Подписка
                </button>
                <button @click="subTab = 'locations'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition',
                                 subTab === 'locations' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    Точки продаж
                </button>
            </div>
        </div>

        <!-- General Settings -->
        <div v-if="subTab === 'general'" class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-6">Настройки ресторана</h3>
            <div class="max-w-2xl space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Название заведения</label>
                    <input v-model="settings.name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Адрес</label>
                    <input v-model="settings.address" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Телефон</label>
                        <input v-model="settings.phone" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input v-model="settings.email" type="email" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>
                </div>
                <!-- Working Hours by Day -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Режим работы</label>
                    <div class="border rounded-xl overflow-hidden">
                        <div
                            v-for="(day, index) in daysOfWeek"
                            :key="day.key"
                            :class="[
                                'flex items-center gap-4 px-4 py-3',
                                index !== daysOfWeek.length - 1 ? 'border-b' : '',
                                !settings.working_hours[day.key]?.enabled ? 'bg-gray-50' : ''
                            ]"
                        >
                            <!-- Day toggle -->
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="settings.working_hours[day.key].enabled"
                                    class="sr-only peer"
                                >
                                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>

                            <!-- Day name -->
                            <div class="w-28 font-medium" :class="!settings.working_hours[day.key]?.enabled ? 'text-gray-400' : ''">
                                {{ day.label }}
                            </div>

                            <!-- Time inputs or "Closed" -->
                            <div v-if="settings.working_hours[day.key]?.enabled" class="flex items-center gap-2 flex-1">
                                <input
                                    v-model="settings.working_hours[day.key].open"
                                    type="time"
                                    class="px-3 py-1.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent text-sm"
                                >
                                <span class="text-gray-400">—</span>
                                <input
                                    v-model="settings.working_hours[day.key].close"
                                    type="time"
                                    class="px-3 py-1.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent text-sm"
                                >
                            </div>
                            <div v-else class="flex-1 text-gray-400 text-sm">
                                Выходной
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Для работы после полуночи укажите время закрытия меньше открытия (например, 12:00 — 02:00)</p>
                </div>

                <!-- Business Day Ends At -->
                <div class="border rounded-xl p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Время окончания рабочего дня</label>
                    <div class="flex items-center gap-4">
                        <select v-model.number="settings.business_day_ends_at" class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            <option :value="0">00:00</option>
                            <option :value="1">01:00</option>
                            <option :value="2">02:00</option>
                            <option :value="3">03:00</option>
                            <option :value="4">04:00</option>
                            <option :value="5">05:00 (по умолчанию)</option>
                            <option :value="6">06:00</option>
                            <option :value="7">07:00</option>
                            <option :value="8">08:00</option>
                        </select>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        До этого времени система считает что идёт "вчерашний" рабочий день.
                        Например, если установлено 05:00 и сейчас 03:00 ночи - в бронированиях будет показан вчерашний день.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Валюта</label>
                    <select v-model="settings.currency" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <option value="RUB">Рубли (₽)</option>
                        <option value="USD">Доллары ($)</option>
                        <option value="EUR">Евро (€)</option>
                        <option value="KZT">Тенге (₸)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Часовой пояс</label>
                    <select v-model="settings.timezone" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        <option value="Europe/Moscow">Москва (UTC+3)</option>
                        <option value="Europe/Kaliningrad">Калининград (UTC+2)</option>
                        <option value="Asia/Yekaterinburg">Екатеринбург (UTC+5)</option>
                        <option value="Asia/Novosibirsk">Новосибирск (UTC+7)</option>
                        <option value="Asia/Vladivostok">Владивосток (UTC+10)</option>
                    </select>
                </div>

                <!-- Rounding setting -->
                <div class="flex items-center justify-between p-4 border rounded-xl">
                    <div>
                        <div class="font-medium">Округлять суммы до целых</div>
                        <div class="text-sm text-gray-500">Все суммы в системе будут округлены до целого числа</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="settings.round_amounts" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <button @click="saveSettings" class="px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    Сохранить
                </button>
            </div>
        </div>

        <!-- Integrations -->
        <div v-if="subTab === 'integrations'" class="space-y-6">
            <!-- ATOL -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🧾</span>
                        <div>
                            <h4 class="font-semibold">АТОЛ Онлайн</h4>
                            <p class="text-sm text-gray-500">Фискализация чеков (54-ФЗ)</p>
                        </div>
                    </div>
                    <span :class="['px-3 py-1 rounded-full text-sm font-medium',
                                   integrations.atol?.enabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                        {{ integrations.atol?.enabled ? 'Подключено' : 'Отключено' }}
                    </span>
                </div>
                <button @click="openIntegrationModal('atol')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Настроить
                </button>
            </div>

            <!-- Payment Systems -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">💳</span>
                        <div>
                            <h4 class="font-semibold">Платёжные системы</h4>
                            <p class="text-sm text-gray-500">ЮKassa, СберPay, Тинькофф</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">
                        Не настроено
                    </span>
                </div>
                <button @click="openIntegrationModal('payment')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Настроить
                </button>
            </div>

            <!-- Telegram -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">📱</span>
                        <div>
                            <h4 class="font-semibold">Telegram бот</h4>
                            <p class="text-sm text-gray-500">Уведомления и заказы</p>
                        </div>
                    </div>
                    <span :class="['px-3 py-1 rounded-full text-sm font-medium',
                                   integrations.telegram?.enabled ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
                        {{ integrations.telegram?.enabled ? 'Подключено' : 'Не настроено' }}
                    </span>
                </div>
                <button @click="openIntegrationModal('telegram')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Настроить
                </button>
            </div>

            <!-- iiko -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🔗</span>
                        <div>
                            <h4 class="font-semibold">iiko</h4>
                            <p class="text-sm text-gray-500">Синхронизация меню и заказов</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">
                        Не настроено
                    </span>
                </div>
                <button @click="openIntegrationModal('iiko')" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Настроить
                </button>
            </div>

            <!-- Yandex Maps / Geocoder -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="text-2xl mr-3">🗺️</span>
                        <div>
                            <h4 class="font-semibold">Яндекс Карты</h4>
                            <p class="text-sm text-gray-500">Геокодирование и расчёт доставки</p>
                        </div>
                    </div>
                    <span :class="['px-3 py-1 rounded-full text-sm font-medium',
                                   integrations.yandex?.enabled ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
                        {{ integrations.yandex?.enabled ? 'Подключено' : 'Не настроено' }}
                    </span>
                </div>
                <button @click="openYandexModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Настроить
                </button>
            </div>
        </div>

        <!-- Printers -->
        <div v-if="subTab === 'printers'" class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Принтеры</h3>
                    <p class="text-sm text-gray-500">Настройка термопринтеров для чеков и кухни</p>
                </div>
                <button @click="openPrinterModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Добавить принтер
                </button>
            </div>
            <div class="space-y-3">
                <div v-for="printer in printers" :key="printer.id"
                     class="flex items-center justify-between p-4 border rounded-xl hover:border-orange-300 transition">
                    <div class="flex items-center gap-4">
                        <!-- Иконка типа -->
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                             :class="getPrinterTypeClass(printer.type)">
                            {{ getPrinterIcon(printer.type) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium">{{ printer.name }}</h4>
                                <span v-if="printer.is_default" class="text-yellow-500 text-sm" title="По умолчанию">⭐</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <!-- Подключение -->
                                <span v-if="printer.connection_type === 'network'" class="font-mono">
                                    🌐 {{ printer.ip_address }}:{{ printer.port }}
                                </span>
                                <span v-else-if="printer.connection_type === 'usb'" class="font-mono">
                                    🔌 {{ printer.device_path }}
                                </span>
                                <span v-else class="font-mono">
                                    📁 Файл
                                </span>
                                <!-- Разделитель -->
                                <span class="text-gray-300">•</span>
                                <!-- Размер -->
                                <span>{{ printer.paper_width }}мм</span>
                            </div>
                            <!-- Цех (если есть) -->
                            <div v-if="printer.kitchen_station" class="mt-1">
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                      :style="{ backgroundColor: printer.kitchen_station.color + '20', color: printer.kitchen_station.color }">
                                    {{ printer.kitchen_station.icon }} {{ printer.kitchen_station.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Тип принтера -->
                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">
                            {{ getPrinterTypeLabel(printer.type) }}
                        </span>
                        <!-- Статус -->
                        <span :class="['px-2 py-1 rounded text-xs font-medium',
                                       printer.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                            {{ printer.is_active ? 'Активен' : 'Выкл' }}
                        </span>
                        <!-- Кнопки -->
                        <button @click="testPrinter(printer)" class="p-2 hover:bg-gray-100 rounded-lg" title="Тест печати">
                            🔧
                        </button>
                        <button @click="openPrinterModal(printer)" class="p-2 hover:bg-gray-100 rounded-lg" title="Редактировать">
                            ✏️
                        </button>
                        <button v-can="'settings.edit'" @click="deletePrinter(printer)" class="p-2 hover:bg-gray-100 rounded-lg text-red-500" title="Удалить">
                            🗑️
                        </button>
                    </div>
                </div>
                <div v-if="printers.length === 0" class="text-center py-12 text-gray-400">
                    <div class="text-4xl mb-2">🖨️</div>
                    <p>Принтеры не настроены</p>
                    <button @click="openPrinterModal()" class="text-orange-500 hover:underline mt-2">Добавить первый принтер</button>
                </div>
            </div>

            <!-- Настройки автопечати -->
            <div class="mt-6 border rounded-xl p-4">
                <h4 class="font-medium mb-4">Автоматическая печать</h4>
                <div class="space-y-4">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Автопечать на кухню</span>
                            <p class="text-xs text-gray-500">Автоматически печатать заказ на кухню при создании</p>
                        </div>
                        <div class="relative inline-flex items-center">
                            <input type="checkbox" v-model="printSettings.auto_print_kitchen" @change="savePrintSettings" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </div>
                    </label>
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Печать новых позиций</span>
                            <p class="text-xs text-gray-500">Печатать на кухню при добавлении позиций в существующий заказ</p>
                        </div>
                        <div class="relative inline-flex items-center">
                            <input type="checkbox" v-model="printSettings.auto_print_new_items" @change="savePrintSettings" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </div>
                    </label>
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Автопечать чека</span>
                            <p class="text-xs text-gray-500">Автоматически печатать чек после оплаты</p>
                        </div>
                        <div class="relative inline-flex items-center">
                            <input type="checkbox" v-model="printSettings.auto_print_receipt" @change="savePrintSettings" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Системные принтеры (диагностика) -->
            <div class="mt-6 border rounded-xl p-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="font-medium">Системные принтеры</h4>
                        <p class="text-xs text-gray-500">Принтеры, установленные в Windows. Используйте точное имя для USB-подключения.</p>
                    </div>
                    <button @click="scanSystemPrinters" :disabled="scanningPrinters"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition text-sm flex items-center gap-2">
                        <span v-if="scanningPrinters" class="animate-spin">⏳</span>
                        <span v-else>🔍</span>
                        {{ scanningPrinters ? 'Сканирование...' : 'Сканировать' }}
                    </button>
                </div>
                <div v-if="systemPrinters.length > 0" class="space-y-2">
                    <div v-for="sp in systemPrinters" :key="sp.Name"
                         class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <div class="font-mono text-sm">{{ sp.Name }}</div>
                            <div class="text-xs text-gray-500">
                                Порт: {{ sp.PortName }} | Драйвер: {{ sp.DriverName }}
                                <span v-if="sp.ShareName"> | Общий: {{ sp.ShareName }}</span>
                            </div>
                        </div>
                        <button @click="useSystemPrinter(sp)" class="text-xs px-3 py-1 bg-orange-100 text-orange-600 hover:bg-orange-200 rounded">
                            Использовать
                        </button>
                    </div>
                </div>
                <div v-else-if="scannedOnce" class="text-sm text-gray-500 py-2">
                    Принтеры не найдены
                </div>
            </div>

            <!-- Подсказка -->
            <div class="mt-6 p-4 bg-blue-50 rounded-xl">
                <h4 class="font-medium text-blue-800 mb-2">Как настроить печать на кухню по цехам</h4>
                <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                    <li>Создайте цеха во вкладке "Цеха кухни" (горячий, холодный, бар)</li>
                    <li>Назначьте категории блюд цехам (в разделе Меню)</li>
                    <li>Создайте принтер для каждого цеха и выберите нужный цех</li>
                    <li>При отправке заказа блюда автоматически распределятся по принтерам</li>
                </ol>
            </div>
        </div>

        <!-- Notifications -->
        <div v-if="subTab === 'notifications'" class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-6">Настройки уведомлений</h3>
            <div class="space-y-4 max-w-2xl">
                <div class="flex items-center justify-between p-4 border rounded-xl">
                    <div>
                        <div class="font-medium">Новый заказ</div>
                        <div class="text-sm text-gray-500">Звуковое уведомление о новых заказах</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="notifications.newOrder" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-4 border rounded-xl">
                    <div>
                        <div class="font-medium">Заказ готов</div>
                        <div class="text-sm text-gray-500">Уведомление когда кухня отметила заказ готовым</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="notifications.orderReady" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-4 border rounded-xl">
                    <div>
                        <div class="font-medium">Email отчёты</div>
                        <div class="text-sm text-gray-500">Ежедневный отчёт на email администратора</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="notifications.dailyReport" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-4 border rounded-xl">
                    <div>
                        <div class="font-medium">Telegram уведомления</div>
                        <div class="text-sm text-gray-500">Отправлять важные уведомления в Telegram</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" v-model="notifications.telegram" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <button @click="saveNotifications" class="px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition mt-4">
                    Сохранить
                </button>
            </div>
        </div>

        <!-- Receipt Settings -->
        <div v-if="subTab === 'receipts'" class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Настройки чеков</h3>
                    <p class="text-sm text-gray-500">Настройте внешний вид и содержимое печатных документов</p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="testPrintReceipt"
                            :disabled="testingPrint"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center gap-2 disabled:opacity-50">
                        <svg v-if="!testingPrint" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span v-else class="w-4 h-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></span>
                        Тест печати
                    </button>
                    <button @click="savePrintSettings"
                            class="px-6 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Сохранить
                    </button>
                </div>
            </div>

            <!-- Подтабы для типов чеков -->
            <div class="flex gap-2 mb-6 border-b">
                <button @click="receiptSubTab = 'guest'"
                        :class="receiptSubTab === 'guest' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    🧾 Чек для гостя
                </button>
                <button @click="receiptSubTab = 'delivery'"
                        :class="receiptSubTab === 'delivery' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    🚗 Чек доставки
                </button>
                <button @click="receiptSubTab = 'kitchen'"
                        :class="receiptSubTab === 'kitchen' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    👨‍🍳 Производственный чек
                </button>
                <button @click="receiptSubTab = 'precheck'"
                        :class="receiptSubTab === 'precheck' ? 'text-orange-500 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition">
                    📋 Счёт
                </button>
            </div>

            <!-- ==================== ЧЕК ДЛЯ ГОСТЯ ==================== -->
            <div v-if="receiptSubTab === 'guest'" class="flex gap-8">
                <!-- Настройки -->
                <div class="flex-1 space-y-6">
                    <!-- Шапка чека -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs">1</span>
                            Шапка чека
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Название заведения</label>
                                <input v-model="printSettings.receipt_header_name"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Мой ресторан">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Адрес</label>
                                <input v-model="printSettings.receipt_header_address"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="ул. Примерная, д. 1">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Телефон</label>
                                <input v-model="printSettings.receipt_header_phone"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="+7 (999) 123-45-67">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">ИНН</label>
                                <input v-model="printSettings.receipt_header_inn"
                                       class="w-full px-3 py-2 border rounded-lg font-mono focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="0000000000">
                            </div>
                        </div>
                    </div>

                    <!-- Отображаемая информация -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs">2</span>
                            Отображаемая информация
                        </h4>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.show_waiter" class="rounded text-orange-500">
                                <span class="text-sm">Официант</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.show_table" class="rounded text-orange-500">
                                <span class="text-sm">Номер стола</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.show_guests_count" class="rounded text-orange-500">
                                <span class="text-sm">Кол-во гостей</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.show_order_number" class="rounded text-orange-500">
                                <span class="text-sm">Номер заказа</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.show_order_time" class="rounded text-orange-500">
                                <span class="text-sm">Время заказа</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.show_payment_method" class="rounded text-orange-500">
                                <span class="text-sm">Способ оплаты</span>
                            </label>
                        </div>
                    </div>

                    <!-- Футер чека -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs">3</span>
                            Футер чека
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Строка 1</label>
                                <input v-model="printSettings.receipt_footer_line1"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Спасибо за визит!">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Строка 2</label>
                                <input v-model="printSettings.receipt_footer_line2"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Ждем вас снова!">
                            </div>
                        </div>
                    </div>

                    <!-- QR-код -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center text-xs">4</span>
                            QR-код
                        </h4>
                        <label class="flex items-center gap-3 mb-3 cursor-pointer">
                            <input type="checkbox" v-model="printSettings.print_qr" class="rounded text-orange-500">
                            <span class="text-sm">Печатать QR-код на чеке</span>
                        </label>
                        <div v-if="printSettings.print_qr" class="space-y-3">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">URL для QR-кода</label>
                                <input v-model="printSettings.qr_url"
                                       class="w-full px-3 py-2 border rounded-lg font-mono text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="https://example.com/review/{order_id}">
                                <p class="text-xs text-gray-500 mt-1">Используйте {order_id} для подстановки номера заказа</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Текст под QR-кодом</label>
                                <input v-model="printSettings.qr_text"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Сканируйте для отзыва">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Предпросмотр -->
                <div class="flex-shrink-0" :class="previewPrinterWidth === '58' ? 'w-56' : 'w-80'">
                    <div class="sticky top-4">
                        <h4 class="text-sm font-medium text-gray-600 mb-2 text-center">Предпросмотр</h4>
                        <!-- Переключатель ширины принтера -->
                        <div class="flex justify-center gap-1 mb-3">
                            <button @click="previewPrinterWidth = '58'"
                                    :class="previewPrinterWidth === '58' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-l-lg font-medium transition-colors">
                                58мм
                            </button>
                            <button @click="previewPrinterWidth = '80'"
                                    :class="previewPrinterWidth === '80' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-r-lg font-medium transition-colors">
                                80мм
                            </button>
                        </div>
                        <div class="receipt-preview bg-white border-2 border-gray-200 rounded-lg shadow-lg overflow-hidden">
                            <div class="h-4 bg-gradient-to-b from-gray-100 to-white border-b border-dashed border-gray-300"></div>
                            <div class="p-4 font-mono text-xs leading-relaxed text-gray-800" style="font-size: 11px;">
                                <!-- Шапка -->
                                <div class="text-center mb-3">
                                    <div class="font-bold text-base">{{ printSettings.receipt_header_name || 'РЕСТОРАН' }}</div>
                                    <div v-if="printSettings.receipt_header_address" class="text-gray-600">{{ printSettings.receipt_header_address }}</div>
                                    <div v-if="printSettings.receipt_header_phone" class="text-gray-600">Тел: {{ printSettings.receipt_header_phone }}</div>
                                    <div v-if="printSettings.receipt_header_inn" class="text-gray-600">ИНН: {{ printSettings.receipt_header_inn }}</div>
                                </div>
                                <div class="border-t border-dashed border-gray-400 my-2"></div>
                                <div class="text-center font-bold mb-2">КАССОВЫЙ ЧЕК</div>

                                <div v-if="printSettings.show_order_number !== false" class="flex justify-between"><span>Чек №:</span><span>0001</span></div>
                                <div v-if="printSettings.show_order_time !== false" class="flex justify-between"><span>Дата:</span><span>{{ formatReceiptDate(new Date()) }}</span></div>
                                <div v-if="printSettings.show_table !== false" class="flex justify-between"><span>Стол:</span><span>5</span></div>
                                <div v-if="printSettings.show_waiter !== false" class="flex justify-between"><span>Официант:</span><span>Анна</span></div>
                                <div v-if="printSettings.show_guests_count" class="flex justify-between"><span>Гостей:</span><span>2</span></div>

                                <div class="border-t border-dashed border-gray-400 my-2"></div>
                                <div class="font-bold flex text-xs">
                                    <span class="w-6">Кол</span>
                                    <span class="flex-1">Наименование</span>
                                    <span class="w-14 text-right">Сумма</span>
                                </div>
                                <div class="border-t border-dashed border-gray-400 my-1"></div>
                                <div class="flex text-xs"><span class="w-6">2x</span><span class="flex-1">Пицца Маргарита</span><span class="w-14 text-right">890.00</span></div>
                                <div class="flex text-xs"><span class="w-6">1x</span><span class="flex-1">Цезарь</span><span class="w-14 text-right">450.00</span></div>
                                <div class="flex text-xs"><span class="w-6">2x</span><span class="flex-1">Кола 0.5л</span><span class="w-14 text-right">180.00</span></div>

                                <div class="border-t border-dashed border-gray-400 my-2"></div>
                                <div class="flex justify-between"><span>Подитог:</span><span>1 520.00 р.</span></div>
                                <div class="border-t-2 border-gray-600 my-2"></div>
                                <div class="flex justify-between font-bold"><span>ИТОГО:</span><span>1 520.00 р.</span></div>
                                <div class="border-t border-dashed border-gray-400 my-2"></div>
                                <div v-if="printSettings.show_payment_method !== false" class="flex justify-between"><span>Оплата:</span><span>Карта</span></div>

                                <div v-if="printSettings.print_qr" class="text-center my-3">
                                    <div class="inline-block p-2 bg-gray-100 rounded">
                                        <div class="w-16 h-16 bg-white border flex items-center justify-center">
                                            <span class="text-2xl">📱</span>
                                        </div>
                                    </div>
                                    <div class="text-xs mt-1 text-gray-600">{{ printSettings.qr_text || 'Сканируйте для отзыва' }}</div>
                                </div>

                                <div class="text-center mt-3">
                                    <div>{{ printSettings.receipt_footer_line1 || 'Спасибо за визит!' }}</div>
                                    <div>{{ printSettings.receipt_footer_line2 || 'Ждем вас снова!' }}</div>
                                    <div class="text-gray-500 mt-2 text-xs">{{ formatReceiptDateTime(new Date()) }}</div>
                                </div>
                            </div>
                            <div class="h-4 bg-gradient-to-t from-gray-100 to-white border-t border-dashed border-gray-300"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== ЧЕК ДОСТАВКИ ==================== -->
            <div v-if="receiptSubTab === 'delivery'" class="flex gap-8">
                <!-- Настройки -->
                <div class="flex-1 space-y-6">
                    <!-- Шапка (общая с гостевым) -->
                    <div class="border rounded-xl p-4 bg-gray-50">
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Шапка чека берётся из настроек "Чек для гостя"
                        </div>
                    </div>

                    <!-- Информация о доставке -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs">1</span>
                            Информация о доставке
                        </h4>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.delivery_show_customer" class="rounded text-orange-500">
                                <span class="text-sm">ФИО клиента</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.delivery_show_phone" class="rounded text-orange-500">
                                <span class="text-sm">Телефон клиента</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.delivery_show_address" class="rounded text-orange-500">
                                <span class="text-sm">Адрес доставки</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.delivery_show_entrance" class="rounded text-orange-500">
                                <span class="text-sm">Подъезд/Этаж/Кв</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.delivery_show_intercom" class="rounded text-orange-500">
                                <span class="text-sm">Домофон</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.delivery_show_courier" class="rounded text-orange-500">
                                <span class="text-sm">Курьер</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50 col-span-2">
                                <input type="checkbox" v-model="printSettings.delivery_show_comment" class="rounded text-orange-500">
                                <span class="text-sm">Комментарий к заказу</span>
                            </label>
                        </div>
                    </div>

                    <!-- Футер доставки -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs">2</span>
                            Футер чека доставки
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Строка 1</label>
                                <input v-model="printSettings.delivery_footer_line1"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Спасибо за заказ!">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Строка 2</label>
                                <input v-model="printSettings.delivery_footer_line2"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="Приятного аппетита!">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Предпросмотр доставки -->
                <div class="flex-shrink-0" :class="previewPrinterWidth === '58' ? 'w-56' : 'w-80'">
                    <div class="sticky top-4">
                        <h4 class="text-sm font-medium text-gray-600 mb-2 text-center">Предпросмотр</h4>
                        <!-- Переключатель ширины принтера -->
                        <div class="flex justify-center gap-1 mb-3">
                            <button @click="previewPrinterWidth = '58'"
                                    :class="previewPrinterWidth === '58' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-l-lg font-medium transition-colors">
                                58мм
                            </button>
                            <button @click="previewPrinterWidth = '80'"
                                    :class="previewPrinterWidth === '80' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-r-lg font-medium transition-colors">
                                80мм
                            </button>
                        </div>
                        <div class="receipt-preview bg-white border-2 border-gray-200 rounded-lg shadow-lg overflow-hidden">
                            <div class="h-4 bg-gradient-to-b from-gray-100 to-white border-b border-dashed border-gray-300"></div>
                            <div class="p-4 font-mono text-xs leading-relaxed text-gray-800" style="font-size: 11px;">
                                <!-- Шапка -->
                                <div class="text-center mb-3">
                                    <div class="font-bold text-base">{{ printSettings.receipt_header_name || 'РЕСТОРАН' }}</div>
                                    <div v-if="printSettings.receipt_header_address" class="text-gray-600">{{ printSettings.receipt_header_address }}</div>
                                    <div v-if="printSettings.receipt_header_phone" class="text-gray-600">Тел: {{ printSettings.receipt_header_phone }}</div>
                                </div>
                                <div class="border-t border-dashed border-gray-400 my-2"></div>

                                <!-- Бейдж доставки -->
                                <div class="text-center my-2">
                                    <span class="bg-gray-800 text-white px-4 py-1 font-bold text-sm">ДОСТАВКА</span>
                                </div>

                                <div class="flex justify-between"><span>Заказ №:</span><span>D-0042</span></div>
                                <div class="flex justify-between"><span>Дата:</span><span>{{ formatReceiptDate(new Date()) }}</span></div>

                                <div class="border-t border-dashed border-gray-400 my-2"></div>

                                <!-- Информация о клиенте -->
                                <div v-if="printSettings.delivery_show_customer !== false" class="font-bold">КЛИЕНТ:</div>
                                <div v-if="printSettings.delivery_show_customer !== false">Иван Петров</div>
                                <div v-if="printSettings.delivery_show_phone !== false">Тел: +7 (999) 123-45-67</div>

                                <div v-if="printSettings.delivery_show_address !== false" class="mt-2 font-bold">АДРЕС:</div>
                                <div v-if="printSettings.delivery_show_address !== false">ул. Ленина, д. 10</div>
                                <div v-if="printSettings.delivery_show_entrance !== false">Подъезд: 2, Этаж: 5, Кв: 42</div>
                                <div v-if="printSettings.delivery_show_intercom !== false">Домофон: 42#</div>

                                <div v-if="printSettings.delivery_show_courier !== false" class="mt-2 flex justify-between">
                                    <span>Курьер:</span><span>Алексей</span>
                                </div>

                                <div v-if="printSettings.delivery_show_comment !== false" class="mt-2 p-2 bg-gray-100 rounded text-xs">
                                    💬 Позвонить за 5 мин
                                </div>

                                <div class="border-t border-dashed border-gray-400 my-2"></div>
                                <div class="font-bold flex text-xs">
                                    <span class="w-6">Кол</span>
                                    <span class="flex-1">Наименование</span>
                                    <span class="w-14 text-right">Сумма</span>
                                </div>
                                <div class="border-t border-dashed border-gray-400 my-1"></div>
                                <div class="flex text-xs"><span class="w-6">2x</span><span class="flex-1">Пицца Пепперони</span><span class="w-14 text-right">990.00</span></div>
                                <div class="flex text-xs"><span class="w-6">1x</span><span class="flex-1">Ролл Филадельфия</span><span class="w-14 text-right">590.00</span></div>

                                <div class="border-t border-dashed border-gray-400 my-2"></div>
                                <div class="flex justify-between"><span>Подитог:</span><span>1 580.00 р.</span></div>
                                <div class="flex justify-between"><span>Доставка:</span><span>БЕСПЛАТНО</span></div>
                                <div class="border-t-2 border-gray-600 my-2"></div>
                                <div class="flex justify-between font-bold"><span>ИТОГО:</span><span>1 580.00 р.</span></div>

                                <div class="text-center mt-3">
                                    <div>{{ printSettings.delivery_footer_line1 || 'Спасибо за заказ!' }}</div>
                                    <div>{{ printSettings.delivery_footer_line2 || 'Приятного аппетита!' }}</div>
                                    <div class="text-gray-500 mt-2 text-xs">{{ formatReceiptDateTime(new Date()) }}</div>
                                </div>
                            </div>
                            <div class="h-4 bg-gradient-to-t from-gray-100 to-white border-t border-dashed border-gray-300"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== ПРОИЗВОДСТВЕННЫЙ ЧЕК ==================== -->
            <div v-if="receiptSubTab === 'kitchen'" class="flex gap-8">
                <!-- Настройки -->
                <div class="flex-1 space-y-6">
                    <!-- Основные настройки -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs">1</span>
                            Основные настройки
                        </h4>
                        <div class="space-y-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="printSettings.kitchen_beep" class="rounded text-orange-500">
                                <div>
                                    <span class="text-sm font-medium">Звуковой сигнал</span>
                                    <p class="text-xs text-gray-500">Принтер издает звук при печати нового заказа</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="printSettings.kitchen_large_font" class="rounded text-orange-500">
                                <div>
                                    <span class="text-sm font-medium">Крупный шрифт</span>
                                    <p class="text-xs text-gray-500">Позиции печатаются увеличенным шрифтом для удобства</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="printSettings.kitchen_bold_items" class="rounded text-orange-500">
                                <div>
                                    <span class="text-sm font-medium">Жирный шрифт для позиций</span>
                                    <p class="text-xs text-gray-500">Названия блюд выделяются жирным</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Отображаемая информация -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs">2</span>
                            Отображаемая информация
                        </h4>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.kitchen_show_table" class="rounded text-orange-500">
                                <span class="text-sm">Номер стола</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.kitchen_show_waiter" class="rounded text-orange-500">
                                <span class="text-sm">Официант</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.kitchen_show_order_number" class="rounded text-orange-500">
                                <span class="text-sm">Номер заказа</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.kitchen_show_time" class="rounded text-orange-500">
                                <span class="text-sm">Время заказа</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.kitchen_show_order_type" class="rounded text-orange-500">
                                <span class="text-sm">Тип заказа</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.kitchen_show_modifiers" class="rounded text-orange-500">
                                <span class="text-sm">Модификаторы</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50 col-span-2">
                                <input type="checkbox" v-model="printSettings.kitchen_show_comments" class="rounded text-orange-500">
                                <span class="text-sm">Комментарии к позициям (выделенные)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Заголовок -->
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs">3</span>
                            Заголовок чека
                        </h4>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Текст заголовка</label>
                            <input v-model="printSettings.kitchen_header_text"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                   placeholder="НОВЫЙ ЗАКАЗ">
                        </div>
                    </div>
                </div>

                <!-- Предпросмотр кухонного чека -->
                <div class="flex-shrink-0" :class="previewPrinterWidth === '58' ? 'w-56' : 'w-80'">
                    <div class="sticky top-4">
                        <h4 class="text-sm font-medium text-gray-600 mb-2 text-center">Предпросмотр</h4>
                        <!-- Переключатель ширины принтера -->
                        <div class="flex justify-center gap-1 mb-3">
                            <button @click="previewPrinterWidth = '58'"
                                    :class="previewPrinterWidth === '58' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-l-lg font-medium transition-colors">
                                58мм
                            </button>
                            <button @click="previewPrinterWidth = '80'"
                                    :class="previewPrinterWidth === '80' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-r-lg font-medium transition-colors">
                                80мм
                            </button>
                        </div>
                        <div class="receipt-preview bg-white border-2 border-gray-200 rounded-lg shadow-lg overflow-hidden">
                            <div class="h-4 bg-gradient-to-b from-gray-100 to-white border-b border-dashed border-gray-300"></div>
                            <div class="p-4 font-mono leading-relaxed text-gray-800" :class="printSettings.kitchen_large_font ? 'text-sm' : 'text-xs'" style="min-height: 300px;">

                                <!-- Заголовок -->
                                <div class="text-center mb-3">
                                    <div class="bg-gray-800 text-white py-2 px-4 font-bold" :class="printSettings.kitchen_large_font ? 'text-lg' : 'text-base'">
                                        {{ printSettings.kitchen_header_text || 'НОВЫЙ ЗАКАЗ' }}
                                    </div>
                                </div>

                                <!-- Информация -->
                                <div v-if="printSettings.kitchen_show_table !== false" class="text-center font-bold mb-2" :class="printSettings.kitchen_large_font ? 'text-xl' : 'text-lg'">
                                    СТОЛ: 5
                                </div>

                                <div v-if="printSettings.kitchen_show_order_number !== false" class="flex justify-between">
                                    <span>Заказ №:</span><span>0001</span>
                                </div>
                                <div v-if="printSettings.kitchen_show_time !== false" class="flex justify-between">
                                    <span>Время:</span><span>{{ new Date().toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'}) }}</span>
                                </div>
                                <div v-if="printSettings.kitchen_show_waiter !== false" class="flex justify-between">
                                    <span>Официант:</span><span>Анна</span>
                                </div>

                                <div v-if="printSettings.kitchen_show_order_type !== false" class="text-center my-2">
                                    <span class="bg-gray-200 px-3 py-1 text-xs font-bold">В ЗАЛЕ</span>
                                </div>

                                <div class="border-t-2 border-gray-600 my-3"></div>

                                <!-- Позиции -->
                                <div class="space-y-3">
                                    <div>
                                        <div :class="[printSettings.kitchen_large_font ? 'text-base' : 'text-sm', printSettings.kitchen_bold_items !== false ? 'font-bold' : '']">
                                            2x Пицца Маргарита
                                        </div>
                                        <div v-if="printSettings.kitchen_show_modifiers !== false" class="text-gray-600 ml-4">+ Двойной сыр</div>
                                    </div>
                                    <div>
                                        <div :class="[printSettings.kitchen_large_font ? 'text-base' : 'text-sm', printSettings.kitchen_bold_items !== false ? 'font-bold' : '']">
                                            1x Цезарь с курицей
                                        </div>
                                        <div v-if="printSettings.kitchen_show_comments !== false" class="bg-gray-800 text-white px-2 py-1 text-xs mt-1">
                                            ⚠️ Без лука!
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t-2 border-gray-600 my-3"></div>
                                <div class="text-center text-sm">
                                    >>> ПРИНЯТ В {{ new Date().toLocaleTimeString('ru-RU', {hour: '2-digit', minute: '2-digit'}) }} <<<
                                </div>

                                <div v-if="printSettings.kitchen_beep" class="text-center mt-3 text-gray-500 text-xs">
                                    🔔 Звуковой сигнал
                                </div>
                            </div>
                            <div class="h-4 bg-gradient-to-t from-gray-100 to-white border-t border-dashed border-gray-300"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== СЧЁТ ==================== -->
            <div v-if="receiptSubTab === 'precheck'" class="flex gap-8">
                <!-- Настройки -->
                <div class="flex-1 space-y-6">
                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs">1</span>
                            Настройки счёта
                        </h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Заголовок</label>
                                <input v-model="printSettings.precheck_title"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Подзаголовок</label>
                                <input v-model="printSettings.precheck_subtitle"
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="(не является фискальным документом)">
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs">2</span>
                            Отображаемая информация
                        </h4>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.precheck_show_table" class="rounded text-orange-500">
                                <span class="text-sm">Номер стола</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.precheck_show_waiter" class="rounded text-orange-500">
                                <span class="text-sm">Официант</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.precheck_show_date" class="rounded text-orange-500">
                                <span class="text-sm">Дата и время</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" v-model="printSettings.precheck_show_guests" class="rounded text-orange-500">
                                <span class="text-sm">Кол-во гостей</span>
                            </label>
                        </div>
                    </div>

                    <div class="border rounded-xl p-4">
                        <h4 class="font-medium mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs">3</span>
                            Футер счёта
                        </h4>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Текст в конце</label>
                            <input v-model="printSettings.precheck_footer"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                   placeholder="Приятного аппетита!">
                        </div>
                    </div>
                </div>

                <!-- Предпросмотр счёта -->
                <div class="flex-shrink-0" :class="previewPrinterWidth === '58' ? 'w-56' : 'w-80'">
                    <div class="sticky top-4">
                        <h4 class="text-sm font-medium text-gray-600 mb-2 text-center">Предпросмотр</h4>
                        <!-- Переключатель ширины принтера -->
                        <div class="flex justify-center gap-1 mb-3">
                            <button @click="previewPrinterWidth = '58'"
                                    :class="previewPrinterWidth === '58' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-l-lg font-medium transition-colors">
                                58мм
                            </button>
                            <button @click="previewPrinterWidth = '80'"
                                    :class="previewPrinterWidth === '80' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-3 py-1 text-xs rounded-r-lg font-medium transition-colors">
                                80мм
                            </button>
                        </div>
                        <div class="receipt-preview bg-white border-2 border-gray-200 rounded-lg shadow-lg overflow-hidden">
                            <div class="h-4 bg-gradient-to-b from-gray-100 to-white border-b border-dashed border-gray-300"></div>
                            <div class="p-4 font-mono text-xs leading-relaxed text-gray-800" style="font-size: 11px;">
                                <div class="text-center mb-3">
                                    <div class="font-bold text-base">{{ printSettings.precheck_title || 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ' }}</div>
                                    <div class="text-gray-500 text-xs">{{ printSettings.precheck_subtitle || '(не является фискальным документом)' }}</div>
                                </div>

                                <div class="border-t border-dashed border-gray-400 my-2"></div>

                                <div v-if="printSettings.precheck_show_table !== false" class="flex justify-between"><span>Стол №:</span><span>5</span></div>
                                <div v-if="printSettings.precheck_show_date !== false" class="flex justify-between"><span>Дата:</span><span>{{ formatReceiptDate(new Date()) }}</span></div>
                                <div v-if="printSettings.precheck_show_waiter !== false" class="flex justify-between"><span>Официант:</span><span>Анна</span></div>
                                <div v-if="printSettings.precheck_show_guests" class="flex justify-between"><span>Гостей:</span><span>2</span></div>

                                <!-- Гость 1 -->
                                <div class="text-center font-bold my-2">--- Гость 1 ---</div>
                                <div class="font-bold flex text-xs">
                                    <span class="w-6">Кол</span>
                                    <span class="flex-1">Наименование</span>
                                    <span class="w-14 text-right">Сумма</span>
                                </div>
                                <div class="border-t border-dashed border-gray-400 my-1"></div>
                                <div class="flex text-xs"><span class="w-6">2x</span><span class="flex-1">Пицца Маргарита</span><span class="w-14 text-right">890.00</span></div>
                                <div class="flex text-xs"><span class="w-6">1x</span><span class="flex-1">Кола 0.5л</span><span class="w-14 text-right">90.00</span></div>
                                <div class="border-t border-dashed border-gray-400 my-1"></div>
                                <div class="flex justify-between font-bold text-xs"><span>Итого Гость 1:</span><span>980.00</span></div>

                                <!-- Гость 2 -->
                                <div class="text-center font-bold my-2">--- Гость 2 ---</div>
                                <div class="font-bold flex text-xs">
                                    <span class="w-6">Кол</span>
                                    <span class="flex-1">Наименование</span>
                                    <span class="w-14 text-right">Сумма</span>
                                </div>
                                <div class="border-t border-dashed border-gray-400 my-1"></div>
                                <div class="flex text-xs"><span class="w-6">1x</span><span class="flex-1">Цезарь с курицей</span><span class="w-14 text-right">450.00</span></div>
                                <div class="flex text-xs"><span class="w-6">1x</span><span class="flex-1">Кола 0.5л</span><span class="w-14 text-right">90.00</span></div>
                                <div class="border-t border-dashed border-gray-400 my-1"></div>
                                <div class="flex justify-between font-bold text-xs"><span>Итого Гость 2:</span><span>540.00</span></div>

                                <div class="border-t-2 border-gray-600 my-2"></div>
                                <div class="flex justify-between font-bold text-sm"><span>К ОПЛАТЕ:</span><span>1 520.00 р.</span></div>

                                <div class="text-center mt-4">
                                    <div>{{ printSettings.precheck_footer || 'Приятного аппетита!' }}</div>
                                </div>
                            </div>
                            <div class="h-4 bg-gradient-to-t from-gray-100 to-white border-t border-dashed border-gray-300"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kitchen Stations -->
        <div v-if="subTab === 'stations'" class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Цеха кухни</h3>
                    <p class="text-sm text-gray-500">Назначьте блюда цехам для раздельного отображения на кухонных дисплеях</p>
                </div>
                <button @click="openStationModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Добавить цех
                </button>
            </div>

            <!-- Stations List -->
            <div class="space-y-3">
                <div v-for="station in stations" :key="station.id"
                     class="flex items-center justify-between p-4 border rounded-xl hover:border-orange-300 transition">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl"
                             :style="{ backgroundColor: station.color + '20', color: station.color }">
                            {{ station.icon || '🍳' }}
                        </div>
                        <div>
                            <h4 class="font-medium">{{ station.name }}</h4>
                            <p class="text-sm text-gray-500">
                                <span class="font-mono text-xs bg-gray-100 px-1 rounded">{{ station.slug }}</span>
                                <span class="mx-2">·</span>
                                {{ station.dishes_count || 0 }} блюд
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span v-if="station.is_bar" class="px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-700">
                            🍸 Бар
                        </span>
                        <span :class="['px-2 py-1 rounded text-xs font-medium',
                                       station.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                            {{ station.is_active ? 'Активен' : 'Неактивен' }}
                        </span>
                        <button @click="copyKitchenUrl(station)" class="p-1.5 hover:bg-gray-100 rounded" title="Скопировать URL">
                            🔗
                        </button>
                        <button @click="openStationModal(station)" class="p-1.5 hover:bg-gray-100 rounded" title="Редактировать">
                            ✏️
                        </button>
                        <button @click="toggleStation(station)" class="p-1.5 hover:bg-gray-100 rounded" :title="station.is_active ? 'Деактивировать' : 'Активировать'">
                            {{ station.is_active ? '🔴' : '🟢' }}
                        </button>
                        <button v-can="'settings.edit'" @click="deleteStation(station)" class="p-1.5 hover:bg-gray-100 rounded text-red-500" title="Удалить">
                            🗑️
                        </button>
                    </div>
                </div>

                <div v-if="stations.length === 0" class="text-center py-12 text-gray-400">
                    <div class="text-4xl mb-2">🍳</div>
                    <p>Цеха не созданы</p>
                    <button @click="openStationModal()" class="text-orange-500 hover:underline mt-2">Создать первый цех</button>
                </div>
            </div>

            <!-- URL Info -->
            <div class="mt-6 p-4 bg-blue-50 rounded-xl">
                <h4 class="font-medium text-blue-800 mb-2">Как использовать</h4>
                <p class="text-sm text-blue-700">
                    Откройте кухонный дисплей с параметром station для фильтрации:
                </p>
                <code class="block mt-2 p-2 bg-blue-100 rounded text-sm text-blue-800 font-mono">
                    /kitchen?station=hot
                </code>
                <p class="text-sm text-blue-600 mt-2">
                    Блюда без указанного цеха будут показаны на всех дисплеях.
                </p>
            </div>
        </div>

        <!-- Kitchen Devices -->
        <div v-if="subTab === 'devices'" class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Устройства кухни</h3>
                    <p class="text-sm text-gray-500">Планшеты и терминалы для кухонных дисплеев</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="loadDevices" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center gap-2">
                        <span>🔄</span> Обновить
                    </button>
                    <button @click="openCreateDeviceModal" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition flex items-center gap-2">
                        <span>➕</span> Добавить устройство
                    </button>
                </div>
            </div>

            <!-- Devices List -->
            <div class="space-y-3">
                <div v-for="device in devices" :key="device.id"
                     class="p-4 border rounded-xl hover:border-orange-300 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <!-- Status indicator -->
                            <div class="relative">
                                <span class="text-3xl">📱</span>
                                <span v-if="device.is_linked && isDeviceOnline(device)"
                                      class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
                                <span v-else-if="device.is_linked"
                                      class="absolute -bottom-1 -right-1 w-3 h-3 bg-gray-400 rounded-full border-2 border-white"></span>
                                <span v-else
                                      class="absolute -bottom-1 -right-1 w-3 h-3 bg-yellow-400 rounded-full border-2 border-white"></span>
                            </div>
                            <div>
                                <h4 class="font-medium">{{ device.name }}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span v-if="device.kitchen_station"
                                          class="text-xs px-2 py-0.5 rounded-full"
                                          :style="{ backgroundColor: device.kitchen_station.color + '20', color: device.kitchen_station.color }">
                                        {{ device.kitchen_station.icon }} {{ device.kitchen_station.name }}
                                    </span>
                                    <span v-else class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">
                                        Цех не назначен
                                    </span>
                                    <span v-if="device.is_linked" class="text-xs text-gray-400">
                                        {{ device.last_seen_at ? formatDate(device.last_seen_at) : '' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="['px-2 py-1 rounded text-xs font-medium',
                                           !device.is_linked ? 'bg-blue-100 text-blue-700' :
                                           device.status === 'active' ? 'bg-green-100 text-green-700' :
                                           device.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                                           'bg-red-100 text-red-700']">
                                {{ !device.is_linked ? 'Не привязан' :
                                   device.status === 'active' ? 'Активен' :
                                   device.status === 'pending' ? 'Ожидает' : 'Отключён' }}
                            </span>
                            <button @click="openDeviceModal(device)" class="p-1.5 hover:bg-gray-100 rounded" title="Настроить">
                                ⚙️
                            </button>
                            <button v-can="'settings.edit'" @click="deleteDevice(device)" class="p-1.5 hover:bg-gray-100 rounded text-red-500" title="Удалить">
                                🗑️
                            </button>
                        </div>
                    </div>

                    <!-- Linking code section (for unlinked devices) -->
                    <div v-if="!device.is_linked" class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-700 font-medium">Код привязки:</p>
                                <p class="text-xs text-blue-500 mt-0.5">Введите этот код на планшете в /kitchen</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="device.linking_code?.code" class="text-right">
                                    <span class="font-mono text-2xl font-bold text-blue-700 tracking-[0.2em]">{{ device.linking_code.code }}</span>
                                    <p class="text-xs text-blue-400">{{ Math.ceil(device.linking_code.expires_in_seconds / 60) }} мин</p>
                                </div>
                                <div v-else class="text-sm text-blue-400">
                                    Код истёк
                                </div>
                                <button
                                    @click="regenerateLinkingCode(device)"
                                    class="p-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition"
                                    title="Обновить код"
                                >
                                    🔄
                                </button>
                                <button
                                    @click="copyLinkingCode(device)"
                                    v-if="device.linking_code?.code"
                                    class="p-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition"
                                    title="Копировать"
                                >
                                    📋
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Linked device info -->
                    <div v-else class="mt-3 flex items-center justify-between text-xs text-gray-500">
                        <span class="font-mono">ID: {{ device.device_id }}</span>
                        <button
                            @click="unlinkDevice(device)"
                            class="text-orange-500 hover:text-orange-700 transition"
                        >
                            Отвязать устройство
                        </button>
                    </div>
                </div>

                <div v-if="devices.length === 0" class="text-center py-12 text-gray-400">
                    <div class="text-4xl mb-2">📱</div>
                    <p>Нет устройств</p>
                    <p class="text-sm mt-2">Нажмите "Добавить устройство" для создания</p>
                </div>
            </div>

            <!-- How it works -->
            <div class="mt-6 p-4 bg-green-50 rounded-xl">
                <h4 class="font-medium text-green-800 mb-2">Как добавить устройство</h4>
                <ol class="text-sm text-green-700 list-decimal list-inside space-y-1">
                    <li>Нажмите "Добавить устройство" и настройте его (название, цех)</li>
                    <li>Скопируйте 6-значный код привязки</li>
                    <li>Откройте <code class="bg-green-100 px-1 rounded">/kitchen</code> на планшете</li>
                    <li>Введите код на планшете - устройство привяжется автоматически</li>
                </ol>
                <p class="text-xs text-green-600 mt-2">Код действует 10 минут. Можно обновить в любой момент.</p>
            </div>
        </div>

        <!-- Device Modal -->
        <Teleport to="body">
            <div v-if="showDeviceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDeviceModal = false">
                <div class="bg-white rounded-2xl w-[500px] p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">{{ deviceForm.id ? 'Настройка устройства' : 'Новое устройство' }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                            <input v-model="deviceForm.name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Планшет горячего цеха">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Цех</label>
                            <select v-model="deviceForm.kitchen_station_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option :value="null">Не назначен (все заказы)</option>
                                <option v-for="s in stations" :key="s.id" :value="s.id">
                                    {{ s.icon }} {{ s.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="deviceForm.id">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Статус</label>
                            <select v-model="deviceForm.status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="pending">Ожидает настройки</option>
                                <option value="active">Активен</option>
                                <option value="disabled">Отключён</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">PIN для смены станции (опционально)</label>
                            <input v-model="deviceForm.pin" type="text" maxlength="6" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono tracking-widest" placeholder="123456">
                            <p class="text-xs text-gray-500 mt-1">Если указан, повар сможет сменить цех только введя этот PIN</p>
                        </div>

                        <!-- Device info (only for existing linked devices) -->
                        <div v-if="deviceForm.id && deviceForm.device_id" class="p-3 bg-gray-50 rounded-lg text-sm">
                            <div class="grid grid-cols-2 gap-2 text-gray-600">
                                <div>
                                    <span class="text-gray-400">ID:</span>
                                    <span class="font-mono text-xs ml-1">{{ deviceForm.device_id }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">IP:</span>
                                    <span class="ml-1">{{ deviceForm.ip_address || 'Неизвестен' }}</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-gray-400">Последняя активность:</span>
                                    <span class="ml-1">{{ deviceForm.last_seen_at ? formatDate(deviceForm.last_seen_at) : 'Никогда' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showDeviceModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveDevice" :disabled="!deviceForm.name" class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:bg-gray-300 disabled:text-gray-500 transition">
                            {{ deviceForm.id ? 'Сохранить' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Station Modal -->
        <Teleport to="body">
            <div v-if="showStationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showStationModal = false">
                <div class="bg-white rounded-2xl w-[500px] p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">{{ stationForm.id ? 'Редактировать цех' : 'Новый цех' }}</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                                <input v-model="stationForm.name" @input="generateSlug" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Горячий цех">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Slug (URL)</label>
                                <input v-model="stationForm.slug" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono" placeholder="hot">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Иконка</label>
                                <div class="flex gap-2">
                                    <input v-model="stationForm.icon" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent text-center text-xl" placeholder="🔥">
                                    <div class="flex gap-1">
                                        <button v-for="emoji in stationEmojis" :key="emoji" @click="stationForm.icon = emoji"
                                                class="w-10 h-10 border rounded-lg hover:bg-gray-100 text-lg">{{ emoji }}</button>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Цвет</label>
                                <div class="flex gap-2">
                                    <input v-model="stationForm.color" type="color" class="w-12 h-10 border rounded-lg cursor-pointer">
                                    <input v-model="stationForm.color" class="flex-1 px-4 py-2 border rounded-lg font-mono" placeholder="#EF4444">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                            <input v-model="stationForm.description" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Горячие блюда, супы, гарниры">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">🔔 Звук уведомления</label>
                            <div class="flex gap-2">
                                <select v-model="stationForm.notification_sound" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option value="bell">🔔 Колокольчик</option>
                                    <option value="chime">🎵 Перезвон</option>
                                    <option value="ding">📢 Динг</option>
                                    <option value="kitchen">🍳 Кухонный звонок</option>
                                    <option value="alert">⚠️ Сигнал</option>
                                    <option value="gong">🎶 Гонг</option>
                                </select>
                                <button type="button" @click="playStationSound(stationForm.notification_sound)"
                                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center gap-1"
                                        title="Прослушать звук">
                                    ▶️ Тест
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Разные звуки помогут различать станции на слух</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Порядок сортировки</label>
                                <input v-model.number="stationForm.sort_order" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div class="flex flex-col gap-3 justify-end">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="stationForm.is_active" class="w-5 h-5 accent-orange-500">
                                    <span class="text-sm text-gray-700">Активен</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="stationForm.is_bar" class="w-5 h-5 accent-purple-500">
                                    <span class="text-sm text-gray-700">Это бар</span>
                                    <span class="text-xs text-purple-500">(панель на POS)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showStationModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveStation" :disabled="!stationForm.name" class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Printer Modal -->
        <Teleport to="body">
            <div v-if="showPrinterModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPrinterModal = false">
                <div class="bg-white rounded-2xl w-[600px] max-h-[90vh] overflow-y-auto p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">{{ printerForm.id ? 'Редактировать принтер' : 'Новый принтер' }}</h3>
                    <div class="space-y-4">
                        <!-- Название -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                            <input v-model="printerForm.name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Чековый принтер - касса">
                        </div>

                        <!-- Тип принтера -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Тип принтера *</label>
                                <select v-model="printerForm.type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option value="receipt">🧾 Касса (чеки, счета)</option>
                                    <option value="kitchen">🍳 Кухня</option>
                                    <option value="bar">🍸 Бар</option>
                                    <option value="delivery">🚗 Доставка</option>
                                    <option value="label">🏷️ Этикетки</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Тип подключения *</label>
                                <select v-model="printerForm.connection_type" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option value="network">🌐 Сеть (IP)</option>
                                    <option value="usb">🔌 USB / COM</option>
                                    <option value="file">📁 Файл (тест)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Подключение: Сеть -->
                        <div v-if="printerForm.connection_type === 'network'" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">IP адрес *</label>
                                <input v-model="printerForm.ip_address" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono" placeholder="192.168.1.100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Порт</label>
                                <input v-model.number="printerForm.port" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono" placeholder="9100">
                            </div>
                        </div>

                        <!-- Подключение: USB -->
                        <div v-if="printerForm.connection_type === 'usb'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Путь к устройству *</label>
                            <input v-model="printerForm.device_path" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono" placeholder="COM1 или /dev/usb/lp0">
                            <p class="text-xs text-gray-500 mt-1">Windows: COM1, COM2... | Linux: /dev/usb/lp0, /dev/ttyUSB0</p>
                        </div>

                        <!-- Привязка к цеху (для кухни/бара) -->
                        <div v-if="printerForm.type === 'kitchen' || printerForm.type === 'bar'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Цех (опционально)</label>
                            <select v-model="printerForm.kitchen_station_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option :value="null">Все позиции (без фильтрации)</option>
                                <option v-for="s in stations" :key="s.id" :value="s.id">
                                    {{ s.icon }} {{ s.name }}
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Если указан цех, на этот принтер будут печататься только блюда этого цеха</p>
                        </div>

                        <!-- Настройки бумаги -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ширина бумаги</label>
                                <select v-model.number="printerForm.paper_width" @change="updateCharsPerLine" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option :value="80">80 мм (стандарт)</option>
                                    <option :value="58">58 мм (узкий)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Кодировка</label>
                                <select v-model="printerForm.encoding" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option value="cp866">CP866 (DOS русский)</option>
                                    <option value="cp1251">CP1251 (Windows)</option>
                                    <option value="utf8">UTF-8</option>
                                </select>
                            </div>
                        </div>

                        <!-- Опции печати -->
                        <div class="border rounded-xl p-4 space-y-3">
                            <div class="text-sm font-medium text-gray-700 mb-2">Опции печати</div>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="printerForm.cut_paper" class="w-5 h-5 accent-orange-500">
                                    <span class="text-sm text-gray-700">✂️ Отрезка бумаги</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="printerForm.open_drawer" class="w-5 h-5 accent-orange-500">
                                    <span class="text-sm text-gray-700">🗃️ Открывать ящик</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="printerForm.print_qr" class="w-5 h-5 accent-orange-500">
                                    <span class="text-sm text-gray-700">📱 QR-код на чеке</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="printerForm.print_logo" class="w-5 h-5 accent-orange-500">
                                    <span class="text-sm text-gray-700">🖼️ Логотип</span>
                                </label>
                            </div>
                        </div>

                        <!-- Статус -->
                        <div class="flex items-center justify-between border rounded-xl p-4">
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="printerForm.is_active" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-green-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                </label>
                                <span class="text-sm text-gray-700">Активен</span>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" v-model="printerForm.is_default" class="w-5 h-5 accent-orange-500">
                                <span class="text-sm text-gray-700">⭐ По умолчанию</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showPrinterModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="savePrinter" :disabled="!canSavePrinter" class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Yandex Maps Modal -->
        <Teleport to="body">
            <div v-if="showYandexModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showYandexModal = false">
                <div class="bg-white rounded-2xl w-[550px] p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold mb-4">Настройки Яндекс Карт</h3>

                    <!-- Enable toggle -->
                    <div class="flex items-center justify-between p-4 border rounded-xl mb-4">
                        <div>
                            <div class="font-medium">Включить интеграцию</div>
                            <div class="text-sm text-gray-500">Геокодирование адресов и расчёт расстояния</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="yandexForm.enabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        </label>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">API ключ Яндекс *</label>
                            <input v-model="yandexForm.api_key"
                                   type="password"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            <p class="text-xs text-gray-500 mt-1">
                                Получите ключ в <a href="https://developer.tech.yandex.ru/" target="_blank" class="text-orange-500 hover:underline">Кабинете разработчика Яндекс</a>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Город по умолчанию</label>
                            <input v-model="yandexForm.city"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                   placeholder="Москва">
                            <p class="text-xs text-gray-500 mt-1">Используется для геокодирования неполных адресов</p>
                        </div>

                        <!-- Адрес ресторана с автоопределением координат -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Адрес ресторана</label>
                            <div class="flex gap-2">
                                <input v-model="yandexForm.restaurant_address"
                                       class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                       placeholder="ул. Примерная, д. 1"
                                       @keyup.enter="geocodeRestaurantAddress">
                                <button @click="geocodeRestaurantAddress"
                                        :disabled="!yandexForm.api_key || !yandexForm.restaurant_address || geocodingAddress"
                                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 whitespace-nowrap flex items-center gap-2">
                                    <span v-if="geocodingAddress" class="animate-spin">⏳</span>
                                    <span v-else>📍</span>
                                    Найти
                                </button>
                            </div>
                            <p v-if="geocodeAddressResult" class="text-xs mt-1"
                               :class="geocodeAddressResult.success ? 'text-green-600' : 'text-red-500'">
                                {{ geocodeAddressResult.message }}
                            </p>
                            <p v-else class="text-xs text-gray-500 mt-1">Введите адрес и нажмите "Найти" для автоматического определения координат</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Широта ресторана *</label>
                                <input v-model="yandexForm.restaurant_lat"
                                       type="number"
                                       step="0.000001"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                                       placeholder="55.751244">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Долгота ресторана *</label>
                                <input v-model="yandexForm.restaurant_lng"
                                       type="number"
                                       step="0.000001"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono"
                                       placeholder="37.618423">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 -mt-2">
                            Координаты заполнятся автоматически при поиске адреса, или введите вручную.
                        </p>

                        <!-- Test connection -->
                        <div class="p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-sm">Проверка подключения</div>
                                    <div class="text-xs text-gray-500">Проверить работу геокодера</div>
                                </div>
                                <button @click="testYandexConnection"
                                        :disabled="!yandexForm.api_key || yandexTestingConnection"
                                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50 flex items-center gap-2">
                                    <span v-if="yandexTestingConnection" class="animate-spin">⏳</span>
                                    <span v-else>🔌</span>
                                    Проверить
                                </button>
                            </div>
                            <div v-if="yandexTestResult" class="mt-3 p-3 rounded-lg text-sm"
                                 :class="yandexTestResult.success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                {{ yandexTestResult.message }}
                            </div>
                        </div>

                        <!-- API limits info -->
                        <div class="p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                            <div class="font-medium mb-1">Лимиты API</div>
                            <ul class="list-disc list-inside text-xs space-y-1">
                                <li>Геокодер: 10 000 запросов в сутки (бесплатно)</li>
                                <li>JavaScript API: без ограничений</li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button @click="showYandexModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveYandexSettings"
                                :disabled="!yandexForm.api_key || !yandexForm.restaurant_lat || !yandexForm.restaurant_lng"
                                class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Subscription Settings -->
        <SubscriptionTab v-if="subTab === 'subscription'" />

        <!-- Locations / Points of Sale -->
        <div v-if="subTab === 'locations'" class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold">Точки продаж</h3>
                        <p class="text-sm text-gray-500">Управление ресторанами и филиалами</p>
                    </div>
                    <button @click="openLocationModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Добавить точку
                    </button>
                </div>

                <!-- Locations list -->
                <div v-if="locations.length === 0" class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <p>Нет точек продаж</p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="loc in locations" :key="loc.id"
                         :class="['border rounded-xl overflow-hidden transition', loc.is_current ? 'border-orange-500 bg-orange-50' : '']">
                        <!-- Location Header -->
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold text-lg">{{ loc.name }}</span>
                                        <span v-if="loc.is_main" class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-xs font-medium">Главная</span>
                                        <span v-if="loc.is_current" class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">Текущая</span>
                                        <span v-if="!loc.is_active" class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">Отключена</span>
                                    </div>
                                    <div class="text-sm text-gray-500 space-y-1">
                                        <p v-if="loc.address">{{ loc.address }}</p>
                                        <p v-if="loc.phone || loc.email" class="flex items-center gap-3">
                                            <span v-if="loc.phone">{{ loc.phone }}</span>
                                            <span v-if="loc.email">{{ loc.email }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button v-if="!loc.is_current" @click="switchLocation(loc)"
                                            class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                                        Переключиться
                                    </button>
                                    <button @click="openLocationModal(loc)"
                                            class="p-2 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button v-if="!loc.is_main" @click="makeMainLocation(loc)"
                                            class="p-2 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition"
                                            title="Сделать главной">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                        </svg>
                                    </button>
                                    <button v-if="!loc.is_main" v-can="'settings.edit'" @click="deleteLocation(loc)"
                                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Legal Entities Section -->
                        <div class="border-t bg-gray-50">
                            <button @click="toggleLocationLegalEntities(loc.id)"
                                    class="w-full px-4 py-3 flex items-center justify-between text-sm hover:bg-gray-100 transition">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🏢</span>
                                    <span class="font-medium text-gray-700">Юридические лица</span>
                                    <span class="px-2 py-0.5 bg-gray-200 text-gray-600 rounded-full text-xs">
                                        {{ getLocationLegalEntities(loc.id).length }}
                                    </span>
                                </div>
                                <svg :class="['w-5 h-5 text-gray-400 transition-transform', expandedLocationEntities.has(loc.id) ? 'rotate-180' : '']"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Expanded Legal Entities -->
                            <div v-if="expandedLocationEntities.has(loc.id)" class="px-4 pb-4">
                                <div v-if="getLocationLegalEntities(loc.id).length === 0" class="text-center py-6 text-gray-500">
                                    <p class="mb-2">Нет юридических лиц</p>
                                    <button @click="openLegalEntityModal(loc.id)"
                                            class="text-orange-500 hover:text-orange-600 text-sm font-medium">
                                        + Добавить юрлицо
                                    </button>
                                </div>
                                <div v-else class="space-y-2">
                                    <div v-for="entity in getLocationLegalEntities(loc.id)" :key="entity.id"
                                         class="bg-white rounded-lg p-3 border flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div :class="[
                                                'w-10 h-10 rounded-lg flex items-center justify-center text-xs font-bold',
                                                entity.type === 'llc' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'
                                            ]">
                                                {{ entity.type === 'llc' ? 'ООО' : 'ИП' }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-gray-900">{{ entity.name }}</span>
                                                    <span v-if="entity.is_default" class="text-xs px-1.5 py-0.5 bg-orange-100 text-orange-600 rounded">
                                                        По умолчанию
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500">ИНН: {{ entity.inn }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button @click="openLegalEntityModal(loc.id, entity)"
                                                    class="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button v-can="'settings.edit'" @click="deleteLegalEntity(entity)"
                                                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <button @click="openLegalEntityModal(loc.id)"
                                            class="w-full py-2 text-sm text-orange-500 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition">
                                        + Добавить юрлицо
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plan limit warning -->
                <div v-if="locationLimitReached" class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-yellow-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p class="font-medium text-yellow-800">Достигнут лимит точек для вашего тарифа</p>
                            <p class="text-sm text-yellow-700 mt-1">Перейдите на более высокий тариф, чтобы добавить больше точек продаж.</p>
                            <button @click="subTab = 'subscription'" class="mt-2 text-sm text-orange-600 hover:text-orange-700 font-medium">
                                Изменить тариф &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Modal -->
        <Teleport to="body">
            <div v-if="showLocationModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showLocationModal = false">
                <div class="bg-white rounded-2xl w-[500px] max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">{{ locationForm.id ? 'Редактировать точку' : 'Новая точка продаж' }}</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Название <span class="text-red-500">*</span></label>
                            <input v-model="locationForm.name" type="text" placeholder="Ресторан на Пушкина"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Адрес</label>
                            <input v-model="locationForm.address" type="text" placeholder="ул. Пушкина, д. 10"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Телефон</label>
                                <input v-model="locationForm.phone" type="tel" placeholder="+7 (999) 123-45-67"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input v-model="locationForm.email" type="email" placeholder="branch@example.com"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="locationForm.is_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                            </label>
                            <span class="text-sm text-gray-700">Точка активна</span>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex gap-3">
                        <button @click="showLocationModal = false" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                            Отмена
                        </button>
                        <button @click="saveLocation" :disabled="!locationForm.name || savingLocation"
                                class="flex-1 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            {{ savingLocation ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Legal Entity Modal -->
        <Teleport to="body">
            <div v-if="showLegalEntityModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showLegalEntityModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ legalEntityForm.id ? 'Редактировать' : 'Новое' }} юридическое лицо</h3>
                        <button @click="showLegalEntityModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Основное -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Основное</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm text-gray-600 mb-1">Название *</label>
                                    <input v-model="legalEntityForm.name" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="ООО Ресторан" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Короткое название</label>
                                    <input v-model="legalEntityForm.short_name" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="ООО (для чека)" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Тип *</label>
                                    <select v-model="legalEntityForm.type"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        <option value="llc">ООО</option>
                                        <option value="ie">ИП</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">ИНН *</label>
                                    <input v-model="legalEntityForm.inn" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           :placeholder="legalEntityForm.type === 'ie' ? '12 цифр' : '10 цифр'" />
                                </div>
                                <div v-if="legalEntityForm.type === 'llc'">
                                    <label class="block text-sm text-gray-600 mb-1">КПП</label>
                                    <input v-model="legalEntityForm.kpp" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="9 цифр" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">ОГРН</label>
                                    <input v-model="legalEntityForm.ogrn" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           :placeholder="legalEntityForm.type === 'ie' ? 'ОГРНИП (15 цифр)' : 'ОГРН (13 цифр)'" />
                                </div>
                            </div>
                        </div>

                        <!-- Адреса -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Адреса</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Юридический адрес</label>
                                    <input v-model="legalEntityForm.legal_address" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Фактический адрес</label>
                                    <input v-model="legalEntityForm.actual_address" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                                </div>
                            </div>
                        </div>

                        <!-- Руководитель -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Руководитель</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">ФИО</label>
                                    <input v-model="legalEntityForm.director_name" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Должность</label>
                                    <input v-model="legalEntityForm.director_position" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="Генеральный директор" />
                                </div>
                            </div>
                        </div>

                        <!-- Банк -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Банковские реквизиты</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm text-gray-600 mb-1">Название банка</label>
                                    <input v-model="legalEntityForm.bank_name" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">БИК</label>
                                    <input v-model="legalEntityForm.bank_bik" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="9 цифр" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Корр. счёт</label>
                                    <input v-model="legalEntityForm.bank_corr_account" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="20 цифр" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm text-gray-600 mb-1">Расчётный счёт</label>
                                    <input v-model="legalEntityForm.bank_account" type="text"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                           placeholder="20 цифр" />
                                </div>
                            </div>
                        </div>

                        <!-- Налоги -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Налогообложение</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Система налогообложения</label>
                                    <select v-model="legalEntityForm.taxation_system"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        <option value="osn">ОСН (общая)</option>
                                        <option value="usn_income">УСН (доходы 6%)</option>
                                        <option value="usn_income_expense">УСН (доходы-расходы 15%)</option>
                                        <option value="patent">Патент</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Ставка НДС</label>
                                    <select v-model="legalEntityForm.vat_rate"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        <option :value="null">Без НДС</option>
                                        <option :value="0">0%</option>
                                        <option :value="10">10%</option>
                                        <option :value="20">20%</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Алкоголь -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Лицензия на алкоголь</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-2">
                                    <input v-model="legalEntityForm.has_alcohol_license" type="checkbox"
                                           class="rounded text-orange-500 focus:ring-orange-500" />
                                    <span class="text-sm text-gray-700">Есть лицензия на алкоголь</span>
                                </label>
                                <div v-if="legalEntityForm.has_alcohol_license" class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Номер лицензии</label>
                                        <input v-model="legalEntityForm.alcohol_license_number" type="text"
                                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Срок действия</label>
                                        <input v-model="legalEntityForm.alcohol_license_expires_at" type="date"
                                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Статус -->
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input v-model="legalEntityForm.is_active" type="checkbox"
                                       class="rounded text-orange-500 focus:ring-orange-500" />
                                <span class="text-sm text-gray-700">Активен</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="legalEntityForm.is_default" type="checkbox"
                                       class="rounded text-orange-500 focus:ring-orange-500" />
                                <span class="text-sm text-gray-700">По умолчанию</span>
                            </label>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button @click="showLegalEntityModal = false"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                            Отмена
                        </button>
                        <button @click="saveLegalEntity" :disabled="!legalEntityForm.name || !legalEntityForm.inn || savingLegalEntity"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            {{ savingLegalEntity ? 'Сохранение...' : (legalEntityForm.id ? 'Сохранить' : 'Создать') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';
import SubscriptionTab from './SubscriptionTab.vue';

const store = useBackofficeStore();

// State
const subTab = ref('general');

// Locations state
const locations = ref<any[]>([]);
const showLocationModal = ref(false);
const savingLocation = ref(false);
const locationLimitReached = ref(false);
const locationForm = ref({
    id: null as any,
    name: '',
    address: '',
    phone: '',
    email: '',
    is_active: true
});

// Legal Entities state
const legalEntities = ref<any[]>([]);
const expandedLocationEntities = ref(new Set());
const showLegalEntityModal = ref(false);
const savingLegalEntity = ref(false);
const legalEntityForm = ref({
    id: null as any,
    restaurant_id: null as any,
    name: '',
    short_name: '',
    type: 'llc',
    inn: '',
    kpp: '',
    ogrn: '',
    legal_address: '',
    actual_address: '',
    director_name: '',
    director_position: '',
    bank_name: '',
    bank_bik: '',
    bank_account: '',
    bank_corr_account: '',
    taxation_system: 'usn_income',
    vat_rate: null as any,
    has_alcohol_license: false,
    alcohol_license_number: '',
    alcohol_license_expires_at: null as any,
    is_active: true,
    is_default: false
});

const showPrinterModal = ref(false);
const showStationModal = ref(false);
const showDeviceModal = ref(false);
const showYandexModal = ref(false);
const yandexTestingConnection = ref(false);
const yandexTestResult = ref<any>(null);
const geocodingAddress = ref(false);
const geocodeAddressResult = ref<any>(null);

// Settings
const settings = ref({
    name: '',
    address: '',
    phone: '',
    email: '',
    currency: 'RUB',
    timezone: 'Europe/Moscow',
    round_amounts: false,
    business_day_ends_at: 5, // Час окончания рабочего дня (по умолчанию 05:00)
    working_hours: {
        monday: { enabled: true, open: '10:00', close: '23:00' },
        tuesday: { enabled: true, open: '10:00', close: '23:00' },
        wednesday: { enabled: true, open: '10:00', close: '23:00' },
        thursday: { enabled: true, open: '10:00', close: '23:00' },
        friday: { enabled: true, open: '10:00', close: '23:00' },
        saturday: { enabled: true, open: '10:00', close: '23:00' },
        sunday: { enabled: true, open: '10:00', close: '23:00' }
    } as Record<string, { enabled: boolean; open: string; close: string }>
});

// Days of week for working hours
const daysOfWeek = [
    { key: 'monday', label: 'Понедельник', short: 'Пн' },
    { key: 'tuesday', label: 'Вторник', short: 'Вт' },
    { key: 'wednesday', label: 'Среда', short: 'Ср' },
    { key: 'thursday', label: 'Четверг', short: 'Чт' },
    { key: 'friday', label: 'Пятница', short: 'Пт' },
    { key: 'saturday', label: 'Суббота', short: 'Сб' },
    { key: 'sunday', label: 'Воскресенье', short: 'Вс' }
];

const integrations = ref({
    atol: { enabled: false },
    telegram: { enabled: false },
    yandex: { enabled: false }
});

// Yandex Form
const yandexForm = ref({
    enabled: false,
    api_key: '',
    city: '',
    restaurant_address: '',
    restaurant_lat: '',
    restaurant_lng: ''
});

const printers = ref<any[]>([]);
const systemPrinters = ref<any[]>([]);
const scanningPrinters = ref(false);
const scannedOnce = ref(false);

// Тип предпросмотра чека
const receiptPreviewType = ref('dine_in');
const receiptSubTab = ref('guest');
const testingPrint = ref(false);
const previewPrinterWidth = ref('80'); // 58 или 80 мм

const printSettings = ref({
    // Автопечать
    auto_print_kitchen: true,
    auto_print_new_items: true,
    auto_print_receipt: false,
    receipt_copies: 1,
    kitchen_copies: 1,

    // Шапка чека
    receipt_header_name: '',
    receipt_header_address: '',
    receipt_header_phone: '',
    receipt_header_inn: '',

    // Настройки печати
    print_logo: false,
    print_qr: false,
    qr_url: '',
    qr_text: 'Сканируйте для отзыва',

    // Отображение на чеке гостя
    show_waiter: true,
    show_table: true,
    show_guests_count: false,
    show_order_number: true,
    show_order_time: true,
    show_payment_method: true,

    // Футер чека
    receipt_footer_line1: 'Спасибо за визит!',
    receipt_footer_line2: 'Ждем вас снова!',

    // Футер доставки
    delivery_footer_line1: 'Спасибо за заказ!',
    delivery_footer_line2: 'Приятного аппетита!',

    // Отображение на чеке доставки
    delivery_show_customer: true,
    delivery_show_phone: true,
    delivery_show_address: true,
    delivery_show_entrance: true,
    delivery_show_intercom: true,
    delivery_show_courier: true,
    delivery_show_comment: true,

    // Кухня
    kitchen_beep: true,
    kitchen_large_font: true,
    kitchen_bold_items: true,
    kitchen_header_text: 'НОВЫЙ ЗАКАЗ',
    kitchen_show_table: true,
    kitchen_show_waiter: true,
    kitchen_show_order_number: true,
    kitchen_show_time: true,
    kitchen_show_order_type: true,
    kitchen_show_modifiers: true,
    kitchen_show_comments: true,

    // Пречек
    precheck_title: 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ',
    precheck_subtitle: '(не является фискальным документом)',
    precheck_show_table: true,
    precheck_show_waiter: true,
    precheck_show_date: true,
    precheck_show_guests: false,
    precheck_footer: 'Приятного аппетита!',
});

const stations = ref<any[]>([]);

const devices = ref<any[]>([]);

const notifications = ref({
    newOrder: true,
    orderReady: true,
    dailyReport: false,
    telegram: false
});

// Forms
const printerForm = ref({
    id: null as any,
    name: '',
    type: 'receipt',
    kitchen_station_id: null as any,
    connection_type: 'network',
    ip_address: '',
    port: 9100,
    device_path: '',
    paper_width: 80,
    chars_per_line: 48,
    encoding: 'cp866',
    cut_paper: true,
    open_drawer: false,
    print_logo: false,
    print_qr: true,
    is_active: true,
    is_default: false
});

const stationForm = ref({
    id: null as any,
    name: '',
    slug: '',
    icon: '',
    color: '#6366F1',
    description: '',
    notification_sound: 'bell',
    sort_order: 0,
    is_active: true,
    is_bar: false
});

const deviceForm = ref({
    id: null as any,
    device_id: '',
    name: '',
    kitchen_station_id: null as any,
    status: 'pending',
    pin: '',
    ip_address: '',
    last_seen_at: null as any
});

// Constants
const stationEmojis = ['🔥', '❄️', '🥩', '🍕', '🍣', '🍸', '🍰', '🥗'];
const printerDestinations = [
    { key: 'receipt', label: 'Чеки' },
    { key: 'precheck', label: 'Счета' },
    { key: 'kitchen', label: 'Кухня' },
    { key: 'bar', label: 'Бар' }
];

// Methods
async function loadSettings() {
    try {
        const res = await store.api('/backoffice/settings') as Record<string, any>;
        if (res.settings) {
            const s = res.settings as Record<string, any>;
            // Defaults for fields stored in cache
            const defaults = {
                working_hours: {
                    monday: { enabled: true, open: '10:00', close: '23:00' },
                    tuesday: { enabled: true, open: '10:00', close: '23:00' },
                    wednesday: { enabled: true, open: '10:00', close: '23:00' },
                    thursday: { enabled: true, open: '10:00', close: '23:00' },
                    friday: { enabled: true, open: '10:00', close: '23:00' },
                    saturday: { enabled: true, open: '10:00', close: '23:00' },
                    sunday: { enabled: true, open: '10:00', close: '23:00' }
                },
                timezone: 'Europe/Moscow',
                currency: 'RUB',
                round_amounts: false
            };
            settings.value = {
                ...settings.value,
                ...s,
                working_hours: s.working_hours || defaults.working_hours,
                timezone: s.timezone || defaults.timezone,
                currency: s.currency || defaults.currency,
                round_amounts: s.round_amounts ?? defaults.round_amounts,
                business_day_ends_at: s.business_day_ends_at ?? 5
            };
        }
        if (res.integrations) integrations.value = res.integrations as typeof integrations.value;
        if (res.notifications) notifications.value = res.notifications as typeof notifications.value;
    } catch (e: any) {
        console.error('Failed to load settings:', e);
    }
}

async function saveSettings() {
    try {
        // Отправляем только поля, которые ожидает API
        const payload = {
            name: settings.value.name,
            address: settings.value.address,
            phone: settings.value.phone,
            email: settings.value.email,
            round_amounts: settings.value.round_amounts,
            working_hours: settings.value.working_hours,
            timezone: settings.value.timezone,
            currency: settings.value.currency,
            business_day_ends_at: settings.value.business_day_ends_at
        };
        await store.api('/backoffice/settings', {
            method: 'PUT',
            body: JSON.stringify(payload)
        });
        store.showToast('Настройки сохранены', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function saveNotifications() {
    try {
        await store.api('/backoffice/settings/notifications', {
            method: 'PUT',
            body: JSON.stringify(notifications.value)
        });
        store.showToast('Настройки уведомлений сохранены', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

function openIntegrationModal(type: any) {
    store.showToast('Настройка интеграций в разработке', 'info');
}

// Yandex Maps settings
async function loadYandexSettings() {
    try {
        const res = await store.api('/backoffice/settings/yandex') as Record<string, any>;
        if (res) {
            yandexForm.value = {
                enabled: res.enabled || false,
                api_key: res.api_key || '',
                city: res.city || '',
                restaurant_address: res.restaurant_address || '',
                restaurant_lat: res.restaurant_lat || '',
                restaurant_lng: res.restaurant_lng || ''
            };
            integrations.value.yandex = { enabled: res.enabled || false };
        }
    } catch (e: any) {
        console.error('Failed to load Yandex settings:', e);
    }
}

function openYandexModal() {
    yandexTestResult.value = null;
    geocodeAddressResult.value = null;
    showYandexModal.value = true;
}

async function geocodeRestaurantAddress() {
    if (!yandexForm.value.api_key || !yandexForm.value.restaurant_address) return;

    geocodingAddress.value = true;
    geocodeAddressResult.value = null;

    try {
        // Добавляем город к адресу если указан
        let address = yandexForm.value.restaurant_address;
        if (yandexForm.value.city && !address.toLowerCase().includes(yandexForm.value.city.toLowerCase())) {
            address = yandexForm.value.city + ', ' + address;
        }

        const res = await store.api('/backoffice/settings/yandex/geocode', {
            method: 'POST',
            body: JSON.stringify({
                address: address,
                api_key: yandexForm.value.api_key
            })
        }) as Record<string, any>;

        if (res.success && res.lat && res.lng) {
            yandexForm.value.restaurant_lat = String(res.lat);
            yandexForm.value.restaurant_lng = String(res.lng);
            geocodeAddressResult.value = {
                success: true,
                message: `Найдено: ${res.formatted_address || address}`
            };
        } else {
            geocodeAddressResult.value = {
                success: false,
                message: res.error || 'Адрес не найден'
            };
        }
    } catch (e: any) {
        geocodeAddressResult.value = {
            success: false,
            message: 'Ошибка геокодирования'
        };
    } finally {
        geocodingAddress.value = false;
    }
}

async function saveYandexSettings() {
    if (!yandexForm.value.api_key || !yandexForm.value.restaurant_lat || !yandexForm.value.restaurant_lng) return;

    try {
        await store.api('/backoffice/settings/yandex', {
            method: 'PUT',
            body: JSON.stringify(yandexForm.value)
        });

        integrations.value.yandex = { enabled: yandexForm.value.enabled };
        showYandexModal.value = false;
        store.showToast('Настройки Яндекс Карт сохранены', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function testYandexConnection() {
    if (!yandexForm.value.api_key) return;

    yandexTestingConnection.value = true;
    yandexTestResult.value = null;

    try {
        const res = await store.api('/backoffice/settings/yandex/test', {
            method: 'POST',
            body: JSON.stringify({ api_key: yandexForm.value.api_key })
        });

        yandexTestResult.value = {
            success: res.success,
            message: res.success ? 'Подключение успешно! Геокодер работает.' : (res.error || 'Ошибка подключения')
        };
    } catch (e: any) {
        yandexTestResult.value = {
            success: false,
            message: 'Ошибка проверки подключения'
        };
    } finally {
        yandexTestingConnection.value = false;
    }
}

// Printers
async function loadPrinters() {
    try {
        const res = await store.api('/backoffice/printers') as Record<string, any>;
        printers.value = res.printers || [];
    } catch (e: any) {
        console.error('Failed to load printers:', e);
    }
}

// Вспомогательные функции для отображения принтеров
function getPrinterIcon(type: any) {
    return ({
        receipt: '🧾',
        kitchen: '🍳',
        bar: '🍸',
        delivery: '🚗',
        label: '🏷️'
    } as Record<string, string>)[type] || '🖨️';
}

function getPrinterTypeLabel(type: any) {
    return ({
        receipt: 'Касса',
        kitchen: 'Кухня',
        bar: 'Бар',
        delivery: 'Доставка',
        label: 'Этикетки'
    } as Record<string, string>)[type] || type;
}

function getPrinterTypeClass(type: any) {
    return ({
        receipt: 'bg-blue-100',
        kitchen: 'bg-orange-100',
        bar: 'bg-purple-100',
        delivery: 'bg-green-100',
        label: 'bg-gray-100'
    } as Record<string, string>)[type] || 'bg-gray-100';
}

// Загрузка настроек печати
async function loadPrintSettings() {
    try {
        const res = await store.api('/settings/print');
        if (res.data) {
            // Объединяем загруженные данные с текущими дефолтами
            printSettings.value = {
                ...printSettings.value,
                ...res.data
            };
        }
    } catch (e: any) {
        console.error('Failed to load print settings:', e);
    }
}

// Сохранение настроек печати
async function savePrintSettings() {
    try {
        await store.api('/settings/print', {
            method: 'PUT',
            body: JSON.stringify(printSettings.value)
        });
        window.$toast?.('Настройки сохранены', 'success');
    } catch (e: any) {
        console.error('Failed to save print settings:', e);
        window.$toast?.('Ошибка сохранения', 'error');
    }
}

async function testPrintReceipt() {
    // Проверяем есть ли принтеры для чеков
    const receiptPrinter = printers.value.find((p: any) => p.type === 'receipt' && p.is_active);
    const kitchenPrinter = printers.value.find((p: any) => p.type === 'kitchen' && p.is_active);
    const deliveryPrinter = printers.value.find((p: any) => p.type === 'delivery' && p.is_active);

    let printerId = null;
    let testType = receiptSubTab.value;

    // Выбираем принтер в зависимости от типа чека
    if (testType === 'kitchen') {
        if (!kitchenPrinter) {
            window.$toast?.('Нет активного кухонного принтера', 'error');
            return;
        }
        printerId = kitchenPrinter.id;
    } else if (testType === 'delivery') {
        // Для доставки используем принтер доставки или кассовый
        const printer = deliveryPrinter || receiptPrinter;
        if (!printer) {
            window.$toast?.('Нет активного принтера для доставки', 'error');
            return;
        }
        printerId = printer.id;
    } else {
        if (!receiptPrinter) {
            window.$toast?.('Нет активного принтера чеков', 'error');
            return;
        }
        printerId = receiptPrinter.id;
    }

    testingPrint.value = true;
    try {
        // Сначала сохраняем настройки
        await store.api('/settings/print', {
            method: 'PUT',
            body: JSON.stringify(printSettings.value)
        });

        // Затем печатаем тестовый чек
        const response = await store.api(`/backoffice/printers/${printerId}/test-receipt`, {
            method: 'POST',
            body: JSON.stringify({ type: testType })
        });

        if (response?.success) {
            window.$toast?.('Тестовый чек отправлен на печать', 'success');
        } else {
            window.$toast?.(response?.message || 'Ошибка печати', 'error');
        }
    } catch (e: any) {
        console.error('Test print error:', e);
        window.$toast?.(e.message || 'Ошибка тестовой печати', 'error');
    } finally {
        testingPrint.value = false;
    }
}

function openPrinterModal(printer: any = null) {
    if (printer) {
        printerForm.value = {
            id: printer.id,
            name: printer.name || '',
            type: printer.type || 'receipt',
            kitchen_station_id: printer.kitchen_station_id || null,
            connection_type: printer.connection_type || 'network',
            ip_address: printer.ip_address || '',
            port: printer.port || 9100,
            device_path: printer.device_path || '',
            paper_width: printer.paper_width || 80,
            chars_per_line: printer.chars_per_line || 48,
            encoding: printer.encoding || 'cp866',
            cut_paper: printer.cut_paper ?? true,
            open_drawer: printer.open_drawer ?? false,
            print_logo: printer.print_logo ?? false,
            print_qr: printer.print_qr ?? true,
            is_active: printer.is_active ?? true,
            is_default: printer.is_default ?? false
        };
    } else {
        printerForm.value = {
            id: null as any,
            name: '',
            type: 'receipt',
            kitchen_station_id: null as any,
            connection_type: 'network',
            ip_address: '',
            port: 9100,
            device_path: '',
            paper_width: 80,
            chars_per_line: 48,
            encoding: 'cp866',
            cut_paper: true,
            open_drawer: false,
            print_logo: false,
            print_qr: true,
            is_active: true,
            is_default: false
        };
    }
    showPrinterModal.value = true;
}

// Computed для валидации формы принтера
const canSavePrinter = computed(() => {
    if (!printerForm.value.name) return false;
    if (printerForm.value.connection_type === 'network' && !printerForm.value.ip_address) return false;
    if (printerForm.value.connection_type === 'usb' && !printerForm.value.device_path) return false;
    return true;
});

// Автообновление символов в строке при смене ширины бумаги
function updateCharsPerLine() {
    printerForm.value.chars_per_line = printerForm.value.paper_width === 80 ? 48 : 32;
}

async function savePrinter() {
    if (!canSavePrinter.value) return;

    try {
        const url = printerForm.value.id
            ? `/backoffice/printers/${printerForm.value.id}`
            : '/backoffice/printers';
        const method = printerForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(printerForm.value)
        });

        showPrinterModal.value = false;
        loadPrinters();
        store.showToast('Принтер сохранён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deletePrinter(printer: any) {
    if (!confirm(`Удалить принтер "${printer.name}"?`)) return;

    try {
        await store.api(`/backoffice/printers/${printer.id}`, { method: 'DELETE' });
        loadPrinters();
        store.showToast('Принтер удалён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function testPrinter(printer: any) {
    try {
        store.showToast('Отправка тестовой печати...', 'info');
        const res = await store.api(`/backoffice/printers/${printer.id}/test`, { method: 'POST' });
        store.showToast(res.message || 'Тестовая печать отправлена', 'success');
    } catch (e: any) {
        console.error('Print error object:', e);
        console.error('Print error response:', e.response);
        console.error('Print error data:', e.response?.data);

        // Пробуем получить данные из разных мест
        let errorData = e.response?.data || e.data || {};

        // Если ответ - строка, пробуем распарсить как JSON
        if (typeof errorData === 'string') {
            try {
                errorData = JSON.parse(errorData);
            } catch (parseErr: any) {
                console.error('Failed to parse error response:', errorData);
            }
        }

        const message = errorData.message || e.message || 'Ошибка печати';
        const debug = errorData.debug;

        console.error('Error message:', message);
        console.error('Error debug:', debug);

        if (debug) {
            console.error('=== PRINT DEBUG LOG ===');
            console.error(debug);
            console.error('=== END DEBUG LOG ===');

            // Показываем первые 300 символов debug в toast
            const shortDebug = debug.substring(0, 300);
            store.showToast(`${message}\n\n${shortDebug}...`, 'error');
        } else {
            store.showToast(message, 'error');
        }
    }
}

async function scanSystemPrinters() {
    scanningPrinters.value = true;
    scannedOnce.value = true;
    try {
        const res = await store.api('/backoffice/printers/system') as Record<string, any>;
        systemPrinters.value = res.printers || [];
        store.showToast(res.message || 'Сканирование завершено', 'success');
    } catch (e: any) {
        console.error('Failed to scan printers:', e);
        store.showToast('Ошибка сканирования принтеров', 'error');
    } finally {
        scanningPrinters.value = false;
    }
}

function useSystemPrinter(sp: any) {
    // Открываем модал добавления принтера с заполненным именем
    printerForm.value = {
        id: null as any,
        name: sp.Name,
        type: 'receipt',
        kitchen_station_id: null as any,
        connection_type: 'usb',
        ip_address: '',
        port: 9100,
        device_path: sp.Name, // Используем точное имя принтера из Windows
        paper_width: 58,
        chars_per_line: 32,
        encoding: 'cp866',
        cut_paper: true,
        open_drawer: false,
        print_logo: false,
        print_qr: false,
        is_active: true,
        is_default: false
    };
    showPrinterModal.value = true;
}

// Stations
async function loadStations() {
    try {
        const res = await store.api('/kitchen-stations') as Record<string, any>;
        stations.value = res.data || [];
    } catch (e: any) {
        console.error('Failed to load stations:', e);
    }
}

function openStationModal(station: any = null) {
    if (station) {
        stationForm.value = { ...station };
    } else {
        stationForm.value = {
            id: null as any,
            name: '',
            slug: '',
            icon: '🔥',
            color: '#6366F1',
            description: '',
            notification_sound: 'bell',
            sort_order: stations.value.length,
            is_active: true,
            is_bar: false
        };
    }
    showStationModal.value = true;
}

function generateSlug() {
    if (!stationForm.value.id) {
        const translitMap: Record<string, string> = {
            'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
            'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
            'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
            'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ъ': '',
            'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya', ' ': '-'
        };
        stationForm.value.slug = stationForm.value.name
            .toLowerCase()
            .split('')
            .map((c: any) => translitMap[c] || c)
            .join('')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-+/g, '-');
    }
}

async function saveStation() {
    if (!stationForm.value.name) return;

    try {
        const url = stationForm.value.id
            ? `/kitchen-stations/${stationForm.value.id}`
            : '/kitchen-stations';
        const method = stationForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(stationForm.value)
        });

        showStationModal.value = false;
        loadStations();
        store.showToast('Цех сохранён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteStation(station: any) {
    if (!confirm(`Удалить цех "${station.name}"? Блюда этого цеха станут доступны на всех дисплеях.`)) return;

    try {
        await store.api(`/kitchen-stations/${station.id}`, { method: 'DELETE' });
        loadStations();
        store.showToast('Цех удалён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function toggleStation(station: any) {
    try {
        await store.api(`/kitchen-stations/${station.id}/toggle`, { method: 'PATCH' });
        loadStations();
        store.showToast(station.is_active ? 'Цех деактивирован' : 'Цех активирован', 'success');
    } catch (e: any) {
        store.showToast('Ошибка', 'error');
    }
}

function copyKitchenUrl(station: any) {
    const url = `${window.location.origin}/kitchen?station=${station.slug}`;
    navigator.clipboard.writeText(url);
    store.showToast('URL скопирован', 'success');
}

// Web Audio API Synthesizer для качественных звуков
let settingsAudioContext: any = null;

function getSettingsAudioContext() {
    if (!settingsAudioContext) {
        settingsAudioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    return settingsAudioContext;
}

// Синтезируем различные звуки уведомлений
function playStationSound(type: any) {
    const ctx = getSettingsAudioContext();
    const now = ctx.currentTime;

    switch (type) {
        case 'bell': {
            // Классический сервисный колокольчик с гармониками
            const fundamental = 880;
            [1, 2, 3, 4.2, 5.4].forEach((harmonic: any, i: any) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = fundamental * harmonic;
                gain.gain.setValueAtTime(0.3 / (i + 1), now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 1.5);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 1.5);
            });
            break;
        }
        case 'chime': {
            // Мелодичный перезвон - 3 ноты мажорного аккорда
            const notes = [523.25, 659.25, 783.99]; // C5, E5, G5
            notes.forEach((freq: any, i: any) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                const startTime = now + i * 0.15;
                gain.gain.setValueAtTime(0, startTime);
                gain.gain.linearRampToValueAtTime(0.25, startTime + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + 1.0);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(startTime);
                osc.stop(startTime + 1.0);
            });
            break;
        }
        case 'ding': {
            // Яркий одиночный звук с обертоном
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gain = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.value = 1046.5; // C6
            osc2.type = 'sine';
            osc2.frequency.value = 2093; // C7 (октава выше)
            gain.gain.setValueAtTime(0.35, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(ctx.destination);
            osc1.start(now);
            osc2.start(now);
            osc1.stop(now + 0.8);
            osc2.stop(now + 0.8);
            break;
        }
        case 'kitchen': {
            // Двойной звонок дин-дин
            [0, 0.2].forEach((delay: any) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.value = 740; // F#5
                const startTime = now + delay;
                gain.gain.setValueAtTime(0.3, startTime);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(startTime);
                osc.stop(startTime + 0.3);
            });
            break;
        }
        case 'alert': {
            // Двухтональный приятный сигнал
            const frequencies = [587.33, 783.99]; // D5, G5
            frequencies.forEach((freq: any, i: any) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                const startTime = now + i * 0.12;
                gain.gain.setValueAtTime(0.25, startTime);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.4);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(startTime);
                osc.stop(startTime + 0.4);
            });
            break;
        }
        case 'gong': {
            // Глубокий гонг с длинным затуханием
            const fundamental = 110; // A2
            [1, 2.4, 3.5, 4.7].forEach((harmonic: any, i: any) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = fundamental * harmonic;
                gain.gain.setValueAtTime(0.2 / (i + 1), now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 3.0);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 3.0);
            });
            break;
        }
        default: {
            // Fallback - простой beep
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.3, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.5);
        }
    }
}

// Devices
async function loadDevices() {
    try {
        const res = await store.api('/kitchen-devices') as Record<string, any>;
        devices.value = res.data || [];
    } catch (e: any) {
        console.error('Failed to load devices:', e);
    }
}

function openCreateDeviceModal() {
    deviceForm.value = {
        id: null as any,
        device_id: null as any,
        name: '',
        kitchen_station_id: null as any,
        status: 'pending',
        pin: '',
        ip_address: null as any,
        last_seen_at: null as any
    };
    showDeviceModal.value = true;
}

function openDeviceModal(device: any) {
    deviceForm.value = {
        id: device.id,
        device_id: device.device_id,
        name: device.name,
        kitchen_station_id: device.kitchen_station_id,
        status: device.status,
        pin: device.has_pin ? '******' : '',
        ip_address: device.ip_address,
        last_seen_at: device.last_seen_at
    };
    showDeviceModal.value = true;
}

async function saveDevice() {
    try {
        const data: Record<string, any> = {
            name: deviceForm.value.name,
            kitchen_station_id: deviceForm.value.kitchen_station_id,
        };

        // Only send PIN if it was changed (not the masked value)
        if (deviceForm.value.pin && deviceForm.value.pin !== '******') {
            data.pin = deviceForm.value.pin;
        } else if (!deviceForm.value.pin) {
            data.pin = null;
        }

        if (deviceForm.value.id) {
            // Update existing device
            data.status = deviceForm.value.status;
            await store.api(`/kitchen-devices/${deviceForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
            store.showToast('Устройство обновлено', 'success');
        } else {
            // Create new device
            await store.api('/kitchen-devices', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            store.showToast('Устройство создано', 'success');
        }

        showDeviceModal.value = false;
        loadDevices();
    } catch (e: any) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteDevice(device: any) {
    if (!confirm(`Удалить устройство "${device.name}"?`)) return;

    try {
        await store.api(`/kitchen-devices/${device.id}`, { method: 'DELETE' });
        loadDevices();
        store.showToast('Устройство удалено', 'success');
    } catch (e: any) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function regenerateLinkingCode(device: any) {
    try {
        await store.api(`/kitchen-devices/${device.id}/regenerate-code`, {
            method: 'POST'
        });
        loadDevices();
        store.showToast('Код обновлён', 'success');
    } catch (e: any) {
        store.showToast('Ошибка обновления кода', 'error');
    }
}

function copyLinkingCode(device: any) {
    if (device.linking_code?.code) {
        navigator.clipboard.writeText(device.linking_code.code);
        store.showToast('Код скопирован', 'success');
    }
}

async function unlinkDevice(device: any) {
    if (!confirm(`Отвязать устройство "${device.name}"?\n\nПосле отвязки потребуется заново ввести код на планшете.`)) return;

    try {
        await store.api(`/kitchen-devices/${device.id}/unlink`, {
            method: 'POST'
        });
        loadDevices();
        store.showToast('Устройство отвязано', 'success');
    } catch (e: any) {
        store.showToast('Ошибка отвязки', 'error');
    }
}

function isDeviceOnline(device: any) {
    if (!device.last_seen_at) return false;
    const lastSeen = new Date(device.last_seen_at);
    const now = new Date();
    const diffMinutes = (now.getTime() - lastSeen.getTime()) / 1000 / 60;
    return diffMinutes < 5; // Online if seen in last 5 minutes
}

function formatDate(dateStr: any) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMinutes = Math.floor((now.getTime() - date.getTime()) / 1000 / 60);

    if (diffMinutes < 1) return 'только что';
    if (diffMinutes < 60) return `${diffMinutes} мин. назад`;
    if (diffMinutes < 1440) {
        const hours = Math.floor(diffMinutes / 60);
        return `${hours} ч. назад`;
    }

    return date.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Форматирование даты для превью чеков
function formatReceiptDate(date: any) {
    return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatReceiptDateTime(date: any) {
    return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU');
}

// Init
// === Location Management ===
async function loadLocations() {
    try {
        const res = await store.api('/tenant/restaurants') as Record<string, any>;
        if (res.data) {
            locations.value = res.data;
        }
    } catch (e: any) {
        console.error('Failed to load locations:', e);
    }
}

function openLocationModal(loc: any = null) {
    if (loc) {
        locationForm.value = {
            id: loc.id,
            name: loc.name || '',
            address: loc.address || '',
            phone: loc.phone || '',
            email: loc.email || '',
            is_active: loc.is_active !== false
        };
    } else {
        locationForm.value = {
            id: null as any,
            name: '',
            address: '',
            phone: '',
            email: '',
            is_active: true
        };
    }
    showLocationModal.value = true;
}

async function saveLocation() {
    if (!locationForm.value.name) return;

    savingLocation.value = true;
    try {
        if (locationForm.value.id) {
            // Update existing
            await store.api(`/tenant/restaurants/${locationForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    name: locationForm.value.name,
                    address: locationForm.value.address,
                    phone: locationForm.value.phone,
                    email: locationForm.value.email,
                    is_active: locationForm.value.is_active
                })
            });
            store.showToast('Точка обновлена', 'success');
        } else {
            // Create new
            const res = await store.api('/tenant/restaurants', {
                method: 'POST',
                body: JSON.stringify({
                    name: locationForm.value.name,
                    address: locationForm.value.address,
                    phone: locationForm.value.phone,
                    email: locationForm.value.email
                })
            });
            if (res.upgrade_required) {
                locationLimitReached.value = true;
                store.showToast('Достигнут лимит точек для вашего тарифа', 'error');
                return;
            }
            store.showToast('Точка создана', 'success');
        }
        showLocationModal.value = false;
        await loadLocations();
        await store.loadRestaurants();
    } catch (e: any) {
        if (e.message?.includes('лимит') || e.message?.includes('upgrade')) {
            locationLimitReached.value = true;
        }
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        savingLocation.value = false;
    }
}

async function deleteLocation(loc: any) {
    if (!confirm(`Удалить точку "${loc.name}"? Это действие нельзя отменить.`)) return;

    try {
        await store.api(`/tenant/restaurants/${loc.id}`, { method: 'DELETE' });
        store.showToast('Точка удалена', 'success');
        await loadLocations();
        await store.loadRestaurants();
    } catch (e: any) {
        store.showToast(e.message || 'Ошибка удаления', 'error');
    }
}

async function makeMainLocation(loc: any) {
    if (!confirm(`Сделать "${loc.name}" главной точкой?`)) return;

    try {
        await store.api(`/tenant/restaurants/${loc.id}/make-main`, { method: 'POST' });
        store.showToast('Главная точка изменена', 'success');
        await loadLocations();
        await store.loadRestaurants();
    } catch (e: any) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

async function switchLocation(loc: any) {
    try {
        await store.switchRestaurant(loc.id);
        await loadLocations();
        store.showToast(`Переключено на ${loc.name}`, 'success');
    } catch (e: any) {
        store.showToast(e.message || 'Ошибка переключения', 'error');
    }
}

// Legal Entity methods
async function loadLegalEntities() {
    try {
        const res = await store.api('/legal-entities') as Record<string, any>;
        if (res.data) {
            legalEntities.value = res.data;
        }
    } catch (e: any) {
        console.error('Failed to load legal entities:', e);
    }
}

function getLocationLegalEntities(locationId: any) {
    return legalEntities.value.filter((e: any) => e.restaurant_id === locationId);
}

function toggleLocationLegalEntities(locationId: any) {
    if (expandedLocationEntities.value.has(locationId)) {
        expandedLocationEntities.value.delete(locationId);
    } else {
        expandedLocationEntities.value.add(locationId);
    }
}

function openLegalEntityModal(restaurantId: any, entity: any = null) {
    if (entity) {
        legalEntityForm.value = { ...entity };
    } else {
        legalEntityForm.value = {
            id: null as any,
            restaurant_id: restaurantId,
            name: '',
            short_name: '',
            type: 'llc',
            inn: '',
            kpp: '',
            ogrn: '',
            legal_address: '',
            actual_address: '',
            director_name: '',
            director_position: '',
            bank_name: '',
            bank_bik: '',
            bank_account: '',
            bank_corr_account: '',
            taxation_system: 'usn_income',
            vat_rate: null as any,
            has_alcohol_license: false,
            alcohol_license_number: '',
            alcohol_license_expires_at: null as any,
            is_active: true,
            is_default: false
        };
    }
    showLegalEntityModal.value = true;
}

async function saveLegalEntity() {
    if (!legalEntityForm.value.name || !legalEntityForm.value.inn) return;

    savingLegalEntity.value = true;
    try {
        const method = legalEntityForm.value.id ? 'PUT' : 'POST';
        const endpoint = legalEntityForm.value.id
            ? `/legal-entities/${legalEntityForm.value.id}`
            : '/legal-entities';

        await store.api(endpoint, {
            method,
            body: JSON.stringify(legalEntityForm.value)
        });

        store.showToast(legalEntityForm.value.id ? 'Юрлицо обновлено' : 'Юрлицо создано', 'success');
        showLegalEntityModal.value = false;
        await loadLegalEntities();
    } catch (e: any) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        savingLegalEntity.value = false;
    }
}

async function deleteLegalEntity(entity: any) {
    if (!confirm(`Удалить юрлицо "${entity.name}"?`)) return;

    try {
        await store.api(`/legal-entities/${entity.id}`, { method: 'DELETE' });
        store.showToast('Юрлицо удалено', 'success');
        await loadLegalEntities();
    } catch (e: any) {
        store.showToast(e.message || 'Ошибка удаления', 'error');
    }
}

onMounted(() => {
    loadSettings();
    loadPrinters();
    loadPrintSettings();
    loadStations();
    loadDevices();
    loadYandexSettings();
    loadLocations();
    loadLegalEntities();
});
</script>

<style scoped>
.receipt-preview {
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 3%, #ffffff 97%, #fafafa 100%);
    box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
}

.receipt-preview .font-mono {
    font-family: 'Courier New', Courier, monospace;
    letter-spacing: -0.5px;
}
</style>
