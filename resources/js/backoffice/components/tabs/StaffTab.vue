<template>
    <div>
        <!-- Sub-tabs -->
        <div class="flex gap-2 mb-6 border-b">
            <button @click="subTab = 'employees'"
                    :class="['px-4 py-2 font-medium transition border-b-2 -mb-px',
                             subTab === 'employees' ? 'text-orange-600 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                Сотрудники
            </button>
            <button @click="subTab = 'schedule'; loadSchedule()"
                    :class="['px-4 py-2 font-medium transition border-b-2 -mb-px',
                             subTab === 'schedule' ? 'text-orange-600 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                Расписание
            </button>
            <button @click="subTab = 'roles'; loadRoles()"
                    :class="['px-4 py-2 font-medium transition border-b-2 -mb-px',
                             subTab === 'roles' ? 'text-orange-600 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                Роли и права
            </button>
            <button @click="subTab = 'invitations'; loadInvitations()"
                    :class="['px-4 py-2 font-medium transition border-b-2 -mb-px',
                             subTab === 'invitations' ? 'text-orange-600 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                Приглашения
                <span v-if="pendingInvitations > 0" class="ml-1 bg-orange-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ pendingInvitations }}</span>
            </button>
            <button @click="subTab = 'timesheet'; loadTimesheet()"
                    :class="['px-4 py-2 font-medium transition border-b-2 -mb-px',
                             subTab === 'timesheet' ? 'text-orange-600 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                Табель
            </button>
            <button @click="subTab = 'payroll'; payrollView = 'periods'; loadSalaryPeriods()"
                    :class="['px-4 py-2 font-medium transition border-b-2 -mb-px',
                             subTab === 'payroll' ? 'text-orange-600 border-orange-500' : 'text-gray-500 border-transparent hover:text-gray-700']">
                Зарплата
            </button>
        </div>

        <!-- ========== TAB: Employees ========== -->
        <div v-if="subTab === 'employees'">
            <!-- Staff Stats Filter -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4 text-center cursor-pointer transition hover:shadow-md"
                     :class="staffFilter === 'all' ? 'ring-2 ring-orange-500' : ''"
                     @click="staffFilter = 'all'">
                    <div class="text-2xl font-bold text-gray-900">{{ store.staff.length }}</div>
                    <div class="text-sm text-gray-500">Всего</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center cursor-pointer transition hover:shadow-md"
                     :class="staffFilter === 'waiter' ? 'ring-2 ring-blue-500' : ''"
                     @click="staffFilter = 'waiter'">
                    <div class="text-2xl font-bold text-blue-600">{{ store.staff.filter(s => s.role?.startsWith('waiter')).length }}</div>
                    <div class="text-sm text-gray-500">Официанты</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center cursor-pointer transition hover:shadow-md"
                     :class="staffFilter === 'cook' ? 'ring-2 ring-yellow-500' : ''"
                     @click="staffFilter = 'cook'">
                    <div class="text-2xl font-bold text-yellow-600">{{ store.staff.filter(s => s.role?.startsWith('cook')).length }}</div>
                    <div class="text-sm text-gray-500">Повара</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center cursor-pointer transition hover:shadow-md"
                     :class="staffFilter === 'cashier' ? 'ring-2 ring-green-500' : ''"
                     @click="staffFilter = 'cashier'">
                    <div class="text-2xl font-bold text-green-600">{{ store.staff.filter(s => s.role?.startsWith('cashier')).length }}</div>
                    <div class="text-sm text-gray-500">Кассиры</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center cursor-pointer transition hover:shadow-md"
                     :class="staffFilter === 'admin' ? 'ring-2 ring-purple-500' : ''"
                     @click="staffFilter = 'admin'">
                    <div class="text-2xl font-bold text-purple-600">{{ store.staff.filter(s => matchesRoles(s.role, ['super_admin', 'owner', 'admin', 'manager'])).length }}</div>
                    <div class="text-sm text-gray-500">Управление</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center cursor-pointer transition hover:shadow-md"
                     :class="staffFilter === 'service' ? 'ring-2 ring-pink-500' : ''"
                     @click="staffFilter = 'service'">
                    <div class="text-2xl font-bold text-pink-600">{{ store.staff.filter(s => matchesRoles(s.role, ['courier', 'hostess'])).length }}</div>
                    <div class="text-sm text-gray-500">Сервис</div>
                </div>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <h3 class="text-lg font-semibold">Сотрудники</h3>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" v-model="showInactive" class="w-4 h-4 accent-orange-500">
                        Показать неактивных
                    </label>
                </div>
                <button v-can="'staff.edit'" @click="openStaffModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Добавить
                </button>
            </div>

            <!-- Staff Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Часы</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Заказы</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Статус</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <!-- Loading -->
                        <template v-if="store.loading.staff">
                            <tr v-for="i in 5" :key="i" class="animate-pulse">
                                <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
                                <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-20"></div></td>
                                <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-12 mx-auto"></div></td>
                                <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-12 mx-auto"></div></td>
                                <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-16 mx-auto"></div></td>
                                <td class="px-6 py-4"><div class="h-4 bg-gray-200 rounded w-20 ml-auto"></div></td>
                            </tr>
                        </template>

                        <!-- Data -->
                        <template v-else>
                            <tr v-for="staff in filteredStaff" :key="staff.id"
                                class="hover:bg-gray-50 transition"
                                :class="!staff.is_active ? 'opacity-50' : ''">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-semibold text-white"
                                             :style="getRoleAvatarStyle(staff.role)">
                                            {{ staff.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ staff.name }}</div>
                                            <div v-if="staff.is_working" class="text-xs text-green-600">На смене</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="['px-2 py-1 rounded text-xs font-medium', getRoleBadgeClass(staff.role)]"
                                          :style="getRoleBadgeStyle(staff.role)">
                                        {{ roleIcon(staff.role) }} {{ roleLabel(staff.role) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="text-sm font-medium text-gray-900">{{ staff.month_hours_worked || 0 }}ч</div>
                                    <div class="text-xs text-gray-500">в месяц</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="text-sm font-medium text-gray-900">{{ staff.month_orders_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">{{ formatMoney(staff.month_orders_sum || 0) }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span v-if="staff.is_active" class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">
                                            Активен
                                        </span>
                                        <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-medium">
                                            Неактивен
                                        </span>
                                        <div class="flex items-center gap-1 text-xs">
                                            <span v-if="staff.has_password" class="text-green-500" title="Пароль установлен">🔑</span>
                                            <span v-else class="text-gray-300" title="Нет пароля">🔑</span>
                                            <span v-if="staff.has_pin" class="text-green-500" title="PIN установлен">📱</span>
                                            <span v-else class="text-gray-300" title="Нет PIN">📱</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openStaffModal(staff)"
                                                class="p-1.5 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded transition"
                                                title="Редактировать">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                        </button>
                                        <button @click="openDevicesModal(staff)"
                                                class="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded transition"
                                                title="Доступ к устройствам">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke-width="2"></rect>
                                                <line x1="8" y1="21" x2="16" y2="21" stroke-width="2"></line>
                                                <line x1="12" y1="17" x2="12" y2="21" stroke-width="2"></line>
                                            </svg>
                                        </button>
                                        <button @click="toggleActive(staff)"
                                                :class="['p-1.5 rounded transition', staff.is_active ? 'text-gray-400 hover:text-yellow-500 hover:bg-yellow-50' : 'text-gray-400 hover:text-green-500 hover:bg-green-50']"
                                                :title="staff.is_active ? 'Деактивировать' : 'Активировать'">
                                            <svg v-if="staff.is_active" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            </svg>
                                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                        <button v-if="!staff.has_password && !staff.pending_invitation"
                                                @click="sendInvite(staff)"
                                                class="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded transition"
                                                title="Отправить приглашение">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <!-- Empty State -->
                        <tr v-if="!store.loading.staff && filteredStaff.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-5xl mb-3">👥</div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-1">Нет сотрудников</h3>
                                <p class="text-gray-500 mb-4">Добавьте первого сотрудника для начала работы</p>
                                <button @click="openStaffModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                                    + Добавить сотрудника
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========== TAB: Schedule ========== -->
        <div v-if="subTab === 'schedule'">
            <!-- Header with navigation and actions -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <button @click="changeWeek(-1)" class="p-2 hover:bg-gray-100 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <h3 class="text-lg font-semibold">{{ weekLabel }}</h3>
                    <button @click="changeWeek(1)" class="p-2 hover:bg-gray-100 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <button @click="goToday()" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                        Сегодня
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="copyFromPrevWeek" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        Скопировать с прошлой недели
                    </button>
                    <button v-if="scheduleStats.draft_count > 0"
                            @click="publishWeek"
                            class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Опубликовать ({{ scheduleStats.draft_count }})
                    </button>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="grid grid-cols-4 gap-4 mb-4">
                <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                    <div class="text-xl font-bold text-gray-900">{{ scheduleStats.total_shifts || 0 }}</div>
                    <div class="text-xs text-gray-500">Всего смен</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                    <div class="text-xl font-bold text-gray-900">{{ scheduleStats.total_hours || 0 }}</div>
                    <div class="text-xs text-gray-500">Часов</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                    <div class="text-xl font-bold text-green-600">{{ scheduleStats.published_count || 0 }}</div>
                    <div class="text-xs text-gray-500">Опубликовано</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                    <div class="text-xl font-bold text-yellow-600">{{ scheduleStats.draft_count || 0 }}</div>
                    <div class="text-xs text-gray-500">Черновики</div>
                </div>
            </div>

            <!-- Schedule Grid -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-700 w-48">Сотрудник</th>
                                <th v-for="day in scheduleDays" :key="day.date"
                                    class="px-2 py-3 text-center text-sm font-medium min-w-[100px]"
                                    :class="day.isToday ? 'bg-orange-50 text-orange-700' : 'text-gray-700'">
                                    <div>{{ day.dayName }}</div>
                                    <div class="text-xs font-normal">{{ day.dateLabel }}</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in scheduleData" :key="row.user.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold text-white"
                                             :style="getRoleAvatarStyle(row.user.role)">
                                            {{ row.user.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 text-sm">{{ row.user.name }}</div>
                                            <div class="text-xs text-gray-500">{{ roleLabel(row.user.role) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td v-for="day in row.days" :key="day.date"
                                    class="px-2 py-2 text-center"
                                    :class="isToday(day.date) ? 'bg-orange-50/50' : ''">
                                    <div v-if="day.shift"
                                         :class="['rounded-lg px-2 py-1 text-xs font-medium cursor-pointer transition',
                                                   day.shift.status === 'published' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200 border border-dashed border-yellow-400']"
                                         @click="openShiftModal(day.shift, row.user)">
                                        {{ day.shift.start_time?.slice(0,5) }}-{{ day.shift.end_time?.slice(0,5) }}
                                        <span v-if="day.shift.status === 'draft'" class="block text-[10px] opacity-70">черновик</span>
                                    </div>
                                    <button v-else
                                            @click="openShiftModal(null, row.user, day.date)"
                                            class="w-full py-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded text-lg">
                                        +
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="scheduleData.length === 0">
                                <td :colspan="8" class="px-4 py-12 text-center text-gray-400">
                                    Нет сотрудников для отображения расписания
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Templates section -->
            <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-medium text-gray-700">Шаблоны смен</h4>
                    <button @click="openTemplateModal()" class="text-sm text-orange-600 hover:text-orange-700">
                        + Добавить шаблон
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div v-for="tpl in scheduleTemplates" :key="tpl.id"
                         class="px-3 py-2 rounded-lg text-sm cursor-pointer hover:shadow transition"
                         :style="{ backgroundColor: tpl.color + '20', borderLeft: '3px solid ' + tpl.color }"
                         @click="openTemplateModal(tpl)">
                        <span class="font-medium">{{ tpl.name }}</span>
                        <span class="text-gray-500 ml-2">{{ tpl.start_time?.slice(0,5) }}-{{ tpl.end_time?.slice(0,5) }}</span>
                    </div>
                    <div v-if="scheduleTemplates.length === 0" class="text-gray-400 text-sm py-2">
                        Нет шаблонов. Создайте первый для быстрого добавления смен.
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== TAB: Roles ========== -->
        <div v-if="subTab === 'roles'">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold mb-1">Роли и права доступа</h3>
                    <p class="text-gray-500 text-sm">Настройте права доступа и лимиты для каждой роли сотрудников</p>
                </div>
                <button @click="openRoleModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Добавить роль
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="role in roles" :key="role.id || role.key"
                     class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow border-l-4"
                     :style="{ borderLeftColor: role.color || '#6b7280' }">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-lg shadow-sm"
                                 :style="{ backgroundColor: role.color || '#6b7280' }">
                                {{ role.icon || '👤' }}
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">{{ role.name || role.label }}</h4>
                                <p class="text-xs text-gray-500">{{ role.users_count || 0 }} сотрудников</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <span v-if="role.is_system" class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
                                Системная
                            </span>
                            <button @click="openRoleModal(role)" class="p-1.5 hover:bg-gray-100 rounded-lg transition" title="Редактировать">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="role.description" class="text-sm text-gray-500 mb-3">{{ role.description }}</div>

                    <!-- Interface Access Icons -->
                    <div class="flex items-center gap-2 mb-3">
                        <span v-if="role.can_access_pos" class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-xs flex items-center gap-1" title="POS терминал">
                            🖥️ POS
                        </span>
                        <span v-if="role.can_access_backoffice" class="px-2 py-1 bg-purple-50 text-purple-600 rounded text-xs flex items-center gap-1" title="Бэк-офис">
                            📊 Офис
                        </span>
                        <span v-if="role.can_access_kitchen" class="px-2 py-1 bg-orange-50 text-orange-600 rounded text-xs flex items-center gap-1" title="Кухня">
                            👨‍🍳 Кухня
                        </span>
                        <span v-if="role.can_access_delivery" class="px-2 py-1 bg-green-50 text-green-600 rounded text-xs flex items-center gap-1" title="Доставка">
                            🚴 Доставка
                        </span>
                    </div>

                    <!-- Limits -->
                    <div class="grid grid-cols-3 gap-2 mb-3 text-center">
                        <div class="bg-gray-50 rounded-lg p-2">
                            <div class="text-lg font-bold" :class="role.max_discount_percent > 0 ? 'text-green-600' : 'text-gray-400'">
                                {{ role.max_discount_percent || 0 }}%
                            </div>
                            <div class="text-[10px] text-gray-500 uppercase">Скидка</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2">
                            <div class="text-lg font-bold" :class="role.max_refund_amount > 0 ? 'text-amber-600' : 'text-gray-400'">
                                {{ formatLimit(role.max_refund_amount) }}
                            </div>
                            <div class="text-[10px] text-gray-500 uppercase">Возврат</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2">
                            <div class="text-lg font-bold" :class="role.max_cancel_amount > 0 ? 'text-red-600' : 'text-gray-400'">
                                {{ formatLimit(role.max_cancel_amount) }}
                            </div>
                            <div class="text-[10px] text-gray-500 uppercase">Отмена</div>
                        </div>
                    </div>

                    <!-- Manager Confirm Badge -->
                    <div v-if="role.require_manager_confirm" class="flex items-center gap-1 text-xs text-amber-600 bg-amber-50 rounded-lg px-2 py-1 mb-3">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Требуется подтверждение менеджера
                    </div>

                    <!-- Permissions summary -->
                    <div class="mb-3">
                        <div class="flex flex-wrap gap-1">
                            <template v-if="(role.permissions_list || role.permissions || []).includes('*')">
                                <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">
                                    Полный доступ
                                </span>
                            </template>
                            <template v-else>
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">
                                    {{ (role.permissions_list || role.permissions || []).length }} прав
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-3 border-t">
                        <button @click="openRoleModal(role)" class="flex-1 px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                            Редактировать
                        </button>
                        <button v-if="!role.is_system" @click="cloneRole(role)" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="Дублировать">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </button>
                        <button v-if="!role.is_system && (role.users_count || 0) === 0" v-can="'staff.delete'" @click="deleteRole(role)"
                                class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition" title="Удалить">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty State for Roles -->
                <div v-if="roles.length === 0" class="col-span-full text-center py-12 bg-white rounded-xl shadow-sm">
                    <div class="text-5xl mb-3">🎭</div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Нет ролей</h3>
                    <p class="text-gray-500 mb-4">Создайте роли для настройки прав доступа сотрудников</p>
                    <button @click="createDefaultRoles" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                        Создать базовые роли
                    </button>
                </div>
            </div>
        </div>

        <!-- ========== TAB: Invitations ========== -->
        <div v-if="subTab === 'invitations'">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold">Приглашения сотрудников</h3>
                <button @click="openInviteModal()" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Создать приглашение
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Создано</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="inv in invitations" :key="inv.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium">{{ inv.name }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ inv.email }}</td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-0.5 rounded text-xs font-medium', getRoleBadgeClass(inv.role)]">
                                    {{ roleLabel(inv.role) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-1 rounded text-xs font-medium',
                                              inv.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                                              inv.status === 'accepted' ? 'bg-green-100 text-green-700' :
                                              'bg-red-100 text-red-700']">
                                    {{ inv.status === 'pending' ? 'Ожидает' : inv.status === 'accepted' ? 'Принято' : 'Истекло' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm">{{ formatDate(inv.created_at) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button v-if="inv.status === 'pending'"
                                            @click="copyInvitationLink(inv)"
                                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                                            title="Копировать ссылку">
                                        📋 Ссылка
                                    </button>
                                    <button v-if="inv.status === 'pending'"
                                            @click="resendInvite(inv)"
                                            class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                                        🔄 Обновить
                                    </button>
                                    <button v-if="inv.status === 'pending'"
                                            @click="cancelInvite(inv)"
                                            class="text-sm text-red-600 hover:text-red-700 font-medium">
                                        ❌
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="invitations.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                Нет активных приглашений
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========== TAB: Timesheet ========== -->
        <div v-if="subTab === 'timesheet'" class="space-y-6">
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
                                <p class="text-xs text-green-600">{{ session.user?.role_label || getRoleLabel(session.user?.role) }}</p>
                            </div>
                        </div>
                        <div class="text-xs text-green-600 mt-2">
                            <span>С {{ formatShiftTime(session.clock_in) }}</span>
                            <span class="float-right font-medium">{{ session.duration_formatted || calculateDuration(session.clock_in) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex items-center gap-4 flex-wrap">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Сотрудник</label>
                        <select v-model="timesheetFilter.userId" @change="loadTimesheet"
                                class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                            <option :value="null">Все сотрудники</option>
                            <option v-for="s in store.staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">С даты</label>
                        <input type="date" v-model="timesheetFilter.startDate" @change="loadTimesheet"
                               class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">По дату</label>
                        <input type="date" v-model="timesheetFilter.endDate" @change="loadTimesheet"
                               class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500" />
                    </div>
                    <div class="flex-1"></div>
                    <div class="bg-gray-100 rounded-lg px-4 py-2 text-center">
                        <div class="text-2xl font-bold text-gray-800">{{ totalTimesheetHours }}</div>
                        <div class="text-xs text-gray-500">часов за период</div>
                    </div>
                </div>
            </div>

            <!-- Sessions Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
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
                                        <p class="text-xs text-gray-500">{{ session.user?.role_label || getRoleLabel(session.user?.role) }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ formatSessionDate(session.clock_in) }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ formatShiftTime(session.clock_in) }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ session.clock_out ? formatShiftTime(session.clock_out) : '—' }}</td>
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
                                        class="text-red-500 hover:text-red-700 text-sm font-medium">
                                    Завершить
                                </button>
                            </td>
                        </tr>
                        <tr v-if="timesheetSessions.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                Нет записей за выбранный период
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========== TAB: Payroll ========== -->
        <div v-if="subTab === 'payroll'">
            <!-- View Toggle -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2 bg-gray-100 rounded-lg p-1">
                    <button @click="payrollView = 'periods'; loadSalaryPeriods()"
                            :class="['px-4 py-2 rounded-md text-sm font-medium transition',
                                     payrollView === 'periods' ? 'bg-white text-orange-600 shadow' : 'text-gray-600 hover:text-gray-900']">
                        Расчётные периоды
                    </button>
                    <button @click="payrollView = 'payments'; loadPayroll()"
                            :class="['px-4 py-2 rounded-md text-sm font-medium transition',
                                     payrollView === 'payments' ? 'bg-white text-orange-600 shadow' : 'text-gray-600 hover:text-gray-900']">
                        История платежей
                    </button>
                </div>
                <div class="flex items-center gap-4">
                    <select v-model="selectedMonth" @change="payrollView === 'payments' ? loadPayroll() : null"
                            class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <select v-model="selectedYear" @change="payrollView === 'payments' ? loadPayroll() : null"
                            class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- ===== SALARY PERIODS VIEW ===== -->
            <template v-if="payrollView === 'periods'">
                <!-- Create Period Button -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Расчётные периоды</h3>
                    <button @click="createSalaryPeriod" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                        + Создать период за {{ months.find(m => m.value === selectedMonth)?.label }} {{ selectedYear }}
                    </button>
                </div>

                <!-- Periods List -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Период</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Сотрудников</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Сумма</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Статус</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="period in salaryPeriods" :key="period.id"
                                class="hover:bg-gray-50 cursor-pointer"
                                @click="openPeriodDetails(period)">
                                <td class="px-6 py-4">
                                    <div class="font-medium">{{ period.name }}</div>
                                    <div class="text-sm text-gray-500">{{ period.period_label }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-lg font-semibold">{{ period.calculations_count || 0 }}</span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-green-600">
                                    {{ formatMoney(period.total_amount || 0) }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusColor(period.status)]">
                                        {{ getStatusLabel(period.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="text-orange-600 hover:text-orange-700 font-medium text-sm">
                                        Открыть &rarr;
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="salaryPeriods.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                                    Расчётных периодов пока нет. Создайте первый период.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- ===== PAYMENTS VIEW ===== -->
            <template v-else>
                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 mb-4">
                    <button @click="addPayment('bonus')" class="px-4 py-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg font-medium transition">
                        + Премия
                    </button>
                    <button @click="addPayment('advance')" class="px-4 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg font-medium transition">
                        + Аванс
                    </button>
                    <button @click="addPayment('penalty')" class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-medium transition">
                        + Штраф
                    </button>
                </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-blue-600 mb-1">Сотрудников</p>
                            <p class="text-2xl font-bold text-blue-900">{{ store.staff.filter(s => s.is_active).length }}</p>
                        </div>
                        <span class="text-3xl">👥</span>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-green-600 mb-1">Выплачено</p>
                            <p class="text-2xl font-bold text-green-900">{{ formatMoney(paidTotal) }}</p>
                        </div>
                        <span class="text-3xl">💰</span>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-yellow-600 mb-1">Ожидает</p>
                            <p class="text-2xl font-bold text-yellow-900">{{ formatMoney(pendingTotal) }}</p>
                        </div>
                        <span class="text-3xl">⏳</span>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-purple-600 mb-1">Всего за период</p>
                            <p class="text-2xl font-bold text-purple-900">{{ formatMoney(paidTotal + pendingTotal) }}</p>
                        </div>
                        <span class="text-3xl">📊</span>
                    </div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold">История начислений и выплат</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Дата</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Сумма</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Описание</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="payment in salaryPayments" :key="payment.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm">{{ formatDate(payment.created_at) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold text-white"
                                         :style="getRoleAvatarStyle(payment.user?.role)">
                                        {{ payment.user?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <span class="font-medium">{{ payment.user?.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-1 rounded text-xs font-medium', getPaymentTypeClass(payment.type)]">
                                    {{ getPaymentTypeLabel(payment.type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-bold" :class="payment.type === 'penalty' ? 'text-red-600' : 'text-green-600'">
                                {{ payment.type === 'penalty' ? '-' : '+' }}{{ formatMoney(payment.amount) }}
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-1 rounded text-xs font-medium',
                                              payment.status === 'paid' ? 'bg-green-100 text-green-700' :
                                              payment.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700']">
                                    {{ payment.status === 'paid' ? 'Выплачено' : payment.status === 'pending' ? 'Ожидает' : 'Отменено' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm max-w-[200px] truncate">{{ payment.description || '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                <button v-if="payment.status === 'pending'" @click="markPaymentPaid(payment)"
                                        class="text-green-600 hover:text-green-700 mr-2" title="Выплатить">✅</button>
                                <button v-if="payment.status === 'pending'" @click="cancelPayment(payment)"
                                        class="text-red-600 hover:text-red-700" title="Отменить">❌</button>
                            </td>
                        </tr>
                        <tr v-if="salaryPayments.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                Нет начислений за выбранный период
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </template>
        </div>

        <!-- ========== Salary Period Details Modal ========== -->
        <Teleport to="body">
            <div v-if="showPeriodDetails" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPeriodDetails = false">
                <div class="bg-white rounded-2xl w-[900px] max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">{{ currentPeriod?.name }}</h3>
                            <p class="text-sm text-gray-500">{{ currentPeriod?.period_label }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="['px-3 py-1 rounded-full text-sm font-medium', getStatusColor(currentPeriod?.status)]">
                                {{ getStatusLabel(currentPeriod?.status) }}
                            </span>
                            <button @click="showPeriodDetails = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                    </div>

                    <!-- Actions Bar -->
                    <div class="p-4 bg-gray-50 border-b flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button v-if="currentPeriod?.status === 'draft' || currentPeriod?.status === 'calculated'"
                                    @click="calculatePeriod"
                                    :disabled="calculatingSalary"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition disabled:opacity-50">
                                {{ calculatingSalary ? 'Расчёт...' : 'Рассчитать зарплаты' }}
                            </button>
                            <button v-if="currentPeriod?.status === 'calculated'"
                                    @click="approvePeriod"
                                    class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                                Утвердить
                            </button>
                            <button v-if="currentPeriod?.status === 'approved'"
                                    @click="payAllPeriod"
                                    class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition">
                                Выплатить всё
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="addBonusOrPenalty('bonus')" class="px-3 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-sm font-medium transition">
                                + Премия
                            </button>
                            <button @click="addBonusOrPenalty('penalty')" class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-sm font-medium transition">
                                + Штраф
                            </button>
                            <button @click="payAdvance" class="px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg text-sm font-medium transition">
                                + Аванс
                            </button>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="p-4 grid grid-cols-4 gap-4 border-b">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ periodCalculations.length }}</div>
                            <div class="text-sm text-gray-500">Сотрудников</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ formatMoney(currentPeriod?.total_amount || 0) }}</div>
                            <div class="text-sm text-gray-500">К выплате</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ formatMoney(periodCalculations.reduce((s, c) => s + (parseFloat(c.paid_amount) || 0), 0)) }}</div>
                            <div class="text-sm text-gray-500">Выплачено</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">{{ formatMoney(periodCalculations.reduce((s, c) => s + (parseFloat(c.balance) || 0), 0)) }}</div>
                            <div class="text-sm text-gray-500">Остаток</div>
                        </div>
                    </div>

                    <!-- Calculations Table -->
                    <div class="overflow-y-auto max-h-[400px]">
                        <table class="w-full">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Сотрудник</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Часы</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Оклад</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Бонусы</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Штрафы</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Итого</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Выплачено</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Остаток</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="calc in periodCalculations" :key="calc.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold text-white"
                                                 :style="getRoleAvatarStyle(calc.user?.role)">
                                                {{ calc.user?.name?.charAt(0)?.toUpperCase() }}
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm">{{ calc.user?.name }}</div>
                                                <div class="text-xs text-gray-500">{{ calc.salary_type_label }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        {{ calc.hours_worked || 0 }}ч / {{ calc.days_worked || 0 }}д
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        {{ formatMoney(parseFloat(calc.base_amount || 0) + parseFloat(calc.hourly_amount || 0) + parseFloat(calc.percent_amount || 0)) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-green-600">
                                        +{{ formatMoney(calc.bonus_amount || 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-red-600">
                                        -{{ formatMoney(calc.penalty_amount || 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold">
                                        {{ formatMoney(calc.net_amount || 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-green-600">
                                        {{ formatMoney(calc.paid_amount || 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm" :class="parseFloat(calc.balance) > 0 ? 'text-yellow-600 font-medium' : 'text-gray-400'">
                                        {{ formatMoney(calc.balance || 0) }}
                                    </td>
                                </tr>
                                <tr v-if="periodCalculations.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                                        <template v-if="loadingPeriod">Загрузка...</template>
                                        <template v-else>Нажмите "Рассчитать зарплаты" для начала расчёта</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Staff Modal ========== -->
        <Teleport to="body">
            <div v-if="showStaffModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showStaffModal = false">
                <div class="bg-white rounded-2xl w-[600px] max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ staffForm.id ? 'Редактировать сотрудника' : 'Новый сотрудник' }}</h3>
                        <button @click="showStaffModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto max-h-[65vh]">
                        <!-- Ошибка сохранения -->
                        <div v-if="saveError" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                            {{ saveError }}
                        </div>
                        <!-- Основная информация -->
                        <div class="pb-4 border-b">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-3">Основная информация</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ФИО *</label>
                                    <input v-model="staffForm.name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Иванов Иван Иванович">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                        <input v-model="staffForm.email" type="email" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="email@example.com">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Телефон</label>
                                        <input v-model="staffForm.phone" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="+7 999 123-45-67">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Дата рождения</label>
                                        <input v-model="staffForm.birth_date" type="date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Адрес</label>
                                        <input v-model="staffForm.address" type="text" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="г. Москва, ул. Примерная, 1">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Контакт для экстренной связи</label>
                                    <input v-model="staffForm.emergency_contact" type="text" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Имя, телефон">
                                </div>
                            </div>
                        </div>

                        <!-- Должность и доступ -->
                        <div class="pb-4 border-b">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-3">Должность и доступ</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Роль *</label>
                                    <select v-model="staffForm.role" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        <option value="">Выберите роль</option>
                                        <option v-for="role in activeRoles" :key="role.key" :value="role.key">
                                            {{ role.icon }} {{ role.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Дата найма</label>
                                        <input v-model="staffForm.hired_at" type="date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Статус</label>
                                        <div class="flex items-center gap-3 h-[42px]">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" v-model="staffForm.is_active" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-green-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                            </label>
                                            <span class="text-sm" :class="staffForm.is_active ? 'text-green-600' : 'text-gray-500'">
                                                {{ staffForm.is_active ? 'Активен' : 'Неактивен' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Аутентификация (Enterprise-level) -->
                        <div class="pb-4 border-b">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-3">Доступ к приложениям</h4>

                            <!-- Role hint -->
                            <div v-if="staffForm.role && currentRoleConfig.hint" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-700">{{ currentRoleConfig.hint }}</p>
                            </div>

                            <div class="space-y-4">
                                <!-- PIN Section -->
                                <div class="p-4 border rounded-lg" :class="staffForm.enable_pin ? 'border-orange-300 bg-orange-50' : 'border-gray-200'">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" v-model="staffForm.enable_pin" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                            </label>
                                            <div>
                                                <span class="font-medium text-gray-900">PIN-код для терминала</span>
                                                <p class="text-xs text-gray-500">Быстрый вход на POS-терминалах и Kitchen Display</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-if="staffForm.has_pin" class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full">Установлен</span>
                                            <span v-else-if="staffForm.id" class="text-xs px-2 py-1 bg-gray-100 text-gray-500 rounded-full">Не установлен</span>
                                        </div>
                                    </div>

                                    <div v-if="staffForm.enable_pin" class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ staffForm.has_pin ? 'Новый PIN-код (оставьте пустым для сохранения)' : 'PIN-код' }}
                                        </label>
                                        <div class="flex gap-2">
                                            <input v-model="staffForm.pin"
                                                   type="text"
                                                   maxlength="4"
                                                   pattern="[0-9]*"
                                                   inputmode="numeric"
                                                   @input="staffForm.pin = staffForm.pin.replace(/\D/g, '')"
                                                   class="w-32 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent font-mono text-lg tracking-widest text-center"
                                                   :placeholder="staffForm.has_pin ? '••••' : '1234'">
                                            <button v-if="staffForm.has_pin && !staffForm.pin"
                                                    @click="clearStaffPin"
                                                    type="button"
                                                    class="px-3 py-2 text-red-600 hover:bg-red-100 rounded-lg transition text-sm flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Удалить PIN
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">4 цифры для быстрой идентификации на терминале</p>
                                    </div>
                                </div>

                                <!-- Password Section -->
                                <div class="p-4 border rounded-lg" :class="staffForm.enable_password ? 'border-orange-300 bg-orange-50' : 'border-gray-200'">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" v-model="staffForm.enable_password" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                            </label>
                                            <div>
                                                <span class="font-medium text-gray-900">Полный доступ (логин + пароль)</span>
                                                <p class="text-xs text-gray-500">Для мобильных приложений и BackOffice</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-if="staffForm.has_password" class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full">Пароль установлен</span>
                                            <span v-else-if="staffForm.pending_invitation" class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">Ожидает приглашения</span>
                                            <span v-else-if="staffForm.id" class="text-xs px-2 py-1 bg-gray-100 text-gray-500 rounded-full">Не настроено</span>
                                        </div>
                                    </div>

                                    <div v-if="staffForm.enable_password" class="mt-3 space-y-3">
                                        <!-- Method selection (only for new employees without password) -->
                                        <div v-if="!staffForm.has_password">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Способ настройки</label>
                                            <div class="flex gap-2">
                                                <label class="flex-1">
                                                    <input type="radio" v-model="staffForm.password_method" value="invite" class="sr-only peer">
                                                    <div class="p-3 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-white hover:bg-gray-50">
                                                        <div class="font-medium">Пригласить</div>
                                                        <div class="text-xs text-gray-500">Отправить ссылку</div>
                                                    </div>
                                                </label>
                                                <label class="flex-1">
                                                    <input type="radio" v-model="staffForm.password_method" value="manual" class="sr-only peer">
                                                    <div class="p-3 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-white hover:bg-gray-50">
                                                        <div class="font-medium">Установить вручную</div>
                                                        <div class="text-xs text-gray-500">Задать логин и пароль</div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Invite info -->
                                        <div v-if="staffForm.password_method === 'invite' && !staffForm.has_password" class="p-3 bg-blue-50 rounded-lg">
                                            <p class="text-sm text-blue-700">
                                                После сохранения будет создана ссылка-приглашение. Сотрудник сам установит пароль.
                                                <span v-if="!staffForm.email" class="block mt-1 text-blue-600 font-medium">
                                                    Укажите email выше для отправки приглашения.
                                                </span>
                                            </p>
                                        </div>

                                        <!-- Manual password entry -->
                                        <div v-if="staffForm.password_method === 'manual' || staffForm.has_password" class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Логин</label>
                                                <input v-model="staffForm.login"
                                                       type="text"
                                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                                       :placeholder="staffForm.email || 'логин или email'">
                                                <p class="text-xs text-gray-500 mt-1">Если пусто - будет использован email</p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    {{ staffForm.has_password ? 'Новый пароль (оставьте пустым для сохранения)' : 'Пароль' }}
                                                </label>
                                                <input v-model="staffForm.password"
                                                       type="password"
                                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                                       :placeholder="staffForm.has_password ? '••••••••' : 'Минимум 6 символов'"
                                                       minlength="6">
                                            </div>
                                        </div>

                                        <!-- Reset password for existing -->
                                        <div v-if="staffForm.has_password && staffForm.id" class="pt-2 border-t">
                                            <button type="button"
                                                    @click="sendPasswordReset"
                                                    class="text-sm text-orange-600 hover:text-orange-700 flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                Отправить ссылку для сброса пароля
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Warning if no auth method selected -->
                                <div v-if="!staffForm.enable_pin && !staffForm.enable_password && staffForm.role" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <p class="text-sm text-yellow-700">
                                        Сотрудник не сможет войти в систему без PIN-кода или пароля.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Зарплата -->
                        <div class="pb-4 border-b">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase mb-3">Оплата труда</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Тип оплаты</label>
                                    <div class="flex gap-2">
                                        <label class="flex-1">
                                            <input type="radio" v-model="staffForm.salary_type" value="fixed" class="sr-only peer">
                                            <div class="p-3 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                                Фиксированная
                                            </div>
                                        </label>
                                        <label class="flex-1">
                                            <input type="radio" v-model="staffForm.salary_type" value="hourly" class="sr-only peer">
                                            <div class="p-3 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                                Почасовая
                                            </div>
                                        </label>
                                        <label class="flex-1">
                                            <input type="radio" v-model="staffForm.salary_type" value="percent" class="sr-only peer">
                                            <div class="p-3 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                                % от продаж
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Оклад (в месяц)</label>
                                        <input v-model.number="staffForm.salary" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="50000" :disabled="staffForm.salary_type !== 'fixed'">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Ставка в час</label>
                                        <input v-model.number="staffForm.hourly_rate" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="300" :disabled="staffForm.salary_type !== 'hourly'">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">% от продаж</label>
                                        <input v-model.number="staffForm.sales_percent" type="number" step="0.1" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="5" :disabled="staffForm.salary_type !== 'percent'">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Номер банковской карты</label>
                                    <input v-model="staffForm.bank_card" type="text" maxlength="19" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="0000 0000 0000 0000">
                                </div>
                            </div>
                        </div>

                        <!-- Увольнение (только для существующих) -->
                        <div v-if="staffForm.id && staffForm.is_active" class="pb-2">
                            <h4 class="text-sm font-semibold text-red-500 uppercase mb-3">Увольнение</h4>
                            <p class="text-sm text-gray-500 mb-3">При увольнении сотрудник будет деактивирован и не сможет работать в системе.</p>
                            <button @click="fireEmployee" class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Уволить сотрудника
                            </button>
                        </div>

                        <!-- Информация об увольнении -->
                        <div v-if="staffForm.id && staffForm.fired_at" class="p-4 bg-red-50 rounded-lg">
                            <p class="text-sm text-red-700">
                                <span class="font-medium">Уволен:</span> {{ formatDate(staffForm.fired_at) }}
                                <span v-if="staffForm.fire_reason" class="block mt-1">
                                    <span class="font-medium">Причина:</span> {{ staffForm.fire_reason }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="showStaffModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveStaff" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition" :disabled="saving">
                            {{ saving ? 'Сохранение...' : (staffForm.id ? 'Сохранить' : 'Создать') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Fire Confirmation Modal ========== -->
        <Teleport to="body">
            <div v-if="showFireModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="showFireModal = false">
                <div class="bg-white rounded-2xl w-[400px] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b bg-red-50">
                        <h3 class="text-lg font-semibold text-red-700">Подтверждение увольнения</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-gray-600">Вы уверены, что хотите уволить сотрудника <strong>{{ staffForm.name }}</strong>?</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Причина увольнения</label>
                            <textarea v-model="fireReason" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Укажите причину увольнения..."></textarea>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="showFireModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="confirmFire" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                            Уволить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Shift Modal ========== -->
        <Teleport to="body">
            <div v-if="showShiftModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showShiftModal = false">
                <div class="bg-white rounded-2xl w-[400px] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ shiftForm.id ? 'Редактировать смену' : 'Добавить смену' }}</h3>
                        <button @click="showShiftModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Сотрудник</label>
                            <div class="px-4 py-2 bg-gray-100 rounded-lg">{{ shiftForm.userName }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Дата</label>
                            <input v-model="shiftForm.date" type="date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Начало</label>
                                <input v-model="shiftForm.start_time" type="time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Конец</label>
                                <input v-model="shiftForm.end_time" type="time" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-between">
                        <button v-if="shiftForm.id" v-can="'staff.delete'" @click="deleteShift" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                            Удалить
                        </button>
                        <div class="flex gap-3 ml-auto">
                            <button @click="showShiftModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                            <button @click="saveShift" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                                {{ shiftForm.id ? 'Сохранить' : 'Создать' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Template Modal ========== -->
        <Teleport to="body">
            <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showTemplateModal = false">
                <div class="bg-white rounded-2xl w-[400px] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ templateForm.id ? 'Редактировать шаблон' : 'Новый шаблон' }}</h3>
                        <button @click="showTemplateModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название</label>
                            <input v-model="templateForm.name" type="text" placeholder="Утренняя смена"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Начало</label>
                                <input v-model="templateForm.start_time" type="time"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Конец</label>
                                <input v-model="templateForm.end_time" type="time"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Перерыв (минут)</label>
                            <input v-model.number="templateForm.break_minutes" type="number" min="0" max="120"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Цвет</label>
                            <div class="flex gap-2">
                                <input v-model="templateForm.color" type="color" class="w-10 h-10 rounded cursor-pointer">
                                <input v-model="templateForm.color" type="text" class="flex-1 px-4 py-2 border rounded-lg">
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-between">
                        <button v-if="templateForm.id" v-can="'staff.delete'" @click="deleteTemplate" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                            Удалить
                        </button>
                        <div class="flex gap-3 ml-auto">
                            <button @click="showTemplateModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                            <button @click="saveTemplate" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                                {{ templateForm.id ? 'Сохранить' : 'Создать' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Role Modal (Enhanced with Tabs) ========== -->
        <Teleport to="body">
            <div v-if="showRoleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showRoleModal = false">
                <div class="bg-white rounded-2xl w-[700px] max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ roleForm.id ? 'Редактировать роль' : 'Новая роль' }}</h3>
                        <button @click="showRoleModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>

                    <!-- Role Modal Tabs -->
                    <div class="border-b px-6">
                        <nav class="flex gap-4">
                            <button @click="roleModalTab = 'basic'"
                                    :class="['py-3 px-1 border-b-2 text-sm font-medium transition',
                                             roleModalTab === 'basic' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                                Основное
                            </button>
                            <button @click="roleModalTab = 'limits'"
                                    :class="['py-3 px-1 border-b-2 text-sm font-medium transition',
                                             roleModalTab === 'limits' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                                Лимиты
                            </button>
                            <button @click="roleModalTab = 'access'"
                                    :class="['py-3 px-1 border-b-2 text-sm font-medium transition',
                                             roleModalTab === 'access' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                                Доступ
                            </button>
                            <button @click="roleModalTab = 'permissions'"
                                    :class="['py-3 px-1 border-b-2 text-sm font-medium transition',
                                             roleModalTab === 'permissions' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                                Права
                            </button>
                        </nav>
                    </div>

                    <div class="p-6 overflow-y-auto max-h-[55vh]">
                        <!-- Basic Tab -->
                        <div v-if="roleModalTab === 'basic'" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Название роли *</label>
                                <input v-model="roleForm.name" @input="autoGenerateKey" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Например: Старший официант">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                                <textarea v-model="roleForm.description" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Иконка</label>
                                    <input v-model="roleForm.icon" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="👤">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Цвет</label>
                                    <input v-model="roleForm.color" type="color" class="w-full h-[42px] border rounded-lg cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <!-- Limits Tab -->
                        <div v-if="roleModalTab === 'limits'" class="space-y-5">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-sm text-blue-700">Здесь вы можете настроить лимиты операций для сотрудников с этой ролью. Если сотрудник превысит лимит, потребуется подтверждение менеджера.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Максимальная скидка (%)</label>
                                <div class="flex items-center gap-3">
                                    <input v-model.number="roleForm.max_discount_percent" type="range" min="0" max="100" class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-orange-500">
                                    <span class="w-12 text-center font-semibold">{{ roleForm.max_discount_percent }}%</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">0% = нельзя давать скидки, 100% = любая скидка</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Максимальная сумма возврата</label>
                                <div class="flex items-center gap-2">
                                    <input v-model.number="roleForm.max_refund_amount" type="number" min="0" step="1000"
                                           class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <span class="text-gray-500">тг</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">0 = нельзя делать возвраты</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Максимальная сумма отмены заказа</label>
                                <div class="flex items-center gap-2">
                                    <input v-model.number="roleForm.max_cancel_amount" type="number" min="0" step="1000"
                                           class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <span class="text-gray-500">тг</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">0 = нельзя отменять заказы</p>
                            </div>

                            <div class="pt-3 border-t">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="roleForm.require_manager_confirm" class="w-5 h-5 accent-orange-500 rounded">
                                    <div>
                                        <span class="font-medium text-gray-900">Требуется подтверждение менеджера</span>
                                        <p class="text-xs text-gray-500">Для операций с лимитами потребуется ввод PIN менеджера</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Access Tab -->
                        <div v-if="roleModalTab === 'access'" class="space-y-4">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <p class="text-sm text-green-700">Настройте доступ к интерфейсам и модулям системы для этой роли.</p>
                            </div>

                            <!-- POS Access -->
                            <div class="border rounded-xl overflow-hidden">
                                <label class="flex items-center gap-4 p-4 hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" v-model="roleForm.can_access_pos" class="w-5 h-5 accent-orange-500 rounded">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">🖥️</span>
                                            <span class="font-medium">POS терминал</span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">Работа с заказами, касса, оплата</p>
                                    </div>
                                </label>
                                <!-- POS Modules -->
                                <div v-if="roleForm.can_access_pos" class="border-t bg-gray-50 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-medium text-gray-700">Доступные вкладки POS:</span>
                                        <button @click="toggleAllPosModules" class="text-xs text-orange-600 hover:text-orange-700">
                                            {{ roleForm.pos_modules?.length === POS_MODULES.length ? 'Снять все' : 'Выбрать все' }}
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label v-for="mod in POS_MODULES" :key="mod.key"
                                               class="flex items-center gap-2 p-2 bg-white border rounded-lg hover:border-orange-300 cursor-pointer transition">
                                            <input type="checkbox" :value="mod.key" v-model="roleForm.pos_modules" class="w-4 h-4 accent-orange-500 rounded">
                                            <span class="text-base">{{ mod.icon }}</span>
                                            <span class="text-sm">{{ mod.label }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Backoffice Access -->
                            <div class="border rounded-xl overflow-hidden">
                                <label class="flex items-center gap-4 p-4 hover:bg-gray-50 cursor-pointer transition">
                                    <input type="checkbox" v-model="roleForm.can_access_backoffice" class="w-5 h-5 accent-orange-500 rounded">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">📊</span>
                                            <span class="font-medium">Бэк-офис</span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">Управление рестораном, отчёты, настройки</p>
                                    </div>
                                </label>
                                <!-- Backoffice Modules -->
                                <div v-if="roleForm.can_access_backoffice" class="border-t bg-gray-50 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-sm font-medium text-gray-700">Доступные разделы бэк-офиса:</span>
                                        <button @click="toggleAllBackofficeModules" class="text-xs text-orange-600 hover:text-orange-700">
                                            {{ roleForm.backoffice_modules?.length === BACKOFFICE_MODULES.length ? 'Снять все' : 'Выбрать все' }}
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label v-for="mod in BACKOFFICE_MODULES" :key="mod.key"
                                               class="flex items-center gap-2 p-2 bg-white border rounded-lg hover:border-orange-300 cursor-pointer transition">
                                            <input type="checkbox" :value="mod.key" v-model="roleForm.backoffice_modules" class="w-4 h-4 accent-orange-500 rounded">
                                            <span class="text-base">{{ mod.icon }}</span>
                                            <span class="text-sm">{{ mod.label }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Kitchen Access -->
                            <label class="flex items-center gap-4 p-4 border rounded-xl hover:bg-gray-50 cursor-pointer transition">
                                <input type="checkbox" v-model="roleForm.can_access_kitchen" class="w-5 h-5 accent-orange-500 rounded">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">👨‍🍳</span>
                                        <span class="font-medium">Экран кухни</span>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">Просмотр и управление заказами на кухне</p>
                                </div>
                            </label>

                            <!-- Delivery Access -->
                            <label class="flex items-center gap-4 p-4 border rounded-xl hover:bg-gray-50 cursor-pointer transition">
                                <input type="checkbox" v-model="roleForm.can_access_delivery" class="w-5 h-5 accent-orange-500 rounded">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">🚴</span>
                                        <span class="font-medium">Приложение курьера</span>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">Доставка заказов, маршруты</p>
                                </div>
                            </label>
                        </div>

                        <!-- Permissions Tab -->
                        <div v-if="roleModalTab === 'permissions'" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" :checked="roleForm.permissions?.includes('*')" @change="toggleFullAccess" class="w-5 h-5 accent-orange-500 rounded">
                                    <span class="font-medium text-gray-900">Полный доступ</span>
                                </label>
                                <span class="text-sm text-gray-500">{{ roleForm.permissions?.length || 0 }} прав выбрано</span>
                            </div>

                            <div v-if="!roleForm.permissions?.includes('*')" class="space-y-4">
                                <div v-for="(group, groupKey) in permissionGroups" :key="groupKey" class="border rounded-xl overflow-hidden">
                                    <div class="bg-gray-50 px-4 py-3 flex items-center justify-between cursor-pointer" @click="togglePermissionGroup(groupKey)">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">{{ group.icon }}</span>
                                            <span class="font-medium">{{ group.label }}</span>
                                            <span class="text-xs text-gray-500">({{ getGroupSelectedCount(groupKey) }}/{{ Object.keys(group.permissions).length }})</span>
                                        </div>
                                        <svg :class="['w-5 h-5 transition-transform', expandedPermGroups.includes(groupKey) ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                    <div v-if="expandedPermGroups.includes(groupKey)" class="p-3 space-y-1 border-t">
                                        <label v-for="(permName, permKey) in group.permissions" :key="permKey"
                                               class="flex items-center gap-2 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                            <input type="checkbox" :value="permKey" v-model="roleForm.permissions" class="w-4 h-4 accent-orange-500 rounded">
                                            <span class="text-sm">{{ permName }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-center py-8 text-gray-500">
                                <div class="text-4xl mb-2">✨</div>
                                <p>Эта роль имеет полный доступ ко всем функциям</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="showRoleModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="saveRole" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                            {{ roleForm.id ? 'Сохранить' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Payment Modal ========== -->
        <Teleport to="body">
            <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPaymentModal = false">
                <div class="bg-white rounded-2xl w-[450px] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ getPaymentTypeLabel(paymentForm.type) }}</h3>
                        <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Сотрудник *</label>
                            <select v-model="paymentForm.user_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <option value="">Выберите сотрудника</option>
                                <option v-for="s in store.staff.filter(s => s.is_active)" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Сумма *</label>
                            <input v-model.number="paymentForm.amount" type="number" min="0" step="100"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Описание</label>
                            <input v-model="paymentForm.description" type="text"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Причина выплаты">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Статус</label>
                            <div class="flex gap-3">
                                <label class="flex-1">
                                    <input type="radio" v-model="paymentForm.status" value="pending" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-yellow-500 peer-checked:bg-yellow-50">
                                        ⏳ Ожидает
                                    </div>
                                </label>
                                <label class="flex-1">
                                    <input type="radio" v-model="paymentForm.status" value="paid" class="sr-only peer">
                                    <div class="p-3 border rounded-lg text-center cursor-pointer peer-checked:border-green-500 peer-checked:bg-green-50">
                                        ✅ Выплачено
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="showPaymentModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">Отмена</button>
                        <button @click="savePayment" :disabled="!paymentForm.user_id || !paymentForm.amount"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:bg-gray-300 transition">
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Invitation Modal ========== -->
        <Teleport to="body">
            <div v-if="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showInviteModal = false">
                <div class="bg-white rounded-2xl w-[500px] max-h-[90vh] overflow-hidden shadow-2xl">
                    <div class="p-6 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Создать приглашение</h3>
                        <button @click="showInviteModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                    </div>
                    <div class="p-6 space-y-4 overflow-y-auto max-h-[60vh]">
                        <!-- Invitation created success -->
                        <div v-if="inviteLink" class="p-4 bg-green-50 border border-green-200 rounded-xl">
                            <p class="text-green-800 font-medium mb-2">Приглашение создано!</p>
                            <p class="text-sm text-green-600 mb-3">Отправьте эту ссылку сотруднику:</p>
                            <div class="flex gap-2">
                                <input :value="inviteLink" readonly class="flex-1 px-3 py-2 bg-white border rounded-lg text-sm font-mono">
                                <button @click="copyInviteLink" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm">
                                    {{ copiedLink ? 'Скопировано!' : 'Копировать' }}
                                </button>
                            </div>
                        </div>

                        <template v-else>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Имя сотрудника (необязательно)</label>
                                <input v-model="inviteForm.name" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Будет заполнено при регистрации">
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                                <p class="text-sm text-blue-700">
                                    Если оставить поля пустыми, сотрудник сам заполнит свои данные при регистрации
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email (необязательно)</label>
                                    <input v-model="inviteForm.email" type="email" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Заполнит при регистрации">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Телефон (необязательно)</label>
                                    <input v-model="inviteForm.phone" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Заполнит при регистрации">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Роль *</label>
                                <select v-model="inviteForm.role" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <option value="">Выберите роль</option>
                                    <option value="waiter">Официант</option>
                                    <option value="cook">Повар</option>
                                    <option value="cashier">Кассир</option>
                                    <option value="courier">Курьер</option>
                                    <option value="manager">Менеджер</option>
                                    <option value="admin">Администратор</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Тип оплаты</label>
                                <div class="flex gap-2">
                                    <label class="flex-1">
                                        <input type="radio" v-model="inviteForm.salary_type" value="fixed" class="sr-only peer">
                                        <div class="p-2 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                            Оклад
                                        </div>
                                    </label>
                                    <label class="flex-1">
                                        <input type="radio" v-model="inviteForm.salary_type" value="hourly" class="sr-only peer">
                                        <div class="p-2 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                            Почасовая
                                        </div>
                                    </label>
                                    <label class="flex-1">
                                        <input type="radio" v-model="inviteForm.salary_type" value="percent" class="sr-only peer">
                                        <div class="p-2 border rounded-lg text-center cursor-pointer text-sm peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50">
                                            % от продаж
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ inviteForm.salary_type === 'fixed' ? 'Оклад' : inviteForm.salary_type === 'hourly' ? 'Ставка/час' : '% от продаж' }}
                                    </label>
                                    <input v-model.number="inviteForm.salary_amount" type="number" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" :placeholder="inviteForm.salary_type === 'percent' ? '5' : '50000'">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Срок действия (дней)</label>
                                    <select v-model="inviteForm.expires_days" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                        <option :value="3">3 дня</option>
                                        <option :value="7">7 дней</option>
                                        <option :value="14">14 дней</option>
                                        <option :value="30">30 дней</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Комментарий</label>
                                <textarea v-model="inviteForm.notes" rows="2" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent" placeholder="Заметка для себя..."></textarea>
                            </div>
                        </template>
                    </div>
                    <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                        <button @click="closeInviteModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                            {{ inviteLink ? 'Закрыть' : 'Отмена' }}
                        </button>
                        <button v-if="!inviteLink" @click="createInvitation" :disabled="!inviteForm.role || savingInvite"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:bg-gray-300 transition">
                            {{ savingInvite ? 'Создание...' : 'Создать приглашение' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== Staff Devices Modal ========== -->
        <StaffDevicesModal
            v-model="showDevicesModal"
            :user-id="selectedDevicesUserId"
            @updated="store.loadStaff()"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';
import StaffDevicesModal from '../modals/StaffDevicesModal.vue';

// Helper для локальной даты (не UTC!)
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const store = useBackofficeStore();

// State
const subTab = ref('employees');
const staffFilter = ref('all');
const showInactive = ref(false);
const saving = ref(false);
const saveError = ref('');

// Modals
const showStaffModal = ref(false);
const showShiftModal = ref(false);
const showRoleModal = ref(false);
const showPaymentModal = ref(false);
const showFireModal = ref(false);
const showInviteModal = ref(false);
const showDevicesModal = ref(false);
const selectedDevicesUserId = ref(null);
const fireReason = ref('');

// Invitation state
const inviteLink = ref('');
const copiedLink = ref(false);
const savingInvite = ref(false);
const inviteForm = ref({
    name: '',
    email: '',
    phone: '',
    role: '',
    salary_type: 'fixed',
    salary_amount: null,
    expires_days: 7,
    notes: ''
});

// Payroll state
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());
const salaryPayments = ref([]);

// Salary calculation state
const salaryPeriods = ref([]);
const currentPeriod = ref(null);
const periodCalculations = ref([]);
const loadingPeriod = ref(false);
const calculatingSalary = ref(false);
const showPeriodDetails = ref(false);
const payrollView = ref('payments'); // 'payments' or 'periods'

// Timesheet state
const workingNow = ref([]);
const timesheetSessions = ref([]);
const loadingWorkingSessions = ref(false);
const timesheetFilter = ref({
    userId: null,
    startDate: new Date(new Date().setDate(new Date().getDate() - 7)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
});

// Forms
const staffForm = ref({
    id: null,
    name: '',
    email: '',
    phone: '',
    role: '',
    pin: '',
    has_pin: false,
    has_password: false,
    pending_invitation: false,
    // Credential settings
    enable_pin: false,
    enable_password: false,
    password_method: 'none', // 'none', 'invite', 'manual'
    password: '',
    login: '', // For manual password setup
    birth_date: null,
    address: '',
    emergency_contact: '',
    hired_at: null,
    fired_at: null,
    fire_reason: '',
    salary_type: 'fixed',
    salary: null,
    hourly_rate: null,
    sales_percent: null,
    bank_card: '',
    is_active: true
});

// Role-based credential recommendations
const roleCredentialConfig = {
    cashier: { pin: true, password: false, pinRequired: true, hint: 'Кассиру нужен PIN для быстрой смены на терминале' },
    waiter: { pin: true, password: true, pinRequired: false, hint: 'Официанту нужен PIN для POS и пароль для приложения' },
    cook: { pin: true, password: false, pinRequired: false, hint: 'Повару нужен PIN для Kitchen Display' },
    courier: { pin: false, password: true, pinRequired: false, hint: 'Курьеру нужен пароль для мобильного приложения' },
    manager: { pin: true, password: true, pinRequired: false, hint: 'Менеджеру рекомендуется PIN и пароль для BackOffice' },
    admin: { pin: true, password: true, pinRequired: false, hint: 'Администратору рекомендуется PIN и пароль для полного доступа' },
    hostess: { pin: false, password: true, pinRequired: false, hint: 'Хостес нужен пароль для BackOffice' },
};

// Computed for current role config
const currentRoleConfig = computed(() => {
    return roleCredentialConfig[staffForm.value.role] || { pin: false, password: false, pinRequired: false, hint: '' };
});

// Watch role changes to auto-set credential options for new employees
watch(() => staffForm.value.role, (newRole) => {
    if (!staffForm.value.id && newRole) {
        const config = roleCredentialConfig[newRole];
        if (config) {
            staffForm.value.enable_pin = config.pin;
            staffForm.value.enable_password = config.password;
            // Set default method if password is enabled
            if (config.password && staffForm.value.password_method === 'none') {
                staffForm.value.password_method = staffForm.value.email ? 'invite' : 'manual';
            }
        }
    }
});

// Auto-select password method when enable_password is toggled on (UX fix)
watch(() => staffForm.value.enable_password, (enabled) => {
    if (enabled && staffForm.value.password_method === 'none' && !staffForm.value.has_password) {
        // Default to 'manual' for immediate setup, 'invite' if email is provided
        staffForm.value.password_method = staffForm.value.email ? 'invite' : 'manual';
    }
});

const shiftForm = ref({
    id: null,
    user_id: null,
    userName: '',
    date: '',
    start_time: '09:00',
    end_time: '18:00'
});

const roleForm = ref({
    id: null,
    name: '',
    key: '',
    description: '',
    icon: '👤',
    color: '#6b7280',
    permissions: [],
    is_system: false,
    // Лимиты
    max_discount_percent: 0,
    max_refund_amount: 0,
    max_cancel_amount: 0,
    // Доступ к интерфейсам (Level 1) - по умолчанию POS доступен
    can_access_pos: true,
    can_access_backoffice: false,
    can_access_kitchen: false,
    can_access_delivery: false,
    require_manager_confirm: false,
    // Доступ к модулям (Level 2)
    pos_modules: ['cash', 'orders'],
    backoffice_modules: [],
});

// Доступные модули
const POS_MODULES = [
    { key: 'cash', label: 'Касса', icon: '💵', description: 'Работа с заказами и оплатой' },
    { key: 'orders', label: 'Заказы', icon: '📋', description: 'Просмотр и управление заказами' },
    { key: 'delivery', label: 'Доставка', icon: '🚚', description: 'Заказы на доставку' },
    { key: 'customers', label: 'Клиенты', icon: '👥', description: 'База клиентов' },
    { key: 'warehouse', label: 'Склад', icon: '📦', description: 'Остатки и инвентаризация' },
    { key: 'stoplist', label: 'Стоп-лист', icon: '🚫', description: 'Управление стоп-листом' },
    { key: 'writeoffs', label: 'Списания', icon: '📝', description: 'Списание продукции' },
    { key: 'settings', label: 'Настройки', icon: '⚙️', description: 'Настройки терминала' },
];

const BACKOFFICE_MODULES = [
    { key: 'dashboard', label: 'Дашборд', icon: '📊', description: 'Сводка и статистика' },
    { key: 'menu', label: 'Меню', icon: '🍽️', description: 'Управление блюдами' },
    { key: 'pricelists', label: 'Прайс-листы', icon: '💲', description: 'Ценообразование' },
    { key: 'hall', label: 'Зал', icon: '🪑', description: 'Столы и зоны' },
    { key: 'staff', label: 'Персонал', icon: '👥', description: 'Сотрудники и роли' },
    { key: 'attendance', label: 'Учёт времени', icon: '⏱️', description: 'Рабочее время' },
    { key: 'inventory', label: 'Склад', icon: '📦', description: 'Ингредиенты и поставки' },
    { key: 'customers', label: 'Клиенты', icon: '👤', description: 'База клиентов' },
    { key: 'loyalty', label: 'Лояльность', icon: '🎁', description: 'Акции и промокоды' },
    { key: 'delivery', label: 'Доставка', icon: '🚚', description: 'Зоны и курьеры' },
    { key: 'finance', label: 'Финансы', icon: '💰', description: 'Транзакции и отчёты' },
    { key: 'analytics', label: 'Аналитика', icon: '📈', description: 'Детальная аналитика' },
    { key: 'integrations', label: 'Интеграции', icon: '🔗', description: 'Внешние сервисы' },
    { key: 'settings', label: 'Настройки', icon: '⚙️', description: 'Настройки системы' },
];

const roleModalTab = ref('basic');
const expandedPermGroups = ref([]);

// Группы прав доступа
const permissionGroups = ref({
    staff: {
        label: 'Персонал',
        icon: '👥',
        permissions: {
            'staff.view': 'Просмотр сотрудников',
            'staff.create': 'Создание сотрудников',
            'staff.edit': 'Редактирование сотрудников',
            'staff.delete': 'Удаление сотрудников',
            'staff.schedule': 'Управление расписанием',
        },
    },
    menu: {
        label: 'Меню',
        icon: '🍽️',
        permissions: {
            'menu.view': 'Просмотр меню',
            'menu.create': 'Добавление блюд',
            'menu.edit': 'Редактирование блюд',
            'menu.delete': 'Удаление блюд',
            'menu.categories': 'Управление категориями',
            'menu.modifiers': 'Управление модификаторами',
        },
    },
    orders: {
        label: 'Заказы',
        icon: '📋',
        permissions: {
            'orders.view': 'Просмотр заказов',
            'orders.create': 'Создание заказов',
            'orders.edit': 'Редактирование заказов',
            'orders.cancel': 'Отмена заказов',
            'orders.discount': 'Применение скидок',
            'orders.refund': 'Возврат заказов',
        },
    },
    hall: {
        label: 'Зал',
        icon: '🪑',
        permissions: {
            'hall.view': 'Просмотр зала',
            'hall.manage': 'Управление столами',
            'hall.reservations': 'Управление бронями',
        },
    },
    customers: {
        label: 'Клиенты',
        icon: '👤',
        permissions: {
            'customers.view': 'Просмотр клиентов',
            'customers.create': 'Создание клиентов',
            'customers.edit': 'Редактирование клиентов',
            'customers.delete': 'Удаление клиентов',
        },
    },
    finance: {
        label: 'Финансы',
        icon: '💰',
        permissions: {
            'finance.view': 'Просмотр финансов',
            'finance.shifts': 'Управление сменами',
            'finance.operations': 'Кассовые операции',
            'finance.reports': 'Финансовые отчёты',
        },
    },
    inventory: {
        label: 'Склад',
        icon: '📦',
        permissions: {
            'inventory.view': 'Просмотр склада',
            'inventory.manage': 'Управление запасами',
            'inventory.write_off': 'Списание товаров',
        },
    },
    reports: {
        label: 'Отчёты',
        icon: '📊',
        permissions: {
            'reports.view': 'Просмотр отчётов',
            'reports.export': 'Экспорт отчётов',
            'reports.analytics': 'Аналитика',
        },
    },
    settings: {
        label: 'Настройки',
        icon: '⚙️',
        permissions: {
            'settings.view': 'Просмотр настроек',
            'settings.edit': 'Изменение настроек',
            'settings.integrations': 'Управление интеграциями',
            'settings.roles': 'Управление ролями',
        },
    },
});

const paymentForm = ref({
    id: null,
    user_id: null,
    type: 'bonus',
    amount: 0,
    description: '',
    status: 'pending'
});

// Schedule
const weekOffset = ref(0);
const scheduleShifts = ref([]);
const scheduleStats = ref({ total_shifts: 0, total_hours: 0, draft_count: 0, published_count: 0 });
const scheduleTemplates = ref([]);
const showTemplateModal = ref(false);
const templateForm = ref({ id: null, name: '', start_time: '09:00', end_time: '18:00', break_minutes: 30, color: '#f97316' });

// Roles & Invitations
const roles = ref([]);
const invitations = ref([]);

// Available permissions
const availablePermissions = [
    { key: '*', label: 'Полный доступ' },
    { key: 'pos.access', label: 'Доступ к POS' },
    { key: 'pos.orders', label: 'Работа с заказами' },
    { key: 'pos.payments', label: 'Приём оплаты' },
    { key: 'pos.discounts', label: 'Применение скидок' },
    { key: 'kitchen.access', label: 'Доступ к кухне' },
    { key: 'backoffice.access', label: 'Доступ к BackOffice' },
    { key: 'backoffice.menu', label: 'Управление меню' },
    { key: 'backoffice.staff', label: 'Управление персоналом' },
    { key: 'backoffice.finance', label: 'Финансы' },
    { key: 'backoffice.analytics', label: 'Аналитика' },
    { key: 'backoffice.settings', label: 'Настройки' }
];

// Helper: проверяет соответствие роли базовым ключам (поддержка суффиксов _2, _3 и т.д.)
const matchesRoles = (role, baseKeys) => {
    if (!role) return false;
    return baseKeys.some(key => role === key || role.startsWith(key + '_'));
};

// Computed
const activeRoles = computed(() => {
    return roles.value.filter(r => r.is_active !== false);
});

const filteredStaff = computed(() => {
    let list = store.staff;

    if (!showInactive.value) {
        list = list.filter(s => s.is_active);
    }

    if (staffFilter.value === 'all') return list;
    if (staffFilter.value === 'admin') {
        return list.filter(s => matchesRoles(s.role, ['super_admin', 'owner', 'admin', 'manager']));
    }
    if (staffFilter.value === 'service') {
        return list.filter(s => matchesRoles(s.role, ['courier', 'hostess']));
    }
    // Для остальных фильтров используем startsWith
    return list.filter(s => s.role?.startsWith(staffFilter.value));
});

const pendingInvitations = computed(() => {
    return invitations.value.filter(i => i.status === 'pending').length;
});

// Payroll computed
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

const paidTotal = computed(() => {
    return salaryPayments.value
        .filter(p => p.status === 'paid' && p.type !== 'penalty')
        .reduce((sum, p) => sum + (p.amount || 0), 0);
});

const pendingTotal = computed(() => {
    return salaryPayments.value
        .filter(p => p.status === 'pending' && p.type !== 'penalty')
        .reduce((sum, p) => sum + (p.amount || 0), 0);
});

const weekLabel = computed(() => {
    const start = getWeekStart();
    const end = new Date(start);
    end.setDate(end.getDate() + 6);

    const formatDate = (d) => d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
    return `${formatDate(start)} - ${formatDate(end)}`;
});

const scheduleDays = computed(() => {
    const start = getWeekStart();
    const days = [];
    const dayNames = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    const today = getLocalDateString();

    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(d.getDate() + i);
        const dateStr = getLocalDateString(d);
        days.push({
            date: dateStr,
            dayName: dayNames[d.getDay()],
            dateLabel: d.getDate().toString(),
            isToday: dateStr === today
        });
    }
    return days;
});

const scheduleData = computed(() => {
    const activeStaff = store.staff.filter(s => s.is_active);
    return activeStaff.map(user => ({
        user,
        days: scheduleDays.value.map(day => ({
            date: day.date,
            shift: scheduleShifts.value.find(s => s.user_id === user.id && s.date === day.date)
        }))
    }));
});

// Methods
function getWeekStart() {
    const now = new Date();
    now.setDate(now.getDate() + (weekOffset.value * 7));
    const day = now.getDay();
    const diff = now.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(now.setDate(diff));
}

function changeWeek(delta) {
    weekOffset.value += delta;
    loadSchedule();
}

function goToday() {
    weekOffset.value = 0;
    loadSchedule();
}

function isToday(dateStr) {
    return dateStr === getLocalDateString();
}

function getRoleData(roleKey) {
    return roles.value.find(r => r.key === roleKey);
}

// Возвращает inline style для аватара сотрудника
function getRoleAvatarStyle(roleKey) {
    const roleData = getRoleData(roleKey);
    if (roleData?.color) {
        return { backgroundColor: roleData.color };
    }
    // Fallback цвета
    const fallbackColors = {
        waiter: '#3b82f6',
        cook: '#eab308',
        cashier: '#22c55e',
        courier: '#06b6d4',
        manager: '#a855f7',
        admin: '#ef4444'
    };
    return { backgroundColor: fallbackColors[roleKey] || '#6b7280' };
}

// Возвращает inline style для бейджа роли
function getRoleBadgeStyle(roleKey) {
    const roleData = getRoleData(roleKey);
    if (roleData?.color) {
        return {
            backgroundColor: roleData.color + '20',
            color: roleData.color
        };
    }
    // Fallback - возвращаем пустой объект, класс используется
    return null;
}

// Fallback класс для бейджа (когда нет динамического цвета)
function getRoleBadgeClass(roleKey) {
    const roleData = getRoleData(roleKey);
    if (roleData?.color) {
        return ''; // Используем inline style
    }
    const fallbackClasses = {
        waiter: 'bg-blue-100 text-blue-700',
        cook: 'bg-yellow-100 text-yellow-700',
        cashier: 'bg-green-100 text-green-700',
        courier: 'bg-cyan-100 text-cyan-700',
        manager: 'bg-purple-100 text-purple-700',
        admin: 'bg-red-100 text-red-700'
    };
    return fallbackClasses[roleKey] || 'bg-gray-100 text-gray-700';
}

function roleLabel(roleKey) {
    const roleData = getRoleData(roleKey);
    if (roleData) {
        return roleData.name;
    }
    const fallbackLabels = {
        waiter: 'Официант',
        cook: 'Повар',
        cashier: 'Кассир',
        courier: 'Курьер',
        manager: 'Менеджер',
        admin: 'Администратор'
    };
    return fallbackLabels[roleKey] || roleKey;
}

function roleIcon(roleKey) {
    const roleData = getRoleData(roleKey);
    return roleData?.icon || '👤';
}

// Форматирование лимита (для отображения на карточках ролей)
function formatLimit(amount) {
    if (!amount || amount === 0) return '0';
    if (amount >= 999999999) return '∞';
    if (amount >= 1000000) return Math.round(amount / 1000000) + 'M';
    if (amount >= 1000) return Math.round(amount / 1000) + 'K';
    return amount.toString();
}

function formatPermission(perm) {
    const map = {
        '*': 'Полный доступ',
        'pos.access': 'POS',
        'pos.orders': 'Заказы',
        'pos.payments': 'Оплата',
        'kitchen.access': 'Кухня',
        'backoffice.access': 'BackOffice',
        'backoffice.menu': 'Меню',
        'backoffice.staff': 'Персонал',
        'backoffice.finance': 'Финансы'
    };
    return map[perm] || perm;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('ru-RU');
}

// Staff CRUD
function openStaffModal(staff = null) {
    if (staff) {
        staffForm.value = {
            id: staff.id,
            name: staff.name || '',
            email: staff.email || '',
            phone: staff.phone || '',
            role: staff.role || '',
            pin: '', // Не показываем существующий PIN
            has_pin: staff.has_pin || false,
            has_password: staff.has_password || false,
            pending_invitation: staff.pending_invitation || false,
            // Credential settings (for existing staff, show current state)
            enable_pin: staff.has_pin || false,
            enable_password: staff.has_password || staff.pending_invitation || false,
            password_method: staff.pending_invitation ? 'invite' : (staff.has_password ? 'manual' : 'none'),
            password: '',
            login: staff.login || staff.email || '',
            birth_date: staff.birth_date || null,
            address: staff.address || '',
            emergency_contact: staff.emergency_contact || '',
            hired_at: staff.hired_at || staff.hire_date || null,
            fired_at: staff.fired_at || null,
            fire_reason: staff.fire_reason || '',
            salary_type: staff.salary_type || 'fixed',
            salary: staff.salary || null,
            hourly_rate: staff.hourly_rate || null,
            sales_percent: staff.sales_percent || null,
            bank_card: staff.bank_card || '',
            is_active: staff.is_active !== false
        };
    } else {
        staffForm.value = {
            id: null,
            name: '',
            email: '',
            phone: '',
            role: '',
            pin: '',
            has_pin: false,
            has_password: false,
            pending_invitation: false,
            enable_pin: false,
            enable_password: false,
            password_method: 'none',
            password: '',
            login: '',
            birth_date: null,
            address: '',
            emergency_contact: '',
            hired_at: getLocalDateString(),
            fired_at: null,
            fire_reason: '',
            salary_type: 'fixed',
            salary: null,
            hourly_rate: null,
            sales_percent: null,
            bank_card: '',
            is_active: true
        };
    }
    showStaffModal.value = true;
}

// Clear staff PIN
async function clearStaffPin() {
    if (!staffForm.value.id) return;

    if (!confirm('Удалить PIN-код сотрудника? Он больше не сможет входить по PIN.')) {
        return;
    }

    try {
        await store.api(`/staff/${staffForm.value.id}/pin`, {
            method: 'DELETE'
        });
        staffForm.value.has_pin = false;
        store.showToast('PIN-код удалён', 'success');
        store.loadStaff();
    } catch (e) {
        store.showToast('Ошибка удаления PIN', 'error');
    }
}

// Send password reset link
async function sendPasswordReset() {
    if (!staffForm.value.id) return;

    if (!staffForm.value.email) {
        store.showToast('Укажите email сотрудника', 'error');
        return;
    }

    try {
        await store.api(`/staff/${staffForm.value.id}/password-reset`, {
            method: 'POST'
        });
        store.showToast('Ссылка для сброса пароля отправлена на ' + staffForm.value.email, 'success');
    } catch (e) {
        store.showToast('Ошибка отправки', 'error');
    }
}

// Open devices modal
function openDevicesModal(staff) {
    selectedDevicesUserId.value = staff.id;
    showDevicesModal.value = true;
}

// Fire employee
function fireEmployee() {
    fireReason.value = '';
    showFireModal.value = true;
}

async function confirmFire() {
    try {
        await store.api(`/backoffice/staff/${staffForm.value.id}/fire`, {
            method: 'POST',
            body: JSON.stringify({ reason: fireReason.value })
        });

        showFireModal.value = false;
        showStaffModal.value = false;
        store.loadStaff();
        store.showToast('Сотрудник уволен', 'success');
    } catch (e) {
        store.showToast('Ошибка при увольнении', 'error');
    }
}

async function saveStaff() {
    if (!staffForm.value.name || !staffForm.value.role) {
        store.showToast('Заполните обязательные поля', 'error');
        return;
    }

    // Validate PIN if enabled
    if (staffForm.value.enable_pin && !staffForm.value.has_pin && (!staffForm.value.pin || staffForm.value.pin.length !== 4)) {
        store.showToast('PIN должен содержать 4 цифры', 'error');
        return;
    }

    // Validate password if manual method and no existing password
    if (staffForm.value.enable_password && staffForm.value.password_method === 'manual' && !staffForm.value.has_password) {
        if (!staffForm.value.password || staffForm.value.password.length < 6) {
            store.showToast('Пароль должен быть минимум 6 символов', 'error');
            return;
        }
    }

    saving.value = true;
    saveError.value = '';
    try {
        const url = staffForm.value.id
            ? `/backoffice/staff/${staffForm.value.id}`
            : '/backoffice/staff';
        const method = staffForm.value.id ? 'PUT' : 'POST';

        // Prepare data - clean up empty values
        const data = { ...staffForm.value };

        // Handle PIN
        if (!data.enable_pin) {
            delete data.pin;
        } else if (!data.pin || data.pin.length === 0) {
            delete data.pin; // Keep existing PIN if not provided
        }

        // Handle password
        if (!data.enable_password) {
            delete data.password;
            delete data.login;
            data.send_invitation = false;
        } else if (data.password_method === 'invite' && !data.has_password) {
            delete data.password;
            data.send_invitation = true;
            // Use email as login if not specified
            if (!data.login) {
                data.login = data.email;
            }
        } else if (data.password_method === 'manual') {
            data.send_invitation = false;
            // Use email as login if not specified
            if (!data.login) {
                data.login = data.email;
            }
            // Don't send empty password (keep existing)
            if (!data.password || data.password.length === 0) {
                delete data.password;
            }
        }

        // Remove internal state fields that backend doesn't expect
        delete data.has_pin;
        delete data.has_password;
        delete data.pending_invitation;
        delete data.enable_pin;
        delete data.enable_password;
        delete data.password_method;
        delete data.fire_reason; // handled separately when firing

        // Convert empty strings to null for numeric fields
        const numericFields = ['salary', 'hourly_rate', 'sales_percent'];
        numericFields.forEach(field => {
            if (data[field] === '' || data[field] === null || data[field] === undefined) {
                data[field] = null;
            }
        });

        // Convert empty strings to null for date fields
        const dateFields = ['birth_date', 'hired_at', 'fired_at'];
        dateFields.forEach(field => {
            if (data[field] === '' || data[field] === undefined) {
                data[field] = null;
            }
        });

        const res = await store.api(url, {
            method,
            body: JSON.stringify(data)
        });

        if (res.success) {
            let message = staffForm.value.id ? 'Сотрудник обновлён' : 'Сотрудник создан';
            if (data.send_invitation) {
                message += '. Приглашение создано.';
            }
            store.showToast(message, 'success');
            showStaffModal.value = false;
            store.loadStaff();
        }
    } catch (e) {
        const errorMsg = e.message || 'Ошибка сохранения';
        saveError.value = errorMsg;
        store.showToast(errorMsg, 'error');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(staff) {
    try {
        await store.api(`/backoffice/staff/${staff.id}/toggle-active`, { method: 'POST' });
        store.loadStaff();
        store.showToast(staff.is_active ? 'Сотрудник деактивирован' : 'Сотрудник активирован', 'success');
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function sendInvite(staff) {
    try {
        await store.api(`/backoffice/staff/${staff.id}/invite`, { method: 'POST' });
        store.showToast('Приглашение отправлено', 'success');
        store.loadStaff();
    } catch (e) {
        store.showToast('Ошибка отправки', 'error');
    }
}

// Schedule
async function loadSchedule() {
    const start = getLocalDateString(getWeekStart());
    try {
        // Load schedule data
        const res = await store.api(`/backoffice/schedule?week_start=${start}`);
        if (res.success && res.data) {
            // Flatten schedules from all dates into single array
            const allShifts = [];
            Object.values(res.data.schedules || {}).forEach(dayShifts => {
                allShifts.push(...dayShifts);
            });
            scheduleShifts.value = allShifts;
        } else {
            scheduleShifts.value = res.shifts || [];
        }

        // Load stats
        const statsRes = await store.api(`/backoffice/schedule/stats?week_start=${start}`);
        if (statsRes.success && statsRes.data) {
            scheduleStats.value = statsRes.data;
        }

        // Load templates
        const tplRes = await store.api('/backoffice/schedule/templates');
        if (tplRes.success && tplRes.data) {
            scheduleTemplates.value = tplRes.data;
        }
    } catch (e) {
        console.error('Failed to load schedule:', e);
    }
}

function openShiftModal(shift, user, date = null) {
    if (shift) {
        shiftForm.value = {
            id: shift.id,
            user_id: user.id,
            userName: user.name,
            date: shift.date,
            start_time: shift.start_time?.slice(0, 5) || shift.start_time,
            end_time: shift.end_time?.slice(0, 5) || shift.end_time,
            break_minutes: shift.break_minutes || 0,
            notes: shift.notes || ''
        };
    } else {
        shiftForm.value = {
            id: null,
            user_id: user.id,
            userName: user.name,
            date: date || getLocalDateString(),
            start_time: '09:00',
            end_time: '18:00',
            break_minutes: 0,
            notes: ''
        };
    }
    showShiftModal.value = true;
}

async function saveShift() {
    try {
        const url = shiftForm.value.id
            ? `/backoffice/schedule/${shiftForm.value.id}`
            : '/backoffice/schedule';
        const method = shiftForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify({
                user_id: shiftForm.value.user_id,
                date: shiftForm.value.date,
                start_time: shiftForm.value.start_time,
                end_time: shiftForm.value.end_time,
                break_minutes: shiftForm.value.break_minutes || 0,
                notes: shiftForm.value.notes || null
            })
        });

        showShiftModal.value = false;
        loadSchedule();
        store.showToast('Смена сохранена', 'success');
    } catch (e) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    }
}

async function deleteShift() {
    if (!confirm('Удалить смену?')) return;
    try {
        await store.api(`/backoffice/schedule/${shiftForm.value.id}`, { method: 'DELETE' });
        showShiftModal.value = false;
        loadSchedule();
        store.showToast('Смена удалена', 'success');
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function publishWeek() {
    if (!confirm('Опубликовать расписание на эту неделю? Сотрудники получат уведомления.')) return;
    try {
        const start = getLocalDateString(getWeekStart());
        const res = await store.api('/backoffice/schedule/publish', {
            method: 'POST',
            body: JSON.stringify({ week_start: start })
        });
        loadSchedule();
        store.showToast(res.message || 'Расписание опубликовано', 'success');
    } catch (e) {
        store.showToast(e.message || 'Ошибка публикации', 'error');
    }
}

async function copyFromPrevWeek() {
    if (!confirm('Скопировать смены с прошлой недели?')) return;
    try {
        const currentWeekStart = getWeekStart();
        const prevWeekStart = new Date(currentWeekStart);
        prevWeekStart.setDate(prevWeekStart.getDate() - 7);

        const res = await store.api('/backoffice/schedule/copy-week', {
            method: 'POST',
            body: JSON.stringify({
                from_week: getLocalDateString(prevWeekStart),
                to_week: getLocalDateString(currentWeekStart)
            })
        });
        loadSchedule();
        store.showToast(res.message || 'Смены скопированы', 'success');
    } catch (e) {
        store.showToast(e.message || 'Ошибка копирования', 'error');
    }
}

function openTemplateModal(template = null) {
    if (template) {
        templateForm.value = {
            id: template.id,
            name: template.name,
            start_time: template.start_time?.slice(0, 5) || template.start_time,
            end_time: template.end_time?.slice(0, 5) || template.end_time,
            break_minutes: template.break_minutes || 0,
            color: template.color || '#f97316'
        };
    } else {
        templateForm.value = {
            id: null,
            name: '',
            start_time: '09:00',
            end_time: '18:00',
            break_minutes: 30,
            color: '#f97316'
        };
    }
    showTemplateModal.value = true;
}

async function saveTemplate() {
    try {
        const url = templateForm.value.id
            ? `/backoffice/schedule/templates/${templateForm.value.id}`
            : '/backoffice/schedule/templates';
        const method = templateForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(templateForm.value)
        });

        showTemplateModal.value = false;
        loadSchedule();
        store.showToast('Шаблон сохранён', 'success');
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteTemplate() {
    if (!confirm('Удалить шаблон?')) return;
    try {
        await store.api(`/backoffice/schedule/templates/${templateForm.value.id}`, { method: 'DELETE' });
        showTemplateModal.value = false;
        loadSchedule();
        store.showToast('Шаблон удалён', 'success');
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// Roles
async function loadRoles() {
    try {
        const res = await store.api('/backoffice/roles');
        roles.value = res.data || res.roles || [];
    } catch (e) {
        console.error('Failed to load roles:', e);
    }
}

function openRoleModal(role = null) {
    roleModalTab.value = 'basic';
    expandedPermGroups.value = [];

    if (role) {
        roleForm.value = {
            id: role.id,
            name: role.name || role.label,
            key: role.key,
            description: role.description || '',
            icon: role.icon || '👤',
            color: role.color || '#6b7280',
            permissions: role.permissions_list || role.permissions || [],
            is_system: role.is_system || false,
            // Лимиты
            max_discount_percent: role.max_discount_percent ?? 0,
            max_refund_amount: role.max_refund_amount ?? 0,
            max_cancel_amount: role.max_cancel_amount ?? 0,
            // Доступ к интерфейсам (Level 1)
            can_access_pos: role.can_access_pos ?? false,
            can_access_backoffice: role.can_access_backoffice ?? false,
            can_access_kitchen: role.can_access_kitchen ?? false,
            can_access_delivery: role.can_access_delivery ?? false,
            require_manager_confirm: role.require_manager_confirm ?? false,
            // Доступ к модулям (Level 2)
            pos_modules: role.pos_modules || [],
            backoffice_modules: role.backoffice_modules || [],
        };
    } else {
        roleForm.value = {
            id: null,
            name: '',
            key: '',
            description: '',
            icon: '👤',
            color: '#6b7280',
            permissions: [],
            is_system: false,
            max_discount_percent: 0,
            max_refund_amount: 0,
            max_cancel_amount: 0,
            can_access_pos: true,
            can_access_backoffice: false,
            can_access_kitchen: false,
            can_access_delivery: false,
            require_manager_confirm: false,
            pos_modules: ['cash', 'orders'], // Базовые модули по умолчанию
            backoffice_modules: [],
        };
    }
    showRoleModal.value = true;
}

// Переключить все POS модули
function toggleAllPosModules() {
    if (roleForm.value.pos_modules?.length === POS_MODULES.length) {
        roleForm.value.pos_modules = [];
    } else {
        roleForm.value.pos_modules = POS_MODULES.map(m => m.key);
    }
}

// Переключить все Backoffice модули
function toggleAllBackofficeModules() {
    if (roleForm.value.backoffice_modules?.length === BACKOFFICE_MODULES.length) {
        roleForm.value.backoffice_modules = [];
    } else {
        roleForm.value.backoffice_modules = BACKOFFICE_MODULES.map(m => m.key);
    }
}

// Автогенерация ключа из названия роли (транслитерация)
function autoGenerateKey() {
    // Только для новых ролей (не системных и без ID)
    if (roleForm.value.is_system || roleForm.value.id) return;

    const translitMap = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e',
        'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
        'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
        'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch',
        'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
        ' ': '_', '-': '_'
    };

    const name = roleForm.value.name.toLowerCase();
    let key = '';
    for (const char of name) {
        key += translitMap[char] || (char.match(/[a-z0-9_]/) ? char : '');
    }
    // Убираем двойные подчёркивания и обрезаем
    roleForm.value.key = key.replace(/_+/g, '_').replace(/^_|_$/g, '').substring(0, 50);
}

// Переключение полного доступа
function toggleFullAccess(e) {
    if (e.target.checked) {
        roleForm.value.permissions = ['*'];
    } else {
        roleForm.value.permissions = [];
    }
}

// Развернуть/свернуть группу прав
function togglePermissionGroup(groupKey) {
    const index = expandedPermGroups.value.indexOf(groupKey);
    if (index === -1) {
        expandedPermGroups.value.push(groupKey);
    } else {
        expandedPermGroups.value.splice(index, 1);
    }
}

// Подсчёт выбранных прав в группе
function getGroupSelectedCount(groupKey) {
    const group = permissionGroups.value[groupKey];
    if (!group) return 0;
    const permKeys = Object.keys(group.permissions);
    return permKeys.filter(k => roleForm.value.permissions?.includes(k)).length;
}

async function saveRole() {
    if (!roleForm.value.name) {
        store.showToast('Введите название роли', 'error');
        return;
    }

    try {
        const url = roleForm.value.id
            ? `/backoffice/roles/${roleForm.value.id}`
            : '/backoffice/roles';
        const method = roleForm.value.id ? 'PUT' : 'POST';

        await store.api(url, {
            method,
            body: JSON.stringify(roleForm.value)
        });

        showRoleModal.value = false;
        loadRoles();
        store.showToast('Роль сохранена', 'success');
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

function cloneRole(role) {
    roleForm.value = {
        id: null,
        name: role.name + ' (копия)',
        key: role.key + '_copy',
        description: role.description,
        icon: role.icon,
        color: role.color,
        permissions: [...(role.permissions || [])],
        // Лимиты
        max_discount_percent: role.max_discount_percent ?? 0,
        max_refund_amount: role.max_refund_amount ?? 0,
        max_cancel_amount: role.max_cancel_amount ?? 0,
        // Доступ к интерфейсам
        can_access_pos: role.can_access_pos ?? false,
        can_access_backoffice: role.can_access_backoffice ?? false,
        can_access_kitchen: role.can_access_kitchen ?? false,
        can_access_delivery: role.can_access_delivery ?? false,
        require_manager_confirm: role.require_manager_confirm ?? false,
        // Доступ к модулям
        pos_modules: [...(role.pos_modules || [])],
        backoffice_modules: [...(role.backoffice_modules || [])],
    };
    showRoleModal.value = true;
}

async function deleteRole(role) {
    if (!confirm(`Удалить роль "${role.name}"?`)) return;

    try {
        await store.api(`/backoffice/roles/${role.id}`, { method: 'DELETE' });
        loadRoles();
        store.showToast('Роль удалена', 'success');
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function createDefaultRoles() {
    const defaultRoles = [
        { name: 'Администратор', key: 'admin', icon: '👑', color: '#dc2626', permissions: ['*'], description: 'Полный доступ ко всем функциям' },
        { name: 'Менеджер', key: 'manager', icon: '👔', color: '#7c3aed', permissions: ['pos.access', 'pos.orders', 'pos.payments', 'pos.discounts', 'backoffice.access', 'backoffice.menu', 'backoffice.staff'], description: 'Управление рестораном' },
        { name: 'Официант', key: 'waiter', icon: '🍽️', color: '#2563eb', permissions: ['pos.access', 'pos.orders'], description: 'Работа с заказами' },
        { name: 'Кассир', key: 'cashier', icon: '💵', color: '#16a34a', permissions: ['pos.access', 'pos.orders', 'pos.payments'], description: 'Приём оплаты' },
        { name: 'Повар', key: 'cook', icon: '👨‍🍳', color: '#ea580c', permissions: ['kitchen.access'], description: 'Работа на кухне' },
        { name: 'Курьер', key: 'courier', icon: '🚴', color: '#0891b2', permissions: ['pos.access'], description: 'Доставка заказов' },
    ];

    try {
        for (const role of defaultRoles) {
            await store.api('/backoffice/roles', {
                method: 'POST',
                body: JSON.stringify(role)
            });
        }
        loadRoles();
        store.showToast('Базовые роли созданы', 'success');
    } catch (e) {
        store.showToast('Ошибка создания ролей', 'error');
    }
}

// Invitations
async function loadInvitations() {
    try {
        const res = await store.api('/backoffice/invitations');
        invitations.value = res.invitations || [];
    } catch (e) {
        console.error('Failed to load invitations:', e);
    }
}

function openInviteModal() {
    inviteForm.value = {
        name: '',
        email: '',
        phone: '',
        role: '',
        salary_type: 'fixed',
        salary_amount: null,
        expires_days: 7,
        notes: ''
    };
    inviteLink.value = '';
    copiedLink.value = false;
    showInviteModal.value = true;
}

function closeInviteModal() {
    showInviteModal.value = false;
    if (inviteLink.value) {
        loadInvitations();
    }
}

async function createInvitation() {
    if (!inviteForm.value.role) {
        store.showToast('Выберите роль', 'error');
        return;
    }

    savingInvite.value = true;
    try {
        const payload = {
            name: inviteForm.value.name || null,
            email: inviteForm.value.email || null,
            phone: inviteForm.value.phone || null,
            role: inviteForm.value.role,
            salary_type: inviteForm.value.salary_type,
            salary_amount: inviteForm.value.salary_amount || 0,
            expires_days: inviteForm.value.expires_days,
            notes: inviteForm.value.notes || null
        };

        // Map salary based on type
        if (inviteForm.value.salary_type === 'hourly') {
            payload.hourly_rate = inviteForm.value.salary_amount;
            payload.salary_amount = 0;
        } else if (inviteForm.value.salary_type === 'percent') {
            payload.percent_rate = inviteForm.value.salary_amount;
            payload.salary_amount = 0;
        }

        const res = await store.api('/backoffice/invitations', {
            method: 'POST',
            body: JSON.stringify(payload)
        });

        if (res.success && res.invite_url) {
            inviteLink.value = res.invite_url;
            store.showToast('Приглашение создано', 'success');
        } else if (res.success && res.data?.token) {
            // Build URL manually if only token returned
            inviteLink.value = `${window.location.origin}/register/invite/${res.data.token}`;
            store.showToast('Приглашение создано', 'success');
        }
    } catch (e) {
        store.showToast('Ошибка создания приглашения', 'error');
    } finally {
        savingInvite.value = false;
    }
}

async function copyInviteLink() {
    try {
        await navigator.clipboard.writeText(inviteLink.value);
        copiedLink.value = true;
        setTimeout(() => copiedLink.value = false, 2000);
    } catch (e) {
        // Fallback for older browsers
        const input = document.createElement('input');
        input.value = inviteLink.value;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copiedLink.value = true;
        setTimeout(() => copiedLink.value = false, 2000);
    }
}

async function copyInvitationLink(inv) {
    const link = inv.invite_url || `${window.location.origin}/register/invite/${inv.token}`;
    try {
        await navigator.clipboard.writeText(link);
        store.showToast('Ссылка скопирована', 'success');
    } catch (e) {
        // Fallback
        const input = document.createElement('input');
        input.value = link;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        store.showToast('Ссылка скопирована', 'success');
    }
}

async function resendInvite(inv) {
    try {
        await store.api(`/backoffice/invitations/${inv.id}/resend`, { method: 'POST' });
        store.showToast('Приглашение отправлено повторно', 'success');
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function cancelInvite(inv) {
    if (!confirm('Отменить приглашение?')) return;
    try {
        await store.api(`/backoffice/invitations/${inv.id}`, { method: 'DELETE' });
        loadInvitations();
        store.showToast('Приглашение отменено', 'success');
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

// Payroll methods
function formatMoney(val) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(val || 0);
}

function getPaymentTypeLabel(type) {
    const labels = {
        salary: 'Зарплата',
        advance: 'Аванс',
        bonus: 'Премия',
        penalty: 'Штраф',
        overtime: 'Переработка'
    };
    return labels[type] || type;
}

function getPaymentTypeClass(type) {
    const classes = {
        salary: 'bg-blue-100 text-blue-700',
        advance: 'bg-purple-100 text-purple-700',
        bonus: 'bg-green-100 text-green-700',
        penalty: 'bg-red-100 text-red-700',
        overtime: 'bg-yellow-100 text-yellow-700'
    };
    return classes[type] || 'bg-gray-100 text-gray-700';
}

// =============== TIMESHEET FUNCTIONS ===============

function getRoleLabel(role) {
    const labels = {
        'super_admin': 'Супер-админ',
        'owner': 'Владелец',
        'admin': 'Администратор',
        'manager': 'Менеджер',
        'cashier': 'Кассир',
        'waiter': 'Официант',
        'cook': 'Повар',
        'courier': 'Курьер',
        'hostess': 'Хостес'
    };
    return labels[role] || role || 'Сотрудник';
}

const totalTimesheetHours = computed(() => {
    return timesheetSessions.value.reduce((sum, s) => sum + (s.hours_worked || 0), 0).toFixed(1);
});

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

function formatShiftTime(datetime) {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function formatSessionDate(datetime) {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleDateString('ru-RU');
}

function calculateDuration(clockIn) {
    if (!clockIn) return '-';
    const diffMs = new Date() - new Date(clockIn);
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    return hours > 0 ? `${hours}ч ${minutes}м` : `${minutes}м`;
}

async function forceClockOut(session) {
    if (!confirm(`Завершить смену для ${session.user?.name}?`)) return;
    try {
        await store.api('/payroll/clock-out', {
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

// =============== PAYROLL FUNCTIONS ===============

async function loadPayroll() {
    try {
        const res = await store.api(`/backoffice/salary-payments?month=${selectedMonth.value}&year=${selectedYear.value}`);
        salaryPayments.value = res.data || res.payments || [];
    } catch (e) {
        console.error('Failed to load payroll:', e);
        salaryPayments.value = [];
    }
}

function addPayment(type) {
    paymentForm.value = {
        id: null,
        user_id: null,
        type: type,
        amount: 0,
        description: '',
        status: 'pending'
    };
    showPaymentModal.value = true;
}

async function savePayment() {
    if (!paymentForm.value.user_id || !paymentForm.value.amount) {
        store.showToast('Заполните все обязательные поля', 'error');
        return;
    }

    try {
        await store.api('/backoffice/salary-payments', {
            method: 'POST',
            body: JSON.stringify(paymentForm.value)
        });
        showPaymentModal.value = false;
        store.showToast('Начисление создано', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка создания', 'error');
    }
}

async function markPaymentPaid(payment) {
    try {
        await store.api(`/backoffice/salary-payments/${payment.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: 'paid', paid_at: new Date().toISOString() })
        });
        store.showToast('Выплата проведена', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function cancelPayment(payment) {
    if (!confirm('Отменить начисление?')) return;
    try {
        await store.api(`/backoffice/salary-payments/${payment.id}`, { method: 'DELETE' });
        store.showToast('Начисление отменено', 'success');
        loadPayroll();
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

// =============== SALARY CALCULATION FUNCTIONS ===============

async function loadSalaryPeriods() {
    try {
        const res = await store.api('/salary/periods');
        salaryPeriods.value = res.data?.data || res.data || [];
    } catch (e) {
        console.error('Failed to load salary periods:', e);
        salaryPeriods.value = [];
    }
}

async function createSalaryPeriod() {
    try {
        const res = await store.api('/salary/periods', {
            method: 'POST',
            body: JSON.stringify({
                year: selectedYear.value,
                month: selectedMonth.value
            })
        });
        if (res.success) {
            store.showToast(res.message || 'Период создан', 'success');
            await loadSalaryPeriods();
            openPeriodDetails(res.data);
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        store.showToast('Ошибка создания периода', 'error');
    }
}

async function openPeriodDetails(period) {
    loadingPeriod.value = true;
    currentPeriod.value = period;
    showPeriodDetails.value = true;
    try {
        const res = await store.api(`/salary/periods/${period.id}`);
        currentPeriod.value = res.data?.period || res.period || period;
        periodCalculations.value = currentPeriod.value.calculations || [];
    } catch (e) {
        console.error('Failed to load period details:', e);
    } finally {
        loadingPeriod.value = false;
    }
}

async function calculatePeriod() {
    if (!currentPeriod.value) return;
    calculatingSalary.value = true;
    try {
        const res = await store.api(`/salary/periods/${currentPeriod.value.id}/calculate`, {
            method: 'POST'
        });
        if (res.success) {
            store.showToast(res.message || 'Зарплаты рассчитаны', 'success');
            currentPeriod.value = res.data || currentPeriod.value;
            periodCalculations.value = currentPeriod.value.calculations || [];
            await loadSalaryPeriods();
        }
    } catch (e) {
        store.showToast('Ошибка расчёта', 'error');
    } finally {
        calculatingSalary.value = false;
    }
}

async function approvePeriod() {
    if (!currentPeriod.value) return;
    if (!confirm('Утвердить расчёт зарплат за этот период?')) return;
    try {
        const res = await store.api(`/salary/periods/${currentPeriod.value.id}/approve`, {
            method: 'POST'
        });
        if (res.success) {
            store.showToast('Период утверждён', 'success');
            currentPeriod.value = res.data || currentPeriod.value;
            await loadSalaryPeriods();
        }
    } catch (e) {
        store.showToast('Ошибка утверждения', 'error');
    }
}

async function payAllPeriod() {
    if (!currentPeriod.value) return;
    if (!confirm('Выплатить все зарплаты за этот период?')) return;
    try {
        const res = await store.api(`/salary/periods/${currentPeriod.value.id}/pay-all`, {
            method: 'POST'
        });
        if (res.success) {
            store.showToast(res.message || 'Зарплаты выплачены', 'success');
            await openPeriodDetails(currentPeriod.value);
            await loadSalaryPeriods();
        }
    } catch (e) {
        store.showToast('Ошибка выплаты', 'error');
    }
}

async function addBonusOrPenalty(type) {
    if (!currentPeriod.value) return;
    paymentForm.value = {
        id: null,
        user_id: null,
        period_id: currentPeriod.value.id,
        type: type,
        amount: 0,
        description: ''
    };
    showPaymentModal.value = true;
}

async function savePaymentForPeriod() {
    if (!paymentForm.value.user_id || !paymentForm.value.amount) {
        store.showToast('Заполните все поля', 'error');
        return;
    }

    const endpoint = paymentForm.value.type === 'bonus' ? '/salary/bonus' : '/salary/penalty';
    try {
        await store.api(endpoint, {
            method: 'POST',
            body: JSON.stringify({
                user_id: paymentForm.value.user_id,
                period_id: paymentForm.value.period_id,
                amount: paymentForm.value.amount,
                description: paymentForm.value.description
            })
        });
        showPaymentModal.value = false;
        store.showToast(`${paymentForm.value.type === 'bonus' ? 'Премия' : 'Штраф'} добавлен(а)`, 'success');
        await openPeriodDetails(currentPeriod.value);
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function payAdvance() {
    if (!currentPeriod.value) return;
    paymentForm.value = {
        id: null,
        user_id: null,
        period_id: currentPeriod.value.id,
        type: 'advance',
        amount: 0,
        description: 'Аванс'
    };
    showPaymentModal.value = true;
}

async function saveAdvance() {
    if (!paymentForm.value.user_id || !paymentForm.value.amount) {
        store.showToast('Заполните все поля', 'error');
        return;
    }

    try {
        await store.api('/salary/advance', {
            method: 'POST',
            body: JSON.stringify({
                user_id: paymentForm.value.user_id,
                period_id: paymentForm.value.period_id,
                amount: paymentForm.value.amount,
                payment_method: 'cash'
            })
        });
        showPaymentModal.value = false;
        store.showToast('Аванс выплачен', 'success');
        await openPeriodDetails(currentPeriod.value);
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

function getStatusColor(status) {
    const colors = {
        draft: 'bg-gray-100 text-gray-700',
        calculating: 'bg-blue-100 text-blue-700',
        calculated: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        paid: 'bg-emerald-100 text-emerald-700',
        closed: 'bg-gray-200 text-gray-500'
    };
    return colors[status] || 'bg-gray-100 text-gray-700';
}

function getStatusLabel(status) {
    const labels = {
        draft: 'Черновик',
        calculating: 'Расчёт...',
        calculated: 'Рассчитано',
        approved: 'Утверждено',
        paid: 'Выплачено',
        closed: 'Закрыто'
    };
    return labels[status] || status;
}

// Init
onMounted(() => {
    if (store.staff.length === 0) {
        store.loadStaff();
    }
    // Загружаем роли для корректного отображения в списке сотрудников
    if (roles.value.length === 0) {
        loadRoles();
    }
});
</script>
