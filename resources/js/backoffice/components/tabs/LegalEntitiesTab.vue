<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Юридические лица</h2>
                <p class="text-sm text-gray-500 mt-1">Управление юрлицами и кассовыми аппаратами</p>
            </div>
            <button
                @click="openEntityModal()"
                class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition flex items-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Добавить юрлицо
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="spinner"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!entities.length" class="text-center py-12 bg-white rounded-xl">
            <div class="text-5xl mb-4">🏢</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Нет юридических лиц</h3>
            <p class="text-gray-500 mb-4">Добавьте юрлица для разделения чеков по категориям</p>
            <button
                @click="openEntityModal()"
                class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium"
            >
                Добавить юрлицо
            </button>
        </div>

        <!-- Entities List -->
        <div v-else class="space-y-4">
            <div
                v-for="entity in entities"
                :key="entity.id"
                class="bg-white rounded-xl shadow-sm overflow-hidden"
            >
                <!-- Entity Header -->
                <div class="px-6 py-4 flex items-center justify-between border-b">
                    <div class="flex items-center gap-4">
                        <div :class="[
                            'w-12 h-12 rounded-xl flex items-center justify-center text-lg font-semibold',
                            entity.type === 'llc' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'
                        ]">
                            {{ entity.type === 'llc' ? 'ООО' : 'ИП' }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ entity.name }}</h3>
                                <span v-if="entity.is_default" class="text-xs px-2 py-0.5 bg-orange-100 text-orange-600 rounded">
                                    По умолчанию
                                </span>
                                <span v-if="!entity.is_active" class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded">
                                    Неактивен
                                </span>
                            </div>
                            <div class="text-sm text-gray-500 flex items-center gap-3 mt-1">
                                <span>ИНН: {{ entity.inn }}</span>
                                <span v-if="entity.kpp">КПП: {{ entity.kpp }}</span>
                                <span class="text-gray-400">|</span>
                                <span>{{ entity.categories_count || 0 }} категорий</span>
                                <span>{{ entity.cash_registers_count || 0 }} касс</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="!entity.is_default"
                            @click="makeDefault(entity)"
                            class="px-3 py-1.5 text-sm text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition"
                            title="Сделать по умолчанию"
                        >
                            По умолчанию
                        </button>
                        <button
                            @click="openEntityModal(entity)"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"
                            title="Редактировать"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button
                            @click="toggleEntity(entity.id)"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"
                        >
                            <svg :class="['w-5 h-5 transition-transform', expandedEntities.has(entity.id) ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Entity Details (expanded) -->
                <div v-if="expandedEntities.has(entity.id)" class="px-6 py-4 bg-gray-50 border-b">
                    <div class="grid grid-cols-3 gap-6">
                        <!-- Реквизиты -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Реквизиты</h4>
                            <div class="space-y-1 text-sm text-gray-600">
                                <div v-if="entity.ogrn"><span class="text-gray-400">ОГРН:</span> {{ entity.ogrn }}</div>
                                <div v-if="entity.legal_address"><span class="text-gray-400">Юр. адрес:</span> {{ entity.legal_address }}</div>
                                <div v-if="entity.actual_address"><span class="text-gray-400">Факт. адрес:</span> {{ entity.actual_address }}</div>
                                <div v-if="entity.director_name">
                                    <span class="text-gray-400">Руководитель:</span>
                                    {{ entity.director_position || '' }} {{ entity.director_name }}
                                </div>
                            </div>
                        </div>

                        <!-- Банковские реквизиты -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Банк</h4>
                            <div class="space-y-1 text-sm text-gray-600">
                                <div v-if="entity.bank_name">{{ entity.bank_name }}</div>
                                <div v-if="entity.bank_bik"><span class="text-gray-400">БИК:</span> {{ entity.bank_bik }}</div>
                                <div v-if="entity.bank_account"><span class="text-gray-400">Р/с:</span> {{ entity.bank_account }}</div>
                                <div v-if="entity.bank_corr_account"><span class="text-gray-400">К/с:</span> {{ entity.bank_corr_account }}</div>
                            </div>
                        </div>

                        <!-- Налоги и лицензии -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Налогообложение</h4>
                            <div class="space-y-1 text-sm text-gray-600">
                                <div><span class="text-gray-400">Система:</span> {{ getTaxationLabel(entity.taxation_system) }}</div>
                                <div v-if="entity.vat_rate !== null"><span class="text-gray-400">НДС:</span> {{ entity.vat_rate }}%</div>
                                <div v-if="entity.has_alcohol_license" class="flex items-center gap-1 text-green-600">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    Лицензия на алкоголь
                                    <span v-if="entity.alcohol_license_expires_at" class="text-gray-400">
                                        до {{ formatDate(entity.alcohol_license_expires_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cash Registers -->
                <div class="px-6 py-3">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-medium text-gray-700">Кассовые аппараты (ККТ)</h4>
                        <button
                            @click="openRegisterModal(entity)"
                            class="px-3 py-1 text-sm text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded-lg transition"
                        >
                            + Касса
                        </button>
                    </div>

                    <div v-if="entity.cash_registers?.length" class="space-y-2">
                        <div
                            v-for="register in entity.cash_registers"
                            :key="register.id"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-sm text-gray-900">{{ register.name }}</span>
                                        <span v-if="register.is_default" class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-600 rounded">
                                            По умолчанию
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500 flex items-center gap-2">
                                        <span v-if="register.registration_number">Рег. № {{ register.registration_number }}</span>
                                        <span v-if="register.fn_number">ФН {{ register.fn_number }}</span>
                                        <span v-if="register.fn_expires_at" :class="isFnExpiringSoon(register) ? 'text-orange-500' : ''">
                                            до {{ formatDate(register.fn_expires_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button
                                    @click="openRegisterModal(entity, register)"
                                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-white rounded transition"
                                    title="Редактировать"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    @click="deleteRegister(register)"
                                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition"
                                    title="Удалить"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 text-center py-4">
                        Нет привязанных касс
                    </div>
                </div>

                <!-- Delete Entity -->
                <div v-if="expandedEntities.has(entity.id)" class="px-6 py-3 border-t bg-gray-50">
                    <button
                        @click="deleteEntity(entity)"
                        :disabled="entity.categories_count > 0"
                        class="text-sm text-red-500 hover:text-red-600 disabled:text-gray-400 disabled:cursor-not-allowed"
                    >
                        Удалить юрлицо
                        <span v-if="entity.categories_count > 0" class="text-gray-400">
                            (привязано {{ entity.categories_count }} категорий)
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Entity Modal -->
        <Teleport to="body">
            <div v-if="showEntityModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showEntityModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ entityForm.id ? 'Редактировать' : 'Новое' }} юридическое лицо</h3>
                        <button @click="showEntityModal = false" class="text-gray-400 hover:text-gray-600">
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
                                    <input v-model="entityForm.name" type="text" class="input" placeholder="ООО Ресторан" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Короткое название</label>
                                    <input v-model="entityForm.short_name" type="text" class="input" placeholder="ООО (для чека)" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Тип *</label>
                                    <select v-model="entityForm.type" class="input">
                                        <option value="llc">ООО</option>
                                        <option value="ie">ИП</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">ИНН *</label>
                                    <input v-model="entityForm.inn" type="text" class="input" :placeholder="entityForm.type === 'ie' ? '12 цифр' : '10 цифр'" />
                                </div>
                                <div v-if="entityForm.type === 'llc'">
                                    <label class="block text-sm text-gray-600 mb-1">КПП</label>
                                    <input v-model="entityForm.kpp" type="text" class="input" placeholder="9 цифр" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">ОГРН</label>
                                    <input v-model="entityForm.ogrn" type="text" class="input" :placeholder="entityForm.type === 'ie' ? 'ОГРНИП (15 цифр)' : 'ОГРН (13 цифр)'" />
                                </div>
                            </div>
                        </div>

                        <!-- Адреса -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Адреса</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Юридический адрес</label>
                                    <input v-model="entityForm.legal_address" type="text" class="input" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Фактический адрес</label>
                                    <input v-model="entityForm.actual_address" type="text" class="input" />
                                </div>
                            </div>
                        </div>

                        <!-- Руководитель -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Руководитель</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">ФИО</label>
                                    <input v-model="entityForm.director_name" type="text" class="input" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Должность</label>
                                    <input v-model="entityForm.director_position" type="text" class="input" placeholder="Генеральный директор" />
                                </div>
                            </div>
                        </div>

                        <!-- Банк -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Банковские реквизиты</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm text-gray-600 mb-1">Название банка</label>
                                    <input v-model="entityForm.bank_name" type="text" class="input" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">БИК</label>
                                    <input v-model="entityForm.bank_bik" type="text" class="input" placeholder="9 цифр" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Корр. счёт</label>
                                    <input v-model="entityForm.bank_corr_account" type="text" class="input" placeholder="20 цифр" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-sm text-gray-600 mb-1">Расчётный счёт</label>
                                    <input v-model="entityForm.bank_account" type="text" class="input" placeholder="20 цифр" />
                                </div>
                            </div>
                        </div>

                        <!-- Налоги -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Налогообложение</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Система налогообложения</label>
                                    <select v-model="entityForm.taxation_system" class="input">
                                        <option value="osn">ОСН (общая)</option>
                                        <option value="usn_income">УСН (доходы 6%)</option>
                                        <option value="usn_income_expense">УСН (доходы-расходы 15%)</option>
                                        <option value="patent">Патент</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Ставка НДС</label>
                                    <select v-model="entityForm.vat_rate" class="input">
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
                                    <input v-model="entityForm.has_alcohol_license" type="checkbox" class="rounded text-orange-500 focus:ring-orange-500" />
                                    <span class="text-sm text-gray-700">Есть лицензия на алкоголь</span>
                                </label>
                                <div v-if="entityForm.has_alcohol_license" class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Номер лицензии</label>
                                        <input v-model="entityForm.alcohol_license_number" type="text" class="input" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-600 mb-1">Срок действия</label>
                                        <input v-model="entityForm.alcohol_license_expires_at" type="date" class="input" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Статус -->
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input v-model="entityForm.is_active" type="checkbox" class="rounded text-orange-500 focus:ring-orange-500" />
                                <span class="text-sm text-gray-700">Активен</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="entityForm.is_default" type="checkbox" class="rounded text-orange-500 focus:ring-orange-500" />
                                <span class="text-sm text-gray-700">По умолчанию</span>
                            </label>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button @click="showEntityModal = false" class="btn-secondary">Отмена</button>
                        <button @click="saveEntity" :disabled="!entityForm.name || !entityForm.inn" class="btn-primary">
                            {{ entityForm.id ? 'Сохранить' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Cash Register Modal -->
        <Teleport to="body">
            <div v-if="showRegisterModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showRegisterModal = false">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ registerForm.id ? 'Редактировать' : 'Новая' }} касса</h3>
                        <button @click="showRegisterModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Название *</label>
                            <input v-model="registerForm.name" type="text" class="input" placeholder="Касса 1" />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Серийный номер ККТ</label>
                            <input v-model="registerForm.serial_number" type="text" class="input" />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Регистрационный номер (ФНС)</label>
                            <input v-model="registerForm.registration_number" type="text" class="input" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Номер ФН</label>
                                <input v-model="registerForm.fn_number" type="text" class="input" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Срок действия ФН</label>
                                <input v-model="registerForm.fn_expires_at" type="date" class="input" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">ОФД</label>
                            <input v-model="registerForm.ofd_name" type="text" class="input" placeholder="Название ОФД" />
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2">
                                <input v-model="registerForm.is_active" type="checkbox" class="rounded text-orange-500 focus:ring-orange-500" />
                                <span class="text-sm text-gray-700">Активна</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="registerForm.is_default" type="checkbox" class="rounded text-orange-500 focus:ring-orange-500" />
                                <span class="text-sm text-gray-700">По умолчанию</span>
                            </label>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button @click="showRegisterModal = false" class="btn-secondary">Отмена</button>
                        <button @click="saveRegister" :disabled="!registerForm.name" class="btn-primary">
                            {{ registerForm.id ? 'Сохранить' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

const loading = ref(false);
const entities = ref([]);
const expandedEntities = ref(new Set());

// Entity Modal
const showEntityModal = ref(false);
const entityForm = ref({});

// Register Modal
const showRegisterModal = ref(false);
const registerForm = ref({});
const currentEntityForRegister = ref(null);

// Load entities
const loadEntities = async () => {
    loading.value = true;
    try {
        const data = await store.api('/legal-entities');
        if (data.success) {
            entities.value = data.data || [];
        }
    } catch (e) {
        store.showToast(e.message, 'error');
    } finally {
        loading.value = false;
    }
};

// Toggle entity expand
const toggleEntity = (id) => {
    if (expandedEntities.value.has(id)) {
        expandedEntities.value.delete(id);
    } else {
        expandedEntities.value.add(id);
    }
};

// Open entity modal
const openEntityModal = (entity = null) => {
    if (entity) {
        entityForm.value = { ...entity };
    } else {
        entityForm.value = {
            restaurant_id: store.currentRestaurant?.id || 1,
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
            vat_rate: null,
            has_alcohol_license: false,
            alcohol_license_number: '',
            alcohol_license_expires_at: null,
            is_active: true,
            is_default: false,
        };
    }
    showEntityModal.value = true;
};

// Save entity
const saveEntity = async () => {
    try {
        const method = entityForm.value.id ? 'PUT' : 'POST';
        const endpoint = entityForm.value.id
            ? `/legal-entities/${entityForm.value.id}`
            : '/legal-entities';

        const data = await store.api(endpoint, {
            method,
            body: JSON.stringify(entityForm.value),
        });

        if (data.success) {
            store.showToast(entityForm.value.id ? 'Юрлицо обновлено' : 'Юрлицо создано');
            showEntityModal.value = false;
            await loadEntities();
        }
    } catch (e) {
        store.showToast(e.message, 'error');
    }
};

// Delete entity
const deleteEntity = async (entity) => {
    if (!confirm(`Удалить "${entity.name}"?`)) return;

    try {
        const data = await store.api(`/legal-entities/${entity.id}`, { method: 'DELETE' });
        if (data.success) {
            store.showToast('Юрлицо удалено');
            await loadEntities();
        } else {
            store.showToast(data.message, 'error');
        }
    } catch (e) {
        store.showToast(e.message, 'error');
    }
};

// Make entity default
const makeDefault = async (entity) => {
    try {
        const data = await store.api(`/legal-entities/${entity.id}/default`, { method: 'POST' });
        if (data.success) {
            store.showToast('Установлено по умолчанию');
            await loadEntities();
        }
    } catch (e) {
        store.showToast(e.message, 'error');
    }
};

// Open register modal
const openRegisterModal = (entity, register = null) => {
    currentEntityForRegister.value = entity;

    if (register) {
        registerForm.value = { ...register };
    } else {
        registerForm.value = {
            restaurant_id: entity.restaurant_id,
            legal_entity_id: entity.id,
            name: '',
            serial_number: '',
            registration_number: '',
            fn_number: '',
            fn_expires_at: null,
            ofd_name: '',
            ofd_inn: '',
            is_active: true,
            is_default: false,
        };
    }
    showRegisterModal.value = true;
};

// Save register
const saveRegister = async () => {
    try {
        const method = registerForm.value.id ? 'PUT' : 'POST';
        const endpoint = registerForm.value.id
            ? `/cash-registers/${registerForm.value.id}`
            : '/cash-registers';

        const data = await store.api(endpoint, {
            method,
            body: JSON.stringify(registerForm.value),
        });

        if (data.success) {
            store.showToast(registerForm.value.id ? 'Касса обновлена' : 'Касса создана');
            showRegisterModal.value = false;
            await loadEntities();
        }
    } catch (e) {
        store.showToast(e.message, 'error');
    }
};

// Delete register
const deleteRegister = async (register) => {
    if (!confirm(`Удалить кассу "${register.name}"?`)) return;

    try {
        const data = await store.api(`/cash-registers/${register.id}`, { method: 'DELETE' });
        if (data.success) {
            store.showToast('Касса удалена');
            await loadEntities();
        } else {
            store.showToast(data.message, 'error');
        }
    } catch (e) {
        store.showToast(e.message, 'error');
    }
};

// Helpers
const getTaxationLabel = (system) => {
    const labels = {
        osn: 'ОСН',
        usn_income: 'УСН (доходы)',
        usn_income_expense: 'УСН (доходы-расходы)',
        patent: 'Патент',
    };
    return labels[system] || system;
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('ru-RU');
};

const isFnExpiringSoon = (register) => {
    if (!register.fn_expires_at) return false;
    const expires = new Date(register.fn_expires_at);
    const now = new Date();
    const daysLeft = Math.floor((expires - now) / (1000 * 60 * 60 * 24));
    return daysLeft <= 30 && daysLeft > 0;
};

onMounted(() => {
    loadEntities();
});
</script>
