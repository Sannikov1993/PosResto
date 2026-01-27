<template>
    <div>
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-600 mb-1">Всего позиций</p>
                        <p class="text-2xl font-bold text-blue-900">{{ ingredients.length }}</p>
                    </div>
                    <span class="text-3xl">📦</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-600 mb-1">Стоимость склада</p>
                        <p class="text-2xl font-bold text-green-900">{{ formatMoney(inventoryTotalValue) }}</p>
                    </div>
                    <span class="text-3xl">💰</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-red-600 mb-1">Мало на складе</p>
                        <p class="text-2xl font-bold text-red-900">{{ lowStockCount }}</p>
                    </div>
                    <span class="text-3xl">⚠️</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-600 mb-1">Инвентаризаций</p>
                        <p class="text-2xl font-bold text-purple-900">{{ inventoryChecks.length }}</p>
                    </div>
                    <span class="text-3xl">📋</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex border-b bg-gray-50">
                <button @click="activeTab = 'ingredients'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'ingredients' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🥕</span> Ингредиенты
                </button>
                <button @click="activeTab = 'movements'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'movements' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📊</span> Движение
                </button>
                <button @click="activeTab = 'checks'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'checks' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📋</span> Инвентаризация
                </button>
                <button @click="activeTab = 'suppliers'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'suppliers' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>🚚</span> Поставщики
                </button>
            </div>

            <!-- Tab: Ingredients -->
            <div v-if="activeTab === 'ingredients'" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <input v-model="ingredientSearch" type="text" placeholder="Поиск..."
                           class="px-4 py-2 border rounded-lg w-64 focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <div class="flex gap-3">
                        <button @click="openQuickIncomeModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            📥 Приход
                        </button>
                        <button @click="openQuickWriteOffModal" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            📤 Списание
                        </button>
                        <button @click="openIngredientModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                            + Ингредиент
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3 font-medium">Ингредиент</th>
                                <th class="pb-3 font-medium">Категория</th>
                                <th class="pb-3 font-medium text-right">Остаток</th>
                                <th class="pb-3 font-medium text-right">Себестоимость</th>
                                <th class="pb-3 font-medium text-center">Потери</th>
                                <th class="pb-3 font-medium text-center">Фасовки</th>
                                <th class="pb-3 font-medium">Статус</th>
                                <th class="pb-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ing in filteredIngredients" :key="ing.id"
                                @click="openIngredientModal(ing)"
                                class="border-b hover:bg-orange-50 cursor-pointer transition">
                                <td class="py-3 font-medium">{{ ing.name }}</td>
                                <td class="py-3 text-gray-500">{{ ing.category?.name || '-' }}</td>
                                <td class="py-3 text-right">{{ ing.total_stock || ing.quantity || 0 }} {{ ing.unit?.short_name || ing.unit_name || '' }}</td>
                                <td class="py-3 text-right">{{ formatMoney(ing.cost_price) }}</td>
                                <td class="py-3 text-center">
                                    <span v-if="ing.cold_loss_percent > 0 || ing.hot_loss_percent > 0"
                                          class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">
                                        {{ (ing.cold_loss_percent || 0) + (ing.hot_loss_percent || 0) }}%
                                    </span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="py-3 text-center">
                                    <span v-if="ing.packagings_count || ing.packagings?.length"
                                          class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                                        {{ ing.packagings_count || ing.packagings?.length }}
                                    </span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="py-3">
                                    <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                                   ing.is_low_stock ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700']">
                                        {{ ing.is_low_stock ? 'Мало' : 'Норма' }}
                                    </span>
                                </td>
                                <td class="py-3 text-right">
                                    <button @click.stop="openIngredientModal(ing)" class="text-orange-500 hover:text-orange-600 text-sm">
                                        Изменить
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!filteredIngredients.length">
                                <td colspan="8" class="py-8 text-center text-gray-400">Нет ингредиентов</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Movements -->
            <div v-if="activeTab === 'movements'" class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <input v-model="movementDateFrom" type="date"
                           class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <span class="text-gray-400">—</span>
                    <input v-model="movementDateTo" type="date"
                           class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <button @click="loadMovements" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                        Показать
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3 font-medium">Дата</th>
                                <th class="pb-3 font-medium">Тип</th>
                                <th class="pb-3 font-medium">Ингредиент</th>
                                <th class="pb-3 font-medium text-right">Кол-во</th>
                                <th class="pb-3 font-medium text-right">Сумма</th>
                                <th class="pb-3 font-medium">Примечание</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in stockMovements" :key="m.id" class="border-b hover:bg-gray-50">
                                <td class="py-3 text-sm">{{ formatDateTime(m.created_at) }}</td>
                                <td class="py-3">
                                    <span :class="['px-2 py-1 text-xs font-medium rounded-full', getMovementBadge(m.type)]">
                                        {{ m.type_label || getMovementLabel(m.type) }}
                                    </span>
                                </td>
                                <td class="py-3">{{ m.ingredient?.name }}</td>
                                <td class="py-3 text-right font-medium" :class="m.type === 'income' ? 'text-green-600' : 'text-red-600'">
                                    {{ m.type === 'income' ? '+' : '-' }}{{ m.quantity }}
                                </td>
                                <td class="py-3 text-right">{{ formatMoney(m.total_cost) }}</td>
                                <td class="py-3 text-gray-500 text-sm">{{ m.reason || '-' }}</td>
                            </tr>
                            <tr v-if="!stockMovements.length">
                                <td colspan="6" class="py-8 text-center text-gray-400">Нет движений</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Checks -->
            <div v-if="activeTab === 'checks'" class="p-6">
                <div class="flex justify-between mb-4">
                    <h3 class="text-lg font-semibold">Инвентаризации</h3>
                    <button @click="createInventoryCheck" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                        + Новая инвентаризация
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="check in inventoryChecks" :key="check.id"
                         @click="openInventoryCheck(check)"
                         class="bg-white border rounded-xl p-4 cursor-pointer hover:shadow-md transition">
                        <div class="flex justify-between mb-2">
                            <span class="font-semibold">{{ check.number }}</span>
                            <span :class="['px-2 py-1 text-xs font-medium rounded-full', getCheckStatusBadge(check.status)]">
                                {{ check.status_label || getCheckStatusLabel(check.status) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mb-2">{{ formatDate(check.date) }}</p>
                        <div class="flex justify-between text-sm">
                            <span>Позиций: {{ check.items_count || 0 }}</span>
                            <span v-if="check.discrepancy_count" class="text-red-500">
                                Расхождений: {{ check.discrepancy_count }}
                            </span>
                        </div>
                    </div>
                    <div v-if="!inventoryChecks.length" class="col-span-3 text-center text-gray-400 py-8">
                        Нет инвентаризаций
                    </div>
                </div>
            </div>

            <!-- Tab: Suppliers -->
            <div v-if="activeTab === 'suppliers'" class="p-6">
                <div class="flex justify-between mb-4">
                    <h3 class="text-lg font-semibold">Поставщики</h3>
                    <button @click="openSupplierModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                        + Добавить
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3 font-medium">Название</th>
                                <th class="pb-3 font-medium">Контакт</th>
                                <th class="pb-3 font-medium">Телефон</th>
                                <th class="pb-3 font-medium">Email</th>
                                <th class="pb-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in suppliers" :key="s.id" class="border-b hover:bg-gray-50">
                                <td class="py-3 font-medium">{{ s.name }}</td>
                                <td class="py-3">{{ s.contact_person || '-' }}</td>
                                <td class="py-3">{{ s.phone || '-' }}</td>
                                <td class="py-3 text-gray-500">{{ s.email || '-' }}</td>
                                <td class="py-3 text-right">
                                    <button @click="openSupplierModal(s)" class="text-orange-500 hover:text-orange-600">
                                        Изменить
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!suppliers.length">
                                <td colspan="5" class="py-8 text-center text-gray-400">Нет поставщиков</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Income Modal -->
        <Teleport to="body">
            <div v-if="showQuickIncomeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showQuickIncomeModal = false">
                <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-semibold mb-4">Оформить приход</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Склад</label>
                            <select v-model="quickIncomeForm.warehouse_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Выберите склад</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ингредиент</label>
                            <select v-model="quickIncomeForm.ingredient_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Выберите ингредиент</option>
                                <option v-for="i in ingredients" :key="i.id" :value="i.id">{{ i.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Количество</label>
                                <input v-model.number="quickIncomeForm.quantity" type="number" min="0" step="0.001"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Себестоимость</label>
                                <input v-model.number="quickIncomeForm.cost_price" type="number" min="0" step="0.01"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showQuickIncomeModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="submitQuickIncome"
                                :disabled="!quickIncomeForm.ingredient_id || !quickIncomeForm.quantity"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Оформить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Quick Write-Off Modal -->
        <Teleport to="body">
            <div v-if="showQuickWriteOffModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showQuickWriteOffModal = false">
                <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-semibold mb-4">Оформить списание</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Склад</label>
                            <select v-model="quickWriteOffForm.warehouse_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Выберите склад</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ингредиент</label>
                            <select v-model="quickWriteOffForm.ingredient_id"
                                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Выберите ингредиент</option>
                                <option v-for="i in ingredients" :key="i.id" :value="i.id">{{ i.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Количество</label>
                            <input v-model.number="quickWriteOffForm.quantity" type="number" min="0" step="0.001"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Причина списания</label>
                            <input v-model="quickWriteOffForm.reason" type="text" placeholder="Укажите причину"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showQuickWriteOffModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="submitQuickWriteOff"
                                :disabled="!quickWriteOffForm.ingredient_id || !quickWriteOffForm.quantity"
                                class="flex-1 px-4 py-2 bg-red-500 hover:bg-red-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Списать
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Inventory Check Modal -->
        <Teleport to="body">
            <div v-if="showInventoryCheckModal && currentInventoryCheck" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showInventoryCheckModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="p-6 border-b flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold">{{ currentInventoryCheck.number }}</h3>
                            <p class="text-sm text-gray-500">{{ currentInventoryCheck.warehouse?.name }} - {{ formatDate(currentInventoryCheck.date) }}</p>
                        </div>
                        <span :class="['px-3 py-1 text-sm font-medium rounded-full', getCheckStatusBadge(currentInventoryCheck.status)]">
                            {{ currentInventoryCheck.status_label || getCheckStatusLabel(currentInventoryCheck.status) }}
                        </span>
                    </div>
                    <div class="flex-1 overflow-auto p-6">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-white">
                                <tr class="text-left text-sm text-gray-500 border-b">
                                    <th class="pb-3 font-medium">Ингредиент</th>
                                    <th class="pb-3 font-medium text-right">Ожидаемое</th>
                                    <th class="pb-3 font-medium text-right">Фактическое</th>
                                    <th class="pb-3 font-medium text-right">Разница</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in currentInventoryCheck.items" :key="item.id" class="border-b">
                                    <td class="py-3">{{ item.ingredient?.name }}</td>
                                    <td class="py-3 text-right">{{ item.expected_quantity }} {{ item.ingredient?.unit?.short_name }}</td>
                                    <td class="py-3 text-right">
                                        <input v-if="currentInventoryCheck.status !== 'completed'"
                                               v-model.number="item.actual_quantity"
                                               @change="updateCheckItem(item)"
                                               type="number" min="0" step="0.001"
                                               class="w-24 px-2 py-1 border rounded text-right focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                        <span v-else>{{ item.actual_quantity }} {{ item.ingredient?.unit?.short_name }}</span>
                                    </td>
                                    <td class="py-3 text-right font-medium"
                                        :class="item.difference > 0 ? 'text-green-600' : item.difference < 0 ? 'text-red-600' : ''">
                                        {{ item.difference != null ? (item.difference > 0 ? '+' : '') + item.difference : '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-6 border-t flex justify-between">
                        <button @click="showInventoryCheckModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Закрыть
                        </button>
                        <div class="flex gap-3" v-if="currentInventoryCheck.status !== 'completed' && currentInventoryCheck.status !== 'cancelled'">
                            <button @click="cancelInventoryCheck"
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition">
                                Отменить
                            </button>
                            <button @click="completeInventoryCheck"
                                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                                Завершить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Supplier Modal -->
        <Teleport to="body">
            <div v-if="showSupplierModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showSupplierModal = false">
                <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-semibold mb-4">{{ supplierForm.id ? 'Редактировать' : 'Добавить' }} поставщика</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название *</label>
                            <input v-model="supplierForm.name" type="text" placeholder="ООО Поставщик"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Контактное лицо</label>
                            <input v-model="supplierForm.contact_person" type="text"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Телефон</label>
                                <input v-model="supplierForm.phone" type="text"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input v-model="supplierForm.email" type="email"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Адрес</label>
                            <input v-model="supplierForm.address" type="text"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Примечания</label>
                            <textarea v-model="supplierForm.notes" rows="2"
                                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"></textarea>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showSupplierModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="saveSupplier"
                                :disabled="!supplierForm.name"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Ingredient Form Modal -->
        <IngredientFormModal
            :show="showIngredientModal"
            :ingredient="editingIngredient"
            :categories="categories"
            :units="units"
            @close="showIngredientModal = false"
            @saved="onIngredientSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';
import IngredientFormModal from '../IngredientFormModal.vue';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const store = useBackofficeStore();

// State
const activeTab = ref('ingredients');
const ingredientSearch = ref('');
const movementDateFrom = ref('');
const movementDateTo = ref('');

const ingredients = ref([]);
const warehouses = ref([]);
const suppliers = ref([]);
const stockMovements = ref([]);
const inventoryChecks = ref([]);
const categories = ref([]);
const units = ref([]);

// Modals
const showQuickIncomeModal = ref(false);
const showQuickWriteOffModal = ref(false);
const showInventoryCheckModal = ref(false);
const showSupplierModal = ref(false);
const showIngredientModal = ref(false);
const editingIngredient = ref(null);

const quickIncomeForm = ref({
    warehouse_id: '',
    ingredient_id: '',
    quantity: 0,
    cost_price: 0
});

const quickWriteOffForm = ref({
    warehouse_id: '',
    ingredient_id: '',
    quantity: 0,
    reason: ''
});

const supplierForm = ref({
    id: null,
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    notes: ''
});

const currentInventoryCheck = ref(null);

// Computed
const filteredIngredients = computed(() => {
    if (!ingredientSearch.value) return ingredients.value;
    const search = ingredientSearch.value.toLowerCase();
    return ingredients.value.filter(i =>
        i.name?.toLowerCase().includes(search) ||
        i.category?.name?.toLowerCase().includes(search)
    );
});

const inventoryTotalValue = computed(() => {
    return ingredients.value.reduce((sum, i) => sum + (i.stock_value || 0), 0);
});

const lowStockCount = computed(() => {
    return ingredients.value.filter(i => i.is_low_stock).length;
});

// Methods
function formatMoney(val) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ru-RU');
}

function formatDateTime(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getMovementBadge(type) {
    return type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
}

function getMovementLabel(type) {
    const labels = { income: 'Приход', expense: 'Расход', write_off: 'Списание', sale: 'Продажа' };
    return labels[type] || type;
}

function getCheckStatusBadge(status) {
    const badges = {
        draft: 'bg-gray-100 text-gray-700',
        in_progress: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700'
    };
    return badges[status] || 'bg-gray-100 text-gray-700';
}

function getCheckStatusLabel(status) {
    const labels = { draft: 'Черновик', in_progress: 'В процессе', completed: 'Завершена', cancelled: 'Отменена' };
    return labels[status] || status;
}

async function loadInventory() {
    try {
        const [ingredientsRes, warehousesRes, suppliersRes, checksRes, categoriesRes, unitsRes] = await Promise.all([
            store.api('/backoffice/inventory/ingredients'),
            store.api('/backoffice/inventory/warehouses'),
            store.api('/backoffice/inventory/suppliers'),
            store.api('/backoffice/inventory/checks'),
            store.api('/backoffice/menu/categories').catch(() => ({ data: [] })),
            store.api('/backoffice/inventory/units').catch(() => ({ data: [] }))
        ]);

        ingredients.value = ingredientsRes.data || ingredientsRes || [];
        warehouses.value = warehousesRes.data || warehousesRes || [];
        suppliers.value = suppliersRes.data || suppliersRes || [];
        inventoryChecks.value = checksRes.data || checksRes || [];
        categories.value = categoriesRes.data || categoriesRes || [];
        units.value = unitsRes.data || unitsRes || [];
    } catch (e) {
        console.error('Failed to load inventory:', e);
        loadMockData();
    }
}

async function loadMovements() {
    try {
        const params = new URLSearchParams();
        if (movementDateFrom.value) params.append('from', movementDateFrom.value);
        if (movementDateTo.value) params.append('to', movementDateTo.value);

        const res = await store.api(`/backoffice/inventory/movements?${params.toString()}`);
        stockMovements.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load movements:', e);
    }
}

function loadMockData() {
    ingredients.value = [
        { id: 1, name: 'Томаты', category: { name: 'Овощи' }, total_stock: 25, unit: { short_name: 'кг' }, cost_price: 120, stock_value: 3000, is_low_stock: false },
        { id: 2, name: 'Куриное филе', category: { name: 'Мясо' }, total_stock: 8, unit: { short_name: 'кг' }, cost_price: 350, stock_value: 2800, is_low_stock: true },
        { id: 3, name: 'Моцарелла', category: { name: 'Сыры' }, total_stock: 12, unit: { short_name: 'кг' }, cost_price: 650, stock_value: 7800, is_low_stock: false },
        { id: 4, name: 'Мука пшеничная', category: { name: 'Бакалея' }, total_stock: 50, unit: { short_name: 'кг' }, cost_price: 45, stock_value: 2250, is_low_stock: false },
        { id: 5, name: 'Масло оливковое', category: { name: 'Масла' }, total_stock: 3, unit: { short_name: 'л' }, cost_price: 890, stock_value: 2670, is_low_stock: true }
    ];

    warehouses.value = [
        { id: 1, name: 'Основной склад' },
        { id: 2, name: 'Холодильная камера' }
    ];

    suppliers.value = [
        { id: 1, name: 'ООО "Свежие овощи"', contact_person: 'Иван Петров', phone: '+7 999 123-45-67', email: 'fresh@example.com' },
        { id: 2, name: 'Мясокомбинат №1', contact_person: 'Сергей Сидоров', phone: '+7 999 765-43-21', email: 'meat@example.com' }
    ];

    inventoryChecks.value = [
        { id: 1, number: 'INV-001', date: '2024-01-15', status: 'completed', status_label: 'Завершена', items_count: 25, discrepancy_count: 3 },
        { id: 2, number: 'INV-002', date: '2024-01-20', status: 'in_progress', status_label: 'В процессе', items_count: 30, discrepancy_count: 0 }
    ];
}

function openQuickIncomeModal() {
    quickIncomeForm.value = {
        warehouse_id: warehouses.value[0]?.id || '',
        ingredient_id: '',
        quantity: 0,
        cost_price: 0
    };
    showQuickIncomeModal.value = true;
}

function openQuickWriteOffModal() {
    quickWriteOffForm.value = {
        warehouse_id: warehouses.value[0]?.id || '',
        ingredient_id: '',
        quantity: 0,
        reason: ''
    };
    showQuickWriteOffModal.value = true;
}

async function submitQuickIncome() {
    try {
        await store.api('/backoffice/inventory/quick-income', {
            method: 'POST',
            body: JSON.stringify(quickIncomeForm.value)
        });
        showQuickIncomeModal.value = false;
        store.showToast('Приход оформлен', 'success');
        loadInventory();
    } catch (e) {
        store.showToast('Ошибка оформления прихода', 'error');
    }
}

async function submitQuickWriteOff() {
    try {
        await store.api('/backoffice/inventory/quick-write-off', {
            method: 'POST',
            body: JSON.stringify(quickWriteOffForm.value)
        });
        showQuickWriteOffModal.value = false;
        store.showToast('Списание оформлено', 'success');
        loadInventory();
    } catch (e) {
        store.showToast('Ошибка списания', 'error');
    }
}

async function createInventoryCheck() {
    if (!warehouses.value.length) {
        store.showToast('Сначала создайте склад', 'error');
        return;
    }
    try {
        const res = await store.api('/backoffice/inventory/checks', {
            method: 'POST',
            body: JSON.stringify({ warehouse_id: warehouses.value[0].id })
        });
        store.showToast('Инвентаризация создана', 'success');
        loadInventory();
        if (res.data || res) {
            openInventoryCheck(res.data || res);
        }
    } catch (e) {
        store.showToast('Ошибка создания инвентаризации', 'error');
    }
}

async function openInventoryCheck(check) {
    try {
        const res = await store.api(`/backoffice/inventory/checks/${check.id}`);
        currentInventoryCheck.value = res.data || res || check;
        showInventoryCheckModal.value = true;
    } catch (e) {
        currentInventoryCheck.value = { ...check, items: [] };
        showInventoryCheckModal.value = true;
    }
}

async function updateCheckItem(item) {
    try {
        await store.api(`/backoffice/inventory/checks/${currentInventoryCheck.value.id}/items/${item.id}`, {
            method: 'PUT',
            body: JSON.stringify({ actual_quantity: item.actual_quantity })
        });
        item.difference = (item.actual_quantity || 0) - (item.expected_quantity || 0);
    } catch (e) {
        store.showToast('Ошибка обновления', 'error');
    }
}

async function completeInventoryCheck() {
    try {
        await store.api(`/backoffice/inventory/checks/${currentInventoryCheck.value.id}/complete`, {
            method: 'POST'
        });
        showInventoryCheckModal.value = false;
        store.showToast('Инвентаризация завершена', 'success');
        loadInventory();
    } catch (e) {
        store.showToast('Ошибка завершения', 'error');
    }
}

async function cancelInventoryCheck() {
    try {
        await store.api(`/backoffice/inventory/checks/${currentInventoryCheck.value.id}/cancel`, {
            method: 'POST'
        });
        showInventoryCheckModal.value = false;
        store.showToast('Инвентаризация отменена', 'success');
        loadInventory();
    } catch (e) {
        store.showToast('Ошибка отмены', 'error');
    }
}

function openSupplierModal(supplier = null) {
    if (supplier) {
        supplierForm.value = { ...supplier };
    } else {
        supplierForm.value = { id: null, name: '', contact_person: '', phone: '', email: '', address: '', notes: '' };
    }
    showSupplierModal.value = true;
}

async function saveSupplier() {
    try {
        if (supplierForm.value.id) {
            await store.api(`/backoffice/inventory/suppliers/${supplierForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(supplierForm.value)
            });
        } else {
            await store.api('/backoffice/inventory/suppliers', {
                method: 'POST',
                body: JSON.stringify(supplierForm.value)
            });
        }
        showSupplierModal.value = false;
        store.showToast('Поставщик сохранён', 'success');
        loadInventory();
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

// Ingredient Modal
function openIngredientModal(ingredient = null) {
    editingIngredient.value = ingredient;
    showIngredientModal.value = true;
}

function onIngredientSaved(savedIngredient) {
    if (editingIngredient.value) {
        const index = ingredients.value.findIndex(i => i.id === savedIngredient.id);
        if (index !== -1) {
            ingredients.value[index] = savedIngredient;
        }
    } else {
        ingredients.value.push(savedIngredient);
    }
    showIngredientModal.value = false;
    loadInventory(); // Reload to get updated data
}

// Init
onMounted(() => {
    const today = new Date();
    const monthAgo = new Date(today);
    monthAgo.setMonth(monthAgo.getMonth() - 1);

    movementDateTo.value = getLocalDateString(today);
    movementDateFrom.value = getLocalDateString(monthAgo);

    loadInventory();
});
</script>
