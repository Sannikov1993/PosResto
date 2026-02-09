<template>
    <div class="h-full flex flex-col bg-dark-950" data-testid="warehouse-tab">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-white">Склад</h1>
                <!-- Sub-tabs -->
                <div class="flex bg-dark-800 rounded-lg p-1">
                    <button
                        v-for="tab in subTabs"
                        :key="tab.id"
                        @click="activeSubTab = tab.id"
                        :class="[
                            'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                            activeSubTab === tab.id
                                ? 'bg-accent text-white'
                                : 'text-gray-400 hover:text-white'
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <!-- Загрузка фото накладной -->
                <button
                    v-if="activeSubTab === 'invoices'"
                    @click="openPhotoUpload"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                    title="Загрузить фото накладной"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Загрузить фото
                </button>
                <input
                    ref="photoInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handlePhotoUpload"
                />
                <button
                    v-if="activeSubTab === 'invoices'"
                    @click="openInvoiceModal()"
                    class="px-4 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Новая накладная
                </button>
                <button
                    v-if="activeSubTab === 'inventory'"
                    @click="openInventoryCheckModal()"
                    class="px-4 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Новая инвентаризация
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-hidden">
            <!-- Invoices Tab -->
            <div v-if="activeSubTab === 'invoices'" class="h-full flex flex-col">
                <!-- Filters -->
                <div class="flex items-center gap-4 px-6 py-3 border-b border-gray-800">
                    <select
                        v-model="invoiceFilter.status"
                        class="bg-dark-800 text-white rounded-lg px-3 py-2 text-sm border border-gray-700 focus:border-accent focus:outline-none"
                    >
                        <option value="">Все статусы</option>
                        <option value="draft">Черновики</option>
                        <option value="completed">Проведённые</option>
                        <option value="cancelled">Отменённые</option>
                    </select>
                    <select
                        v-model="invoiceFilter.warehouse_id"
                        class="bg-dark-800 text-white rounded-lg px-3 py-2 text-sm border border-gray-700 focus:border-accent focus:outline-none"
                    >
                        <option value="">Все склады</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <button
                        @click="loadInvoices"
                        class="px-3 py-2 bg-dark-800 hover:bg-dark-700 text-gray-400 hover:text-white rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>

                <!-- Invoices Table -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="loadingInvoices" class="flex items-center justify-center h-64">
                        <div class="animate-spin w-8 h-8 border-2 border-accent border-t-transparent rounded-full"></div>
                    </div>
                    <div v-else-if="invoices.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-500">
                        <svg class="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-lg">Накладных пока нет</p>
                        <p class="text-sm">Создайте первую накладную</p>
                    </div>
                    <table v-else class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500 text-sm border-b border-gray-800">
                                <th class="pb-3 font-medium">№</th>
                                <th class="pb-3 font-medium">Дата</th>
                                <th class="pb-3 font-medium">Поставщик</th>
                                <th class="pb-3 font-medium">Склад</th>
                                <th class="pb-3 font-medium text-right">Сумма</th>
                                <th class="pb-3 font-medium">Статус</th>
                                <th class="pb-3 font-medium text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                class="border-b border-gray-800/50 hover:bg-dark-800/50 transition-colors"
                            >
                                <td class="py-4 text-white font-medium">#{{ invoice.number || invoice.id }}</td>
                                <td class="py-4 text-gray-400">{{ formatDate(invoice.created_at) }}</td>
                                <td class="py-4 text-white">{{ invoice.supplier?.name || '-' }}</td>
                                <td class="py-4 text-gray-400">{{ invoice.warehouse?.name || '-' }}</td>
                                <td class="py-4 text-white text-right font-medium">{{ formatMoney(invoice.total_amount) }} ₽</td>
                                <td class="py-4">
                                    <span :class="getStatusClass(invoice.status)">
                                        {{ getStatusLabel(invoice.status) }}
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="viewInvoice(invoice)"
                                            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
                                            title="Просмотр"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="invoice.status === 'draft'"
                                            @click="completeInvoice(invoice)"
                                            class="p-2 text-green-400 hover:text-green-300 hover:bg-green-500/10 rounded-lg transition-colors"
                                            title="Провести"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="invoice.status === 'draft'"
                                            @click="cancelInvoice(invoice)"
                                            class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors"
                                            title="Отменить"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory Checks Tab -->
            <div v-if="activeSubTab === 'inventory'" class="h-full flex flex-col">
                <!-- Filters -->
                <div class="flex items-center gap-4 px-6 py-3 border-b border-gray-800">
                    <select
                        v-model="checkFilter.status"
                        class="bg-dark-800 text-white rounded-lg px-3 py-2 text-sm border border-gray-700 focus:border-accent focus:outline-none"
                    >
                        <option value="">Все статусы</option>
                        <option value="in_progress">В процессе</option>
                        <option value="completed">Завершённые</option>
                        <option value="cancelled">Отменённые</option>
                    </select>
                    <select
                        v-model="checkFilter.warehouse_id"
                        class="bg-dark-800 text-white rounded-lg px-3 py-2 text-sm border border-gray-700 focus:border-accent focus:outline-none"
                    >
                        <option value="">Все склады</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <button
                        @click="loadInventoryChecks"
                        class="px-3 py-2 bg-dark-800 hover:bg-dark-700 text-gray-400 hover:text-white rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>

                <!-- Inventory Checks Table -->
                <div class="flex-1 overflow-auto p-6">
                    <div v-if="loadingChecks" class="flex items-center justify-center h-64">
                        <div class="animate-spin w-8 h-8 border-2 border-accent border-t-transparent rounded-full"></div>
                    </div>
                    <div v-else-if="inventoryChecks.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-500">
                        <svg class="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <p class="text-lg">Инвентаризаций пока нет</p>
                        <p class="text-sm">Создайте первую инвентаризацию</p>
                    </div>
                    <table v-else class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500 text-sm border-b border-gray-800">
                                <th class="pb-3 font-medium">№</th>
                                <th class="pb-3 font-medium">Дата</th>
                                <th class="pb-3 font-medium">Склад</th>
                                <th class="pb-3 font-medium text-center">Позиций</th>
                                <th class="pb-3 font-medium text-right">Расхождение</th>
                                <th class="pb-3 font-medium">Статус</th>
                                <th class="pb-3 font-medium text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="check in inventoryChecks"
                                :key="check.id"
                                class="border-b border-gray-800/50 hover:bg-dark-800/50 transition-colors"
                            >
                                <td class="py-4 text-white font-medium">#{{ check.id }}</td>
                                <td class="py-4 text-gray-400">{{ formatDate(check.created_at) }}</td>
                                <td class="py-4 text-white">{{ check.warehouse?.name || '-' }}</td>
                                <td class="py-4 text-gray-400 text-center">{{ check.items_count || check.items?.length || 0 }}</td>
                                <td class="py-4 text-right">
                                    <span :class="getDifferenceClass(check.total_difference)">
                                        {{ formatDifference(check.total_difference) }}
                                    </span>
                                </td>
                                <td class="py-4">
                                    <span :class="getCheckStatusClass(check.status)">
                                        {{ getCheckStatusLabel(check.status) }}
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="viewInventoryCheck(check)"
                                            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
                                            title="Открыть"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="check.status === 'in_progress'"
                                            @click="completeInventoryCheck(check)"
                                            class="p-2 text-green-400 hover:text-green-300 hover:bg-green-500/10 rounded-lg transition-colors"
                                            title="Завершить"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="check.status === 'in_progress'"
                                            @click="cancelInventoryCheck(check)"
                                            class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors"
                                            title="Отменить"
                                        >
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Invoice Modal -->
        <div
            v-if="showInvoiceModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50"
            @click.self="showInvoiceModal = false"
        >
            <div class="bg-dark-900 rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <h2 class="text-xl font-bold text-white">
                        {{ editingInvoice ? `Накладная #${editingInvoice.number || editingInvoice.id}` : 'Новая накладная' }}
                    </h2>
                    <button
                        @click="showInvoiceModal = false"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-auto p-6">
                    <!-- Invoice Info -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Поставщик</label>
                            <select
                                v-model="invoiceForm.supplier_id"
                                :disabled="editingInvoice?.status !== 'draft' && editingInvoice"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-3 border border-gray-700 focus:border-accent focus:outline-none disabled:opacity-50"
                            >
                                <option value="">Выберите поставщика</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Склад</label>
                            <select
                                v-model="invoiceForm.warehouse_id"
                                :disabled="editingInvoice?.status !== 'draft' && editingInvoice"
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-3 border border-gray-700 focus:border-accent focus:outline-none disabled:opacity-50"
                            >
                                <option value="">Выберите склад</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-medium text-white">Позиции</h3>
                            <button
                                v-if="!editingInvoice || editingInvoice.status === 'draft'"
                                @click="addInvoiceItem"
                                class="px-3 py-1.5 bg-accent/20 text-accent hover:bg-accent/30 rounded-lg text-sm font-medium transition-colors"
                            >
                                + Добавить позицию
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(item, index) in invoiceForm.items"
                                :key="index"
                                class="flex items-center gap-3 bg-dark-800 rounded-lg p-3"
                            >
                                <div class="flex-1">
                                    <select
                                        v-model="item.ingredient_id"
                                        :disabled="editingInvoice?.status !== 'draft' && editingInvoice"
                                        class="w-full bg-dark-700 text-white rounded-lg px-3 py-2 text-sm border border-gray-600 focus:border-accent focus:outline-none disabled:opacity-50"
                                    >
                                        <option value="">Выберите ингредиент</option>
                                        <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                            {{ ing.name }} ({{ ing.unit?.abbreviation || 'шт' }})
                                        </option>
                                    </select>
                                </div>
                                <div class="w-32">
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        placeholder="Кол-во"
                                        :disabled="editingInvoice?.status !== 'draft' && editingInvoice"
                                        class="w-full bg-dark-700 text-white rounded-lg px-3 py-2 text-sm border border-gray-600 focus:border-accent focus:outline-none disabled:opacity-50"
                                    />
                                </div>
                                <div class="w-32">
                                    <input
                                        v-model.number="item.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Цена"
                                        :disabled="editingInvoice?.status !== 'draft' && editingInvoice"
                                        class="w-full bg-dark-700 text-white rounded-lg px-3 py-2 text-sm border border-gray-600 focus:border-accent focus:outline-none disabled:opacity-50"
                                    />
                                </div>
                                <div class="w-28 text-right text-white font-medium">
                                    {{ formatMoney((item.quantity || 0) * (item.price || 0)) }} ₽
                                </div>
                                <button
                                    v-if="!editingInvoice || editingInvoice.status === 'draft'"
                                    @click="removeInvoiceItem(index)"
                                    class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <div v-if="invoiceForm.items.length === 0" class="text-center text-gray-500 py-8">
                                Добавьте позиции в накладную
                            </div>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="flex justify-end border-t border-gray-800 pt-4">
                        <div class="text-right">
                            <span class="text-gray-400">Итого:</span>
                            <span class="ml-3 text-2xl font-bold text-white">{{ formatMoney(invoiceTotal) }} ₽</span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-800">
                    <button
                        @click="showInvoiceModal = false"
                        class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                    >
                        Закрыть
                    </button>
                    <button
                        v-if="!editingInvoice"
                        @click="createInvoice"
                        :disabled="savingInvoice"
                        class="px-6 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                    >
                        {{ savingInvoice ? 'Сохранение...' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Inventory Check Modal -->
        <div
            v-if="showCheckModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50"
            @click.self="showCheckModal = false"
        >
            <div class="bg-dark-900 rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <h2 class="text-xl font-bold text-white">
                        {{ editingCheck ? `Инвентаризация #${editingCheck.id}` : 'Новая инвентаризация' }}
                    </h2>
                    <button
                        @click="showCheckModal = false"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-auto p-6">
                    <!-- Check Info (for new) -->
                    <div v-if="!editingCheck" class="mb-6">
                        <label class="block text-sm text-gray-400 mb-2">Склад</label>
                        <select
                            v-model="checkForm.warehouse_id"
                            class="w-full max-w-md bg-dark-800 text-white rounded-lg px-4 py-3 border border-gray-700 focus:border-accent focus:outline-none"
                        >
                            <option value="">Выберите склад</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>

                    <!-- Check Items (for editing) -->
                    <div v-if="editingCheck">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="text-gray-400">Склад:</span>
                                <span class="ml-2 text-white font-medium">{{ editingCheck.warehouse?.name }}</span>
                            </div>
                            <div v-if="editingCheck.status === 'in_progress'">
                                <button
                                    @click="addCheckItem"
                                    class="px-3 py-1.5 bg-accent/20 text-accent hover:bg-accent/30 rounded-lg text-sm font-medium transition-colors"
                                >
                                    + Добавить позицию
                                </button>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-gray-500 text-sm border-b border-gray-800">
                                    <th class="pb-3 font-medium">Ингредиент</th>
                                    <th class="pb-3 font-medium text-center">Ед.</th>
                                    <th class="pb-3 font-medium text-right">Ожидаемое</th>
                                    <th class="pb-3 font-medium text-right">Фактическое</th>
                                    <th class="pb-3 font-medium text-right">Разница</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in checkItems"
                                    :key="item.id"
                                    class="border-b border-gray-800/50"
                                >
                                    <td class="py-3 text-white">{{ item.ingredient?.name || '-' }}</td>
                                    <td class="py-3 text-gray-400 text-center">{{ item.ingredient?.unit?.abbreviation || 'шт' }}</td>
                                    <td class="py-3 text-gray-400 text-right">{{ formatQuantity(item.expected_quantity) }}</td>
                                    <td class="py-3 text-right">
                                        <input
                                            v-if="editingCheck.status === 'in_progress'"
                                            v-model.number="item.actual_quantity"
                                            @change="updateCheckItem(item)"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            class="w-28 bg-dark-700 text-white rounded-lg px-3 py-1.5 text-sm text-right border border-gray-600 focus:border-accent focus:outline-none"
                                        />
                                        <span v-else class="text-white">{{ formatQuantity(item.actual_quantity) }}</span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <span :class="getDifferenceClass(item.actual_quantity - item.expected_quantity)">
                                            {{ formatDifference(item.actual_quantity - item.expected_quantity) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="checkItems.length === 0" class="text-center text-gray-500 py-8">
                            Нет позиций для инвентаризации
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-800">
                    <button
                        @click="showCheckModal = false"
                        class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                    >
                        Закрыть
                    </button>
                    <button
                        v-if="!editingCheck"
                        @click="createInventoryCheck"
                        :disabled="savingCheck || !checkForm.warehouse_id"
                        class="px-6 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                    >
                        {{ savingCheck ? 'Создание...' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Check Item Modal -->
        <div
            v-if="showAddItemModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50"
            @click.self="showAddItemModal = false"
        >
            <div class="bg-dark-900 rounded-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-bold text-white mb-4">Добавить позицию</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Ингредиент</label>
                        <select
                            v-model="newCheckItem.ingredient_id"
                            class="w-full bg-dark-800 text-white rounded-lg px-4 py-3 border border-gray-700 focus:border-accent focus:outline-none"
                        >
                            <option value="">Выберите ингредиент</option>
                            <option v-for="ing in availableIngredients" :key="ing.id" :value="ing.id">
                                {{ ing.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Фактическое количество</label>
                        <input
                            v-model.number="newCheckItem.actual_quantity"
                            type="number"
                            step="0.001"
                            min="0"
                            class="w-full bg-dark-800 text-white rounded-lg px-4 py-3 border border-gray-700 focus:border-accent focus:outline-none"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button
                        @click="showAddItemModal = false"
                        class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                    >
                        Отмена
                    </button>
                    <button
                        @click="saveNewCheckItem"
                        :disabled="!newCheckItem.ingredient_id"
                        class="px-6 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                    >
                        Добавить
                    </button>
                </div>
            </div>
        </div>

        <!-- Photo Recognition Modal -->
        <div
            v-if="showRecognitionModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50"
            @click.self="showRecognitionModal = false"
        >
            <div class="bg-dark-900 rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-600/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-white">Распознавание накладной</h2>
                            <p class="text-sm text-gray-400">Проверьте распознанные позиции</p>
                        </div>
                    </div>
                    <button
                        @click="showRecognitionModal = false"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-auto p-6">
                    <!-- Loading -->
                    <div v-if="recognizing" class="flex flex-col items-center justify-center py-16">
                        <div class="animate-spin w-12 h-12 border-3 border-blue-500 border-t-transparent rounded-full mb-4"></div>
                        <p class="text-white text-lg">Распознавание...</p>
                        <p class="text-gray-400 text-sm mt-1">Yandex Vision анализирует изображение</p>
                    </div>

                    <!-- Results -->
                    <div v-else-if="recognizedItems.length > 0">
                        <!-- Stats & Actions -->
                        <div class="flex items-center justify-between gap-4 mb-4 p-4 bg-dark-800 rounded-lg">
                            <div class="flex items-center gap-6">
                                <div>
                                    <span class="text-gray-400">Найдено:</span>
                                    <span class="ml-2 text-white font-medium">{{ recognizedItems.length }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Совпало:</span>
                                    <span class="ml-2 text-green-400 font-medium">{{ recognizedItems.filter(i => i.match_score >= 70).length }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Сомнительно:</span>
                                    <span class="ml-2 text-yellow-400 font-medium">{{ recognizedItems.filter(i => i.match_score >= 40 && i.match_score < 70).length }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Не найдено:</span>
                                    <span class="ml-2 text-red-400 font-medium">{{ recognizedItems.filter(i => i.match_score < 40).length }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer text-sm">
                                    <input
                                        type="checkbox"
                                        :checked="recognizedItems.filter(i => i.match_score >= 70).every(i => i.included)"
                                        @change="selectAllMatched($event.target.checked)"
                                        class="w-4 h-4 rounded border-gray-600 bg-dark-700 text-accent focus:ring-accent"
                                    />
                                    <span class="text-gray-300">Выбрать все совпавшие</span>
                                </label>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="mb-4">
                            <input
                                v-model="ingredientSearch"
                                type="text"
                                placeholder="Поиск по ингредиентам..."
                                class="w-full bg-dark-800 text-white rounded-lg px-4 py-2 text-sm border border-gray-700 focus:border-accent focus:outline-none"
                            />
                        </div>

                        <!-- Items Table -->
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-gray-500 text-sm border-b border-gray-800">
                                    <th class="pb-3 font-medium w-8"></th>
                                    <th class="pb-3 font-medium">Распознано</th>
                                    <th class="pb-3 font-medium">Ингредиент в базе</th>
                                    <th class="pb-3 font-medium text-center w-24">Кол-во</th>
                                    <th class="pb-3 font-medium text-right w-28">Цена</th>
                                    <th class="pb-3 font-medium text-center w-28">Совпадение</th>
                                    <th class="pb-3 font-medium w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in filteredRecognizedItems"
                                    :key="index"
                                    :class="[
                                        'border-b border-gray-800/50 transition-colors',
                                        item.included ? 'bg-dark-800/30' : 'opacity-50'
                                    ]"
                                >
                                    <td class="py-3">
                                        <input
                                            type="checkbox"
                                            v-model="item.included"
                                            class="w-4 h-4 rounded border-gray-600 bg-dark-700 text-accent focus:ring-accent"
                                        />
                                    </td>
                                    <td class="py-3">
                                        <div class="text-white font-medium">{{ item.recognized_name }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5" :title="item.raw_line">{{ item.unit }} | {{ item.raw_line?.substring(0, 40) }}{{ item.raw_line?.length > 40 ? '...' : '' }}</div>
                                    </td>
                                    <td class="py-3">
                                        <select
                                            v-model="item.ingredient_id"
                                            @change="onIngredientSelect(item)"
                                            :class="[
                                                'w-full rounded-lg px-3 py-1.5 text-sm border focus:outline-none',
                                                item.match_score >= 70 ? 'bg-green-900/20 border-green-700 text-white' :
                                                item.match_score >= 40 ? 'bg-yellow-900/20 border-yellow-700 text-white' :
                                                'bg-red-900/20 border-red-700 text-white'
                                            ]"
                                        >
                                            <option value="">-- Выберите ингредиент --</option>
                                            <option v-for="ing in sortedIngredients(item)" :key="ing.id" :value="ing.id">
                                                {{ ing.name }} ({{ ing.unit?.short_name || 'шт' }})
                                            </option>
                                        </select>
                                    </td>
                                    <td class="py-3 text-center">
                                        <input
                                            v-model.number="item.quantity"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            class="w-20 bg-dark-700 text-white rounded-lg px-2 py-1.5 text-sm text-center border border-gray-600 focus:border-accent focus:outline-none"
                                        />
                                    </td>
                                    <td class="py-3 text-right">
                                        <input
                                            v-model.number="item.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 bg-dark-700 text-white rounded-lg px-2 py-1.5 text-sm text-right border border-gray-600 focus:border-accent focus:outline-none"
                                        />
                                    </td>
                                    <td class="py-3 text-center">
                                        <span
                                            :class="[
                                                'px-2 py-1 rounded-full text-xs font-medium',
                                                item.match_score >= 70 ? 'bg-green-500/20 text-green-400' :
                                                item.match_score >= 40 ? 'bg-yellow-500/20 text-yellow-400' :
                                                'bg-red-500/20 text-red-400'
                                            ]"
                                        >
                                            {{ item.match_score || 0 }}%
                                        </span>
                                    </td>
                                    <td class="py-3 text-center">
                                        <button
                                            v-if="!item.ingredient_id"
                                            @click="openQuickIngredientModal(item)"
                                            class="p-1.5 text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 rounded-lg transition-colors"
                                            title="Создать новый ингредиент"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Raw Text (collapsible) -->
                        <details class="mt-4">
                            <summary class="text-gray-400 text-sm cursor-pointer hover:text-white">
                                Показать распознанный текст
                            </summary>
                            <pre class="mt-2 p-4 bg-dark-800 rounded-lg text-xs text-gray-400 whitespace-pre-wrap max-h-48 overflow-auto">{{ recognizedRawText }}</pre>
                        </details>
                    </div>

                    <!-- No items -->
                    <div v-else-if="!recognizing" class="flex flex-col items-center justify-center py-16 text-gray-500">
                        <svg class="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-lg">Позиции не распознаны</p>
                        <p class="text-sm">Попробуйте загрузить другое фото</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-800">
                    <div class="text-gray-400">
                        Выбрано: <span class="text-white font-medium">{{ recognizedItems.filter(i => i.included && i.ingredient_id).length }}</span> позиций
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            @click="showRecognitionModal = false"
                            class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                        >
                            Отмена
                        </button>
                        <button
                            @click="createInvoiceFromRecognized"
                            :disabled="!recognizedItems.some(i => i.included && i.ingredient_id)"
                            class="px-6 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                        >
                            Создать накладную
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Ingredient Creation Modal -->
        <div
            v-if="showQuickIngredientModal"
            class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-[60]"
            @click.self="showQuickIngredientModal = false"
        >
            <div class="bg-dark-900 rounded-xl w-full max-w-md overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    <h3 class="text-lg font-bold text-white">Новый ингредиент</h3>
                    <button @click="showQuickIngredientModal = false" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Название</label>
                        <input
                            v-model="quickIngredientForm.name"
                            type="text"
                            class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                            placeholder="Название ингредиента"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Единица измерения</label>
                        <select
                            v-model="quickIngredientForm.unit_id"
                            class="w-full bg-dark-800 text-white rounded-lg px-4 py-2.5 border border-gray-700 focus:border-accent focus:outline-none"
                        >
                            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.short_name }})</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-800">
                    <button
                        @click="showQuickIngredientModal = false"
                        class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                    >
                        Отмена
                    </button>
                    <button
                        @click="createQuickIngredient"
                        :disabled="savingQuickIngredient || !quickIngredientForm.name.trim()"
                        class="px-6 py-2 bg-accent hover:bg-accent/80 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                    >
                        {{ savingQuickIngredient ? 'Создание...' : 'Создать' }}
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../../api';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('POS:Warehouse');

// Toast helpers
const showSuccess = (msg) => window.$toast?.(msg, 'success');
const showError = (msg) => window.$toast?.(msg, 'error');

// Sub-tabs
const subTabs = [
    { id: 'invoices', label: 'Накладные' },
    { id: 'inventory', label: 'Инвентаризация' }
];
const activeSubTab = ref('invoices');

// Data
const warehouses = ref([]);
const suppliers = ref([]);
const ingredients = ref([]);

// Invoices
const invoices = ref([]);
const loadingInvoices = ref(false);
const invoiceFilter = ref({ status: '', warehouse_id: '' });
const showInvoiceModal = ref(false);
const editingInvoice = ref(null);
const savingInvoice = ref(false);
const invoiceForm = ref({
    supplier_id: '',
    warehouse_id: '',
    items: []
});

// Inventory Checks
const inventoryChecks = ref([]);
const loadingChecks = ref(false);
const checkFilter = ref({ status: '', warehouse_id: '' });
const showCheckModal = ref(false);
const editingCheck = ref(null);
const savingCheck = ref(false);
const checkForm = ref({ warehouse_id: '' });
const checkItems = ref([]);

// Add item modal
const showAddItemModal = ref(false);
const newCheckItem = ref({ ingredient_id: '', actual_quantity: 0 });

// Photo recognition
const photoInput = ref(null);
const showRecognitionModal = ref(false);
const recognizing = ref(false);
const recognizedItems = ref([]);
const recognizedRawText = ref('');
const ingredientSearch = ref('');

// Quick ingredient modal for creating new ingredients from recognition
const showQuickIngredientModal = ref(false);
const quickIngredientItem = ref(null);
const quickIngredientForm = ref({
    name: '',
    unit_id: ''
});
const savingQuickIngredient = ref(false);

// Computed
const invoiceTotal = computed(() => {
    return invoiceForm.value.items.reduce((sum, item) => {
        return sum + (item.quantity || 0) * (item.price || 0);
    }, 0);
});

const availableIngredients = computed(() => {
    const usedIds = checkItems.value.map(i => i.ingredient_id);
    return ingredients.value.filter(i => !usedIds.includes(i.id));
});

// Filtered recognized items based on search
const filteredRecognizedItems = computed(() => {
    if (!ingredientSearch.value.trim()) {
        return recognizedItems.value;
    }
    const search = ingredientSearch.value.toLowerCase();
    return recognizedItems.value.filter(item =>
        item.recognized_name?.toLowerCase().includes(search) ||
        item.ingredient_name?.toLowerCase().includes(search)
    );
});

// Units for quick ingredient creation
const units = computed(() => {
    const unitMap = new Map();
    ingredients.value.forEach(ing => {
        if (ing.unit) {
            unitMap.set(ing.unit.id, ing.unit);
        }
    });
    return Array.from(unitMap.values());
});

// Load reference data
const loadReferenceData = async () => {
    try {
        const [warehousesRes, suppliersRes, ingredientsRes] = await Promise.all([
            api.warehouse.getWarehouses(),
            api.warehouse.getSuppliers(),
            api.warehouse.getIngredients()
        ]);
        warehouses.value = warehousesRes || [];
        suppliers.value = suppliersRes || [];
        ingredients.value = ingredientsRes || [];
    } catch (e) {
        log.error('Error loading reference data:', e);
    }
};

// Invoices
const loadInvoices = async () => {
    loadingInvoices.value = true;
    try {
        const params = {};
        if (invoiceFilter.value.status) params.status = invoiceFilter.value.status;
        if (invoiceFilter.value.warehouse_id) params.warehouse_id = invoiceFilter.value.warehouse_id;
        const res = await api.warehouse.getInvoices(params);
        invoices.value = res || [];
    } catch (e) {
        log.error('Error loading invoices:', e);
        showError('Ошибка загрузки накладных');
    } finally {
        loadingInvoices.value = false;
    }
};

const openInvoiceModal = (invoice = null) => {
    if (invoice) {
        editingInvoice.value = invoice;
        invoiceForm.value = {
            supplier_id: invoice.supplier_id || '',
            warehouse_id: invoice.warehouse_id || '',
            items: (invoice.items || []).map(i => ({
                ingredient_id: i.ingredient_id,
                quantity: i.quantity,
                price: i.price
            }))
        };
    } else {
        editingInvoice.value = null;
        invoiceForm.value = {
            supplier_id: '',
            warehouse_id: warehouses.value[0]?.id || '',
            items: []
        };
    }
    showInvoiceModal.value = true;
};

const addInvoiceItem = () => {
    invoiceForm.value.items.push({
        ingredient_id: '',
        quantity: 1,
        price: 0
    });
};

const removeInvoiceItem = (index) => {
    invoiceForm.value.items.splice(index, 1);
};

const createInvoice = async () => {
    if (!invoiceForm.value.warehouse_id) {
        showError('Выберите склад');
        return;
    }
    if (invoiceForm.value.items.length === 0) {
        showError('Добавьте позиции в накладную');
        return;
    }

    savingInvoice.value = true;
    try {
        await api.warehouse.createInvoice({
            supplier_id: invoiceForm.value.supplier_id || null,
            warehouse_id: invoiceForm.value.warehouse_id,
            items: invoiceForm.value.items.filter(i => i.ingredient_id && i.quantity > 0)
        });
        showSuccess('Накладная создана');
        showInvoiceModal.value = false;
        loadInvoices();
    } catch (e) {
        log.error('Error creating invoice:', e);
        showError(e.response?.data?.message || 'Ошибка создания накладной');
    } finally {
        savingInvoice.value = false;
    }
};

const viewInvoice = async (invoice) => {
    try {
        const res = await api.warehouse.getInvoice(invoice.id);
        openInvoiceModal(res);
    } catch (e) {
        log.error('Error loading invoice:', e);
        showError('Ошибка загрузки накладной');
    }
};

const completeInvoice = async (invoice) => {
    if (!confirm('Провести накладную? Товары будут оприходованы на склад.')) return;

    try {
        await api.warehouse.completeInvoice(invoice.id);
        showSuccess('Накладная проведена');
        loadInvoices();
    } catch (e) {
        log.error('Error completing invoice:', e);
        showError(e.response?.data?.message || 'Ошибка проведения накладной');
    }
};

const cancelInvoice = async (invoice) => {
    if (!confirm('Отменить накладную?')) return;

    try {
        await api.warehouse.cancelInvoice(invoice.id);
        showSuccess('Накладная отменена');
        loadInvoices();
    } catch (e) {
        log.error('Error cancelling invoice:', e);
        showError(e.response?.data?.message || 'Ошибка отмены накладной');
    }
};

// Inventory Checks
const loadInventoryChecks = async () => {
    loadingChecks.value = true;
    try {
        const params = {};
        if (checkFilter.value.status) params.status = checkFilter.value.status;
        if (checkFilter.value.warehouse_id) params.warehouse_id = checkFilter.value.warehouse_id;
        const res = await api.warehouse.getInventoryChecks(params);
        inventoryChecks.value = res || [];
    } catch (e) {
        log.error('Error loading inventory checks:', e);
        showError('Ошибка загрузки инвентаризаций');
    } finally {
        loadingChecks.value = false;
    }
};

const openInventoryCheckModal = () => {
    editingCheck.value = null;
    checkForm.value = { warehouse_id: warehouses.value[0]?.id || '' };
    checkItems.value = [];
    showCheckModal.value = true;
};

const createInventoryCheck = async () => {
    if (!checkForm.value.warehouse_id) {
        showError('Выберите склад');
        return;
    }

    savingCheck.value = true;
    try {
        const res = await api.warehouse.createInventoryCheck({
            warehouse_id: checkForm.value.warehouse_id
        });
        showSuccess('Инвентаризация создана');
        showCheckModal.value = false;
        loadInventoryChecks();
        // Open the newly created check
        viewInventoryCheck(res);
    } catch (e) {
        log.error('Error creating inventory check:', e);
        showError(e.response?.data?.message || 'Ошибка создания инвентаризации');
    } finally {
        savingCheck.value = false;
    }
};

const viewInventoryCheck = async (check) => {
    try {
        const res = await api.warehouse.getInventoryCheck(check.id);
        editingCheck.value = res;
        checkItems.value = res.items || [];
        showCheckModal.value = true;
    } catch (e) {
        log.error('Error loading inventory check:', e);
        showError('Ошибка загрузки инвентаризации');
    }
};

const updateCheckItem = async (item) => {
    try {
        await api.warehouse.updateInventoryCheckItem(editingCheck.value.id, item.id, {
            actual_quantity: item.actual_quantity
        });
    } catch (e) {
        log.error('Error updating item:', e);
        showError('Ошибка сохранения');
    }
};

const addCheckItem = () => {
    newCheckItem.value = { ingredient_id: '', actual_quantity: 0 };
    showAddItemModal.value = true;
};

const saveNewCheckItem = async () => {
    try {
        const res = await api.warehouse.addInventoryCheckItem(editingCheck.value.id, {
            ingredient_id: newCheckItem.value.ingredient_id,
            actual_quantity: newCheckItem.value.actual_quantity
        });
        checkItems.value.push(res);
        showAddItemModal.value = false;
        showSuccess('Позиция добавлена');
    } catch (e) {
        log.error('Error adding item:', e);
        showError(e.response?.data?.message || 'Ошибка добавления позиции');
    }
};

const completeInventoryCheck = async (check) => {
    if (!confirm('Завершить инвентаризацию? Остатки будут скорректированы.')) return;

    try {
        await api.warehouse.completeInventoryCheck(check.id);
        showSuccess('Инвентаризация завершена');
        showCheckModal.value = false;
        loadInventoryChecks();
    } catch (e) {
        log.error('Error completing inventory check:', e);
        showError(e.response?.data?.message || 'Ошибка завершения инвентаризации');
    }
};

const cancelInventoryCheck = async (check) => {
    if (!confirm('Отменить инвентаризацию?')) return;

    try {
        await api.warehouse.cancelInventoryCheck(check.id);
        showSuccess('Инвентаризация отменена');
        showCheckModal.value = false;
        loadInventoryChecks();
    } catch (e) {
        log.error('Error cancelling inventory check:', e);
        showError(e.response?.data?.message || 'Ошибка отмены инвентаризации');
    }
};

// Photo Recognition
const openPhotoUpload = () => {
    photoInput.value?.click();
};

const handlePhotoUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Reset input
    event.target.value = '';

    // Check file type
    if (!file.type.startsWith('image/')) {
        showError('Выберите изображение');
        return;
    }

    // Check file size (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
        showError('Файл слишком большой (макс. 10 МБ)');
        return;
    }

    // Open modal and start recognition
    showRecognitionModal.value = true;
    recognizing.value = true;
    recognizedItems.value = [];
    recognizedRawText.value = '';

    try {
        // Convert to base64
        const base64 = await fileToBase64(file);

        // Send to API
        const result = await api.warehouse.recognizeInvoice(base64);

        if (result.items && result.items.length > 0) {
            // Add 'included' flag to each item
            recognizedItems.value = result.items.map(item => ({
                ...item,
                included: item.matched // Auto-include matched items
            }));
            recognizedRawText.value = result.raw_text || '';
            showSuccess(`Распознано ${result.items.length} позиций`);
        } else {
            showError('Не удалось распознать позиции на изображении');
        }
    } catch (e) {
        log.error('Recognition error:', e);
        showError(e.response?.data?.message || 'Ошибка распознавания');
        showRecognitionModal.value = false;
    } finally {
        recognizing.value = false;
    }
};

const fileToBase64 = (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
};

const createInvoiceFromRecognized = async () => {
    // Filter included items with ingredient_id
    const items = recognizedItems.value
        .filter(i => i.included && i.ingredient_id)
        .map(i => ({
            ingredient_id: i.ingredient_id,
            quantity: i.quantity || 1,
            price: i.price || 0
        }));

    if (items.length === 0) {
        showError('Выберите хотя бы одну позицию');
        return;
    }

    // Close recognition modal
    showRecognitionModal.value = false;

    // Open invoice modal with pre-filled items
    editingInvoice.value = null;
    invoiceForm.value = {
        supplier_id: '',
        warehouse_id: warehouses.value[0]?.id || '',
        items: items
    };
    showInvoiceModal.value = true;

    showSuccess('Позиции добавлены в накладную');
};

// Select all matched items
const selectAllMatched = (checked) => {
    recognizedItems.value.forEach(item => {
        if (item.match_score >= 70) {
            item.included = checked;
        }
    });
};

// Sort ingredients - matched first, then by name
const sortedIngredients = (item) => {
    if (!ingredients.value.length) return [];

    // If item has ingredient_id set, put that one first
    const sorted = [...ingredients.value].sort((a, b) => {
        // Current selection first
        if (a.id === item.ingredient_id) return -1;
        if (b.id === item.ingredient_id) return 1;

        // Then sort by similarity to recognized name
        const nameA = a.name.toLowerCase();
        const nameB = b.name.toLowerCase();
        const search = (item.recognized_name || '').toLowerCase();

        const containsA = nameA.includes(search) || search.includes(nameA);
        const containsB = nameB.includes(search) || search.includes(nameB);

        if (containsA && !containsB) return -1;
        if (!containsA && containsB) return 1;

        return nameA.localeCompare(nameB, 'ru');
    });

    return sorted;
};

// Handle ingredient selection - auto-include when selected
const onIngredientSelect = (item) => {
    if (item.ingredient_id) {
        item.included = true;
        // Update match score to 100 if manually selected
        item.match_score = 100;
    }
};

// Open quick ingredient creation modal
const openQuickIngredientModal = (item) => {
    quickIngredientItem.value = item;
    quickIngredientForm.value = {
        name: item.recognized_name || '',
        unit_id: units.value[0]?.id || ''
    };
    showQuickIngredientModal.value = true;
};

// Create new ingredient quickly
const createQuickIngredient = async () => {
    if (!quickIngredientForm.value.name.trim()) {
        showError('Введите название');
        return;
    }

    savingQuickIngredient.value = true;
    try {
        const result = await api.warehouse.createIngredient({
            name: quickIngredientForm.value.name,
            unit_id: quickIngredientForm.value.unit_id || units.value[0]?.id,
            track_stock: true
        });

        // Add new ingredient to list
        if (result) {
            ingredients.value.push(result);

            // Auto-select in the recognition item
            if (quickIngredientItem.value) {
                quickIngredientItem.value.ingredient_id = result.id;
                quickIngredientItem.value.ingredient_name = result.name;
                quickIngredientItem.value.included = true;
                quickIngredientItem.value.match_score = 100;
            }

            showSuccess('Ингредиент создан');
            showQuickIngredientModal.value = false;
        }
    } catch (e) {
        log.error('Error creating ingredient:', e);
        showError(e.response?.data?.message || 'Ошибка создания ингредиента');
    } finally {
        savingQuickIngredient.value = false;
    }
};

// Formatters
const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatMoney = (n) => {
    return Math.floor(n || 0).toLocaleString('ru-RU');
};

const formatQuantity = (n) => {
    if (n === null || n === undefined) return '-';
    return Number(n).toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
};

const formatDifference = (n) => {
    if (n === null || n === undefined || n === 0) return '0';
    const sign = n > 0 ? '+' : '';
    return sign + formatQuantity(n);
};

const getStatusClass = (status) => {
    const classes = {
        draft: 'px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400',
        completed: 'px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400',
        cancelled: 'px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400'
    };
    return classes[status] || classes.draft;
};

const getStatusLabel = (status) => {
    const labels = {
        draft: 'Черновик',
        completed: 'Проведена',
        cancelled: 'Отменена'
    };
    return labels[status] || status;
};

const getCheckStatusClass = (status) => {
    const classes = {
        in_progress: 'px-2 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400',
        completed: 'px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400',
        cancelled: 'px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400'
    };
    return classes[status] || classes.in_progress;
};

const getCheckStatusLabel = (status) => {
    const labels = {
        in_progress: 'В процессе',
        completed: 'Завершена',
        cancelled: 'Отменена'
    };
    return labels[status] || status;
};

const getDifferenceClass = (diff) => {
    if (diff === null || diff === undefined || diff === 0) return 'text-gray-400';
    return diff > 0 ? 'text-green-400' : 'text-red-400';
};

// Watchers
watch(activeSubTab, (tab) => {
    if (tab === 'invoices') {
        loadInvoices();
    } else {
        loadInventoryChecks();
    }
});

watch(invoiceFilter, () => loadInvoices(), { deep: true });
watch(checkFilter, () => loadInventoryChecks(), { deep: true });

// Lifecycle
onMounted(async () => {
    await loadReferenceData();
    loadInvoices();
});
</script>
