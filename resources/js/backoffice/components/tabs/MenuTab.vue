<template>
    <div class="h-full flex bg-gray-50 overflow-hidden">
        <!-- Left Panel: Categories Tree / Modifiers -->
        <div class="w-64 bg-white border-r flex flex-col">
            <!-- View Switcher -->
            <div class="flex border-b">
                <button
                    @click="menuView = 'dishes'"
                    :class="[
                        'flex-1 py-3 text-sm font-medium border-b-2 -mb-px transition',
                        menuView === 'dishes'
                            ? 'text-orange-600 border-orange-500'
                            : 'text-gray-500 border-transparent hover:text-gray-700'
                    ]"
                >
                    Блюда
                </button>
                <button
                    @click="menuView = 'modifiers'"
                    :class="[
                        'flex-1 py-3 text-sm font-medium border-b-2 -mb-px transition',
                        menuView === 'modifiers'
                            ? 'text-orange-600 border-orange-500'
                            : 'text-gray-500 border-transparent hover:text-gray-700'
                    ]"
                >
                    Модификаторы
                </button>
            </div>

            <!-- Dishes View -->
            <template v-if="menuView === 'dishes'">
                <div class="p-4 border-b">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Найти..."
                            class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                        />
                        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto">
                <!-- All items -->
                <div
                    @click="selectCategory(null)"
                    :class="[
                        'px-4 py-2.5 cursor-pointer flex items-center gap-2 text-sm transition',
                        selectedCategoryId === null ? 'bg-orange-50 text-orange-600 font-medium border-l-3 border-orange-500' : 'hover:bg-gray-50'
                    ]"
                >
                    <span>Все</span>
                    <span class="ml-auto text-xs text-gray-400">{{ dishes.length }}</span>
                </div>

                <!-- Categories Tree -->
                <div v-for="category in categoriesTree" :key="category.id">
                    <div
                        @click="selectCategory(category.id)"
                        @contextmenu="showCategoryContextMenu($event, category)"
                        :class="[
                            'px-4 py-2.5 cursor-pointer flex items-center gap-2 text-sm transition',
                            selectedCategoryId === category.id ? 'bg-orange-50 text-orange-600 font-medium border-l-3 border-orange-500' : 'hover:bg-gray-50'
                        ]"
                    >
                        <button
                            v-if="category.children?.length"
                            @click.stop="toggleCategory(category.id)"
                            class="w-4 h-4 flex items-center justify-center text-gray-400 hover:text-gray-600"
                        >
                            <svg :class="['w-3 h-3 transition-transform', expandedCategories.has(category.id) ? 'rotate-90' : '']" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <span v-else class="w-4"></span>
                        <span class="truncate">{{ category.name }}</span>
                        <span v-if="getCategoryLegalEntityName(category)" class="text-xs px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 ml-1">
                            {{ getCategoryLegalEntityName(category) }}
                        </span>
                        <span class="ml-auto text-xs text-gray-400">{{ getCategoryDishCount(category.id) }}</span>
                    </div>

                    <!-- Subcategories -->
                    <div v-if="category.children?.length && expandedCategories.has(category.id)">
                        <div
                            v-for="child in category.children"
                            :key="child.id"
                            @click="selectCategory(child.id)"
                            @contextmenu="showCategoryContextMenu($event, child)"
                            :class="[
                                'pl-10 pr-4 py-2 cursor-pointer flex items-center gap-2 text-sm transition',
                                selectedCategoryId === child.id ? 'bg-orange-50 text-orange-600 font-medium border-l-3 border-orange-500' : 'hover:bg-gray-50 text-gray-600'
                            ]"
                        >
                            <span class="truncate">{{ child.name }}</span>
                            <span v-if="getCategoryLegalEntityName(child)" class="text-xs px-1 py-0.5 rounded bg-blue-50 text-blue-600 ml-1">
                                {{ getCategoryLegalEntityName(child) }}
                            </span>
                            <span class="ml-auto text-xs text-gray-400">{{ getCategoryDishCount(child.id) }}</span>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Add Category Button -->
                <div class="p-3 border-t mt-auto">
                    <button
                        v-can="'menu.edit'"
                        @click="openCategoryModal()"
                        class="w-full py-2 text-sm text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition flex items-center justify-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Категория
                    </button>
                </div>
            </template>

            <!-- Modifiers View -->
            <template v-if="menuView === 'modifiers'">
                <div class="flex-1 overflow-y-auto">
                    <div
                        v-for="mod in globalModifiers"
                        :key="mod.id"
                        @click="selectModifierForEdit(mod)"
                        :class="[
                            'px-4 py-3 cursor-pointer border-b transition',
                            selectedModifierId === mod.id ? 'bg-orange-50' : 'hover:bg-gray-50'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-sm">{{ mod.name }}</span>
                            <span
                                :class="[
                                    'text-xs px-1.5 py-0.5 rounded',
                                    mod.type === 'single' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600'
                                ]"
                            >
                                {{ mod.type === 'single' ? '1' : '∞' }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            {{ mod.options?.length || 0 }} опций
                            <span v-if="mod.is_required" class="text-red-500 ml-1">• обяз.</span>
                        </div>
                    </div>
                    <div v-if="!globalModifiers.length" class="p-4 text-center text-gray-400 text-sm">
                        Нет модификаторов
                    </div>
                </div>
                <div class="p-3 border-t">
                    <button
                        v-can="'menu.edit'"
                        @click="openModifierModal()"
                        class="w-full py-2 text-sm text-gray-600 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition flex items-center justify-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Модификатор
                    </button>
                </div>
            </template>
        </div>

        <!-- Center Panel: Dishes List -->
        <div v-if="menuView === 'dishes'" class="flex-1 flex flex-col min-w-0">
            <!-- Header -->
            <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ selectedCategory?.name || 'Все блюда' }}
                    </h2>
                    <span class="text-sm text-gray-400">{{ filteredDishes.length }} позиций</span>
                </div>
                <button
                    v-can="'menu.edit'"
                    @click="openDishPanel()"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Блюдо
                </button>
            </div>

            <!-- Dishes Table -->
            <div class="flex-1 overflow-y-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3 font-medium">Наименование</th>
                            <th class="px-6 py-3 font-medium text-right w-28">Цена</th>
                            <th class="px-6 py-3 font-medium text-right w-28">Себест.</th>
                            <th class="px-6 py-3 font-medium text-center w-20">Статус</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <template v-for="dish in filteredDishes" :key="dish.id">
                            <!-- Main dish row -->
                            <tr
                                @click="openDishPanel(dish)"
                                :class="[
                                    'cursor-pointer transition',
                                    selectedDish?.id === dish.id ? 'bg-orange-50' : 'hover:bg-gray-50'
                                ]"
                            >
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <!-- Expand button for parent products -->
                                        <button
                                            v-if="dish.product_type === 'parent' && dish.variants?.length"
                                            @click.stop="toggleParentDish(dish.id)"
                                            class="w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600"
                                        >
                                            <svg :class="['w-4 h-4 transition-transform', expandedParentDishes.has(dish.id) ? 'rotate-90' : '']" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div v-else class="w-5"></div>
                                        <div
                                            v-if="dish.image_url"
                                            class="w-10 h-10 rounded-lg bg-cover bg-center flex-shrink-0"
                                            :style="{ backgroundImage: `url(${dish.image_url})` }"
                                        ></div>
                                        <div v-else class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-gray-900 truncate">{{ dish.name }}</span>
                                                <span v-if="dish.product_type === 'parent'" class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-600 rounded">
                                                    {{ dish.variants?.length || 0 }} вар.
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-400 truncate">{{ dish.category?.name || '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900">
                                    <span v-if="dish.product_type === 'parent'" class="text-gray-500">
                                        от {{ formatPrice(dish.min_price || getMinVariantPrice(dish)) }}
                                    </span>
                                    <span v-else>{{ formatPrice(dish.price) }}</span>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-500">
                                    {{ dish.cost_price ? formatPrice(dish.cost_price) : '-' }}
                                </td>
                                <td class="px-6 py-3 text-center">
                                    <span
                                        :class="[
                                            'inline-flex w-2 h-2 rounded-full',
                                            dish.is_available ? 'bg-green-500' : 'bg-red-500'
                                        ]"
                                    ></span>
                                </td>
                            </tr>
                            <!-- Variant rows (shown when parent is expanded) -->
                            <template v-if="dish.product_type === 'parent' && expandedParentDishes.has(dish.id)">
                                <tr
                                    v-for="variant in dish.variants"
                                    :key="variant.id"
                                    @click="openVariantModal(dish, variant)"
                                    class="cursor-pointer transition bg-gray-50/50 hover:bg-orange-50/50"
                                >
                                    <td class="px-6 py-2">
                                        <div class="flex items-center gap-3 pl-8">
                                            <div class="w-5"></div>
                                            <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm text-gray-700">{{ variant.variant_name || variant.name }}</div>
                                                <div v-if="variant.api_external_id" class="text-xs text-gray-400">ID: {{ variant.api_external_id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2 text-right font-medium text-gray-700 text-sm">{{ formatPrice(variant.price) }}</td>
                                    <td class="px-6 py-2 text-right text-gray-500 text-sm">
                                        {{ variant.cost_price ? formatPrice(variant.cost_price) : '-' }}
                                    </td>
                                    <td class="px-6 py-2 text-center">
                                        <span
                                            :class="[
                                                'inline-flex w-2 h-2 rounded-full',
                                                variant.is_available ? 'bg-green-500' : 'bg-red-500'
                                            ]"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <tr v-if="!filteredDishes.length">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                {{ searchQuery ? 'Ничего не найдено' : 'Нет блюд в категории' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Center Panel: Modifiers Info -->
        <div v-if="menuView === 'modifiers'" class="flex-1 flex flex-col min-w-0">
            <div class="bg-white border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Глобальные модификаторы</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Создавайте шаблоны модификаторов один раз и используйте их для разных блюд
                </p>
            </div>
            <div class="flex-1 overflow-y-auto p-6">
                <div class="max-w-2xl mx-auto space-y-4">
                    <!-- Info Cards -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ globalModifiers.length }}</div>
                            <div class="text-xs text-blue-600">Модификаторов</div>
                        </div>
                        <div class="bg-purple-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-purple-600">
                                {{ globalModifiers.reduce((sum, m) => sum + (m.options?.length || 0), 0) }}
                            </div>
                            <div class="text-xs text-purple-600">Опций</div>
                        </div>
                        <div class="bg-orange-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-orange-600">
                                {{ globalModifiers.filter(m => m.is_required).length }}
                            </div>
                            <div class="text-xs text-orange-600">Обязательных</div>
                        </div>
                    </div>

                    <!-- Examples -->
                    <div class="bg-gray-50 rounded-xl p-5">
                        <h3 class="font-medium text-gray-900 mb-3">Примеры модификаторов</h3>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs flex-shrink-0">1</span>
                                <div>
                                    <strong>Размер пиццы</strong> — один выбор
                                    <div class="text-gray-400">25см, 30см (+150₽), 35см (+300₽)</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xs flex-shrink-0">∞</span>
                                <div>
                                    <strong>Топпинги</strong> — несколько выборов
                                    <div class="text-gray-400">Сыр (+80₽), Грибы (+60₽), Бекон (+100₽)</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs flex-shrink-0">!</span>
                                <div>
                                    <strong>Степень прожарки</strong> — обязательный
                                    <div class="text-gray-400">Rare, Medium, Well Done</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hint -->
                    <div class="text-center text-sm text-gray-400 py-4">
                        Выберите модификатор слева для редактирования<br>
                        или нажмите "+ Модификатор" чтобы создать новый
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Dish Details (Slide-out) -->
        <Teleport to="body">
            <!-- Backdrop -->
            <Transition name="fade">
                <div
                    v-if="showDishPanel"
                    class="fixed inset-0 bg-black/30 z-40"
                    @click="closeDishPanel"
                ></div>
            </Transition>
            <Transition name="slide">
                <div
                    v-if="showDishPanel"
                    class="fixed top-0 right-0 w-[480px] h-screen bg-white border-l flex flex-col shadow-2xl z-50"
                >
                <!-- Panel Header -->
                <div class="px-6 py-4 border-b flex items-center justify-between bg-gradient-to-r from-orange-500 to-amber-500 flex-shrink-0">
                    <h3 class="text-lg font-semibold text-white truncate">
                        {{ dishForm.id ? dishForm.name : 'Новое блюдо' }}
                    </h3>
                    <div class="flex items-center gap-1">
                        <button
                            @click="saveDish"
                            :disabled="!dishForm.name || saving"
                            class="p-2 bg-green-500 hover:bg-green-600 disabled:bg-green-300 text-white rounded-lg transition"
                            title="Сохранить"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                        <button
                            @click="closeDishPanel"
                            class="p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition"
                            title="Закрыть"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Panel Tabs -->
                <div class="flex border-b bg-gray-50 flex-shrink-0">
                    <button
                        v-for="tab in dishTabs"
                        :key="tab.id"
                        @click="activeDishTab = tab.id"
                        :class="[
                            'flex-1 px-4 py-3 text-sm font-medium border-b-2 -mb-px transition',
                            activeDishTab === tab.id
                                ? 'text-orange-600 border-orange-500 bg-white'
                                : 'text-gray-500 border-transparent hover:text-gray-700'
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Panel Content -->
                <div class="flex-1 overflow-y-auto p-6 min-h-0">
                    <!-- Tab: Description -->
                    <div v-if="activeDishTab === 'description'" class="space-y-5">
                        <!-- Image -->
                        <div class="flex justify-center">
                            <div class="relative">
                                <div
                                    v-if="dishForm.image_url"
                                    class="w-40 h-40 rounded-xl bg-cover bg-center shadow-md"
                                    :style="{ backgroundImage: `url(${dishForm.image_url})` }"
                                ></div>
                                <div v-else class="w-40 h-40 rounded-xl bg-gray-100 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <label class="absolute -bottom-2 -right-2 p-2 bg-orange-500 hover:bg-orange-600 text-white rounded-full cursor-pointer shadow-lg transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    </svg>
                                    <input type="file" accept="image/*" class="hidden" @change="uploadImage" />
                                </label>
                            </div>
                        </div>

                        <!-- Product Type Selector -->
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Тип товара</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="dishForm.product_type = 'simple'"
                                    :class="[
                                        'px-4 py-3 rounded-lg border-2 text-sm font-medium transition',
                                        dishForm.product_type === 'simple'
                                            ? 'border-orange-500 bg-orange-50 text-orange-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    <div class="flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        Простой
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">Одна цена</div>
                                </button>
                                <button
                                    type="button"
                                    @click="dishForm.product_type = 'parent'"
                                    :class="[
                                        'px-4 py-3 rounded-lg border-2 text-sm font-medium transition',
                                        dishForm.product_type === 'parent'
                                            ? 'border-purple-500 bg-purple-50 text-purple-700'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    <div class="flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        С вариантами
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">25см, 30см и т.д.</div>
                                </button>
                            </div>
                        </div>

                        <!-- Basic Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                                <input
                                    v-model="dishForm.name"
                                    type="text"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    :placeholder="dishForm.product_type === 'parent' ? 'Маргарита' : 'Маргарита 30см'"
                                />
                            </div>
                            <!-- Price field - only for simple products -->
                            <div v-if="dishForm.product_type === 'simple'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Цена *</label>
                                <div class="relative">
                                    <input
                                        v-model.number="dishForm.price"
                                        type="number"
                                        step="1"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 pr-8"
                                    />
                                    <span class="absolute right-3 top-2.5 text-gray-400">₽</span>
                                </div>
                            </div>
                            <!-- Price info for parent products -->
                            <div v-if="dishForm.product_type === 'parent'" class="col-span-2 p-3 bg-purple-50 rounded-lg">
                                <div class="text-sm text-purple-700">
                                    <span class="font-medium">Цена определяется вариантами</span>
                                    <span v-if="dishForm.variants?.length" class="ml-2">
                                        (от {{ formatPrice(getMinVariantPrice(dishForm)) }})
                                    </span>
                                </div>
                                <div class="text-xs text-purple-500 mt-1">Добавьте варианты с разными ценами ниже</div>
                            </div>
                            <div v-if="dishForm.product_type === 'simple'">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ед. изм.</label>
                                <select
                                    v-model="dishForm.unit"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                >
                                    <option value="шт">шт</option>
                                    <option value="порц">порц</option>
                                    <option value="г">г</option>
                                    <option value="кг">кг</option>
                                    <option value="мл">мл</option>
                                    <option value="л">л</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Категория</label>
                                <select
                                    v-model="dishForm.category_id"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                >
                                    <option :value="null">-- Без категории --</option>
                                    <option v-for="cat in flatCategories" :key="cat.id" :value="cat.id">
                                        {{ cat.level > 0 ? '— ' : '' }}{{ cat.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Цех кухни</label>
                                <select
                                    v-model="dishForm.kitchen_station_id"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                >
                                    <option :value="null">Все дисплеи</option>
                                    <option v-for="station in kitchenStations" :key="station.id" :value="station.id">
                                        {{ station.icon }} {{ station.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                                <textarea
                                    v-model="dishForm.description"
                                    rows="3"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 resize-none"
                                    placeholder="Томатный соус, моцарелла, базилик"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-medium text-gray-900">В продаже</div>
                                <div class="text-sm text-gray-500">Блюдо доступно для заказа</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="dishForm.is_available" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                            </label>
                        </div>

                        <!-- Modifiers Section -->
                        <div class="border-t pt-5">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Модификаторы</h4>
                                <div class="flex gap-2">
                                    <button
                                        @click="showModifierSelector = true"
                                        class="text-sm text-orange-600 hover:text-orange-700 font-medium"
                                    >
                                        + Из шаблонов
                                    </button>
                                    <button
                                        @click="openModifierModal()"
                                        class="text-sm text-green-600 hover:text-green-700 font-medium"
                                    >
                                        + Создать
                                    </button>
                                </div>
                            </div>
                            <div v-if="dishModifiers.length" class="space-y-2">
                                <div
                                    v-for="mod in dishModifiers"
                                    :key="mod.id"
                                    class="p-3 bg-gray-50 rounded-lg text-sm group"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ mod.name }}</span>
                                            <span
                                                :class="[
                                                    'text-xs px-1.5 py-0.5 rounded',
                                                    mod.type === 'single' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600'
                                                ]"
                                            >
                                                {{ mod.type === 'single' ? 'один' : 'несколько' }}
                                            </span>
                                            <span v-if="mod.is_required" class="text-xs text-red-500">обяз.</span>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                            <button
                                                @click="openModifierModal(mod)"
                                                class="p-1 text-gray-400 hover:text-orange-500"
                                                title="Редактировать"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="removeModifierFromDish(mod.id)"
                                                class="p-1 text-gray-400 hover:text-red-500"
                                                title="Убрать"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <span
                                            v-for="opt in mod.options"
                                            :key="opt.id"
                                            class="text-xs px-2 py-0.5 bg-white border rounded"
                                        >
                                            {{ opt.name }}
                                            <span v-if="opt.price > 0" class="text-green-600">+{{ opt.price }}₽</span>
                                            <span v-if="opt.price < 0" class="text-red-600">{{ opt.price }}₽</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-4">
                                Нет модификаторов
                            </div>
                        </div>

                        <!-- Variants Section (for parent products) -->
                        <div v-if="dishForm.product_type === 'parent'" class="border-t pt-5">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Варианты</h4>
                                <button
                                    @click="openVariantModal(dishForm)"
                                    class="text-sm text-purple-600 hover:text-purple-700 font-medium"
                                >
                                    + Добавить вариант
                                </button>
                            </div>
                            <div v-if="dishForm.variants?.length" class="space-y-2">
                                <div
                                    v-for="(variant, index) in dishForm.variants"
                                    :key="variant.id || index"
                                    class="p-3 bg-purple-50 rounded-lg text-sm group"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="font-medium text-purple-900">{{ variant.variant_name }}</div>
                                            <div class="text-purple-700">{{ formatPrice(variant.price) }}</div>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                            <button
                                                @click="openVariantModal(dishForm, variant)"
                                                class="p-1 text-gray-400 hover:text-purple-500"
                                                title="Редактировать"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="deleteVariant(variant)"
                                                class="p-1 text-gray-400 hover:text-red-500"
                                                title="Удалить"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-purple-600">
                                        <span v-if="variant.api_external_id">ID: {{ variant.api_external_id }}</span>
                                        <span v-if="variant.cost_price">Себест: {{ formatPrice(variant.cost_price) }}</span>
                                        <span :class="variant.is_available ? 'text-green-600' : 'text-red-500'">
                                            {{ variant.is_available ? 'В продаже' : 'Недоступен' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                                Добавьте варианты товара<br>
                                <span class="text-xs">например: 25 см, 30 см, 35 см</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Composition (Recipe) -->
                    <div v-if="activeDishTab === 'composition'" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Норма на</div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="recipeYield"
                                        type="number"
                                        min="1"
                                        class="w-16 px-2 py-1 border rounded text-center text-sm"
                                    />
                                    <span class="text-sm text-gray-600">{{ dishForm.unit || 'шт' }}</span>
                                </div>
                            </div>
                            <button
                                @click="addRecipeItem"
                                class="px-3 py-1.5 text-sm text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded-lg font-medium transition"
                            >
                                + Ингредиент
                            </button>
                        </div>

                        <!-- Recipe Table -->
                        <div class="border rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="text-left text-xs text-gray-500 uppercase">
                                        <th class="px-3 py-2 font-medium">Наименование</th>
                                        <th class="px-3 py-2 font-medium text-right w-20">Брутто</th>
                                        <th class="px-3 py-2 font-medium text-right w-16">%ХО</th>
                                        <th class="px-3 py-2 font-medium text-right w-20">Нетто</th>
                                        <th class="px-3 py-2 font-medium text-right w-20">Себест.</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in recipeItems" :key="index" class="hover:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <select
                                                v-model="item.ingredient_id"
                                                @change="onIngredientSelect(item)"
                                                class="w-full px-2 py-1 border rounded text-sm focus:ring-1 focus:ring-orange-500"
                                            >
                                                <option :value="null">Выберите...</option>
                                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                                    {{ ing.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model.number="item.quantity"
                                                @input="calculateNetto(item)"
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                class="w-full px-2 py-1 border rounded text-right text-sm"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500">
                                            {{ item.loss_percent || 0 }}%
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ item.netto?.toFixed(1) || '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-600">
                                            {{ item.cost ? formatPrice(item.cost) : '-' }}
                                        </td>
                                        <td class="px-1 py-2">
                                            <button
                                                @click="removeRecipeItem(index)"
                                                class="p-1 text-gray-400 hover:text-red-500 transition"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!recipeItems.length">
                                        <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                                            Добавьте ингредиенты в состав
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="recipeItems.length" class="bg-gray-50 font-medium">
                                    <tr>
                                        <td class="px-3 py-2">Итого</td>
                                        <td class="px-3 py-2 text-right">{{ totalBrutto.toFixed(1) }}</td>
                                        <td class="px-3 py-2"></td>
                                        <td class="px-3 py-2 text-right">{{ totalNetto.toFixed(1) }}</td>
                                        <td class="px-3 py-2 text-right text-orange-600">{{ formatPrice(totalCost) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Margin Info -->
                        <div v-if="dishForm.price && totalCost" class="p-4 bg-orange-50 rounded-lg">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Наценка</span>
                                <span class="font-semibold text-orange-600">
                                    {{ ((dishForm.price - totalCost) / totalCost * 100).toFixed(0) }}%
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm mt-1">
                                <span class="text-gray-600">Прибыль</span>
                                <span class="font-semibold text-green-600">
                                    {{ formatPrice(dishForm.price - totalCost) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Tech Process -->
                    <div v-if="activeDishTab === 'techprocess'" class="space-y-5">
                        <!-- Cooking Time -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Время приготовления</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="dishForm.cooking_time"
                                        type="number"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        placeholder="0"
                                    />
                                    <span class="text-sm text-gray-500 whitespace-nowrap">мин</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Выход, г</label>
                                <input
                                    v-model.number="dishForm.output_weight"
                                    type="number"
                                    min="0"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    placeholder="0"
                                />
                            </div>
                        </div>

                        <!-- Cooking Method -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Способ приготовления</label>
                            <select
                                v-model="dishForm.cooking_method"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            >
                                <option value="">-- Не указан --</option>
                                <option value="жарка">Жарка</option>
                                <option value="варка">Варка</option>
                                <option value="запекание">Запекание</option>
                                <option value="гриль">Гриль</option>
                                <option value="тушение">Тушение</option>
                                <option value="без обработки">Без тепловой обработки</option>
                                <option value="пассерование">Пассерование</option>
                                <option value="бланширование">Бланширование</option>
                                <option value="фритюр">Фритюр</option>
                            </select>
                        </div>

                        <!-- Storage & Serving -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Срок хранения</label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="dishForm.shelf_life"
                                        type="number"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        placeholder="0"
                                    />
                                    <span class="text-sm text-gray-500 whitespace-nowrap">час</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Температура подачи</label>
                                <select
                                    v-model="dishForm.serving_temp"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                >
                                    <option value="">-- Не указано --</option>
                                    <option value="горячее">Горячее (65-75°C)</option>
                                    <option value="теплое">Тёплое (40-50°C)</option>
                                    <option value="холодное">Холодное (10-14°C)</option>
                                    <option value="заморозка">Заморозка (-18°C)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tech Card (Instructions) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Технологическая карта</label>
                            <textarea
                                v-model="dishForm.tech_card"
                                rows="8"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 resize-none font-mono text-sm"
                                placeholder="1. Подготовить ингредиенты&#10;2. Нарезать овощи&#10;3. Обжарить на сковороде&#10;4. Добавить соус&#10;5. Подать к столу"
                            ></textarea>
                        </div>

                        <!-- Allergens -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Аллергены</label>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="allergen in allergensList"
                                    :key="allergen.id"
                                    :class="[
                                        'px-3 py-1.5 rounded-full text-sm cursor-pointer border transition',
                                        (dishForm.allergens || []).includes(allergen.id)
                                            ? 'bg-orange-100 border-orange-500 text-orange-700'
                                            : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        :value="allergen.id"
                                        v-model="dishForm.allergens"
                                        class="hidden"
                                    />
                                    {{ allergen.name }}
                                </label>
                            </div>
                        </div>

                        <!-- Nutritional Info -->
                        <div class="border-t pt-5">
                            <h4 class="font-medium text-gray-900 mb-3">Пищевая ценность (на 100г)</h4>
                            <div class="grid grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Калории</label>
                                    <input
                                        v-model.number="dishForm.calories"
                                        type="number"
                                        min="0"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500"
                                        placeholder="0"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Белки, г</label>
                                    <input
                                        v-model.number="dishForm.proteins"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500"
                                        placeholder="0"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Жиры, г</label>
                                    <input
                                        v-model.number="dishForm.fats"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500"
                                        placeholder="0"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Углеводы, г</label>
                                    <input
                                        v-model.number="dishForm.carbs"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500"
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Footer - Delete Button -->
                <div v-if="dishForm.id" class="px-6 py-4 border-t bg-gray-50 flex-shrink-0">
                    <button
                        v-can="'menu.delete'"
                        @click="deleteDish"
                        class="w-full py-2.5 text-red-600 hover:bg-red-50 rounded-lg transition flex items-center justify-center gap-2 text-sm font-medium"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Удалить блюдо
                    </button>
                </div>

                </div>
            </Transition>
        </Teleport>

        <!-- Category Modal -->
        <Teleport to="body">
            <div v-if="showCategoryModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCategoryModal = false">
                <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-semibold mb-4">{{ categoryForm.id ? 'Редактировать' : 'Новая' }} категория</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                            <input
                                v-model="categoryForm.name"
                                type="text"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                placeholder="Пицца"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Родительская категория</label>
                            <select
                                v-model="categoryForm.parent_id"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            >
                                <option :value="null">-- Корневая --</option>
                                <option v-for="cat in categories.filter(c => !c.parent_id)" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Юридическое лицо</label>
                            <select
                                v-model="categoryForm.legal_entity_id"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            >
                                <option :value="null">-- По умолчанию --</option>
                                <option v-for="entity in legalEntities" :key="entity.id" :value="entity.id">
                                    {{ entity.short_name || entity.name }} ({{ entity.inn }})
                                </option>
                            </select>
                            <div class="text-xs text-gray-400 mt-1">Для разделения чеков по юрлицам</div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="showCategoryModal = false" class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">
                            Отмена
                        </button>
                        <button
                            @click="saveCategory"
                            :disabled="!categoryForm.name"
                            class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                        >
                            Сохранить
                        </button>
                    </div>
                </div>
            </div>

            <!-- Context Menu -->
            <div
                v-if="contextMenu.show"
                class="fixed z-50"
                :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
            >
                <div
                    class="bg-white rounded-lg shadow-lg border py-1 min-w-[160px]"
                    @click.stop
                >
                    <button
                        @click="openCategoryModal(contextMenu.category)"
                        class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                    >
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Редактировать
                    </button>
                    <button
                        v-can="'menu.delete'"
                        @click="deleteCategory"
                        class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Удалить
                    </button>
                </div>
            </div>

            <!-- Click overlay to close context menu -->
            <div
                v-if="contextMenu.show"
                class="fixed inset-0 z-40"
                @click="closeCategoryContextMenu"
            ></div>

            <!-- Modifier Selector Modal -->
            <div
                v-if="showModifierSelector"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                @click.self="showModifierSelector = false"
            >
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[80vh] flex flex-col">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Выбрать модификатор</h3>
                        <button @click="showModifierSelector = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="availableGlobalModifiers.length" class="space-y-2">
                            <div
                                v-for="mod in availableGlobalModifiers"
                                :key="mod.id"
                                @click="attachModifierToDish(mod.id)"
                                class="p-3 border rounded-lg cursor-pointer hover:border-orange-500 hover:bg-orange-50 transition"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ mod.name }}</span>
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100">
                                            {{ mod.type === 'single' ? 'один' : 'несколько' }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ mod.options?.length || 0 }} опц.</span>
                                </div>
                                <div v-if="mod.options?.length" class="mt-1 text-xs text-gray-500">
                                    {{ mod.options.map(o => o.name).join(', ') }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-400">
                            Нет доступных шаблонов
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modifier Editor Modal -->
            <div
                v-if="showModifierModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                @click.self="closeModifierModal"
            >
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
                    <div class="px-6 py-4 border-b flex items-center justify-between bg-gradient-to-r from-orange-500 to-amber-500 rounded-t-2xl">
                        <h3 class="text-lg font-semibold text-white">
                            {{ modifierForm.id ? 'Редактировать' : 'Новый' }} модификатор
                        </h3>
                        <button @click="closeModifierModal" class="text-white/70 hover:text-white">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 space-y-4">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Название группы *</label>
                            <input
                                v-model="modifierForm.name"
                                type="text"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500"
                                placeholder="Размер пиццы"
                            />
                        </div>

                        <!-- Type & Required -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Тип выбора</label>
                                <select v-model="modifierForm.type" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500">
                                    <option value="single">Один вариант</option>
                                    <option value="multiple">Несколько вариантов</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-3 pt-6">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" v-model="modifierForm.is_required" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                                </label>
                                <span class="text-sm text-gray-700">Обязательный</span>
                            </div>
                        </div>

                        <!-- Min/Max for multiple -->
                        <div v-if="modifierForm.type === 'multiple'" class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Мин. выбор</label>
                                <input v-model.number="modifierForm.min_selections" type="number" min="0" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Макс. выбор</label>
                                <input v-model.number="modifierForm.max_selections" type="number" min="1" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-orange-500" />
                            </div>
                        </div>

                        <!-- Global template -->
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="modifierForm.is_global" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                            <span class="text-sm text-gray-700">Глобальный шаблон (для других блюд)</span>
                        </div>

                        <!-- Options -->
                        <div class="border-t pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Опции</h4>
                                <button @click="addModifierOption" class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                                    + Добавить
                                </button>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(opt, index) in modifierForm.options"
                                    :key="index"
                                    class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg"
                                >
                                    <input
                                        v-model="opt.name"
                                        type="text"
                                        class="flex-1 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500"
                                        placeholder="Название опции"
                                    />
                                    <div class="relative w-24">
                                        <input
                                            v-model.number="opt.price"
                                            type="number"
                                            class="w-full px-3 py-2 border rounded-lg text-sm text-right pr-6 focus:ring-2 focus:ring-orange-500"
                                            placeholder="0"
                                        />
                                        <span class="absolute right-2 top-2 text-gray-400 text-sm">₽</span>
                                    </div>
                                    <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                                        <input type="checkbox" v-model="opt.is_default" class="rounded text-orange-500 focus:ring-orange-500" />
                                        по умолч.
                                    </label>
                                    <button @click="removeModifierOption(index)" class="p-1 text-gray-400 hover:text-red-500">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div v-if="!modifierForm.options.length" class="text-center py-4 text-gray-400 text-sm">
                                    Добавьте опции
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t bg-gray-50 flex items-center justify-between rounded-b-2xl">
                        <button
                            v-if="modifierForm.id"
                            @click="deleteModifier(modifierForm.id); closeModifierModal()"
                            class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg font-medium transition"
                        >
                            Удалить
                        </button>
                        <div v-else></div>
                        <div class="flex gap-3">
                            <button @click="closeModifierModal" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                                Отмена
                            </button>
                            <button
                                @click="saveModifier"
                                :disabled="!modifierForm.name || !modifierForm.options.length"
                                class="px-6 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                            >
                                Сохранить
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variant Editor Modal -->
            <div
                v-if="showVariantModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                @click.self="closeVariantModal"
            >
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                    <div class="px-6 py-4 border-b flex items-center justify-between bg-gradient-to-r from-purple-500 to-violet-500 rounded-t-2xl">
                        <h3 class="text-lg font-semibold text-white">
                            {{ variantForm.id ? 'Редактировать' : 'Новый' }} вариант
                        </h3>
                        <button @click="closeVariantModal" class="text-white/70 hover:text-white">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 space-y-4">
                        <!-- Parent info -->
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="text-xs text-gray-500">Родительский товар</div>
                            <div class="font-medium text-gray-900">{{ editingVariantParent?.name }}</div>
                        </div>

                        <!-- Variant Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Название варианта *</label>
                            <input
                                v-model="variantForm.variant_name"
                                type="text"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500"
                                placeholder="25 см, 4 шт, 300 мл..."
                            />
                            <div class="text-xs text-gray-400 mt-1">Например: размер, количество, объём</div>
                        </div>

                        <!-- Price -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Цена *</label>
                                <div class="relative">
                                    <input
                                        v-model.number="variantForm.price"
                                        type="number"
                                        step="1"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 pr-8"
                                    />
                                    <span class="absolute right-3 top-2.5 text-gray-400">₽</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Себестоимость</label>
                                <div class="relative">
                                    <input
                                        v-model.number="variantForm.cost_price"
                                        type="number"
                                        step="1"
                                        min="0"
                                        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 pr-8"
                                    />
                                    <span class="absolute right-3 top-2.5 text-gray-400">₽</span>
                                </div>
                            </div>
                        </div>

                        <!-- API ID for integrations -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID для интеграций</label>
                            <div class="relative">
                                <input
                                    v-model="variantForm.api_external_id"
                                    type="text"
                                    class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 bg-gray-50"
                                    :placeholder="variantForm.id ? '' : 'Сгенерируется автоматически'"
                                    :readonly="!!variantForm.id && !editingApiId"
                                />
                                <button
                                    v-if="variantForm.id"
                                    @click="editingApiId = !editingApiId"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400 hover:text-purple-600"
                                >
                                    {{ editingApiId ? 'готово' : 'изменить' }}
                                </button>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                {{ variantForm.id ? 'Используется для Яндекс.Еда, Delivery Club, iiko' : 'ID сгенерируется после сохранения (V-123)' }}
                            </div>
                        </div>

                        <!-- Sort Order -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Порядок сортировки</label>
                            <input
                                v-model.number="variantForm.variant_sort"
                                type="number"
                                min="0"
                                class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500"
                                placeholder="0"
                            />
                        </div>

                        <!-- Available toggle -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-medium text-gray-900">В продаже</div>
                                <div class="text-sm text-gray-500">Вариант доступен для заказа</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="variantForm.is_available" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                            </label>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t bg-gray-50 flex items-center justify-between rounded-b-2xl">
                        <button
                            v-if="variantForm.id"
                            @click="deleteVariant(variantForm); closeVariantModal()"
                            class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg font-medium transition"
                        >
                            Удалить
                        </button>
                        <div v-else></div>
                        <div class="flex gap-3">
                            <button @click="closeVariantModal" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                                Отмена
                            </button>
                            <button
                                @click="saveVariant"
                                :disabled="!variantForm.variant_name || !variantForm.price"
                                class="px-6 py-2 bg-purple-500 hover:bg-purple-600 disabled:bg-gray-300 text-white rounded-lg font-medium transition"
                            >
                                Сохранить
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useBackofficeStore } from '../../stores/backoffice';

const store = useBackofficeStore();

// State
const menuView = ref('dishes'); // 'dishes' | 'modifiers'
const searchQuery = ref('');
const selectedCategoryId = ref(null);
const selectedDish = ref(null);
const expandedCategories = ref(new Set());
const showDishPanel = ref(false);
const showCategoryModal = ref(false);
const activeDishTab = ref('description');
const saving = ref(false);
const recipeYield = ref(1);
const selectedModifierId = ref(null);

// Product variants
const expandedParentDishes = ref(new Set());
const showVariantModal = ref(false);
const editingVariantParent = ref(null);
const variantForm = ref(createEmptyVariant());
const editingApiId = ref(false);

// Context menu
const contextMenu = ref({
    show: false,
    x: 0,
    y: 0,
    category: null
});

// Data
const categories = ref([]);
const dishes = ref([]);
const ingredients = ref([]);
const kitchenStations = ref([]);
const legalEntities = ref([]);

// Modifiers
const dishModifiers = ref([]);
const globalModifiers = ref([]);
const showModifierSelector = ref(false);
const showModifierModal = ref(false);
const editingModifier = ref(null);
const modifierForm = ref(createEmptyModifier());

// Allergens list
const allergensList = [
    { id: 'gluten', name: 'Глютен' },
    { id: 'dairy', name: 'Молоко' },
    { id: 'eggs', name: 'Яйца' },
    { id: 'fish', name: 'Рыба' },
    { id: 'shellfish', name: 'Ракообразные' },
    { id: 'nuts', name: 'Орехи' },
    { id: 'peanuts', name: 'Арахис' },
    { id: 'soy', name: 'Соя' },
    { id: 'sesame', name: 'Кунжут' },
    { id: 'celery', name: 'Сельдерей' },
    { id: 'mustard', name: 'Горчица' },
    { id: 'sulfites', name: 'Сульфиты' }
];

// Forms
const dishForm = ref(createEmptyDish());
const categoryForm = ref({ id: null, name: '', parent_id: null, legal_entity_id: null });
const recipeItems = ref([]);

// Tabs
const dishTabs = [
    { id: 'description', label: 'Описание' },
    { id: 'composition', label: 'Состав' },
    { id: 'techprocess', label: 'Техпроцесс' }
];

// Computed
const categoriesTree = computed(() => {
    const roots = categories.value.filter(c => !c.parent_id);
    return roots.map(root => ({
        ...root,
        children: categories.value.filter(c => c.parent_id === root.id)
    }));
});

const flatCategories = computed(() => {
    const result = [];
    categoriesTree.value.forEach(cat => {
        result.push({ ...cat, level: 0 });
        if (cat.children) {
            cat.children.forEach(child => {
                result.push({ ...child, level: 1 });
            });
        }
    });
    return result;
});

const selectedCategory = computed(() => {
    return categories.value.find(c => c.id === selectedCategoryId.value);
});

const filteredDishes = computed(() => {
    let result = dishes.value;

    // Filter by category
    if (selectedCategoryId.value) {
        const categoryIds = [selectedCategoryId.value];
        // Include children categories
        const children = categories.value.filter(c => c.parent_id === selectedCategoryId.value);
        children.forEach(c => categoryIds.push(c.id));
        result = result.filter(d => categoryIds.includes(d.category_id));
    }

    // Filter by search
    if (searchQuery.value.trim()) {
        const search = searchQuery.value.toLowerCase();
        result = result.filter(d =>
            d.name?.toLowerCase().includes(search) ||
            d.description?.toLowerCase().includes(search)
        );
    }

    return result;
});

const totalBrutto = computed(() => {
    return recipeItems.value.reduce((sum, item) => sum + (item.quantity || 0), 0);
});

const totalNetto = computed(() => {
    return recipeItems.value.reduce((sum, item) => sum + (item.netto || 0), 0);
});

const totalCost = computed(() => {
    return recipeItems.value.reduce((sum, item) => sum + (item.cost || 0), 0);
});

// Available global modifiers (not yet attached to current dish)
const availableGlobalModifiers = computed(() => {
    const attachedIds = dishModifiers.value.map(m => m.id);
    return globalModifiers.value.filter(m => !attachedIds.includes(m.id));
});

// Methods
function createEmptyModifier() {
    return {
        id: null,
        name: '',
        type: 'single',
        is_required: false,
        min_selections: 0,
        max_selections: 1,
        is_global: false,
        options: []
    };
}

function createEmptyDish() {
    return {
        id: null,
        name: '',
        category_id: null,
        kitchen_station_id: null,
        product_type: 'simple', // simple, parent, variant
        parent_id: null,
        variant_name: '',
        api_external_id: '',
        variant_sort: 0,
        price: 0,
        cost_price: 0,
        description: '',
        image_url: null,
        unit: 'шт',
        is_available: true,
        modifier_groups: [],
        variants: [], // For parent products
        // Tech process fields
        cooking_time: null,
        output_weight: null,
        cooking_method: '',
        shelf_life: null,
        serving_temp: '',
        tech_card: '',
        allergens: [],
        calories: null,
        proteins: null,
        fats: null,
        carbs: null
    };
}

function createEmptyVariant() {
    return {
        id: null,
        name: '', // Gets inherited from parent
        variant_name: '',
        price: 0,
        cost_price: 0,
        api_external_id: '',
        variant_sort: 0,
        is_available: true
    };
}

function formatPrice(amount) {
    if (!amount) return '0 ₽';
    return new Intl.NumberFormat('ru-RU').format(amount) + ' ₽';
}

function getCategoryDishCount(categoryId) {
    const categoryIds = [categoryId];
    const children = categories.value.filter(c => c.parent_id === categoryId);
    children.forEach(c => categoryIds.push(c.id));
    return dishes.value.filter(d => categoryIds.includes(d.category_id)).length;
}

function getCategoryLegalEntityName(category) {
    if (!category.legal_entity_id) return null;
    const entity = legalEntities.value.find(e => e.id === category.legal_entity_id);
    return entity?.short_name || entity?.name?.substring(0, 10) || null;
}

function toggleCategory(categoryId) {
    if (expandedCategories.value.has(categoryId)) {
        expandedCategories.value.delete(categoryId);
    } else {
        expandedCategories.value.add(categoryId);
    }
}

function selectCategory(categoryId) {
    selectedCategoryId.value = categoryId;
    if (categoryId) {
        expandedCategories.value.add(categoryId);
    }
}

function openDishPanel(dish = null) {
    selectedDish.value = dish;
    if (dish) {
        dishForm.value = {
            ...dish,
            variants: dish.variants || []
        };
        loadDishRecipe(dish.id);
        loadDishModifiers(dish.id);
    } else {
        dishForm.value = createEmptyDish();
        dishForm.value.category_id = selectedCategoryId.value;
        recipeItems.value = [];
        dishModifiers.value = [];
    }
    activeDishTab.value = 'description';
    showDishPanel.value = true;
}

function closeDishPanel() {
    showDishPanel.value = false;
    selectedDish.value = null;
}

async function loadDishRecipe(dishId) {
    try {
        const res = await store.api(`/backoffice/menu/dishes/${dishId}/recipe`);
        const items = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
        recipeItems.value = items.map(item => ({
            ...item,
            netto: calculateNettoValue(item.quantity, item.loss_percent),
            cost: calculateItemCost(item)
        }));
    } catch (e) {
        console.error('Failed to load recipe:', e);
        recipeItems.value = [];
    }
}

function addRecipeItem() {
    recipeItems.value.push({
        ingredient_id: null,
        quantity: 0,
        loss_percent: 0,
        netto: 0,
        cost: 0
    });
}

function removeRecipeItem(index) {
    recipeItems.value.splice(index, 1);
}

function onIngredientSelect(item) {
    const ingredient = ingredients.value.find(i => i.id === item.ingredient_id);
    if (ingredient) {
        item.loss_percent = (ingredient.cold_loss_percent || 0) + (ingredient.hot_loss_percent || 0);
        item.unit = ingredient.unit?.short_name || '';
        item.cost_price = ingredient.cost_price || 0;
        calculateNetto(item);
    }
}

function calculateNetto(item) {
    item.netto = calculateNettoValue(item.quantity, item.loss_percent);
    item.cost = calculateItemCost(item);
}

function calculateNettoValue(brutto, lossPercent) {
    if (!brutto) return 0;
    return brutto * (1 - (lossPercent || 0) / 100);
}

function calculateItemCost(item) {
    if (!item.quantity || !item.cost_price) return 0;
    return item.quantity * item.cost_price;
}

async function saveDish() {
    if (!dishForm.value.name) return;

    // For parent products, set price to 0 (actual price comes from variants)
    if (dishForm.value.product_type === 'parent') {
        dishForm.value.price = 0;
    }

    saving.value = true;
    try {
        let savedDish;
        // Don't send variants array to backend (they're saved separately)
        const { variants, ...dishData } = dishForm.value;

        if (dishForm.value.id) {
            const res = await store.api(`/backoffice/menu/dishes/${dishForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(dishData)
            });
            savedDish = res.data || res;
        } else {
            const res = await store.api('/backoffice/menu/dishes', {
                method: 'POST',
                body: JSON.stringify(dishData)
            });
            savedDish = res.data || res;
        }

        // Save recipe
        if (savedDish?.id && recipeItems.value.length) {
            await store.api(`/backoffice/menu/dishes/${savedDish.id}/recipe`, {
                method: 'POST',
                body: JSON.stringify({ items: recipeItems.value.filter(i => i.ingredient_id) })
            });
        }

        const isNew = !dishForm.value.id;
        store.showToast(isNew ? 'Блюдо создано' : 'Блюдо обновлено', 'success');
        await loadDishes();

        // For new parent products, keep panel open to add variants
        if (isNew && savedDish?.product_type === 'parent') {
            // Reload the saved dish with variants relation
            const updatedDish = dishes.value.find(d => d.id === savedDish.id);
            if (updatedDish) {
                dishForm.value = {
                    ...updatedDish,
                    variants: updatedDish.variants || []
                };
                selectedDish.value = updatedDish;
                store.showToast('Теперь добавьте варианты', 'info');
            }
        } else {
            closeDishPanel();
        }
    } catch (e) {
        console.error('Failed to save dish:', e);
        store.showToast('Ошибка сохранения', 'error');
    } finally {
        saving.value = false;
    }
}

async function deleteDish() {
    if (!confirm('Удалить блюдо?')) return;

    try {
        await store.api(`/backoffice/menu/dishes/${dishForm.value.id}`, { method: 'DELETE' });
        store.showToast('Блюдо удалено', 'success');
        loadDishes();
        closeDishPanel();
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

async function uploadImage(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    // For now, just create a local URL
    // TODO: Implement actual upload
    dishForm.value.image_url = URL.createObjectURL(file);
}

// Modifier methods
async function loadDishModifiers(dishId) {
    try {
        const res = await store.api(`/backoffice/menu/dishes/${dishId}/modifiers`);
        dishModifiers.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load modifiers:', e);
        dishModifiers.value = [];
    }
}

async function loadGlobalModifiers() {
    try {
        const res = await store.api('/backoffice/modifiers?is_global=true&active_only=true');
        globalModifiers.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load global modifiers:', e);
        globalModifiers.value = [];
    }
}

function openModifierModal(modifier = null) {
    if (modifier) {
        editingModifier.value = modifier;
        modifierForm.value = {
            ...modifier,
            options: modifier.options ? [...modifier.options.map(o => ({...o}))] : []
        };
    } else {
        editingModifier.value = null;
        modifierForm.value = createEmptyModifier();
        // При создании из вкладки "Модификаторы" делаем глобальным
        if (menuView.value === 'modifiers') {
            modifierForm.value.is_global = true;
        }
    }
    showModifierModal.value = true;
}

function closeModifierModal() {
    showModifierModal.value = false;
    editingModifier.value = null;
}

function addModifierOption() {
    modifierForm.value.options.push({
        id: null,
        name: '',
        price: 0,
        is_default: false,
        sort_order: modifierForm.value.options.length
    });
}

function removeModifierOption(index) {
    modifierForm.value.options.splice(index, 1);
}

async function saveModifier() {
    if (!modifierForm.value.name) return;

    try {
        let savedModifier;
        const payload = {
            ...modifierForm.value
            // restaurant_id определяется на бэкенде из авторизации
        };

        if (modifierForm.value.id) {
            const res = await store.api(`/backoffice/modifiers/${modifierForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
            savedModifier = res.data || res;
        } else {
            const res = await store.api('/backoffice/modifiers', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            savedModifier = res.data || res;
        }

        // Если блюдо открыто, привязываем модификатор
        if (dishForm.value.id && savedModifier?.id) {
            await store.api('/backoffice/modifiers/attach-dish', {
                method: 'POST',
                body: JSON.stringify({
                    dish_id: dishForm.value.id,
                    modifier_id: savedModifier.id
                })
            });
            await loadDishModifiers(dishForm.value.id);
        }

        loadGlobalModifiers();
        closeModifierModal();
        store.showToast('Модификатор сохранён', 'success');
    } catch (e) {
        console.error('Failed to save modifier:', e);
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function attachModifierToDish(modifierId) {
    if (!dishForm.value.id) return;

    try {
        await store.api('/backoffice/modifiers/attach-dish', {
            method: 'POST',
            body: JSON.stringify({
                dish_id: dishForm.value.id,
                modifier_id: modifierId
            })
        });
        await loadDishModifiers(dishForm.value.id);
        showModifierSelector.value = false;
        store.showToast('Модификатор добавлен', 'success');
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

async function removeModifierFromDish(modifierId) {
    if (!dishForm.value.id) return;

    try {
        await store.api('/backoffice/modifiers/detach-dish', {
            method: 'POST',
            body: JSON.stringify({
                dish_id: dishForm.value.id,
                modifier_id: modifierId
            })
        });
        await loadDishModifiers(dishForm.value.id);
        store.showToast('Модификатор убран', 'success');
    } catch (e) {
        store.showToast('Ошибка', 'error');
    }
}

function selectModifierForEdit(modifier) {
    selectedModifierId.value = modifier.id;
    openModifierModal(modifier);
}

async function deleteModifier(modifierId) {
    if (!confirm('Удалить модификатор? Он будет отвязан от всех блюд.')) return;

    try {
        await store.api(`/backoffice/modifiers/${modifierId}`, { method: 'DELETE' });
        store.showToast('Модификатор удалён', 'success');
        loadGlobalModifiers();
        selectedModifierId.value = null;
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// Variant methods
function toggleParentDish(dishId) {
    if (expandedParentDishes.value.has(dishId)) {
        expandedParentDishes.value.delete(dishId);
    } else {
        expandedParentDishes.value.add(dishId);
    }
}

function getMinVariantPrice(dish) {
    if (!dish.variants?.length) return 0;
    return Math.min(...dish.variants.map(v => v.price || 0));
}

function openVariantModal(parent, variant = null) {
    editingVariantParent.value = parent;
    if (variant) {
        variantForm.value = { ...variant };
    } else {
        variantForm.value = createEmptyVariant();
        variantForm.value.variant_sort = (parent.variants?.length || 0);
    }
    showVariantModal.value = true;
}

function closeVariantModal() {
    showVariantModal.value = false;
    editingVariantParent.value = null;
    variantForm.value = createEmptyVariant();
    editingApiId.value = false;
}

async function saveVariant() {
    if (!variantForm.value.variant_name || !variantForm.value.price) return;

    const parent = editingVariantParent.value;
    if (!parent) return;

    try {
        const payload = {
            ...variantForm.value,
            name: parent.name, // Inherit name from parent
            product_type: 'variant',
            parent_id: parent.id,
            category_id: parent.category_id,
            kitchen_station_id: parent.kitchen_station_id
            // restaurant_id определяется на бэкенде из авторизации
        };

        let savedVariant;
        if (variantForm.value.id) {
            const res = await store.api(`/backoffice/menu/dishes/${variantForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
            savedVariant = res.data || res;

            // Update in local array
            const idx = parent.variants?.findIndex(v => v.id === savedVariant.id);
            if (idx >= 0 && parent.variants) {
                parent.variants[idx] = savedVariant;
            }
        } else {
            const res = await store.api('/backoffice/menu/dishes', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            savedVariant = res.data || res;

            // Add to local array
            if (!parent.variants) parent.variants = [];
            parent.variants.push(savedVariant);
        }

        // Update dishForm if we're editing the parent in panel
        if (dishForm.value.id === parent.id) {
            dishForm.value.variants = parent.variants;
        }

        // Reload dishes to get updated data
        loadDishes();
        closeVariantModal();
        store.showToast('Вариант сохранён', 'success');
    } catch (e) {
        console.error('Failed to save variant:', e);
        store.showToast('Ошибка сохранения', 'error');
    }
}

async function deleteVariant(variant) {
    if (!confirm(`Удалить вариант "${variant.variant_name}"?`)) return;

    try {
        await store.api(`/backoffice/menu/dishes/${variant.id}`, { method: 'DELETE' });

        // Remove from local arrays
        const parent = editingVariantParent.value || dishes.value.find(d => d.id === variant.parent_id);
        if (parent?.variants) {
            const idx = parent.variants.findIndex(v => v.id === variant.id);
            if (idx >= 0) parent.variants.splice(idx, 1);
        }

        // Update dishForm if editing parent
        if (dishForm.value.id === parent?.id) {
            dishForm.value.variants = parent.variants;
        }

        loadDishes();
        store.showToast('Вариант удалён', 'success');
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
}

// Context menu methods
function showCategoryContextMenu(event, category) {
    event.preventDefault();
    contextMenu.value = {
        show: true,
        x: event.clientX,
        y: event.clientY,
        category: category
    };
}

function closeCategoryContextMenu() {
    contextMenu.value.show = false;
    contextMenu.value.category = null;
}

async function deleteCategory() {
    const category = contextMenu.value.category;
    if (!category) return;

    const dishCount = getCategoryDishCount(category.id);
    if (dishCount > 0) {
        if (!confirm(`В категории "${category.name}" есть ${dishCount} блюд. Удалить категорию? Блюда останутся без категории.`)) {
            closeCategoryContextMenu();
            return;
        }
    } else {
        if (!confirm(`Удалить категорию "${category.name}"?`)) {
            closeCategoryContextMenu();
            return;
        }
    }

    try {
        await store.api(`/backoffice/menu/categories/${category.id}`, { method: 'DELETE' });
        store.showToast('Категория удалена', 'success');
        loadCategories();
        loadDishes();
        if (selectedCategoryId.value === category.id) {
            selectedCategoryId.value = null;
        }
    } catch (e) {
        store.showToast('Ошибка удаления', 'error');
    }
    closeCategoryContextMenu();
}

// Category methods
function openCategoryModal(category = null) {
    closeCategoryContextMenu();
    categoryForm.value = category
        ? { ...category }
        : { id: null, name: '', parent_id: null, legal_entity_id: null };
    showCategoryModal.value = true;
}

async function saveCategory() {
    if (!categoryForm.value.name) return;

    try {
        if (categoryForm.value.id) {
            await store.api(`/backoffice/menu/categories/${categoryForm.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(categoryForm.value)
            });
        } else {
            await store.api('/backoffice/menu/categories', {
                method: 'POST',
                body: JSON.stringify(categoryForm.value)
            });
        }
        store.showToast('Категория сохранена', 'success');
        loadCategories();
        showCategoryModal.value = false;
    } catch (e) {
        store.showToast('Ошибка сохранения', 'error');
    }
}

// Data loading
async function loadCategories() {
    try {
        const res = await store.api('/backoffice/menu/categories');
        categories.value = res.data || res || [];
        // Expand all root categories by default
        categories.value.filter(c => !c.parent_id).forEach(c => expandedCategories.value.add(c.id));
    } catch (e) {
        console.error('Failed to load categories:', e);
    }
}

async function loadDishes() {
    try {
        const res = await store.api('/backoffice/menu/dishes');
        dishes.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load dishes:', e);
    }
}

async function loadIngredients() {
    try {
        const res = await store.api('/backoffice/inventory/ingredients');
        ingredients.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load ingredients:', e);
    }
}

async function loadKitchenStations() {
    try {
        const res = await store.api('/kitchen-stations/active');
        kitchenStations.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load kitchen stations:', e);
    }
}

async function loadLegalEntities() {
    try {
        const res = await store.api('/legal-entities');
        legalEntities.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to load legal entities:', e);
    }
}

// Init
onMounted(() => {
    loadCategories();
    loadDishes();
    loadIngredients();
    loadKitchenStations();
    loadGlobalModifiers();
    loadLegalEntities();
});
</script>

<style scoped>
.border-l-3 {
    border-left-width: 3px;
}
</style>

<style>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
