<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Учёт рабочего времени</h2>
                <p class="text-gray-500">Настройка контроля прихода и ухода сотрудников</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b">
            <nav class="flex gap-4">
                <button v-for="tab in tabs" :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'py-3 px-1 border-b-2 font-medium text-sm transition',
                            activeTab === tab.id
                                ? 'border-orange-500 text-orange-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        ]">
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Timesheet Tab - Table + Sliding Panel (Combined Design) -->
        <!-- BACKUP: AttendanceTab.vue.backup-timesheet -->
        <div v-if="activeTab === 'timesheet'" class="relative h-[calc(100vh-220px)] overflow-hidden">

            <!-- Main Table View -->
            <div class="h-full bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col border border-gray-100">
                <!-- Header -->
                <div class="px-4 py-3 bg-gradient-to-r from-gray-50 to-white border-b flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <button @click="prevMonth" class="p-2 hover:bg-gray-100 rounded-xl transition-all text-gray-500 hover:text-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <span class="font-bold text-lg text-gray-800">{{ timesheetData?.month_name }} {{ timesheetYear }}</span>
                        <button @click="nextMonth" class="p-2 hover:bg-gray-100 rounded-xl transition-all text-gray-500 hover:text-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                    <span class="text-sm text-gray-400">{{ timesheetData?.employees?.length || 0 }} сотрудников</span>
                </div>

                <!-- Unclosed sessions warning banner -->
                <div v-if="timesheetData?.unclosed_sessions?.length > 0"
                     class="px-4 py-2.5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-200 flex items-center gap-3 flex-shrink-0">
                    <div class="flex items-center gap-2 text-amber-600">
                        <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span class="font-medium text-sm">Незакрытые смены:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="session in timesheetData.unclosed_sessions" :key="session.session_id"
                                @click="openUnclosedSession(session)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white rounded-lg border border-amber-200 text-xs font-medium text-amber-700 hover:bg-amber-50 transition-colors shadow-sm">
                            <span>{{ session.user_name }}</span>
                            <span class="text-amber-500">{{ session.clock_in }}</span>
                            <span class="text-amber-400">({{ Math.round(session.hours_open) }}ч)</span>
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="flex-1 overflow-auto">
                    <div v-if="timesheetLoading" class="p-8 text-center text-gray-400">
                        <svg class="w-5 h-5 animate-spin inline-block mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Загрузка...
                    </div>
                    <div v-else-if="!timesheetData?.employees?.length" class="p-8 text-center text-gray-400">Нет сотрудников</div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-gray-50/80 sticky top-0 z-10">
                            <tr>
                                <th class="text-left px-4 py-2.5 font-semibold text-gray-700 border-b bg-gray-50/80 backdrop-blur-sm sticky left-0 min-w-[200px]">
                                    Сотрудник
                                </th>
                                <th v-for="day in timesheetData?.days_in_month" :key="day"
                                    :class="[
                                        'px-1 py-1.5 font-medium text-center border-b min-w-[36px]',
                                        [6, 0].includes(new Date(timesheetYear, timesheetMonth - 1, day).getDay())
                                            ? 'text-red-500 bg-red-50/50'
                                            : 'text-gray-500'
                                    ]">
                                    <div class="text-[10px] opacity-70">{{ getWeekdayName(new Date(timesheetYear, timesheetMonth - 1, day).getDay()) }}</div>
                                    <div class="text-xs font-semibold">{{ day }}</div>
                                </th>
                                <th class="px-4 py-2.5 font-semibold text-gray-700 border-b text-right min-w-[80px]">Итого</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="emp in timesheetData.employees" :key="emp.id"
                                @click="selectEmployee(emp)"
                                :class="[
                                    'cursor-pointer transition-all duration-150 group',
                                    selectedEmployee?.id === emp.id
                                        ? 'bg-blue-100'
                                        : 'hover:bg-blue-50'
                                ]">
                                <td :class="[
                                    'px-4 py-2.5 border-b sticky left-0 transition-colors',
                                    selectedEmployee?.id === emp.id ? 'bg-blue-100' : 'bg-white group-hover:bg-blue-50'
                                ]">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-medium shadow-sm"
                                             :style="{ background: getAvatarColor(emp.id) }">
                                            {{ emp.initials }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-800">{{ emp.name }}</div>
                                            <div class="text-xs text-gray-400">{{ getRoleLabel(emp.role) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td v-for="day in timesheetData?.days_in_month" :key="day"
                                    @click.stop="openDayModalFromTable(emp, day, $event)"
                                    :class="[
                                        'px-0.5 py-1 text-center border-b border-l border-gray-100 text-xs cursor-pointer transition-all duration-150 relative',
                                        getTableCellClass(emp, day),
                                        'hover:bg-blue-100 hover:scale-105'
                                    ]">
                                    <!-- Red flag for auto-closed sessions (forgot to clock out) -->
                                    <div v-if="hasAutoClosedFlag(emp, day)"
                                         class="absolute top-0 right-0 w-0 h-0 border-t-[10px] border-t-red-500 border-l-[10px] border-l-transparent"
                                         title="Забыли отметить уход"></div>
                                    <!-- Status badges -->
                                    <template v-if="getDayStatus(emp, day)">
                                        <div v-if="getDayStatus(emp, day) === 'vacation'"
                                             class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white shadow-sm"
                                             title="Отпуск">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                            </svg>
                                        </div>
                                        <div v-else-if="getDayStatus(emp, day) === 'sick_leave'"
                                             class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-500 text-white shadow-sm"
                                             title="Больничный">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                            </svg>
                                        </div>
                                        <div v-else-if="getDayStatus(emp, day) === 'day_off'"
                                             class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-400 text-white shadow-sm"
                                             title="Выходной">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                            </svg>
                                        </div>
                                        <div v-else-if="getDayStatus(emp, day) === 'absence'"
                                             class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white shadow-sm"
                                             title="Прогул">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </div>
                                    </template>
                                    <!-- Hours display -->
                                    <template v-else>
                                        <span :class="getTableCellValueClass(emp, day)">{{ getTableCellValue(emp, day) }}</span>
                                    </template>
                                </td>
                                <td class="px-4 py-2.5 border-b border-l text-right font-bold bg-gray-50/50" :class="getHoursClass(emp.total_worked)">
                                    {{ emp.total_worked_formatted }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Backdrop for closing panel on outside click -->
            <div v-if="selectedEmployee"
                 class="absolute inset-0 z-10"
                 @click="closeEmployeePanel"></div>

            <!-- Right: Employee Calendar Panel (slides over table) -->
            <div :class="[
                'absolute top-0 right-0 h-full bg-white rounded-l-2xl shadow-2xl overflow-hidden flex flex-col transition-all duration-300 ease-out z-20 border-l-4 border-blue-500',
                selectedEmployee ? 'w-[60%] translate-x-0 opacity-100' : 'w-[60%] translate-x-full opacity-0 pointer-events-none'
            ]"
                 @click.stop>
                <div v-if="selectedEmployee" class="flex flex-col h-full">
                    <!-- Employee header -->
                    <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-blue-50 to-white">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white text-xl font-bold shadow-lg"
                                 :style="{ background: `linear-gradient(135deg, ${getAvatarColor(selectedEmployee.id)}, ${getAvatarColor(selectedEmployee.id)}cc)` }">
                                {{ selectedEmployee.initials }}
                            </div>
                            <div>
                                <div class="font-bold text-lg text-gray-800">{{ selectedEmployee.name }}</div>
                                <div class="text-sm text-gray-400">{{ getRoleLabel(selectedEmployee.role) }}</div>
                            </div>
                        </div>
                        <button @click="closeEmployeePanel"
                                class="w-10 h-10 flex items-center justify-center hover:bg-gray-100 rounded-xl transition-all duration-200 text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Calendar -->
                    <div v-if="employeeTimesheetLoading" class="flex-1 flex items-center justify-center text-gray-400">
                        <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Загрузка...
                    </div>
                    <div v-else-if="employeeTimesheet" class="flex-1 overflow-auto p-5">
                        <div class="text-lg font-bold text-gray-800 mb-4">{{ employeeTimesheet.month_name }}'{{ String(employeeTimesheet.year).slice(-2) }}</div>

                        <!-- Calendar grid -->
                        <div class="grid grid-cols-7 gap-1.5 text-center text-sm mb-5">
                            <div class="font-medium text-gray-400 py-1 text-xs">Пн</div>
                            <div class="font-medium text-gray-400 py-1 text-xs">Вт</div>
                            <div class="font-medium text-gray-400 py-1 text-xs">Ср</div>
                            <div class="font-medium text-gray-400 py-1 text-xs">Чт</div>
                            <div class="font-medium text-gray-400 py-1 text-xs">Пт</div>
                            <div class="font-medium text-red-400 py-1 text-xs">Сб</div>
                            <div class="font-medium text-red-400 py-1 text-xs">Вс</div>

                            <!-- Empty cells for alignment -->
                            <div v-for="i in getFirstDayOffset()" :key="'empty-'+i" class="p-1"></div>

                            <!-- Days -->
                            <div v-for="day in timesheetData?.days_in_month" :key="day"
                                 @click="openDayModal(day, $event)"
                                 :class="[
                                    'p-1.5 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105 hover:shadow-md min-h-[52px] flex flex-col items-center justify-center',
                                    getDayClassModern(day)
                                 ]">
                                <div class="text-[10px] text-gray-400 mb-0.5">{{ day }}</div>
                                <!-- Status icons -->
                                <div v-if="employeeTimesheet.calendar[day]?.override?.type === 'vacation'"
                                     class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                    </svg>
                                </div>
                                <div v-else-if="employeeTimesheet.calendar[day]?.override?.type === 'sick_leave'"
                                     class="w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                </div>
                                <div v-else-if="employeeTimesheet.calendar[day]?.override?.type === 'day_off'"
                                     class="w-6 h-6 rounded-full bg-slate-400 text-white flex items-center justify-center shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                    </svg>
                                </div>
                                <div v-else-if="employeeTimesheet.calendar[day]?.override?.type === 'absence'"
                                     class="w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                                <!-- Hours -->
                                <div v-else-if="employeeTimesheet.calendar[day]?.hours > 0"
                                     :class="['text-sm font-bold', getHoursClass(employeeTimesheet.calendar[day].hours)]">
                                    {{ employeeTimesheet.calendar[day].formatted }}
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 rounded-xl p-3 border border-emerald-200/50">
                                <div class="text-xs font-medium text-emerald-600 uppercase tracking-wide mb-0.5">Отработано</div>
                                <div class="text-xl font-bold text-emerald-700">
                                    {{ employeeTimesheet.summary.total_worked_formatted }}
                                </div>
                                <div class="text-xs text-emerald-500">
                                    {{ employeeTimesheet.summary.days_worked }} дней
                                </div>
                            </div>
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl p-3 border border-blue-200/50">
                                <div class="text-xs font-medium text-blue-600 uppercase tracking-wide mb-0.5">По плану</div>
                                <div class="text-xl font-bold text-blue-700">
                                    {{ employeeTimesheet.summary.total_planned_formatted }}
                                </div>
                                <div class="text-xs text-blue-500">
                                    {{ employeeTimesheet.summary.planned_days }} дней
                                </div>
                            </div>
                            <div v-if="employeeTimesheet.summary.underworked > 0"
                                 class="bg-gradient-to-br from-red-50 to-red-100/50 rounded-xl p-3 border border-red-200/50">
                                <div class="text-xs font-medium text-red-600 uppercase tracking-wide mb-0.5">Недоработано</div>
                                <div class="text-xl font-bold text-red-600">
                                    {{ employeeTimesheet.summary.underworked_formatted }}
                                </div>
                            </div>
                            <div v-else class="bg-gradient-to-br from-gray-50 to-gray-100/50 rounded-xl p-3 border border-gray-200/50">
                                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-0.5">Статус</div>
                                <div class="text-base font-bold text-emerald-600 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Норма
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Day Detail Modal (Modern Style) -->
        <!-- OLD STYLE BACKUP: To revert, replace this entire modal block with the backup in AttendanceTab.vue.backup -->
        <div v-if="showDayModal" class="fixed inset-0 z-50" @click="closeDayModal">
            <!-- Backdrop with blur -->
            <div class="absolute inset-0 bg-black/20 backdrop-blur-sm"></div>

            <!-- Modal Card -->
            <div class="absolute bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl shadow-gray-900/10 border border-white/50 w-96 transition-all duration-300"
                 :style="dayModalPosition"
                 @click.stop>

                <!-- Colored stripe indicator on the left -->
                <div class="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-2xl" :class="getDayTypeStripeClass()"></div>

                <!-- Header with gradient accent -->
                <div class="relative px-5 pt-5 pb-4 pl-6">
                    <!-- Subtle gradient background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50/80 to-violet-50/50"></div>

                    <div class="relative flex items-start justify-between">
                        <div>
                            <div class="text-2xl font-bold text-gray-900">{{ selectedDay }}</div>
                            <div class="text-sm text-gray-500 mt-0.5">
                                {{ getMonthNameGenitive(timesheetMonth) }}, {{ getShortDayName(selectedDayData?.day_of_week) }}
                            </div>
                        </div>
                        <button @click="closeDayModal"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/80 hover:bg-white text-gray-400 hover:text-gray-600 transition-all duration-200 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="px-5 py-4 space-y-4 pl-6">
                    <!-- Hours Card -->
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <!-- Hours Display/Edit -->
                                <div v-if="!editingHours"
                                     @click="startEditingHours"
                                     class="text-3xl font-bold text-gray-900 cursor-pointer hover:text-blue-600 transition-colors min-w-[80px]">
                                    {{ formatHoursCompact(getCurrentHours()) }}
                                </div>
                                <input v-else
                                       type="text"
                                       v-model="editTimeValue"
                                       maxlength="5"
                                       placeholder="00:00"
                                       class="w-24 text-2xl font-semibold text-center bg-white border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                       @keyup.enter="saveEditedHours"
                                       @keyup.escape="cancelEditingHours"
                                       @input="formatTimeInput"
                                       ref="hoursInput">

                                <!-- Day type badge -->
                                <span class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap"
                                      :class="getDayTypeBadgeClass()">
                                    {{ getDayTypeLabel() }}
                                </span>
                            </div>

                            <!-- Action buttons -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <template v-if="editingHours">
                                    <button @click="saveEditedHours"
                                            class="w-8 h-8 flex items-center justify-center bg-green-500 hover:bg-green-600 text-white rounded-xl transition-all duration-200 shadow-lg shadow-green-500/30">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    <button @click="cancelEditingHours"
                                            class="w-8 h-8 flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-xl transition-all duration-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </template>
                                <template v-else>
                                    <!-- Day type dropdown trigger -->
                                    <div class="relative">
                                        <button @click="showDayTypeDropdown = !showDayTypeDropdown"
                                                class="w-8 h-8 flex items-center justify-center bg-white hover:bg-gray-50 text-gray-400 hover:text-gray-600 rounded-xl transition-all duration-200 shadow-sm border border-gray-100">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                        </button>

                                        <!-- Modern Dropdown -->
                                        <div v-if="showDayTypeDropdown"
                                             class="absolute right-0 top-10 bg-white backdrop-blur-xl rounded-xl shadow-2xl shadow-gray-900/20 border border-gray-200 w-52 z-50">
                                            <div class="p-2 space-y-0.5">
                                                <button @click="selectDayTypeFromDropdown('shift')"
                                                        :class="['w-full px-3 py-2 text-left text-sm rounded-lg flex items-center gap-3 transition-colors', getCurrentDayType() === 'shift' ? 'bg-blue-50' : 'hover:bg-gray-50']">
                                                    <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-br from-blue-400 to-blue-600"></span>
                                                    <span class="font-medium text-gray-700 flex-1">Рабочий день</span>
                                                    <svg v-if="getCurrentDayType() === 'shift'" class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                                <button @click="selectDayTypeFromDropdown('day_off')"
                                                        :class="['w-full px-3 py-2 text-left text-sm rounded-lg flex items-center gap-3 transition-colors', getCurrentDayType() === 'day_off' ? 'bg-gray-100' : 'hover:bg-gray-50']">
                                                    <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-br from-gray-300 to-gray-500"></span>
                                                    <span class="font-medium text-gray-700 flex-1">Выходной</span>
                                                    <svg v-if="getCurrentDayType() === 'day_off'" class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                                <button @click="selectDayTypeFromDropdown('vacation')"
                                                        :class="['w-full px-3 py-2 text-left text-sm rounded-lg flex items-center gap-3 transition-colors', getCurrentDayType() === 'vacation' ? 'bg-emerald-50' : 'hover:bg-gray-50']">
                                                    <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600"></span>
                                                    <span class="font-medium text-gray-700 flex-1">Отпуск</span>
                                                    <svg v-if="getCurrentDayType() === 'vacation'" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                                <button @click="selectDayTypeFromDropdown('sick_leave')"
                                                        :class="['w-full px-3 py-2 text-left text-sm rounded-lg flex items-center gap-3 transition-colors', getCurrentDayType() === 'sick_leave' ? 'bg-amber-50' : 'hover:bg-gray-50']">
                                                    <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-br from-amber-400 to-amber-600"></span>
                                                    <span class="font-medium text-gray-700 flex-1">Больничный</span>
                                                    <svg v-if="getCurrentDayType() === 'sick_leave'" class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>
                                                <button @click="selectDayTypeFromDropdown('absence')"
                                                        :class="['w-full px-3 py-2 text-left text-sm rounded-lg flex items-center gap-3 transition-colors', getCurrentDayType() === 'absence' ? 'bg-red-50' : 'hover:bg-gray-50']">
                                                    <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-br from-red-400 to-red-600"></span>
                                                    <span class="font-medium text-gray-700 flex-1">Прогул</span>
                                                    <svg v-if="getCurrentDayType() === 'absence'" class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>

                                                <div v-if="selectedDayData?.override" class="border-t border-gray-100 mt-1.5 pt-1.5">
                                                    <button @click="removeDayOverride"
                                                            class="w-full px-3 py-2 text-left text-sm rounded-lg hover:bg-red-50 flex items-center gap-3 text-red-500 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        <span class="font-medium">Сбросить</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete button -->
                                    <button @click="clearDayData"
                                            class="w-8 h-8 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all duration-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Device Marks Section -->
                    <div class="bg-gray-50/50 rounded-xl border border-gray-100 overflow-hidden">
                        <button @click="showDeviceMarks = !showDeviceMarks"
                                class="w-full px-4 py-3 flex items-center gap-3 text-sm hover:bg-gray-100/50 transition-colors">
                            <div class="w-8 h-8 flex items-center justify-center bg-white rounded-lg shadow-sm">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                </svg>
                            </div>
                            <div class="flex-1 text-left">
                                <div class="font-medium text-gray-700">Отметки с устройства</div>
                                <div v-if="getDeviceSessions().length > 0" class="text-xs text-gray-400">
                                    {{ getFirstDeviceClockIn() }} - {{ getLastDeviceClockOut() }}
                                </div>
                            </div>
                            <span v-if="getDeviceSessions().length > 0"
                                  class="px-2 py-0.5 bg-gray-200 text-gray-600 text-xs font-medium rounded-full">
                                {{ getDeviceSessions().length }}
                            </span>
                            <svg :class="['w-4 h-4 text-gray-400 transition-transform duration-200', showDeviceMarks ? 'rotate-180' : '']"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Expanded sessions -->
                        <div v-if="showDeviceMarks" class="px-4 pb-4 pt-1 space-y-2">
                            <div v-if="getDeviceSessions().length > 0" class="space-y-2">
                                <div v-for="session in getDeviceSessions()" :key="session.id"
                                     :class="[
                                         'py-2.5 px-3 rounded-lg border',
                                         session.is_active ? 'bg-amber-50 border-amber-200' :
                                         session.is_auto_closed ? 'bg-slate-50 border-slate-300' :
                                         'bg-white border-gray-100'
                                     ]">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div :class="[
                                                'w-2 h-2 rounded-full',
                                                session.is_active ? 'bg-amber-500 animate-pulse' :
                                                session.is_auto_closed ? 'bg-slate-400' :
                                                'bg-green-500'
                                            ]"></div>
                                            <span class="text-sm font-medium text-gray-700">{{ session.clock_in }}</span>
                                            <span class="text-gray-300">→</span>
                                            <span class="text-sm font-medium text-gray-700">
                                                <template v-if="session.is_active">
                                                    <span class="text-amber-600">не закрыто</span>
                                                </template>
                                                <template v-else-if="session.is_auto_closed">
                                                    <span class="text-slate-500">забыл уйти</span>
                                                </template>
                                                <template v-else>
                                                    {{ session.clock_out || '...' }}<span v-if="session.is_overnight" class="text-xs text-blue-500 ml-1">(+1д)</span>
                                                </template>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-if="session.is_active" class="text-xs text-amber-600 font-medium">
                                                ~{{ formatDuration(session.hours) }}
                                            </span>
                                            <span v-else-if="session.is_auto_closed" class="text-xs text-slate-500 bg-slate-100 px-2 py-1 rounded-md">
                                                0:00
                                            </span>
                                            <span v-else-if="session.hours > 0" class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-md">
                                                {{ formatDuration(session.hours) }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Close session form for active sessions -->
                                    <div v-if="session.is_active" class="mt-2 pt-2 border-t border-amber-200">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-amber-600">Закрыть смену:</span>
                                            <input type="text"
                                                   v-model="closeSessionTime"
                                                   @input="formatCloseSessionTime"
                                                   placeholder="00:00"
                                                   maxlength="5"
                                                   class="w-16 px-2 py-1 text-xs text-center border border-amber-300 rounded focus:ring-1 focus:ring-amber-400 focus:border-amber-400">
                                            <button @click="closeActiveSession(session.id)"
                                                    :disabled="!closeSessionTime || closeSessionTime.length < 5"
                                                    class="px-2 py-1 text-xs font-medium text-white bg-amber-500 rounded hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                Закрыть
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sync from device button -->
                                <button @click="syncFromDevice"
                                        class="w-full mt-2 px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg border border-blue-200 transition-colors flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Взять время с устройства ({{ formatDuration(getDeviceHoursTotal()) }})
                                </button>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-4">
                                Нет отметок с устройства
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div v-if="activeTab === 'settings'" class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Режим контроля</h3>

                <div class="space-y-4">
                    <div v-for="mode in attendanceModes" :key="mode.value"
                         @click="settings.attendance_mode = mode.value"
                         :class="[
                             'p-4 border-2 rounded-xl cursor-pointer transition',
                             settings.attendance_mode === mode.value
                                 ? 'border-orange-500 bg-orange-50'
                                 : 'border-gray-200 hover:border-gray-300'
                         ]">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">{{ mode.icon }}</span>
                            <div>
                                <div class="font-semibold">{{ mode.label }}</div>
                                <div class="text-sm text-gray-500">{{ mode.description }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="settings.attendance_mode !== 'disabled'" class="mt-6 pt-6 border-t space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Можно отметиться за (мин до смены)
                            </label>
                            <input type="number" v-model.number="settings.attendance_early_minutes"
                                   min="0" max="120"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Макс. опоздание (мин после начала)
                            </label>
                            <input type="number" v-model.number="settings.attendance_late_minutes"
                                   min="0" max="480"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Широта ресторана
                            </label>
                            <input type="text" v-model="settings.latitude"
                                   placeholder="55.7558"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Долгота ресторана
                            </label>
                            <input type="text" v-model="settings.longitude"
                                   placeholder="37.6173"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                </div>

                <button @click="saveSettings" :disabled="savingSettings"
                        class="mt-6 px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                    {{ savingSettings ? 'Сохранение...' : 'Сохранить настройки' }}
                </button>
            </div>

            <!-- QR Settings -->
            <div v-if="['qr_only', 'device_or_qr'].includes(settings.attendance_mode)"
                 class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Настройки QR-кода</h3>

                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="qrSettings.type" value="dynamic"
                                   class="text-orange-500">
                            <span>Динамический (обновляется)</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="qrSettings.type" value="static"
                                   class="text-orange-500">
                            <span>Статический</span>
                        </label>
                    </div>

                    <div v-if="qrSettings.type === 'dynamic'">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Интервал обновления (мин)
                        </label>
                        <input type="number" v-model.number="qrSettings.refresh_interval_minutes"
                               min="1" max="60"
                               class="w-40 px-4 py-2 border rounded-lg">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" v-model="qrSettings.require_geolocation"
                               id="require_geo" class="text-orange-500 rounded">
                        <label for="require_geo">Требовать геолокацию при сканировании</label>
                    </div>

                    <div v-if="qrSettings.require_geolocation">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Макс. расстояние от ресторана (м)
                        </label>
                        <input type="number" v-model.number="qrSettings.max_distance_meters"
                               min="10" max="1000"
                               class="w-40 px-4 py-2 border rounded-lg">
                    </div>

                    <button @click="saveQrSettings" :disabled="savingQr"
                            class="px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                        {{ savingQr ? 'Сохранение...' : 'Сохранить настройки QR' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Devices Tab -->
        <div v-if="activeTab === 'devices'" class="space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold">Терминалы биометрии</h3>
                <button @click="showDeviceModal = true"
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    + Добавить устройство
                </button>
            </div>

            <div v-if="devices.length === 0" class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
                <div class="text-4xl mb-2">📟</div>
                <p>Нет добавленных устройств</p>
                <p class="text-sm">Добавьте терминал биометрии для контроля прихода/ухода</p>
            </div>

            <div v-else class="grid gap-4">
                <div v-for="device in devices" :key="device.id"
                     class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div :class="[
                                'w-12 h-12 rounded-full flex items-center justify-center text-2xl',
                                device.is_online ? 'bg-green-100' : 'bg-gray-100'
                            ]">
                                {{ getDeviceIcon(device.type) }}
                            </div>
                            <div>
                                <div class="font-semibold">{{ device.name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ device.type_label }} {{ device.model ? `/ ${device.model}` : '' }}
                                </div>
                                <div class="text-xs text-gray-400">
                                    S/N: {{ device.serial_number }}
                                    <span v-if="device.ip_address">| IP: {{ device.ip_address }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span :class="[
                                'px-2 py-1 rounded text-xs font-medium',
                                device.is_online ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                            ]">
                                {{ device.is_online ? 'Онлайн' : 'Офлайн' }}
                            </span>
                            <button @click="editDevice(device)"
                                    class="p-2 text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button @click="deleteDevice(device)"
                                    class="p-2 text-red-400 hover:text-red-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t flex items-center justify-between text-sm">
                        <span class="text-gray-500">
                            Сотрудников: {{ device.users_count }}
                        </span>
                        <div class="flex gap-2">
                            <button @click="testDeviceConnection(device)"
                                    class="text-blue-600 hover:underline">
                                Проверить связь
                            </button>
                            <button @click="openDeviceUsersModal(device)"
                                    class="text-orange-600 hover:underline">
                                Сотрудники
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Display Tab -->
        <div v-if="activeTab === 'qr-display'" class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                <h3 class="text-lg font-semibold mb-4">QR-код для сканирования</h3>

                <div v-if="!['qr_only', 'device_or_qr'].includes(settings.attendance_mode)"
                     class="text-gray-500 py-8">
                    QR-код отключён. Включите режим "QR-код" или "Терминал или QR-код" в настройках.
                </div>

                <div v-else class="space-y-4">
                    <div class="bg-gray-100 p-8 rounded-xl inline-block">
                        <div v-if="qrCodeUrl" class="bg-white p-4 rounded-lg">
                            <img :src="qrCodeUrl" alt="QR Code" class="w-64 h-64 mx-auto">
                        </div>
                        <div v-else class="w-64 h-64 flex items-center justify-center text-gray-400">
                            Загрузка QR-кода...
                        </div>
                    </div>

                    <div v-if="qrExpiry" class="text-sm text-gray-500">
                        Обновится через: {{ qrCountdown }}
                    </div>

                    <div class="flex justify-center gap-4">
                        <button @click="refreshQrCode"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                            Обновить
                        </button>
                        <button @click="openQrFullscreen"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                            Открыть на весь экран
                        </button>
                        <button @click="printQrCode"
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                            Распечатать
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events Tab -->
        <div v-if="activeTab === 'events'" class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold">События учёта времени</h3>
                    <div class="flex gap-2">
                        <input type="date" v-model="eventsDate"
                               class="px-3 py-1 border rounded-lg text-sm">
                        <button @click="loadEvents"
                                class="px-3 py-1 bg-gray-200 rounded-lg text-sm hover:bg-gray-300">
                            Обновить
                        </button>
                    </div>
                </div>

                <div v-if="events.length === 0" class="p-8 text-center text-gray-500">
                    Нет событий за выбранную дату
                </div>

                <table v-else class="w-full">
                    <thead class="bg-gray-50 text-sm text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Время</th>
                            <th class="px-4 py-3 text-left">Сотрудник</th>
                            <th class="px-4 py-3 text-left">Событие</th>
                            <th class="px-4 py-3 text-left">Источник</th>
                            <th class="px-4 py-3 text-left">Метод</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="event in events" :key="event.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                {{ formatTime(event.event_time) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ event.user?.name }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="[
                                    'px-2 py-1 rounded text-xs font-medium',
                                    event.event_type === 'clock_in'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                ]">
                                    {{ event.event_type === 'clock_in' ? 'Приход' : 'Уход' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ event.source === 'device' ? event.device?.name : event.source_label }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ event.method_label }}
                                <span v-if="event.confidence" class="text-xs text-gray-400">
                                    ({{ event.confidence }}%)
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Device Modal -->
        <div v-if="showDeviceModal"
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
             @click.self="showDeviceModal = false">
            <div class="bg-white rounded-2xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold mb-4">
                    {{ editingDevice ? 'Редактировать устройство' : 'Добавить устройство' }}
                </h3>

                <form @submit.prevent="saveDevice" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Название</label>
                        <input v-model="deviceForm.name" type="text" required
                               placeholder="Терминал у входа"
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Тип</label>
                            <select v-model="deviceForm.type" required
                                    class="w-full px-4 py-2 border rounded-lg">
                                <option value="anviz">Anviz</option>
                                <option value="zkteco">ZKTeco</option>
                                <option value="hikvision">Hikvision</option>
                                <option value="generic">Другое</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Модель</label>
                            <input v-model="deviceForm.model" type="text"
                                   placeholder="Facepass 7"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Серийный номер</label>
                        <input v-model="deviceForm.serial_number" type="text" required
                               placeholder="ABC123456"
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">IP-адрес</label>
                            <input v-model="deviceForm.ip_address" type="text"
                                   placeholder="192.168.1.100"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Порт</label>
                            <input v-model="deviceForm.port" type="number"
                                   placeholder="5010"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>

                    <div v-if="newDeviceApiKey" class="p-4 bg-yellow-50 rounded-lg">
                        <div class="text-sm font-medium text-yellow-800 mb-2">API-ключ для webhook:</div>
                        <code class="block p-2 bg-white rounded text-xs break-all">{{ newDeviceApiKey }}</code>
                        <div class="text-xs text-yellow-600 mt-2">
                            Сохраните этот ключ! Он показывается только один раз.
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showDeviceModal = false"
                                class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                            Отмена
                        </button>
                        <button type="submit" :disabled="savingDevice"
                                class="px-6 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
                            {{ savingDevice ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Device Users Modal -->
        <div v-if="showDeviceUsersModal"
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
             @click.self="showDeviceUsersModal = false">
            <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] flex flex-col">
                <div class="p-6 border-b flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">Сотрудники на устройстве</h3>
                        <p class="text-sm text-gray-500">{{ selectedDevice?.name }}</p>
                    </div>
                    <button @click="showDeviceUsersModal = false"
                            class="p-2 hover:bg-gray-100 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto flex-1">
                    <!-- Loading -->
                    <div v-if="loadingDeviceUsers" class="text-center py-8 text-gray-500">
                        Загрузка списка сотрудников...
                    </div>

                    <!-- Error -->
                    <div v-else-if="deviceUsersError" class="text-center py-8">
                        <div class="text-red-500 mb-2">{{ deviceUsersError }}</div>
                        <button @click="loadDeviceUsers(selectedDevice)"
                                class="text-blue-600 hover:underline">
                            Попробовать снова
                        </button>
                    </div>

                    <!-- Users List -->
                    <div v-else class="space-y-4">
                        <!-- Add new user -->
                        <div class="bg-orange-50 p-4 rounded-xl">
                            <div class="font-medium mb-2">Добавить сотрудника на устройство</div>
                            <div class="flex gap-2">
                                <select v-model="addUserForm.user_id"
                                        class="flex-1 px-3 py-2 border rounded-lg">
                                    <option value="">Выберите сотрудника...</option>
                                    <option v-for="user in availableUsersForDevice"
                                            :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.role_label }})
                                    </option>
                                </select>
                                <button @click="addUserToDevice"
                                        :disabled="!addUserForm.user_id || addingUser"
                                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 disabled:opacity-50">
                                    {{ addingUser ? '...' : 'Добавить' }}
                                </button>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">
                                После добавления сотрудник должен зарегистрировать лицо на устройстве
                            </div>
                        </div>

                        <!-- Device Users -->
                        <div v-if="deviceUsers.length === 0"
                             class="text-center py-8 text-gray-500">
                            На устройстве нет зарегистрированных сотрудников
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="duser in deviceUsers" :key="duser.user_id"
                                 class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-lg">
                                        {{ duser.has_biometric ? '👤' : '❓' }}
                                    </div>
                                    <div>
                                        <div class="font-medium">
                                            {{ duser.name || `ID: ${duser.user_id}` }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Device ID: {{ duser.user_id }}
                                            <span v-if="duser.has_biometric" class="text-green-600 ml-2">
                                                Лицо зарегистрировано
                                            </span>
                                            <span v-else class="text-yellow-600 ml-2">
                                                Нужна регистрация лица
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Link to MenuLab user -->
                                    <div v-if="duser.menulab_user" class="flex items-center gap-2">
                                        <span class="text-sm text-green-600">
                                            → {{ duser.menulab_user.name }}
                                        </span>
                                        <button @click="unlinkDeviceUser(duser)"
                                                class="p-1 text-gray-400 hover:text-red-500"
                                                title="Отвязать">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-else>
                                        <select @change="linkDeviceUser(duser, $event.target.value)"
                                                class="text-sm px-2 py-1 border rounded">
                                            <option value="">Связать с...</option>
                                            <option v-for="user in restaurantUsers"
                                                    :key="user.id" :value="user.id">
                                                {{ user.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Delete from device -->
                                    <button @click="removeUserFromDevice(duser)"
                                            class="p-1 text-red-400 hover:text-red-600"
                                            title="Удалить с устройства">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t bg-gray-50 flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Всего на устройстве: {{ deviceUsers.length }}
                    </div>
                    <button @click="loadDeviceUsers(selectedDevice)"
                            class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                        Обновить список
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

const activeTab = ref('timesheet');
const tabs = [
    { id: 'timesheet', label: 'Табель' },
    { id: 'settings', label: 'Настройки' },
    { id: 'devices', label: 'Устройства' },
    { id: 'qr-display', label: 'QR-код' },
    { id: 'events', label: 'События' },
];

const attendanceModes = [
    {
        value: 'disabled',
        label: 'Отключён',
        description: 'Свободный режим, без контроля',
        icon: '🔓',
    },
    {
        value: 'device_only',
        label: 'Только терминал',
        description: 'Отметка только через устройство биометрии в ресторане',
        icon: '📟',
    },
    {
        value: 'qr_only',
        label: 'Только QR-код',
        description: 'Отметка через сканирование QR-кода на телефоне',
        icon: '📱',
    },
    {
        value: 'device_or_qr',
        label: 'Терминал или QR-код',
        description: 'Можно использовать любой способ',
        icon: '✅',
    },
];

// Settings
const settings = reactive({
    attendance_mode: 'disabled',
    attendance_early_minutes: 30,
    attendance_late_minutes: 120,
    latitude: null,
    longitude: null,
    restaurant_id: null,
});
const savingSettings = ref(false);

// QR Settings
const qrSettings = reactive({
    type: 'dynamic',
    require_geolocation: true,
    max_distance_meters: 100,
    refresh_interval_minutes: 5,
});
const savingQr = ref(false);
const qrCodeUrl = ref(null);
const qrExpiry = ref(null);
const qrCountdown = ref('');
let countdownInterval = null;

// Devices
const devices = ref([]);
const showDeviceModal = ref(false);
const editingDevice = ref(null);
const savingDevice = ref(false);
const newDeviceApiKey = ref(null);
const deviceForm = reactive({
    name: '',
    type: 'anviz',
    model: '',
    serial_number: '',
    ip_address: '',
    port: null,
});

// Events
const events = ref([]);
const eventsDate = ref(new Date().toISOString().split('T')[0]);

// Schedule (График)
const scheduleData = ref(null);
const scheduleLoading = ref(false);
const scheduleYear = ref(new Date().getFullYear());
const scheduleMonth = ref(new Date().getMonth() + 1);
const showShiftModal = ref(false);
const selectedScheduleEmployee = ref(null);
const selectedScheduleDay = ref(null);
const shiftModalPosition = ref({ left: '0px', top: '0px' });
const savingShift = ref(false);

// Shift form
const shiftForm = reactive({
    template: null,
    start_time: '',
    end_time: '',
    break_minutes: 0,
});

// Shift templates
const shiftTemplates = [
    { id: 'morning', label: 'Утренняя', start: '08:00', end: '16:00', break: 30, color: 'amber' },
    { id: 'day', label: 'Дневная', start: '10:00', end: '18:00', break: 30, color: 'blue' },
    { id: 'evening', label: 'Вечерняя', start: '14:00', end: '22:00', break: 30, color: 'purple' },
    { id: 'full', label: 'Полный день', start: '10:00', end: '22:00', break: 60, color: 'emerald' },
    { id: 'night', label: 'Ночная', start: '22:00', end: '08:00', break: 30, color: 'slate' },
    { id: 'custom', label: 'Своя смена', start: '', end: '', break: 0, color: 'gray' },
];

// Multi-select for bulk operations
const scheduleSelectionMode = ref(false);
const selectedScheduleCells = ref([]); // [{empId, day}, ...]

// Device Users Modal
const showDeviceUsersModal = ref(false);
const selectedDevice = ref(null);
const deviceUsers = ref([]);
const loadingDeviceUsers = ref(false);
const deviceUsersError = ref(null);
const restaurantUsers = ref([]);
const addUserForm = reactive({ user_id: '' });
const addingUser = ref(false);

// ==================== TIMESHEET ====================
const timesheetData = ref(null);
const timesheetLoading = ref(false);
const timesheetYear = ref(new Date().getFullYear());
const timesheetMonth = ref(new Date().getMonth() + 1);
const selectedEmployee = ref(null);
const modalEmployee = ref(null); // Employee for day modal (separate from panel)
const employeeTimesheet = ref(null);
const employeeTimesheetLoading = ref(false);

// Get current employee being edited (panel or modal)
const currentEmployee = computed(() => selectedEmployee.value || modalEmployee.value);

// Cache for employee timesheets (for table display)
const employeeTimesheetsCache = ref({});

async function loadTimesheet(clearCache = true, silent = false) {
    if (!silent) {
        timesheetLoading.value = true;
    }
    if (clearCache) {
        employeeTimesheetsCache.value = {}; // Clear cache only on month change
    }
    try {
        const res = await store.api(`/backoffice/attendance/timesheet?year=${timesheetYear.value}&month=${timesheetMonth.value}`);
        if (res.success) {
            timesheetData.value = res.data;
            // Load all employee timesheets in background for table display
            if (clearCache) {
                loadAllEmployeeTimesheets();
            }
        }
    } catch (e) {
        console.error('Failed to load timesheet:', e);
    } finally {
        if (!silent) {
            timesheetLoading.value = false;
        }
    }
}

// Load all employee timesheets in background (for table cells)
async function loadAllEmployeeTimesheets() {
    if (!timesheetData.value?.employees) return;

    for (const emp of timesheetData.value.employees) {
        if (!employeeTimesheetsCache.value[emp.id]) {
            try {
                const res = await store.api(`/backoffice/attendance/timesheet/${emp.id}?year=${timesheetYear.value}&month=${timesheetMonth.value}`);
                if (res.success) {
                    employeeTimesheetsCache.value[emp.id] = res.data;
                }
            } catch (e) {
                console.error(`Failed to load timesheet for employee ${emp.id}:`, e);
            }
        }
    }
}

async function loadEmployeeTimesheet(userId, forceReload = false, silent = false) {
    if (!silent) {
        employeeTimesheetLoading.value = true;
    }
    try {
        // Check cache first (unless force reload)
        if (!forceReload && employeeTimesheetsCache.value[userId]) {
            employeeTimesheet.value = employeeTimesheetsCache.value[userId];
            if (!silent) {
                employeeTimesheetLoading.value = false;
            }
            return;
        }

        const res = await store.api(`/backoffice/attendance/timesheet/${userId}?year=${timesheetYear.value}&month=${timesheetMonth.value}`);
        if (res.success) {
            employeeTimesheet.value = res.data;
            employeeTimesheetsCache.value[userId] = res.data; // Cache it
        }
    } catch (e) {
        console.error('Failed to load employee timesheet:', e);
    } finally {
        if (!silent) {
            employeeTimesheetLoading.value = false;
        }
    }
}

function selectEmployee(employee) {
    selectedEmployee.value = employee;
    loadEmployeeTimesheet(employee.id);
}

function closeEmployeePanel() {
    selectedEmployee.value = null;
    employeeTimesheet.value = null;
}

// Open day modal from table cell (without opening panel)
async function openDayModalFromTable(emp, day, event) {
    modalEmployee.value = emp;

    // Load timesheet from cache or API
    let timesheet = employeeTimesheetsCache.value[emp.id];
    if (!timesheet) {
        try {
            const res = await store.api(`/backoffice/attendance/timesheet/${emp.id}?year=${timesheetYear.value}&month=${timesheetMonth.value}`);
            if (res.success) {
                timesheet = res.data;
                employeeTimesheetsCache.value[emp.id] = timesheet;
            }
        } catch (e) {
            console.error('Failed to load timesheet:', e);
            return;
        }
    }

    if (!timesheet) return;

    // Set employeeTimesheet for modal to use
    employeeTimesheet.value = timesheet;

    // Now open the modal
    const dayData = timesheet.calendar[day];
    if (!dayData) return;

    selectedDay.value = day;
    selectedDayData.value = JSON.parse(JSON.stringify(dayData));

    const date = new Date(timesheetYear.value, timesheetMonth.value - 1, day);
    selectedDayData.value.fullDate = date;
    selectedDayData.value.day_of_week = date.getDay();
    selectedDayData.value.dayName = getDayName(date.getDay());

    // Position modal near click
    if (event) {
        const rect = event.target.getBoundingClientRect();
        const modalWidth = 384;
        const modalHeight = 320;

        let left = rect.left;
        let top = rect.bottom + 8;

        if (left + modalWidth > window.innerWidth - 20) {
            left = window.innerWidth - modalWidth - 20;
        }
        if (top + modalHeight > window.innerHeight - 20) {
            top = rect.top - modalHeight - 8;
        }
        if (left < 20) left = 20;
        if (top < 20) top = 20;

        dayModalPosition.value = { left: `${left}px`, top: `${top}px` };
    }

    showDayModal.value = true;
    editingHours.value = false;
    showDayTypeDropdown.value = false;
    showDeviceMarks.value = false;
}

// Open day modal from unclosed session notification
async function openUnclosedSession(session) {
    // Find employee data
    const emp = timesheetData.value?.employees?.find(e => e.id === session.user_id);
    if (!emp) return;

    // Parse date from session
    const dateParts = session.date.split('-');
    const day = parseInt(dateParts[2], 10);

    // Open the modal for that employee and day
    modalEmployee.value = emp;

    // Load timesheet from cache or API
    let timesheet = employeeTimesheetsCache.value[emp.id];
    if (!timesheet) {
        try {
            const res = await store.api(`/backoffice/attendance/timesheet/${emp.id}?year=${timesheetYear.value}&month=${timesheetMonth.value}`);
            if (res.success) {
                timesheet = res.data;
                employeeTimesheetsCache.value[emp.id] = timesheet;
            }
        } catch (e) {
            console.error('Failed to load timesheet:', e);
            return;
        }
    }

    if (!timesheet) return;

    employeeTimesheet.value = timesheet;

    const dayData = timesheet.calendar[day];
    if (!dayData) return;

    selectedDay.value = day;
    selectedDayData.value = JSON.parse(JSON.stringify(dayData));

    const date = new Date(timesheetYear.value, timesheetMonth.value - 1, day);
    selectedDayData.value.fullDate = date;
    selectedDayData.value.day_of_week = date.getDay();
    selectedDayData.value.dayName = getDayName(date.getDay());

    // Position modal in center of screen
    dayModalPosition.value = {
        left: `${(window.innerWidth - 384) / 2}px`,
        top: `${Math.max(100, (window.innerHeight - 400) / 3)}px`
    };

    showDayModal.value = true;
    editingHours.value = false;
    showDayTypeDropdown.value = false;
    showDeviceMarks.value = true; // Show device marks by default for unclosed sessions
}

function prevMonth() {
    if (timesheetMonth.value === 1) {
        timesheetMonth.value = 12;
        timesheetYear.value--;
    } else {
        timesheetMonth.value--;
    }
    loadTimesheet();
    if (selectedEmployee.value) {
        loadEmployeeTimesheet(currentEmployee.value.id);
    }
}

function nextMonth() {
    if (timesheetMonth.value === 12) {
        timesheetMonth.value = 1;
        timesheetYear.value++;
    } else {
        timesheetMonth.value++;
    }
    loadTimesheet();
    if (selectedEmployee.value) {
        loadEmployeeTimesheet(currentEmployee.value.id);
    }
}

// ==================== SCHEDULE FUNCTIONS ====================

async function loadSchedule() {
    scheduleLoading.value = true;
    try {
        const res = await store.api(`/backoffice/attendance/schedule?year=${scheduleYear.value}&month=${scheduleMonth.value}`);
        if (res.success) {
            scheduleData.value = res.data;
        }
    } catch (e) {
        console.error('Failed to load schedule:', e);
    } finally {
        scheduleLoading.value = false;
    }
}

function prevScheduleMonth() {
    if (scheduleMonth.value === 1) {
        scheduleMonth.value = 12;
        scheduleYear.value--;
    } else {
        scheduleMonth.value--;
    }
    loadSchedule();
}

function nextScheduleMonth() {
    if (scheduleMonth.value === 12) {
        scheduleMonth.value = 1;
        scheduleYear.value++;
    } else {
        scheduleMonth.value++;
    }
    loadSchedule();
}

function openShiftModal(emp, day, event) {
    selectedScheduleEmployee.value = emp;
    selectedScheduleDay.value = day;

    // Get existing shift data if any
    const existingShift = getScheduleShift(emp.id, day);
    if (existingShift) {
        shiftForm.template = existingShift.template || 'custom';
        shiftForm.start_time = existingShift.start_time || '';
        shiftForm.end_time = existingShift.end_time || '';
        shiftForm.break_minutes = existingShift.break_minutes || 0;
    } else {
        shiftForm.template = null;
        shiftForm.start_time = '';
        shiftForm.end_time = '';
        shiftForm.break_minutes = 0;
    }

    // Position modal
    if (event) {
        const rect = event.target.getBoundingClientRect();
        const modalWidth = 320;
        const modalHeight = 400;

        let left = rect.left;
        let top = rect.bottom + 8;

        if (left + modalWidth > window.innerWidth - 20) {
            left = window.innerWidth - modalWidth - 20;
        }
        if (top + modalHeight > window.innerHeight - 20) {
            top = rect.top - modalHeight - 8;
        }
        if (left < 20) left = 20;
        if (top < 20) top = 20;

        shiftModalPosition.value = { left: `${left}px`, top: `${top}px` };
    }

    showShiftModal.value = true;
}

function closeShiftModal() {
    showShiftModal.value = false;
    selectedScheduleEmployee.value = null;
    selectedScheduleDay.value = null;
    shiftForm.template = null;
    shiftForm.start_time = '';
    shiftForm.end_time = '';
    shiftForm.break_minutes = 0;
}

function selectShiftTemplate(template) {
    shiftForm.template = template.id;
    if (template.id !== 'custom') {
        shiftForm.start_time = template.start;
        shiftForm.end_time = template.end;
        shiftForm.break_minutes = template.break;
    }
}

async function saveShift() {
    if (!selectedScheduleEmployee.value || !selectedScheduleDay.value) return;
    if (!shiftForm.start_time || !shiftForm.end_time) {
        store.showToast('Укажите время начала и окончания смены', 'error');
        return;
    }

    savingShift.value = true;
    try {
        const date = `${scheduleYear.value}-${String(scheduleMonth.value).padStart(2, '0')}-${String(selectedScheduleDay.value).padStart(2, '0')}`;

        const res = await store.api('/backoffice/attendance/schedule/shift', {
            method: 'POST',
            body: JSON.stringify({
                user_id: selectedScheduleEmployee.value.id,
                date: date,
                template: shiftForm.template,
                start_time: shiftForm.start_time,
                end_time: shiftForm.end_time,
                break_minutes: shiftForm.break_minutes,
            }),
        });

        if (res.success) {
            store.showToast('Смена сохранена', 'success');
            closeShiftModal();
            await loadSchedule();
        } else {
            store.showToast(res.message || 'Ошибка сохранения', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        savingShift.value = false;
    }
}

async function deleteShift() {
    if (!selectedScheduleEmployee.value || !selectedScheduleDay.value) return;

    savingShift.value = true;
    try {
        const date = `${scheduleYear.value}-${String(scheduleMonth.value).padStart(2, '0')}-${String(selectedScheduleDay.value).padStart(2, '0')}`;

        const res = await store.api('/backoffice/attendance/schedule/shift', {
            method: 'DELETE',
            body: JSON.stringify({
                user_id: selectedScheduleEmployee.value.id,
                date: date,
            }),
        });

        if (res.success) {
            store.showToast('Смена удалена', 'success');
            closeShiftModal();
            await loadSchedule();
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        savingShift.value = false;
    }
}

async function copyWeekSchedule() {
    // Get current week number
    const firstDay = new Date(scheduleYear.value, scheduleMonth.value - 1, 1);
    const currentWeekStart = 1; // For simplicity, copy from first week

    try {
        const res = await store.api('/backoffice/attendance/schedule/copy-week', {
            method: 'POST',
            body: JSON.stringify({
                year: scheduleYear.value,
                month: scheduleMonth.value,
                source_week: 1,
            }),
        });

        if (res.success) {
            store.showToast('Неделя скопирована на весь месяц', 'success');
            await loadSchedule();
        } else {
            store.showToast(res.message || 'Ошибка копирования', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

function getScheduleShift(empId, day) {
    if (!scheduleData.value?.schedule?.[empId]) return null;
    return scheduleData.value.schedule[empId][day] || null;
}

function getShiftDisplay(empId, day) {
    const shift = getScheduleShift(empId, day);
    if (!shift) return '';
    return `${shift.start_time?.slice(0,5)}-${shift.end_time?.slice(0,5)}`;
}

function getShiftClass(empId, day) {
    const shift = getScheduleShift(empId, day);
    if (!shift) {
        // Check if weekend
        const date = new Date(scheduleYear.value, scheduleMonth.value - 1, day);
        const dayOfWeek = date.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) return 'bg-red-50/30';
        return '';
    }

    // Color based on template
    const template = shiftTemplates.find(t => t.id === shift.template);
    if (template) {
        const colors = {
            amber: 'bg-gradient-to-br from-amber-100 to-amber-50',
            blue: 'bg-gradient-to-br from-blue-100 to-blue-50',
            purple: 'bg-gradient-to-br from-purple-100 to-purple-50',
            emerald: 'bg-gradient-to-br from-emerald-100 to-emerald-50',
            slate: 'bg-gradient-to-br from-slate-200 to-slate-100',
            gray: 'bg-gradient-to-br from-gray-100 to-gray-50',
        };
        return colors[template.color] || 'bg-blue-50';
    }
    return 'bg-blue-50';
}

function getEmployeePlannedHours(empId) {
    if (!scheduleData.value?.schedule?.[empId]) return 0;
    let total = 0;
    const shifts = scheduleData.value.schedule[empId];
    for (const day in shifts) {
        const shift = shifts[day];
        if (shift?.hours) {
            total += shift.hours;
        }
    }
    return total;
}

function formatPlannedHours(hours) {
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return m > 0 ? `${h}:${String(m).padStart(2, '0')}` : `${h}`;
}

function toggleScheduleSelection(empId, day) {
    const idx = selectedScheduleCells.value.findIndex(c => c.empId === empId && c.day === day);
    if (idx >= 0) {
        selectedScheduleCells.value.splice(idx, 1);
    } else {
        selectedScheduleCells.value.push({ empId, day });
    }
}

function isScheduleCellSelected(empId, day) {
    return selectedScheduleCells.value.some(c => c.empId === empId && c.day === day);
}

function clearScheduleSelection() {
    selectedScheduleCells.value = [];
    scheduleSelectionMode.value = false;
}

async function applyBulkShift(template) {
    if (selectedScheduleCells.value.length === 0) return;

    try {
        const shifts = selectedScheduleCells.value.map(cell => ({
            user_id: cell.empId,
            date: `${scheduleYear.value}-${String(scheduleMonth.value).padStart(2, '0')}-${String(cell.day).padStart(2, '0')}`,
            template: template.id,
            start_time: template.start,
            end_time: template.end,
            break_minutes: template.break,
        }));

        const res = await store.api('/backoffice/attendance/schedule/bulk', {
            method: 'POST',
            body: JSON.stringify({ shifts }),
        });

        if (res.success) {
            store.showToast(`Назначено ${shifts.length} смен`, 'success');
            clearScheduleSelection();
            await loadSchedule();
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

// ==================== END SCHEDULE FUNCTIONS ====================

function getWeekdayName(dayOfWeek) {
    const names = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    return names[dayOfWeek];
}

function getDayClass(day) {
    if (!employeeTimesheet.value) return '';
    const cal = employeeTimesheet.value.calendar[day];
    if (!cal) return '';
    if (cal.is_weekend) return 'bg-gray-100 text-gray-400';
    if (cal.hours > 0) return 'bg-green-50';
    return '';
}

function getDayClassModern(day) {
    if (!employeeTimesheet.value) return 'bg-gray-50/50';
    const cal = employeeTimesheet.value.calendar[day];
    if (!cal) return 'bg-gray-50/50';

    // Check for override type first (except 'shift' which is just hours)
    if (cal.override?.type && cal.override.type !== 'shift') {
        switch (cal.override.type) {
            case 'vacation':
                return 'bg-gradient-to-br from-emerald-50 to-green-100 border border-emerald-200/50';
            case 'sick_leave':
                return 'bg-gradient-to-br from-amber-50 to-yellow-100 border border-amber-200/50';
            case 'day_off':
                return 'bg-gradient-to-br from-slate-100 to-gray-200 border border-slate-200/50';
            case 'absence':
                return 'bg-gradient-to-br from-red-50 to-rose-100 border border-red-200/50';
        }
    }

    // Hours worked styling (including weekends with hours)
    if (cal.hours > 0) {
        if (cal.hours >= 8) {
            return cal.is_weekend
                ? 'bg-gradient-to-br from-green-100 to-emerald-200 border border-green-300/50'
                : 'bg-gradient-to-br from-emerald-50 to-green-100 border border-emerald-200/50';
        } else if (cal.hours >= 4) {
            return cal.is_weekend
                ? 'bg-gradient-to-br from-amber-100 to-orange-200 border border-amber-300/50'
                : 'bg-gradient-to-br from-amber-50 to-orange-100 border border-amber-200/50';
        } else {
            return cal.is_weekend
                ? 'bg-gradient-to-br from-rose-100 to-red-200 border border-rose-300/50'
                : 'bg-gradient-to-br from-rose-50 to-red-100 border border-rose-200/50';
        }
    }

    // Weekend without hours
    if (cal.is_weekend) {
        return 'bg-gradient-to-br from-red-50/50 to-rose-100/30 text-gray-400';
    }

    return 'bg-gray-50/50 hover:bg-gray-100/80';
}

function getHoursClass(hours) {
    if (hours >= 8) return 'text-green-600 font-medium';
    if (hours >= 4) return 'text-orange-500';
    return 'text-red-500';
}

function getAvatarColor(id) {
    const colors = ['#f97316', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#f59e0b'];
    return colors[id % colors.length];
}

// Translate role to Russian
function getRoleLabel(role) {
    const labels = {
        'owner': 'Владелец',
        'admin': 'Администратор',
        'manager': 'Менеджер',
        'waiter': 'Официант',
        'cook': 'Повар',
        'bartender': 'Бармен',
        'cashier': 'Кассир',
        'hostess': 'Хостес',
        'cleaner': 'Уборщик',
        'security': 'Охранник',
        'delivery': 'Курьер',
        'courier': 'Курьер',
        'staff': 'Сотрудник',
    };
    return labels[role] || role;
}

// Heatmap color based on hours (GitHub contributions style)
function getHeatmapColor(day) {
    if (!employeeTimesheet.value) return 'bg-gray-100';
    const cal = employeeTimesheet.value.calendar[day];
    if (!cal) return 'bg-gray-100';
    if (cal.is_weekend && cal.hours === 0) return 'bg-gray-200';
    if (cal.hours === 0) return 'bg-gray-100';
    if (cal.hours >= 10) return 'bg-green-700';
    if (cal.hours >= 8) return 'bg-green-500';
    if (cal.hours >= 6) return 'bg-green-400';
    if (cal.hours >= 4) return 'bg-green-300';
    if (cal.hours >= 2) return 'bg-green-200';
    return 'bg-green-100';
}

// Heatmap color for employee row (uses timesheetData instead of employeeTimesheet)
function getHeatmapColorForEmployee(emp, day) {
    // For the main list, we don't have per-day data in timesheetData
    // So we'll use a simpler approach - just show if it's a weekend
    const date = new Date(timesheetYear.value, timesheetMonth.value - 1, day);
    const dayOfWeek = date.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) return 'bg-gray-200';
    // We don't have individual day data in the summary, so show neutral
    return 'bg-gray-100';
}

function getHeatmapTooltip(emp, day) {
    return `${emp.name}`;
}

// Table cell functions
function getTableCellClass(emp, day) {
    const date = new Date(timesheetYear.value, timesheetMonth.value - 1, day);
    const dayOfWeek = date.getDay();
    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

    // Check cache for day data
    const cached = employeeTimesheetsCache.value[emp.id];
    if (cached?.calendar?.[day]) {
        const dayData = cached.calendar[day];
        const dayType = dayData.override?.type;

        // Active session - light neutral background (green dot is the indicator)
        if (dayData.has_active) {
            return 'bg-green-50';
        }

        // Auto-closed session - neutral gray style (приход был, уход забыли)
        if (dayData.has_auto_closed) {
            return 'bg-slate-100';
        }

        // Status-based colors (priority) - subtle backgrounds
        if (dayType === 'vacation') return 'bg-emerald-50';
        if (dayType === 'sick_leave') return 'bg-amber-50';
        if (dayType === 'day_off') return 'bg-slate-100';
        if (dayType === 'absence') return 'bg-red-50';

        // Hours-based colors - subtle green for completed shifts
        const hours = dayData.hours || 0;
        if (hours >= 8) return 'bg-green-100';
        if (hours > 0) return 'bg-green-50';
    }

    if (isWeekend) return 'bg-red-50/30';
    return '';
}

function getDayStatus(emp, day) {
    const cached = employeeTimesheetsCache.value[emp.id];
    const type = cached?.calendar?.[day]?.override?.type;
    // Only return special statuses that have icons (not "shift")
    if (type && ['vacation', 'sick_leave', 'day_off', 'absence'].includes(type)) {
        return type;
    }
    return null;
}

function getTableCellValue(emp, day) {
    // Use cached data to show hours
    const cached = employeeTimesheetsCache.value[emp.id];
    if (cached?.calendar?.[day]) {
        const dayData = cached.calendar[day];
        const overrideType = dayData.override?.type;

        // Skip special status types - they show icons instead
        if (overrideType && ['vacation', 'sick_leave', 'day_off', 'absence'].includes(overrideType)) {
            return '';
        }

        // Active session - show green dot (will be styled with pulse animation)
        if (dayData.has_active) {
            return '●';
        }

        // Show hours for completed shifts (including auto-closed with red flag)
        const hours = dayData.hours;
        if (hours > 0) {
            return dayData.formatted || hours;
        }

        // Auto-closed with 0 hours - show dash (red flag will indicate the issue)
        if (dayData.has_auto_closed) {
            return '–';
        }
    }
    return '';
}

function getTableCellValueClass(emp, day) {
    const cached = employeeTimesheetsCache.value[emp.id];
    if (cached?.calendar?.[day]) {
        const dayData = cached.calendar[day];

        // Active session - green pulsing dot
        if (dayData.has_active) {
            return 'text-green-500 animate-pulse text-lg';
        }

        // Auto-closed - neutral gray
        if (dayData.has_auto_closed) {
            return 'font-semibold text-slate-400';
        }

        // Completed shifts - dark text for better contrast
        const hours = dayData.hours || 0;
        if (hours >= 8) return 'font-semibold text-gray-700';
        if (hours >= 4) return 'font-medium text-gray-600';
        if (hours > 0) return 'font-medium text-gray-600';
    }
    return 'text-gray-400';
}

// Check if day has auto-closed session (forgot to clock out)
function hasAutoClosedFlag(emp, day) {
    const cached = employeeTimesheetsCache.value[emp.id];
    return cached?.calendar?.[day]?.has_auto_closed || false;
}

// Day Detail Modal
const showDayModal = ref(false);
const selectedDay = ref(null);
const selectedDayData = ref(null);
const dayModalPosition = ref({ top: '100px', left: '100px' });
const manualSessionForm = reactive({
    clock_in: '',
    clock_out: '',
});
const savingSession = ref(false);
const savingOverride = ref(false);
const selectedDayType = ref(null);
const showDayTypeSelector = ref(false);
const showDayTypeDropdown = ref(false);
const dayTypeSearch = ref('');
const showDeviceMarks = ref(false);
const closeSessionTime = ref('');
const closingSession = ref(false);
const showAddLabelModal = ref(false);
const dayOverrideForm = reactive({
    start_time: '',
    end_time: '',
    notes: '',
});
const editingHours = ref(false);
const editTimeValue = ref('00:00');
const hoursInput = ref(null);
const dayTypes = ref([
    { value: 'shift', label: 'Рабочий день', color: 'blue', has_hours: true },
    { value: 'day_off', label: 'Выходной', color: 'gray', has_hours: false },
    { value: 'vacation', label: 'Отпуск', color: 'green', has_hours: true },
    { value: 'sick_leave', label: 'Больничный', color: 'yellow', has_hours: true },
    { value: 'absence', label: 'Прогул', color: 'red', has_hours: false },
]);

function selectDayType(type) {
    selectedDayType.value = selectedDayType.value === type ? null : type;
    // Reset form when changing type
    dayOverrideForm.start_time = '';
    dayOverrideForm.end_time = '';
    dayOverrideForm.notes = '';
}

function selectDayTypeFromDropdown(type) {
    showDayTypeDropdown.value = false;
    dayTypeSearch.value = '';
    selectedDayType.value = type;

    // Save day type with current hours
    saveDayTypeWithHours(type);
}

async function saveDayTypeWithHours(type) {
    if (!currentEmployee.value || !selectedDay.value) return;

    savingOverride.value = true;
    try {
        const date = `${timesheetYear.value}-${String(timesheetMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;

        // Get current hours
        let hours = getCurrentHours();

        // For types without hours, set to 0
        if (type === 'day_off' || type === 'absence') {
            hours = 0;
        }
        // For vacation/sick_leave, default to 8 hours if no hours
        else if ((type === 'vacation' || type === 'sick_leave') && hours === 0) {
            hours = 8;
        }

        const res = await store.api('/backoffice/attendance/day-override', {
            method: 'POST',
            body: JSON.stringify({
                user_id: currentEmployee.value.id,
                date: date,
                type: type,
                hours: hours,
            }),
        });

        if (res.success) {
            store.showToast('Тип дня установлен', 'success');

            // Сразу обновляем локальные данные
            if (selectedDayData.value) {
                if (!selectedDayData.value.override) {
                    selectedDayData.value.override = { type: type, hours: hours };
                } else {
                    selectedDayData.value.override.type = type;
                    selectedDayData.value.override.hours = hours;
                }
                selectedDayData.value.hours = hours;
                selectedDayData.value.formatted = formatHoursDisplay(hours);
            }

            // Перезагружаем данные в фоне
            const employeeId = currentEmployee.value.id;
            loadEmployeeTimesheet(employeeId, true, true);
            loadTimesheet(false, true);
        } else {
            store.showToast(res.message || 'Ошибка сохранения', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        savingOverride.value = false;
        selectedDayType.value = null;
    }
}

function cancelDayTypeSelection() {
    selectedDayType.value = null;
    dayOverrideForm.start_time = '';
    dayOverrideForm.end_time = '';
    dayOverrideForm.notes = '';
}

// Hours editing functions
function getCurrentHours() {
    if (selectedDayData.value?.override) {
        return selectedDayData.value.override.hours || 0;
    }
    return selectedDayData.value?.hours || 0;
}

function formatHoursDisplay(hours) {
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return `${h}:${String(m).padStart(2, '0')}`;
}

function startEditingHours() {
    const totalHours = getCurrentHours();
    const h = Math.floor(totalHours);
    const m = Math.round((totalHours - h) * 60);
    editTimeValue.value = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    editingHours.value = true;
    // Focus input after Vue updates DOM
    setTimeout(() => {
        hoursInput.value?.focus();
        hoursInput.value?.select();
    }, 50);
}

function formatTimeInput() {
    let val = editTimeValue.value;

    // Если уже есть ":" - работаем с частями
    if (val.includes(':')) {
        let parts = val.split(':');
        let hh = parts[0].replace(/[^\d]/g, '').slice(0, 2);
        let mm = (parts[1] || '').replace(/[^\d]/g, '').slice(0, 2);

        // Validate hours (max 23)
        if (hh && parseInt(hh) > 23) hh = '23';
        // Validate minutes (max 59)
        if (mm && parseInt(mm) > 59) mm = '59';

        editTimeValue.value = `${hh}:${mm}`;
        return;
    }

    // Без ":" - только цифры
    val = val.replace(/[^\d]/g, '');

    // Автоматически добавляем ":" только когда > 2 цифр (при вводе третьей цифры)
    if (val.length > 2) {
        let hh = val.slice(0, 2);
        let mm = val.slice(2, 4);

        if (parseInt(hh) > 23) hh = '23';
        if (mm && parseInt(mm) > 59) mm = '59';

        val = `${hh}:${mm}`;
    }

    editTimeValue.value = val;
}

function cancelEditingHours() {
    editingHours.value = false;
    editTimeValue.value = '00:00';
}

async function saveEditedHours() {
    if (!currentEmployee.value || !selectedDay.value) return;

    const parts = editTimeValue.value.split(':');
    const h = parseInt(parts[0]) || 0;
    const m = parseInt(parts[1]) || 0;
    const hours = h + (m / 60);

    if (hours < 0 || hours > 24) {
        store.showToast('Часы должны быть от 0 до 24', 'error');
        return;
    }

    savingOverride.value = true;
    try {
        const date = `${timesheetYear.value}-${String(timesheetMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;
        const currentType = selectedDayData.value?.override?.type || 'shift';

        const res = await store.api('/backoffice/attendance/day-override', {
            method: 'POST',
            body: JSON.stringify({
                user_id: currentEmployee.value.id,
                date: date,
                type: currentType,
                hours: hours,
            }),
        });

        if (res.success) {
            store.showToast('Часы сохранены', 'success');
            editingHours.value = false;

            // Сразу обновляем локальные данные (до перезагрузки с сервера)
            if (selectedDayData.value) {
                if (!selectedDayData.value.override) {
                    selectedDayData.value.override = { type: currentType, hours: hours };
                } else {
                    selectedDayData.value.override.hours = hours;
                }
                selectedDayData.value.hours = hours;
                selectedDayData.value.formatted = formatHoursDisplay(hours);
            }

            // Перезагружаем данные в фоне
            const employeeId = currentEmployee.value.id;
            loadTimesheet(false, true);
            loadEmployeeTimesheet(employeeId, true, true);
        } else {
            store.showToast(res.message || 'Ошибка сохранения', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        savingOverride.value = false;
    }
}

function getDayTypeLabel() {
    if (selectedDayData.value?.override) {
        const labels = {
            'shift': 'Рабочий день',
            'day_off': 'Выходной',
            'vacation': 'Отпуск',
            'sick_leave': 'Больничный',
            'absence': 'Прогул',
        };
        return labels[selectedDayData.value.override.type] || selectedDayData.value.override.type_label || 'Рабочий день';
    }
    // Default - if there are sessions or hours, it's a work day
    if (selectedDayData.value?.hours > 0) {
        return 'Рабочий день';
    }
    return 'Нет данных';
}

function getDayTypeColorClass() {
    const type = selectedDayData.value?.override?.type;
    if (type) {
        return {
            'text-blue-600': type === 'shift',
            'text-gray-600': type === 'day_off',
            'text-green-600': type === 'vacation',
            'text-yellow-600': type === 'sick_leave',
            'text-red-600': type === 'absence',
        };
    }
    // Default color for auto-calculated shift
    if (selectedDayData.value?.hours > 0) {
        return 'text-blue-600';
    }
    return 'text-gray-400';
}

function openDayModal(day, event) {
    if (!employeeTimesheet.value) return;

    const dayData = employeeTimesheet.value.calendar[day];
    if (!dayData) return;

    selectedDay.value = day;
    // Deep copy чтобы sessions корректно скопировался
    selectedDayData.value = JSON.parse(JSON.stringify(dayData));

    // Формируем дату для заголовка
    const date = new Date(timesheetYear.value, timesheetMonth.value - 1, day);
    selectedDayData.value.fullDate = date;
    selectedDayData.value.day_of_week = date.getDay();
    selectedDayData.value.dayName = getDayName(date.getDay());

    // Позиционирование модального окна рядом с кликом
    if (event) {
        const rect = event.target.getBoundingClientRect();
        const modalWidth = 384; // w-96
        const modalHeight = 320;

        let left = rect.left;
        let top = rect.bottom + 8;

        // Проверяем выход за правую границу
        if (left + modalWidth > window.innerWidth - 20) {
            left = window.innerWidth - modalWidth - 20;
        }
        // Проверяем выход за нижнюю границу
        if (top + modalHeight > window.innerHeight - 20) {
            top = rect.top - modalHeight - 8;
        }
        // Минимальные отступы
        if (left < 20) left = 20;
        if (top < 20) top = 20;

        dayModalPosition.value = {
            top: `${top}px`,
            left: `${left}px`,
        };
    }

    // Сброс формы
    manualSessionForm.clock_in = '';
    manualSessionForm.clock_out = '';
    showDeviceMarks.value = false;

    showDayModal.value = true;
}

function closeDayModal() {
    // Сначала скрываем модалку
    showDayModal.value = false;

    // Сбрасываем данные после небольшой задержки (после анимации закрытия)
    // чтобы не было моргания 00:00
    setTimeout(() => {
        selectedDay.value = null;
        selectedDayData.value = null;
        selectedDayType.value = null;
        showDayTypeSelector.value = false;
        showDayTypeDropdown.value = false;
        dayTypeSearch.value = '';
        showDeviceMarks.value = false;
        showAddLabelModal.value = false;
        editingHours.value = false;
        editTimeValue.value = '00:00';
        dayOverrideForm.start_time = '';
        dayOverrideForm.end_time = '';
        dayOverrideForm.notes = '';
        modalEmployee.value = null;
    }, 200);
}

async function saveDayOverride() {
    if (!selectedDayType.value || !currentEmployee.value || !selectedDay.value) return;

    // Для смены требуется время начала
    if (selectedDayType.value === 'shift' && !dayOverrideForm.start_time) {
        store.showToast('Укажите время начала смены', 'error');
        return;
    }

    savingOverride.value = true;
    try {
        const date = `${timesheetYear.value}-${String(timesheetMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;

        const res = await store.api('/backoffice/attendance/day-override', {
            method: 'POST',
            body: JSON.stringify({
                user_id: currentEmployee.value.id,
                date: date,
                type: selectedDayType.value,
                start_time: dayOverrideForm.start_time || null,
                end_time: dayOverrideForm.end_time || null,
                notes: dayOverrideForm.notes || null,
            }),
        });

        if (res.success) {
            store.showToast('Тип дня установлен', 'success');
            const employeeId = currentEmployee.value.id;
            closeDayModal();
            // Перезагружаем данные (forceReload чтобы не было моргания)
            await loadTimesheet(false, true);
            await loadEmployeeTimesheet(employeeId, true, true);
        } else {
            store.showToast(res.message || 'Ошибка сохранения', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        savingOverride.value = false;
    }
}

async function removeDayOverride() {
    if (!selectedDayData.value?.override?.id) return;

    if (!confirm('Сбросить тип дня и вернуться к автоматическому подсчёту?')) return;

    const employeeId = currentEmployee.value.id;
    try {
        const res = await store.api(`/backoffice/attendance/day-override/${selectedDayData.value.override.id}`, {
            method: 'DELETE',
        });

        if (res.success) {
            store.showToast('Тип дня сброшен', 'success');

            // Сразу удаляем override из локальных данных
            if (selectedDayData.value) {
                selectedDayData.value.override = null;
            }

            // Перезагружаем данные в фоне (для получения автоматических часов)
            loadEmployeeTimesheet(employeeId, true, true).then(() => {
                const dayData = employeeTimesheet.value?.calendar[selectedDay.value];
                if (dayData && selectedDayData.value) {
                    selectedDayData.value.hours = dayData.hours;
                    selectedDayData.value.formatted = dayData.formatted;
                    selectedDayData.value.sessions = dayData.sessions;
                }
            });
            loadTimesheet(false, true);
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

async function clearDayData() {
    if (!currentEmployee.value || !selectedDay.value) return;

    const employeeId = currentEmployee.value.id;
    // Устанавливаем 0 часов (игнорируя отметки устройства)
    try {
        const date = `${timesheetYear.value}-${String(timesheetMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;

        const res = await store.api('/backoffice/attendance/day-override', {
            method: 'POST',
            body: JSON.stringify({
                user_id: employeeId,
                date: date,
                type: 'shift',
                hours: 0,
            }),
        });

        if (res.success) {
            store.showToast('Установлено 0 часов', 'success');

            // Сразу обновляем локальные данные
            if (selectedDayData.value) {
                if (!selectedDayData.value.override) {
                    selectedDayData.value.override = { type: 'shift', hours: 0 };
                } else {
                    selectedDayData.value.override.type = 'shift';
                    selectedDayData.value.override.hours = 0;
                }
                selectedDayData.value.hours = 0;
                selectedDayData.value.formatted = '0:00';
            }

            // Перезагружаем данные в фоне
            loadEmployeeTimesheet(employeeId, true, true);
            loadTimesheet(false, true);
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

function getDeviceHoursTotal() {
    const sessions = getDeviceSessions();
    if (!sessions || sessions.length === 0) return 0;
    return sessions.reduce((sum, s) => sum + (s.hours || 0), 0);
}

async function syncFromDevice() {
    if (!currentEmployee.value || !selectedDay.value) return;

    const employeeId = currentEmployee.value.id;
    const deviceHours = getDeviceHoursTotal();
    if (deviceHours === 0) {
        store.showToast('Нет отметок с устройства', 'info');
        return;
    }

    try {
        const date = `${timesheetYear.value}-${String(timesheetMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;

        const res = await store.api('/backoffice/attendance/day-override', {
            method: 'POST',
            body: JSON.stringify({
                user_id: employeeId,
                date: date,
                type: 'shift',
                hours: deviceHours,
            }),
        });

        if (res.success) {
            store.showToast('Время синхронизировано с устройства', 'success');

            // Сразу обновляем локальные данные
            if (selectedDayData.value) {
                if (!selectedDayData.value.override) {
                    selectedDayData.value.override = { type: 'shift', hours: deviceHours };
                } else {
                    selectedDayData.value.override.type = 'shift';
                    selectedDayData.value.override.hours = deviceHours;
                }
                selectedDayData.value.hours = deviceHours;
                selectedDayData.value.formatted = formatHoursDisplay(deviceHours);
            }

            // Перезагружаем данные в фоне
            loadEmployeeTimesheet(employeeId, true, true);
            loadTimesheet(false, true);
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка синхронизации', 'error');
    }
}

function getDayName(dayOfWeek) {
    const names = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
    return names[dayOfWeek];
}

function getShortDayName(dayOfWeek) {
    const names = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
    return names[dayOfWeek] || '';
}

// Saby-style helper functions
function getDayCode() {
    const type = selectedDayData.value?.override?.type;
    if (type) {
        const codes = {
            'shift': 'Я',      // Явка
            'day_off': 'В',    // Выходной
            'vacation': 'О',   // Отпуск
            'sick_leave': 'Б', // Больничный
            'absence': 'НН',   // Неявка
        };
        return codes[type] || 'Я';
    }
    // Default - if has hours, it's attendance
    if (selectedDayData.value?.hours > 0) {
        return 'Я';
    }
    return '—';
}

function getDayCodeClass() {
    const type = selectedDayData.value?.override?.type;
    if (type) {
        const classes = {
            'shift': 'bg-blue-100 text-blue-600',
            'day_off': 'bg-gray-100 text-gray-500',
            'vacation': 'bg-green-100 text-green-600',
            'sick_leave': 'bg-yellow-100 text-yellow-600',
            'absence': 'bg-red-100 text-red-600',
        };
        return classes[type] || 'bg-blue-100 text-blue-600';
    }
    // Default - if has hours, it's attendance (blue)
    if (selectedDayData.value?.hours > 0) {
        return 'bg-blue-100 text-blue-600';
    }
    return 'bg-gray-100 text-gray-400';
}

function getDayTypeTextClass() {
    const type = selectedDayData.value?.override?.type;
    if (type) {
        const classes = {
            'shift': 'text-blue-600',
            'day_off': 'text-gray-500',
            'vacation': 'text-green-600',
            'sick_leave': 'text-yellow-600',
            'absence': 'text-red-600',
        };
        return classes[type] || 'text-blue-600';
    }
    // Default - if has hours, it's a work day
    if (selectedDayData.value?.hours > 0) {
        return 'text-blue-600';
    }
    return 'text-gray-400';
}

function getDayTypeBadgeClass() {
    const type = selectedDayData.value?.override?.type;
    const classes = {
        'shift': 'bg-blue-100 text-blue-700',
        'day_off': 'bg-gray-100 text-gray-600',
        'vacation': 'bg-emerald-100 text-emerald-700',
        'sick_leave': 'bg-amber-100 text-amber-700',
        'absence': 'bg-red-100 text-red-700',
    };
    if (type && classes[type]) {
        return classes[type];
    }
    // Default - work day
    if (selectedDayData.value?.hours > 0) {
        return 'bg-blue-100 text-blue-700';
    }
    return 'bg-gray-100 text-gray-500';
}

function getDayTypeStripeClass() {
    const type = selectedDayData.value?.override?.type;
    const classes = {
        'shift': 'bg-gradient-to-b from-blue-400 to-blue-600',
        'day_off': 'bg-gradient-to-b from-gray-300 to-gray-500',
        'vacation': 'bg-gradient-to-b from-emerald-400 to-emerald-600',
        'sick_leave': 'bg-gradient-to-b from-amber-400 to-amber-600',
        'absence': 'bg-gradient-to-b from-red-400 to-red-600',
    };
    if (type && classes[type]) {
        return classes[type];
    }
    // Default - work day
    if (selectedDayData.value?.hours > 0) {
        return 'bg-gradient-to-b from-blue-400 to-blue-600';
    }
    return 'bg-gradient-to-b from-gray-300 to-gray-400';
}

function getCurrentDayType() {
    const type = selectedDayData.value?.override?.type;
    if (type) return type;
    // Default - if has hours, it's a work day
    if (selectedDayData.value?.hours > 0) {
        return 'shift';
    }
    return null;
}

function formatHoursCompact(hours) {
    if (!hours || hours === 0) return '0:00';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return `${h}:${String(m).padStart(2, '0')}`;
}

function formatDuration(hours) {
    if (!hours || hours === 0) return '0м';

    const totalMinutes = Math.round(hours * 60);

    if (totalMinutes < 60) {
        // Меньше часа - показываем минуты
        return `${totalMinutes}м`;
    }

    // Час и больше - показываем часы:минуты
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return m > 0 ? `${h}ч ${m}м` : `${h}ч`;
}

function getFirstClockIn() {
    const sessions = selectedDayData.value?.sessions;
    if (!sessions || sessions.length === 0) return '—';
    return sessions[0]?.clock_in || '—';
}

function getLastClockOut() {
    const sessions = selectedDayData.value?.sessions;
    if (!sessions || sessions.length === 0) return '—';
    const lastSession = sessions[sessions.length - 1];
    if (!lastSession?.clock_out) return '...';
    return lastSession.is_overnight ? `${lastSession.clock_out} (+1д)` : lastSession.clock_out;
}

function getTotalSessionHours() {
    const sessions = selectedDayData.value?.sessions;
    if (!sessions || sessions.length === 0) return 0;
    return sessions.reduce((sum, s) => sum + (s.hours || 0), 0);
}

// Фильтр: только сессии с устройства (не вручную)
function getDeviceSessions() {
    const sessions = selectedDayData.value?.sessions;
    if (!sessions || sessions.length === 0) return [];
    return sessions.filter(s => !s.is_manual);
}

function getFirstDeviceClockIn() {
    const sessions = getDeviceSessions();
    if (sessions.length === 0) return '—';
    return sessions[0]?.clock_in || '—';
}

function getLastDeviceClockOut() {
    const sessions = getDeviceSessions();
    if (sessions.length === 0) return '—';
    const lastSession = sessions[sessions.length - 1];
    if (!lastSession?.clock_out) return '...';
    return lastSession.is_overnight ? `${lastSession.clock_out} (+1д)` : lastSession.clock_out;
}

function getTimeRange() {
    if (!selectedDayData.value?.sessions?.length) return null;
    const sessions = selectedDayData.value.sessions;
    const firstIn = sessions[0]?.clock_in;
    const lastSession = sessions[sessions.length - 1];
    const lastOut = lastSession?.clock_out;
    if (firstIn && lastOut) {
        const overnight = lastSession.is_overnight ? ' (+1д)' : '';
        return `${firstIn} - ${lastOut}${overnight}`;
    } else if (firstIn) {
        return `с ${firstIn}`;
    }
    return null;
}

function calculateHours(startTime, endTime) {
    if (!startTime || !endTime) return 0;
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = endTime.split(':').map(Number);
    let startMinutes = sh * 60 + sm;
    let endMinutes = eh * 60 + em;
    // Ночная смена
    if (endMinutes < startMinutes) {
        endMinutes += 24 * 60;
    }
    const diff = (endMinutes - startMinutes) / 60;
    return diff.toFixed(1);
}

function getMonthNameGenitive(month) {
    const names = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                   'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    return names[month - 1];
}

async function saveManualSession() {
    if (!manualSessionForm.clock_in || !currentEmployee.value || !selectedDay.value) return;

    const employeeId = currentEmployee.value.id; // Save before closing modal
    savingSession.value = true;
    try {
        const date = `${timesheetYear.value}-${String(timesheetMonth.value).padStart(2, '0')}-${String(selectedDay.value).padStart(2, '0')}`;

        const res = await store.api('/backoffice/attendance/sessions', {
            method: 'POST',
            body: JSON.stringify({
                user_id: employeeId,
                date: date,
                clock_in: manualSessionForm.clock_in,
                clock_out: manualSessionForm.clock_out || null,
            }),
        });

        if (res.success) {
            store.showToast('Смена добавлена', 'success');
            closeDayModal();
            // Перезагружаем данные (forceReload чтобы не было моргания)
            await loadTimesheet(false, true);
            await loadEmployeeTimesheet(employeeId, true, true);
        } else {
            store.showToast(res.message || 'Ошибка сохранения', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        savingSession.value = false;
    }
}

async function deleteSession(sessionId) {
    if (!confirm('Удалить эту смену?')) return;

    const employeeId = currentEmployee.value?.id;
    if (!employeeId) return;

    try {
        const res = await store.api(`/backoffice/attendance/sessions/${sessionId}`, {
            method: 'DELETE',
        });

        if (res.success) {
            store.showToast('Смена удалена', 'success');

            // Сразу удаляем сессию из локальных данных
            if (selectedDayData.value?.sessions) {
                selectedDayData.value.sessions = selectedDayData.value.sessions.filter(s => s.id !== sessionId);
                // Пересчитываем часы из оставшихся сессий
                const totalHours = selectedDayData.value.sessions.reduce((sum, s) => sum + (s.hours || 0), 0);
                selectedDayData.value.hours = totalHours;
                selectedDayData.value.formatted = formatHoursDisplay(totalHours);
            }

            // Перезагружаем данные в фоне
            loadTimesheet(false, true);
            loadEmployeeTimesheet(employeeId, true, true);
        } else {
            store.showToast(res.message || 'Ошибка удаления', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

// Format close session time input
function formatCloseSessionTime() {
    let val = closeSessionTime.value;

    // Allow backspace to work naturally
    if (val.includes(':')) {
        let parts = val.split(':');
        let hh = parts[0].replace(/[^\d]/g, '').slice(0, 2);
        let mm = (parts[1] || '').replace(/[^\d]/g, '').slice(0, 2);
        if (hh && parseInt(hh) > 23) hh = '23';
        if (mm && parseInt(mm) > 59) mm = '59';
        closeSessionTime.value = `${hh}:${mm}`;
        return;
    }

    val = val.replace(/[^\d]/g, '');
    if (val.length > 2) {
        let hh = val.slice(0, 2);
        let mm = val.slice(2, 4);
        if (parseInt(hh) > 23) hh = '23';
        if (parseInt(mm) > 59) mm = '59';
        closeSessionTime.value = `${hh}:${mm}`;
    } else {
        closeSessionTime.value = val;
    }
}

// Close an active session
async function closeActiveSession(sessionId) {
    if (!closeSessionTime.value || closeSessionTime.value.length < 5) return;

    const employeeId = modalEmployee.value?.id;
    if (!employeeId) return;

    closingSession.value = true;

    try {
        const res = await store.api(`/backoffice/attendance/sessions/${sessionId}/close`, {
            method: 'PUT',
            body: JSON.stringify({
                clock_out: closeSessionTime.value,
            }),
        });

        if (res.success) {
            store.showToast('Смена закрыта', 'success');

            // Reset form
            closeSessionTime.value = '';

            // Reload data silently
            await loadTimesheet(false, true);
            await loadEmployeeTimesheet(employeeId, true, true);

            // Update modal data
            const timesheet = employeeTimesheetsCache.value[employeeId];
            if (timesheet && selectedDay.value) {
                const dayData = timesheet.calendar[selectedDay.value];
                if (dayData) {
                    selectedDayData.value = JSON.parse(JSON.stringify(dayData));
                    const date = new Date(timesheetYear.value, timesheetMonth.value - 1, selectedDay.value);
                    selectedDayData.value.fullDate = date;
                    selectedDayData.value.day_of_week = date.getDay();
                    selectedDayData.value.dayName = getDayName(date.getDay());
                }
            }
        } else {
            store.showToast(res.message || 'Ошибка закрытия смены', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        closingSession.value = false;
    }
}

function getFirstDayOffset() {
    if (!timesheetData.value) return 0;
    // Создаём дату первого дня месяца
    const firstDay = new Date(timesheetYear.value, timesheetMonth.value - 1, 1);
    // getDay() возвращает 0 для воскресенья, нам нужно чтобы понедельник был 0
    let dayOfWeek = firstDay.getDay();
    // Преобразуем: воскресенье (0) -> 6, понедельник (1) -> 0, и т.д.
    return dayOfWeek === 0 ? 6 : dayOfWeek - 1;
}

// ==================== SETTINGS ====================

async function loadSettings() {
    try {
        const res = await store.api('/backoffice/attendance/settings');
        if (res.success && res.data) {
            Object.assign(settings, res.data);
            if (res.data.qr_code) {
                Object.assign(qrSettings, res.data.qr_code);
            }
        }
    } catch (e) {
        console.error('Failed to load settings:', e);
    }
}

async function saveSettings() {
    savingSettings.value = true;
    try {
        await store.api('/backoffice/attendance/settings', {
            method: 'PUT',
            body: JSON.stringify({
                attendance_mode: settings.attendance_mode,
                attendance_early_minutes: settings.attendance_early_minutes,
                attendance_late_minutes: settings.attendance_late_minutes,
                latitude: settings.latitude ? parseFloat(settings.latitude) : null,
                longitude: settings.longitude ? parseFloat(settings.longitude) : null,
            }),
        });
        store.showToast('Настройки сохранены', 'success');
    } catch (e) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        savingSettings.value = false;
    }
}

async function saveQrSettings() {
    savingQr.value = true;
    try {
        await store.api('/backoffice/attendance/qr-settings', {
            method: 'PUT',
            body: JSON.stringify(qrSettings),
        });
        store.showToast('Настройки QR сохранены', 'success');
        await loadQrCode();
    } catch (e) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        savingQr.value = false;
    }
}

// ==================== QR CODE ====================

async function loadQrCode() {
    if (!['qr_only', 'device_or_qr'].includes(settings.attendance_mode)) {
        return;
    }

    try {
        const restaurantId = settings.restaurant_id || 1;
        const res = await store.api(`/attendance/qr/${restaurantId}`);

        if (res.success && res.data) {
            // Generate QR code using Google Charts API
            const encodedUrl = encodeURIComponent(res.data.scan_url);
            qrCodeUrl.value = `https://chart.googleapis.com/chart?cht=qr&chs=256x256&chl=${encodedUrl}&choe=UTF-8`;

            if (res.data.expires_at) {
                qrExpiry.value = new Date(res.data.expires_at);
                startQrCountdown();
            }
        }
    } catch (e) {
        console.error('Failed to load QR code:', e);
    }
}

function startQrCountdown() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }

    const updateCountdown = () => {
        if (!qrExpiry.value) return;

        const now = new Date();
        const diff = Math.max(0, Math.floor((qrExpiry.value - now) / 1000));

        if (diff <= 0) {
            refreshQrCode();
            return;
        }

        const mins = Math.floor(diff / 60);
        const secs = diff % 60;
        qrCountdown.value = `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
}

async function refreshQrCode() {
    try {
        const restaurantId = settings.restaurant_id || 1;
        await store.api(`/attendance/qr/${restaurantId}/refresh`, { method: 'POST' });
        await loadQrCode();
    } catch (e) {
        console.error('Failed to refresh QR:', e);
    }
}

function openQrFullscreen() {
    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR-код для учёта времени</title>
            <style>
                body {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    background: #f3f4f6;
                    font-family: system-ui, sans-serif;
                }
                img { max-width: 80vmin; max-height: 80vmin; }
                h1 { margin-bottom: 2rem; color: #374151; }
                p { color: #6b7280; }
            </style>
        </head>
        <body>
            <h1>Отсканируйте для отметки</h1>
            <img src="${qrCodeUrl.value}" alt="QR Code">
            <p>Используйте камеру телефона</p>
        </body>
        </html>
    `);
}

function printQrCode() {
    const win = window.open('', '_blank');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR-код</title>
            <style>
                body {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    font-family: system-ui, sans-serif;
                }
                img { width: 300px; height: 300px; }
                h2 { margin-bottom: 1rem; }
            </style>
        </head>
        <body>
            <h2>Учёт рабочего времени</h2>
            <img src="${qrCodeUrl.value}" alt="QR Code">
            <p>Отсканируйте для отметки прихода/ухода</p>
        </body>
        </html>
    `);
    win.print();
}

// ==================== DEVICES ====================

async function loadDevices() {
    try {
        const res = await store.api('/backoffice/attendance/devices');
        if (res.success) {
            devices.value = res.data || [];
        }
    } catch (e) {
        console.error('Failed to load devices:', e);
    }
}

function getDeviceIcon(type) {
    return {
        anviz: '👤',
        zkteco: '👆',
        hikvision: '📹',
        generic: '📟',
    }[type] || '📟';
}

function editDevice(device) {
    editingDevice.value = device;
    Object.assign(deviceForm, {
        name: device.name,
        type: device.type,
        model: device.model || '',
        serial_number: device.serial_number,
        ip_address: device.ip_address || '',
        port: device.port || null,
    });
    newDeviceApiKey.value = null;
    showDeviceModal.value = true;
}

async function saveDevice() {
    savingDevice.value = true;
    try {
        // Prepare data - ensure port is integer or null
        const data = {
            ...deviceForm,
            port: deviceForm.port ? parseInt(deviceForm.port, 10) : null,
        };

        if (editingDevice.value) {
            await store.api(`/backoffice/attendance/devices/${editingDevice.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(data),
            });
            store.showToast('Устройство обновлено', 'success');
        } else {
            const res = await store.api('/backoffice/attendance/devices', {
                method: 'POST',
                body: JSON.stringify(data),
            });
            newDeviceApiKey.value = res.api_key;
            store.showToast('Устройство добавлено', 'success');
        }

        await loadDevices();

        if (!newDeviceApiKey.value) {
            showDeviceModal.value = false;
            resetDeviceForm();
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка сохранения', 'error');
    } finally {
        savingDevice.value = false;
    }
}

async function deleteDevice(device) {
    if (!confirm(`Удалить устройство "${device.name}"?`)) return;

    try {
        await store.api(`/backoffice/attendance/devices/${device.id}`, { method: 'DELETE' });
        store.showToast('Устройство удалено', 'success');
        await loadDevices();
    } catch (e) {
        store.showToast(e.message || 'Ошибка удаления', 'error');
    }
}

async function testDeviceConnection(device) {
    try {
        const res = await store.api(`/backoffice/attendance/devices/${device.id}/test-connection`, {
            method: 'POST',
        });
        if (res.success) {
            store.showToast('Соединение установлено', 'success');
        } else {
            store.showToast(res.error || 'Не удалось подключиться', 'error');
        }
    } catch (e) {
        store.showToast('Ошибка подключения', 'error');
    }
}

// ==================== DEVICE USERS ====================

async function openDeviceUsersModal(device) {
    selectedDevice.value = device;
    showDeviceUsersModal.value = true;
    await loadDeviceUsers(device);
    await loadRestaurantUsers();
}

async function loadDeviceUsers(device) {
    if (!device) return;

    loadingDeviceUsers.value = true;
    deviceUsersError.value = null;

    try {
        const res = await store.api(`/backoffice/attendance/devices/${device.id}/device-users`);
        if (res.success) {
            deviceUsers.value = res.data || [];
        } else {
            deviceUsersError.value = res.message || 'Не удалось получить список';
        }
    } catch (e) {
        deviceUsersError.value = e.message || 'Ошибка подключения к устройству';
    } finally {
        loadingDeviceUsers.value = false;
    }
}

async function loadRestaurantUsers() {
    try {
        const res = await store.api('/backoffice/staff');
        if (res.success) {
            restaurantUsers.value = res.data || [];
        }
    } catch (e) {
        console.error('Failed to load users:', e);
    }
}

// Пользователи, которых ещё нет на устройстве
const availableUsersForDevice = computed(() => {
    const deviceUserIds = new Set(deviceUsers.value.map(u => u.menulab_user?.id).filter(Boolean));
    return restaurantUsers.value.filter(u => !deviceUserIds.has(u.id));
});

async function addUserToDevice() {
    if (!addUserForm.user_id || !selectedDevice.value) return;

    addingUser.value = true;
    try {
        const res = await store.api(`/backoffice/attendance/devices/${selectedDevice.value.id}/device-users`, {
            method: 'POST',
            body: JSON.stringify({
                user_id: parseInt(addUserForm.user_id),
            }),
        });

        if (res.success) {
            // Показываем предупреждение если устройство недоступно
            if (res.warning) {
                store.showToast(res.warning, 'warning');
            } else {
                store.showToast('Сотрудник добавлен на устройство', 'success');
            }
            addUserForm.user_id = '';
            await loadDeviceUsers(selectedDevice.value);
            await loadDevices();
        } else {
            store.showToast(res.message || 'Ошибка добавления', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    } finally {
        addingUser.value = false;
    }
}

async function removeUserFromDevice(deviceUser) {
    if (!confirm(`Удалить "${deviceUser.name || 'ID: ' + deviceUser.user_id}" с устройства?`)) return;

    try {
        const res = await store.api(
            `/backoffice/attendance/devices/${selectedDevice.value.id}/device-users/${deviceUser.user_id}`,
            { method: 'DELETE' }
        );

        if (res.success) {
            store.showToast('Сотрудник удалён с устройства', 'success');
            await loadDeviceUsers(selectedDevice.value);
            await loadDevices();
        } else {
            store.showToast(res.message || 'Ошибка удаления', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

async function linkDeviceUser(deviceUser, menulabUserId) {
    if (!menulabUserId) return;

    try {
        const res = await store.api(`/backoffice/attendance/devices/${selectedDevice.value.id}/link-user`, {
            method: 'POST',
            body: JSON.stringify({
                device_user_id: String(deviceUser.user_id),
                user_id: parseInt(menulabUserId),
            }),
        });

        if (res.success) {
            store.showToast('Сотрудник связан', 'success');
            await loadDeviceUsers(selectedDevice.value);
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

async function unlinkDeviceUser(deviceUser) {
    try {
        const res = await store.api(
            `/backoffice/attendance/devices/${selectedDevice.value.id}/unlink-user/${deviceUser.user_id}`,
            { method: 'DELETE' }
        );

        if (res.success) {
            store.showToast('Связь удалена', 'success');
            await loadDeviceUsers(selectedDevice.value);
        } else {
            store.showToast(res.message || 'Ошибка', 'error');
        }
    } catch (e) {
        store.showToast(e.message || 'Ошибка', 'error');
    }
}

function resetDeviceForm() {
    editingDevice.value = null;
    Object.assign(deviceForm, {
        name: '',
        type: 'anviz',
        model: '',
        serial_number: '',
        ip_address: '',
        port: null,
    });
    newDeviceApiKey.value = null;
}

// ==================== EVENTS ====================

async function loadEvents() {
    try {
        const res = await store.api(`/backoffice/attendance/events?date=${eventsDate.value}`);
        if (res.success) {
            events.value = res.data || [];
        }
    } catch (e) {
        console.error('Failed to load events:', e);
    }
}

function formatTime(dateStr) {
    // Извлекаем время напрямую из строки, чтобы избежать конверсии таймзоны
    // Формат: "2026-01-27 02:18:00" или "2026-01-27T02:18:00"
    if (!dateStr) return '';
    const match = dateStr.match(/(\d{2}):(\d{2})/);
    if (match) {
        return `${match[1]}:${match[2]}`;
    }
    // Fallback на старый метод
    return new Date(dateStr).toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

// ==================== LIFECYCLE ====================

let timesheetAutoRefreshInterval = null;

onMounted(async () => {
    await loadTimesheet(); // clearCache = true при первой загрузке
    await loadSettings();
    await loadDevices();
    await loadQrCode();
    await loadEvents();

    // Автообновление табеля каждые 15 секунд для получения новых отметок с устройств
    timesheetAutoRefreshInterval = setInterval(async () => {
        await loadTimesheet(false, true); // silent=true чтобы не показывать спиннер
        await loadEvents();
    }, 15000);
});

onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    if (timesheetAutoRefreshInterval) {
        clearInterval(timesheetAutoRefreshInterval);
    }
});
</script>
