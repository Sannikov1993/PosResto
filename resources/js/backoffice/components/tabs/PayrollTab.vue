<template>
    <div>
        <!-- Period Selector -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <select v-model="selectedMonth" @change="loadPayroll"
                        class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <select v-model="selectedYear" @change="loadPayroll"
                        class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <button @click="calculatePayroll" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                    🔄 Пересчитать
                </button>
                <button @click="exportPayroll" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                    📥 Экспорт
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-600 mb-1">Сотрудников</p>
                        <p class="text-2xl font-bold text-blue-900">{{ payrollItems.length }}</p>
                    </div>
                    <span class="text-3xl">👥</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-600 mb-1">К выплате</p>
                        <p class="text-2xl font-bold text-green-900">{{ formatMoney(totalPayroll) }}</p>
                    </div>
                    <span class="text-3xl">💰</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-600 mb-1">Часов</p>
                        <p class="text-2xl font-bold text-purple-900">{{ totalHours }}</p>
                    </div>
                    <span class="text-3xl">⏰</span>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-orange-600 mb-1">Выплачено</p>
                        <p class="text-2xl font-bold text-orange-900">{{ formatMoney(paidTotal) }}</p>
                    </div>
                    <span class="text-3xl">✅</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">
            <div class="flex border-b bg-gray-50">
                <button @click="activeTab = 'timesheet'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'timesheet' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>⏰</span> Табель
                </button>
                <button @click="activeTab = 'payroll'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'payroll' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📋</span> Расчёт зарплат
                </button>
                <button @click="activeTab = 'history'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'history' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>📜</span> История выплат
                </button>
                <button @click="activeTab = 'rates'"
                        :class="['px-6 py-4 text-sm font-medium border-b-2 -mb-px flex items-center gap-2 transition',
                                 activeTab === 'rates' ? 'text-orange-500 border-orange-500 bg-white' : 'text-gray-500 border-transparent hover:text-gray-700']">
                    <span>💵</span> Ставки
                </button>
            </div>
        </div>

        <!-- Timesheet Tab -->
        <div v-if="activeTab === 'timesheet'" class="space-y-4">
            <!-- Who's Working Now -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Сейчас на смене</h3>
                    <button @click="loadWorkingSessions" class="text-orange-500 hover:text-orange-600">
                        <svg class="w-5 h-5" :class="{ 'animate-spin': loadingWorkingSessions }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
                <div v-if="workingNow.length === 0" class="text-center py-8 text-gray-500">
                    Сейчас никто не на смене
                </div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div v-for="session in workingNow" :key="session.id"
                         class="p-4 bg-green-50 rounded-xl border border-green-200">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center font-bold text-white">
                                {{ session.user?.name?.charAt(0) || '?' }}
                            </div>
                            <div>
                                <p class="font-medium text-green-800">{{ session.user?.name }}</p>
                                <p class="text-xs text-green-600">{{ session.user?.role_label }}</p>
                            </div>
                        </div>
                        <div class="text-xs text-green-600 mt-2">
                            <span>С {{ formatTime(session.clock_in) }}</span>
                            <span class="float-right font-medium">{{ session.duration_formatted }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work Sessions Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="text-lg font-semibold">История смен</h3>
                    <div class="flex items-center gap-4">
                        <select v-model="timesheetFilter.userId" @change="loadTimesheet"
                                class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                            <option :value="null">Все сотрудники</option>
                            <option v-for="staff in staffList" :key="staff.id" :value="staff.id">
                                {{ staff.name }}
                            </option>
                        </select>
                        <input type="date" v-model="timesheetFilter.startDate" @change="loadTimesheet"
                               class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500" />
                        <input type="date" v-model="timesheetFilter.endDate" @change="loadTimesheet"
                               class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500" />
                    </div>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Начало</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Конец</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Часов</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Статус</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="session in timesheetSessions" :key="session.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center font-bold text-orange-600 text-sm">
                                        {{ session.user?.name?.charAt(0) || '?' }}
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ session.user?.name }}</p>
                                        <p class="text-xs text-gray-500">{{ session.user?.role_label }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ formatDate(session.clock_in) }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ formatTime(session.clock_in) }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ session.clock_out ? formatTime(session.clock_out) : '—' }}</td>
                            <td class="px-6 py-4 text-right font-medium">{{ session.hours_worked ? session.hours_worked.toFixed(1) : '—' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                    session.status === 'active' ? 'bg-green-100 text-green-700' :
                                    session.status === 'completed' ? 'bg-gray-100 text-gray-700' :
                                    'bg-red-100 text-red-700']">
                                    {{ session.status === 'active' ? 'Активна' : session.status === 'completed' ? 'Завершена' : session.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button v-if="session.status === 'active'"
                                        @click="forceClockOut(session)"
                                        class="text-red-500 hover:text-red-700 text-sm">
                                    Завершить
                                </button>
                            </td>
                        </tr>
                        <tr v-if="timesheetSessions.length === 0">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                Нет записей за выбранный период
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payroll Tab -->
        <div v-if="activeTab === 'payroll'" class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Должность</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Часов</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Оклад/Ставка</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Бонусы</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Удержания</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">К выплате</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Статус</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="item in payrollItems" :key="item.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center font-bold text-orange-600">
                                    {{ item.staff?.name?.charAt(0) || 'С' }}
                                </div>
                                <span class="font-medium">{{ item.staff?.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ item.staff?.role || '-' }}</td>
                        <td class="px-6 py-4 text-right">{{ item.hours || 0 }}</td>
                        <td class="px-6 py-4 text-right">{{ formatMoney(item.base_salary) }}</td>
                        <td class="px-6 py-4 text-right text-green-600">+{{ formatMoney(item.bonuses) }}</td>
                        <td class="px-6 py-4 text-right text-red-600">-{{ formatMoney(item.deductions) }}</td>
                        <td class="px-6 py-4 text-right font-bold">{{ formatMoney(item.total) }}</td>
                        <td class="px-6 py-4 text-center">
                            <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                           item.status === 'paid' ? 'bg-green-100 text-green-700' :
                                           item.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700']">
                                {{ item.status === 'paid' ? 'Выплачено' : item.status === 'pending' ? 'Ожидает' : 'Не рассчитано' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button @click="openPayrollModal(item)" class="text-gray-400 hover:text-orange-500 mr-2">✏️</button>
                            <button v-if="item.status !== 'paid'" @click="markAsPaid(item)" class="text-gray-400 hover:text-green-500">✅</button>
                        </td>
                    </tr>
                    <tr v-if="!payrollItems.length">
                        <td colspan="9" class="px-6 py-8 text-center text-gray-400">Нет данных для расчёта</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- History Tab -->
        <div v-if="activeTab === 'history'" class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Период</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Сумма</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Способ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Примечание</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="payment in paymentHistory" :key="payment.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm">{{ formatDate(payment.paid_at) }}</td>
                        <td class="px-6 py-4 font-medium">{{ payment.staff?.name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ payment.period }}</td>
                        <td class="px-6 py-4 text-right font-bold text-green-600">{{ formatMoney(payment.amount) }}</td>
                        <td class="px-6 py-4">
                            <span :class="['px-2 py-1 text-xs font-medium rounded-full',
                                           payment.method === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700']">
                                {{ payment.method === 'cash' ? '💵 Наличные' : '💳 Перевод' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-sm">{{ payment.note || '-' }}</td>
                    </tr>
                    <tr v-if="!paymentHistory.length">
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400">Нет выплат</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Rates Tab -->
        <div v-if="activeTab === 'rates'" class="bg-white rounded-xl shadow-sm">
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="text-lg font-semibold">Ставки по должностям</h3>
                <button @click="openRateModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                    + Добавить ставку
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div v-for="rate in rates" :key="rate.id"
                     class="flex items-center justify-between p-4 border rounded-xl group">
                    <div>
                        <div class="font-medium">{{ rate.role }}</div>
                        <div class="text-sm text-gray-500">{{ rate.type === 'hourly' ? 'Почасовая' : 'Оклад' }}</div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <div class="font-bold text-green-600">{{ formatMoney(rate.amount) }}</div>
                            <div class="text-xs text-gray-500">{{ rate.type === 'hourly' ? 'в час' : 'в месяц' }}</div>
                        </div>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button @click="openRateModal(rate)" class="text-gray-400 hover:text-orange-500">✏️</button>
                            <button @click="deleteRate(rate.id)" class="text-gray-400 hover:text-red-500">🗑️</button>
                        </div>
                    </div>
                </div>
                <div v-if="!rates.length" class="text-center py-8 text-gray-400">Нет ставок</div>
            </div>
        </div>

        <!-- Payroll Edit Modal -->
        <Teleport to="body">
            <div v-if="showPayrollModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showPayrollModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">Редактировать расчёт</h3>
                        <p class="text-sm text-gray-500">{{ payrollForm.staff?.name }}</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Часов отработано</label>
                            <input v-model.number="payrollForm.hours" type="number" min="0"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Базовый оклад/ставка</label>
                            <input v-model.number="payrollForm.base_salary" type="number" min="0"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Бонусы</label>
                            <input v-model.number="payrollForm.bonuses" type="number" min="0"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Удержания</label>
                            <input v-model.number="payrollForm.deductions" type="number" min="0"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Итого к выплате:</span>
                                <span class="text-green-600">{{ formatMoney(calculatedTotal) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t flex gap-3">
                        <button @click="showPayrollModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="savePayroll"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Rate Modal -->
        <Teleport to="body">
            <div v-if="showRateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showRateModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">{{ rateForm.id ? 'Редактировать' : 'Новая' }} ставка</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Должность *</label>
                            <input v-model="rateForm.role" type="text" placeholder="Официант"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Тип</label>
                            <div class="flex gap-3">
                                <label class="flex-1">
                                    <input type="radio" v-model="rateForm.type" value="hourly" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-orange-500 peer-checked:bg-orange-50">
                                        ⏰ Почасовая
                                    </div>
                                </label>
                                <label class="flex-1">
                                    <input type="radio" v-model="rateForm.type" value="monthly" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-orange-500 peer-checked:bg-orange-50">
                                        📅 Оклад
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Сумма</label>
                            <input v-model.number="rateForm.amount" type="number" min="0"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>
                    <div class="p-6 border-t flex gap-3">
                        <button @click="showRateModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="saveRate"
                                :disabled="!rateForm.role || !rateForm.amount"
                                class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Pay Modal -->
        <Teleport to="body">
            <div v-if="showPayModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showPayModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">Выплата зарплаты</h3>
                        <p class="text-sm text-gray-500">{{ payForm.staff?.name }}</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <div class="text-sm text-green-600 mb-1">Сумма к выплате</div>
                            <div class="text-2xl font-bold text-green-700">{{ formatMoney(payForm.amount) }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Способ выплаты</label>
                            <div class="flex gap-3">
                                <label class="flex-1">
                                    <input type="radio" v-model="payForm.method" value="cash" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-green-500 peer-checked:bg-green-50">
                                        💵 Наличные
                                    </div>
                                </label>
                                <label class="flex-1">
                                    <input type="radio" v-model="payForm.method" value="transfer" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50">
                                        💳 Перевод
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Примечание</label>
                            <input v-model="payForm.note" type="text"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>
                    <div class="p-6 border-t flex gap-3">
                        <button @click="showPayModal = false"
                                class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button @click="confirmPayment"
                                class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition">
                            Выплатить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

// State
const activeTab = ref('timesheet');
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());

const payrollItems = ref([]);
const paymentHistory = ref([]);
const rates = ref([]);

// Timesheet state
const staffList = ref([]);
const workingNow = ref([]);
const timesheetSessions = ref([]);
const loadingWorkingSessions = ref(false);
const timesheetFilter = ref({
    userId: null,
    startDate: new Date(new Date().setDate(new Date().getDate() - 7)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
});

// Modals
const showPayrollModal = ref(false);
const showRateModal = ref(false);
const showPayModal = ref(false);

const payrollForm = ref({ id: null, staff: null, hours: 0, base_salary: 0, bonuses: 0, deductions: 0 });
const rateForm = ref({ id: null, role: '', type: 'hourly', amount: 0 });
const payForm = ref({ payroll_id: null, staff: null, amount: 0, method: 'cash', note: '' });

// Constants
const months = [
    { value: 1, label: 'Январь' }, { value: 2, label: 'Февраль' }, { value: 3, label: 'Март' },
    { value: 4, label: 'Апрель' }, { value: 5, label: 'Май' }, { value: 6, label: 'Июнь' },
    { value: 7, label: 'Июль' }, { value: 8, label: 'Август' }, { value: 9, label: 'Сентябрь' },
    { value: 10, label: 'Октябрь' }, { value: 11, label: 'Ноябрь' }, { value: 12, label: 'Декабрь' }
];

const years = computed(() => {
    const current = new Date().getFullYear();
    return [current - 1, current, current + 1];
});

// Computed
const totalPayroll = computed(() => payrollItems.value.reduce((sum, i) => sum + (i.total || 0), 0));
const totalHours = computed(() => payrollItems.value.reduce((sum, i) => sum + (i.hours || 0), 0));
const paidTotal = computed(() => payrollItems.value.filter(i => i.status === 'paid').reduce((sum, i) => sum + (i.total || 0), 0));

const calculatedTotal = computed(() => {
    return (payrollForm.value.base_salary || 0) + (payrollForm.value.bonuses || 0) - (payrollForm.value.deductions || 0);
});

// Methods
function formatMoney(val) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('ru-RU');
}

async function loadPayroll() {
    try {
        const [payrollRes, historyRes, ratesRes] = await Promise.all([
            store.api(`/backoffice/payroll?month=${selectedMonth.value}&year=${selectedYear.value}`),
            store.api('/backoffice/payroll/history'),
            store.api('/backoffice/payroll/rates')
        ]);

        payrollItems.value = payrollRes.data || payrollRes || [];
        paymentHistory.value = historyRes.data || historyRes || [];
        rates.value = ratesRes.data || ratesRes || [];
    } catch (e) {
        console.error('Failed to load payroll:', e);
        loadMockData();
    }
}

function loadMockData() {
    payrollItems.value = [
        { id: 1, staff: { name: 'Иван Петров', role: 'Официант' }, hours: 168, base_salary: 25200, bonuses: 5000, deductions: 0, total: 30200, status: 'paid' },
        { id: 2, staff: { name: 'Мария Сидорова', role: 'Официант' }, hours: 156, base_salary: 23400, bonuses: 3500, deductions: 500, total: 26400, status: 'pending' },
        { id: 3, staff: { name: 'Алексей Козлов', role: 'Повар' }, hours: 180, base_salary: 45000, bonuses: 2000, deductions: 0, total: 47000, status: 'pending' },
        { id: 4, staff: { name: 'Елена Смирнова', role: 'Администратор' }, hours: 160, base_salary: 55000, bonuses: 0, deductions: 0, total: 55000, status: 'draft' }
    ];

    paymentHistory.value = [
        { id: 1, staff: { name: 'Иван Петров' }, period: 'Январь 2024', amount: 30200, method: 'transfer', paid_at: '2024-02-05', note: '' },
        { id: 2, staff: { name: 'Алексей Козлов' }, period: 'Декабрь 2023', amount: 45000, method: 'cash', paid_at: '2024-01-05', note: 'Аванс' }
    ];

    rates.value = [
        { id: 1, role: 'Официант', type: 'hourly', amount: 150 },
        { id: 2, role: 'Повар', type: 'monthly', amount: 45000 },
        { id: 3, role: 'Администратор', type: 'monthly', amount: 55000 },
        { id: 4, role: 'Курьер', type: 'hourly', amount: 200 }
    ];
}

async function calculatePayroll() {
    try {
        await store.api(`/backoffice/payroll/calculate`, {
            method: 'POST',
            body: JSON.stringify({ month: selectedMonth.value, year: selectedYear.value })
        });
        store.showToast('Зарплаты пересчитаны', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка расчёта', 'error');
    }
}

function exportPayroll() {
    const period = `${months.find(m => m.value === selectedMonth.value)?.label} ${selectedYear.value}`;
    store.showToast(`Экспорт за ${period}`, 'success');
}

function openPayrollModal(item) {
    payrollForm.value = { ...item };
    showPayrollModal.value = true;
}

async function savePayroll() {
    try {
        payrollForm.value.total = calculatedTotal.value;
        await store.api(`/backoffice/payroll/${payrollForm.value.id}`, {
            method: 'PUT', body: JSON.stringify(payrollForm.value)
        });
        showPayrollModal.value = false;
        store.showToast('Расчёт сохранён', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

function markAsPaid(item) {
    payForm.value = {
        payroll_id: item.id,
        staff: item.staff,
        amount: item.total,
        method: 'cash',
        note: ''
    };
    showPayModal.value = true;
}

async function confirmPayment() {
    try {
        await store.api(`/backoffice/payroll/${payForm.value.payroll_id}/pay`, {
            method: 'POST', body: JSON.stringify(payForm.value)
        });
        showPayModal.value = false;
        store.showToast('Выплата проведена', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка выплаты', 'error');
    }
}

function openRateModal(rate = null) {
    if (rate) {
        rateForm.value = { ...rate };
    } else {
        rateForm.value = { id: null, role: '', type: 'hourly', amount: 0 };
    }
    showRateModal.value = true;
}

async function saveRate() {
    try {
        if (rateForm.value.id) {
            await store.api(`/backoffice/payroll/rates/${rateForm.value.id}`, {
                method: 'PUT', body: JSON.stringify(rateForm.value)
            });
        } else {
            await store.api('/backoffice/payroll/rates', {
                method: 'POST', body: JSON.stringify(rateForm.value)
            });
        }
        showRateModal.value = false;
        store.showToast('Ставка сохранена', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteRate(id) {
    if (!confirm('Удалить ставку?')) return;
    try {
        await store.api(`/backoffice/payroll/rates/${id}`, { method: 'DELETE' });
        rates.value = rates.value.filter(r => r.id !== id);
        store.showToast('Ставка удалена', 'success');
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// ===================== TIMESHEET FUNCTIONS =====================

async function loadStaff() {
    try {
        const res = await store.api('/backoffice/staff');
        staffList.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load staff:', e);
    }
}

async function loadWorkingSessions() {
    loadingWorkingSessions.value = true;
    try {
        const res = await store.api('/payroll/who-is-working');
        workingNow.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load working sessions:', e);
        workingNow.value = [];
    } finally {
        loadingWorkingSessions.value = false;
    }
}

async function loadTimesheet() {
    try {
        let url = `/payroll/timesheet?start_date=${timesheetFilter.value.startDate}&end_date=${timesheetFilter.value.endDate}`;
        if (timesheetFilter.value.userId) {
            url += `&user_id=${timesheetFilter.value.userId}`;
        }
        const res = await store.api(url);
        timesheetSessions.value = res.data?.sessions || res.sessions || res.data || [];
    } catch (e) {
        console.error('Failed to load timesheet:', e);
        timesheetSessions.value = [];
    }
}

function formatTime(datetime) {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

async function forceClockOut(session) {
    if (!confirm(`Завершить смену для ${session.user?.name}?`)) return;
    try {
        await store.api(`/payroll/clock-out`, {
            method: 'POST',
            body: JSON.stringify({ user_id: session.user_id })
        });
        store.showToast('Смена завершена', 'success');
        loadWorkingSessions();
        loadTimesheet();
    } catch (e) {
        store.showToast('Ошибка завершения смены', 'error');
    }
}

// Init
onMounted(() => {
    loadPayroll();
    loadStaff();
    loadWorkingSessions();
    loadTimesheet();
});
</script>
