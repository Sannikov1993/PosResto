<template>
    <div class="flex-1 flex overflow-hidden bg-dark-900">
        <!-- Categories Sidebar (vertical colored strips) -->
        <div class="w-20 bg-dark-950 border-r border-dark-800 overflow-y-auto pb-2 flex-shrink-0">
            <button
                @click="$emit('update:selectedCategory', null)"
                :class="[
                    'w-full px-1 py-3 text-xs font-medium transition-all relative',
                    selectedCategory === null
                        ? 'bg-dark-800 text-white'
                        : 'text-gray-400 hover:text-white hover:bg-dark-800/50'
                ]"
            >
                <div
                    v-if="selectedCategory === null"
                    class="absolute left-0 top-0 bottom-0 w-1 bg-accent"
                ></div>
                <span class="block truncate px-1">Все</span>
            </button>
            <button
                v-for="(category, idx) in categories"
                :key="category.id"
                @click="$emit('update:selectedCategory', category.id)"
                :class="[
                    'w-full px-1 py-3 text-xs font-medium transition-all relative',
                    selectedCategory === category.id
                        ? 'bg-dark-800 text-white'
                        : 'text-gray-400 hover:text-white hover:bg-dark-800/50'
                ]"
            >
                <div
                    class="absolute left-0 top-0 bottom-0 w-1"
                    :style="{ backgroundColor: getCategoryColor(idx) }"
                ></div>
                <span class="block truncate px-1">{{ category.name }}</span>
            </button>
        </div>

        <!-- Dishes Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Search -->
            <div class="px-4 py-3 border-b border-dark-700 flex-shrink-0">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        :value="searchQuery"
                        @input="$emit('update:searchQuery', $event.target.value)"
                        type="text"
                        placeholder="Поиск блюда..."
                        class="w-full bg-dark-800 border-0 rounded-lg pl-9 pr-4 py-2 text-white text-sm placeholder-gray-500 focus:ring-2 focus:ring-accent focus:outline-none"
                    />
                </div>
            </div>

            <!-- Products Grid -->
            <div class="flex-1 overflow-y-auto p-3">
                <!-- Grid View -->
                <div v-if="viewMode === 'grid'" class="grid grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        :class="[
                            'group bg-dark-800 rounded-lg overflow-hidden transition-all relative',
                            !product.is_available ? 'opacity-50 cursor-not-allowed' : '',
                            product.is_available && product.product_type !== 'parent' ? 'hover:scale-[1.03] hover:shadow-xl hover:shadow-black/50 hover:z-10' : ''
                        ]"
                    >
                        <!-- Category color strip -->
                        <div
                            class="absolute top-0 left-0 right-0 h-1"
                            :style="{ backgroundColor: getProductCategoryColor(product) }"
                        ></div>

                        <!-- Image - clickable for simple dishes -->
                        <div
                            @click="product.is_available && product.product_type !== 'parent' && handleProductClick(product)"
                            :class="[
                                'aspect-[4/3] bg-dark-700 relative overflow-hidden',
                                product.is_available && product.product_type !== 'parent' ? 'cursor-pointer' : ''
                            ]"
                        >
                            <img
                                v-if="product.image"
                                :src="product.image"
                                :alt="product.name"
                                class="w-full h-full object-cover transition-transform"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-3xl text-gray-700">
                                🍽️
                            </div>

                            <!-- Stop overlay -->
                            <div v-if="!product.is_available" class="absolute inset-0 bg-black/70 flex items-center justify-center">
                                <span class="bg-red-600 text-white text-sm font-bold px-4 py-1.5 rounded-lg transform -rotate-12">СТОП</span>
                            </div>

                            <!-- Info button -->
                            <button
                                v-if="product.is_available"
                                @click.stop="openDishDetail(product)"
                                class="absolute top-2 left-2 w-6 h-6 bg-black/50 hover:bg-black/70 backdrop-blur-sm rounded-full text-white text-xs font-bold flex items-center justify-center transition-all opacity-0 group-hover:opacity-100"
                                title="Подробнее"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        </div>

                        <!-- Info -->
                        <div class="p-2">
                            <h3 class="text-xs font-medium text-white mb-1 line-clamp-1 leading-tight">{{ product.name }}</h3>

                            <!-- For parent dishes: size buttons (Glass style) -->
                            <div v-if="product.product_type === 'parent' && product.variants?.length" class="flex gap-1 mt-1.5">
                                <button
                                    v-for="variant in product.variants"
                                    :key="variant.id"
                                    @click.stop="product.is_available && variant.is_available !== false && handleVariantClick(product, variant)"
                                    :disabled="!product.is_available || variant.is_available === false"
                                    :class="[
                                        'flex-1 py-2 px-2 rounded-md text-center transition-all min-w-0 border',
                                        !product.is_available || variant.is_available === false
                                            ? 'bg-[#1a1f2e] border-gray-800 text-gray-600 cursor-not-allowed'
                                            : 'bg-white/5 backdrop-blur-sm border-white/10 hover:bg-white/10 hover:border-white/20 text-white cursor-pointer active:scale-[0.97]'
                                    ]"
                                >
                                    <div class="text-xs text-gray-400 truncate">{{ variant.variant_name }}</div>
                                    <div class="text-sm font-semibold text-white">{{ formatPrice(variant.price) }} ₽</div>
                                </button>
                            </div>

                            <!-- For simple dishes: price -->
                            <div
                                v-else
                                @click="product.is_available && handleProductClick(product)"
                                class="cursor-pointer"
                            >
                                <span :class="['text-sm font-bold', !product.is_available ? 'text-gray-500 line-through' : 'text-white']">
                                    {{ formatPrice(product.price) }} <span class="text-xs text-gray-500">₽</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="product.is_available && product.product_type !== 'parent' && handleProductClick(product)"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-2 transition-all relative overflow-hidden',
                            product.is_available && product.product_type !== 'parent'
                                ? 'bg-dark-800 cursor-pointer hover:bg-dark-700'
                                : 'bg-dark-800/50 opacity-50 cursor-not-allowed'
                        ]"
                    >
                        <!-- Category color strip -->
                        <div
                            class="absolute left-0 top-0 bottom-0 w-1"
                            :style="{ backgroundColor: getProductCategoryColor(product) }"
                        ></div>

                        <!-- Icon/Image -->
                        <div class="w-10 h-10 rounded-lg flex-shrink-0 flex items-center justify-center bg-dark-700 ml-1">
                            <img
                                v-if="product.image"
                                :src="product.image"
                                :alt="product.name"
                                class="w-full h-full object-cover rounded-lg"
                            />
                            <span v-else class="text-xl" :class="{ grayscale: !product.is_available }">{{ product.icon || '🍽️' }}</span>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-white truncate">{{ product.name }}</h4>
                            <p v-if="product.description" class="text-xs text-gray-500 truncate">{{ product.description }}</p>
                        </div>

                        <!-- Price & Status -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span v-if="!product.is_available" class="bg-red-500 text-white text-xs px-2 py-0.5 rounded">СТОП</span>
                            <span class="font-bold text-sm" :class="product.is_available ? 'text-accent' : 'text-gray-500'">
                                {{ product.product_type === 'parent' ? 'от ' : '' }}{{ formatPrice(product.product_type === 'parent' ? product.variants?.[0]?.price : product.price) }} ₽
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="filteredProducts.length === 0" class="text-center py-12 text-gray-500">
                    <p class="text-4xl mb-2">🔍</p>
                    <p>Ничего не найдено</p>
                </div>
            </div>
        </div>

        <!-- Modifier Panel (slide from right) -->
        <Teleport to="body">
            <Transition name="slide-panel">
                <div
                    v-if="showModifierPanel"
                    class="fixed top-0 right-0 h-full w-[380px] bg-[#1a1f2e] shadow-2xl z-[60] flex flex-col"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800 bg-[#151923]">
                        <h3 class="text-base font-semibold text-white">{{ editingOrderItem ? 'Модификаторы' : 'Настроить' }}</h3>
                        <button
                            @click="closeModifierPanel"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-700 text-gray-400 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Dish info compact -->
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-800">
                        <div class="w-12 h-12 rounded-lg bg-[#252a3a] overflow-hidden flex-shrink-0">
                            <img v-if="modifierDish?.image" :src="modifierDish.image" :alt="modifierDish?.name" class="w-full h-full object-cover"/>
                            <div v-else class="w-full h-full flex items-center justify-center text-xl text-gray-600">🍽️</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-white truncate">
                                {{ editingOrderItem ? (editingOrderItem.name || editingOrderItem.dish?.name) : modifierDish?.name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ formatPrice(editingOrderItem?.price || selectedVariant?.price || modifierDish?.price || 0) }} ₽
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto">
                        <!-- Variants selection (only when adding new, not when editing) -->
                        <div v-if="!editingOrderItem && modifierDish?.product_type === 'parent' && modifierDish?.variants?.length"
                             class="px-4 py-3 border-b border-gray-800">
                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Размер</div>
                            <div class="flex gap-2">
                                <button
                                    v-for="variant in modifierDish.variants"
                                    :key="variant.id"
                                    @click="variant.is_available !== false && (selectedVariant = variant)"
                                    :disabled="variant.is_available === false"
                                    :class="[
                                        'flex-1 px-3 py-2 rounded-lg text-sm transition-all',
                                        variant.is_available === false
                                            ? 'bg-[#252a3a]/50 text-gray-600 cursor-not-allowed'
                                            : selectedVariant?.id === variant.id
                                                ? 'bg-blue-500 text-white'
                                                : 'bg-[#252a3a] text-white hover:bg-[#2d3348]'
                                    ]"
                                >
                                    <div class="font-medium">{{ variant.variant_name }}</div>
                                    <div class="text-xs mt-0.5" :class="selectedVariant?.id === variant.id ? 'text-blue-200' : 'text-gray-400'">
                                        {{ formatPrice(variant.price) }} ₽
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Modifiers list -->
                        <div v-if="modifierDish?.modifiers?.length">
                            <div v-for="modifier in modifierDish.modifiers" :key="modifier.id" class="border-b border-gray-800 last:border-b-0">
                                <!-- Modifier header -->
                                <div class="flex items-center justify-between px-4 py-2 bg-[#151923]">
                                    <span class="text-xs text-gray-500 uppercase tracking-wide">{{ modifier.name }}</span>
                                    <span v-if="modifier.is_required" class="text-[10px] text-orange-400">обязательно</span>
                                    <span v-else-if="modifier.type === 'multiple'" class="text-[10px] text-gray-600">
                                        {{ modifier.max_selections ? `макс. ${modifier.max_selections}` : '' }}
                                    </span>
                                </div>

                                <!-- Options -->
                                <div class="px-2 py-1">
                                    <button
                                        v-for="option in modifier.options"
                                        :key="option.id"
                                        @click="toggleModifierOption(modifier, option.id)"
                                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors hover:bg-[#252a3a]"
                                    >
                                        <!-- Checkbox/Radio -->
                                        <div :class="[
                                            'w-5 h-5 flex items-center justify-center flex-shrink-0 transition-colors',
                                            modifier.type === 'single' ? 'rounded-full' : 'rounded',
                                            isModifierSelected(modifier.id, option.id)
                                                ? 'bg-blue-500 border-blue-500'
                                                : 'border-2 border-gray-600'
                                        ]">
                                            <svg v-if="isModifierSelected(modifier.id, option.id)"
                                                 class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <!-- Name -->
                                        <span class="flex-1 text-left text-white text-sm">{{ option.name }}</span>
                                        <!-- Price -->
                                        <span v-if="option.price > 0" class="text-sm text-gray-400">+{{ formatPrice(option.price) }} ₽</span>
                                        <span v-else class="text-sm text-gray-600">0 ₽</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!modifierDish?.modifiers?.length && !(modifierDish?.product_type === 'parent')"
                             class="flex flex-col items-center justify-center py-12 text-gray-500">
                            <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span class="text-sm">Нет доступных опций</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-3 border-t border-gray-800 bg-[#151923]">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm text-gray-400">Итого</span>
                            <span class="text-lg font-semibold text-white">{{ formatPrice(totalWithModifiers) }} ₽</span>
                        </div>
                        <button
                            @click="confirmModifiers"
                            class="w-full py-2.5 bg-blue-500 hover:bg-blue-600 rounded-lg text-white font-medium transition-colors"
                        >
                            {{ editingOrderItem ? 'Сохранить' : 'Добавить' }}
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- Backdrop -->
            <Transition name="fade">
                <div
                    v-if="showModifierPanel"
                    class="fixed inset-0 bg-black/60 z-[59]"
                    @click="closeModifierPanel"
                ></div>
            </Transition>
        </Teleport>

        <!-- Dish Detail Panel -->
        <Teleport to="body">
            <!-- Backdrop -->
            <Transition name="fade">
                <div
                    v-if="showDetailPanel"
                    class="fixed inset-0 bg-black/60 z-[70]"
                    @click="closeDishDetail"
                ></div>
            </Transition>

            <!-- Panel -->
            <Transition name="slide-panel">
                <div
                    v-if="showDetailPanel"
                    class="fixed top-0 right-0 h-full w-[420px] bg-[#1a1f2e] shadow-2xl z-[71] flex flex-col"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800 bg-[#151923]">
                        <h3 class="text-base font-semibold text-white">Информация о товаре</h3>
                        <button
                            @click="closeDishDetail"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-700 text-gray-400 hover:text-white transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div v-if="detailDish" class="flex-1 overflow-y-auto">
                        <!-- Image -->
                        <div class="aspect-video bg-dark-800 relative">
                            <img
                                v-if="detailDish.image"
                                :src="detailDish.image"
                                :alt="detailDish.name"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-6xl text-gray-700">
                                🍽️
                            </div>
                            <!-- Tags -->
                            <div v-if="detailDish.is_popular || detailDish.is_new || detailDish.is_spicy || detailDish.is_vegetarian" class="absolute bottom-3 left-3 flex gap-1.5">
                                <span v-if="detailDish.is_popular" class="px-2 py-0.5 bg-red-500 text-white text-xs font-medium rounded">Хит</span>
                                <span v-if="detailDish.is_new" class="px-2 py-0.5 bg-green-500 text-white text-xs font-medium rounded">Новинка</span>
                                <span v-if="detailDish.is_spicy" class="px-2 py-0.5 bg-orange-500 text-white text-xs font-medium rounded">🌶️ Острое</span>
                                <span v-if="detailDish.is_vegetarian" class="px-2 py-0.5 bg-emerald-500 text-white text-xs font-medium rounded">🌱 Вегетарианское</span>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="p-4">
                            <!-- Name & Category -->
                            <h2 class="text-xl font-bold text-white mb-1">{{ detailDish.name }}</h2>
                            <p v-if="detailDish.category?.name" class="text-sm text-gray-500 mb-3">{{ detailDish.category.name }}</p>

                            <!-- Price -->
                            <div class="flex items-baseline gap-2 mb-4">
                                <span v-if="detailDish.product_type === 'parent'" class="text-2xl font-bold text-white">
                                    от {{ formatPrice(detailDish.variants?.[0]?.price || 0) }} ₽
                                </span>
                                <span v-else class="text-2xl font-bold text-white">{{ formatPrice(detailDish.price) }} ₽</span>
                                <span v-if="detailDish.old_price" class="text-lg text-gray-500 line-through">{{ formatPrice(detailDish.old_price) }} ₽</span>
                            </div>

                            <!-- Description -->
                            <div v-if="detailDish.description" class="mb-4">
                                <h4 class="text-xs text-gray-500 uppercase tracking-wide mb-1">Описание</h4>
                                <p class="text-sm text-gray-300 leading-relaxed">{{ detailDish.description }}</p>
                            </div>

                            <!-- Variants -->
                            <div v-if="detailDish.product_type === 'parent' && detailDish.variants?.length" class="mb-4">
                                <h4 class="text-xs text-gray-500 uppercase tracking-wide mb-2">Варианты</h4>
                                <div class="space-y-1.5">
                                    <div
                                        v-for="variant in detailDish.variants"
                                        :key="variant.id"
                                        class="flex items-center justify-between px-3 py-2 bg-[#252a3a] rounded-lg"
                                    >
                                        <span class="text-sm text-white">{{ variant.variant_name }}</span>
                                        <span class="text-sm font-semibold text-white">{{ formatPrice(variant.price) }} ₽</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Weight & Cooking time -->
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div v-if="detailDish.weight" class="px-3 py-2.5 bg-[#252a3a] rounded-lg">
                                    <div class="text-[10px] text-gray-500 uppercase tracking-wide">Вес</div>
                                    <div class="text-sm font-semibold text-white">{{ detailDish.weight }} г</div>
                                </div>
                                <div v-if="detailDish.cooking_time" class="px-3 py-2.5 bg-[#252a3a] rounded-lg">
                                    <div class="text-[10px] text-gray-500 uppercase tracking-wide">Время готовки</div>
                                    <div class="text-sm font-semibold text-white">{{ detailDish.cooking_time }} мин</div>
                                </div>
                            </div>

                            <!-- Nutrition (КБЖУ) -->
                            <div v-if="detailDish.calories || detailDish.proteins || detailDish.fats || detailDish.carbs" class="mb-4">
                                <h4 class="text-xs text-gray-500 uppercase tracking-wide mb-2">Пищевая ценность</h4>
                                <div class="grid grid-cols-4 gap-2">
                                    <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                        <div class="text-lg font-bold text-white">{{ detailDish.calories || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">ккал</div>
                                    </div>
                                    <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                        <div class="text-lg font-bold text-blue-400">{{ detailDish.proteins || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">белки</div>
                                    </div>
                                    <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                        <div class="text-lg font-bold text-yellow-400">{{ detailDish.fats || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">жиры</div>
                                    </div>
                                    <div class="px-2 py-2 bg-[#252a3a] rounded-lg text-center">
                                        <div class="text-lg font-bold text-green-400">{{ detailDish.carbs || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">углеводы</div>
                                    </div>
                                </div>
                            </div>

                            <!-- SKU -->
                            <div v-if="detailDish.sku" class="text-xs text-gray-600">
                                Артикул: {{ detailDish.sku }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 py-3 border-t border-gray-800 bg-[#151923]">
                        <button
                            @click="closeDishDetail"
                            class="w-full py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg text-white font-medium transition-colors"
                        >
                            Закрыть
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    categories: Array,
    selectedCategory: [Number, null],
    searchQuery: String,
    viewMode: { type: String, default: 'grid' },
    editingItem: { type: Object, default: null }
});

const emit = defineEmits(['update:selectedCategory', 'update:searchQuery', 'addItem', 'updateItemModifiers', 'clearEditingItem']);

// Modifier panel state
const showModifierPanel = ref(false);
const modifierDish = ref(null);
const selectedVariant = ref(null);
const selectedModifiers = ref({});
const editingOrderItem = ref(null);

// Dish detail panel state
const showDetailPanel = ref(false);
const detailDish = ref(null);

// Watch for editingItem changes to open modifier panel for existing order item
watch(() => props.editingItem, (newItem, oldItem) => {
    if (newItem && newItem.parentDish) {
        openModifierPanelForEdit(newItem);
    } else if (!newItem && oldItem) {
        // Reset editingOrderItem when prop is cleared
        editingOrderItem.value = null;
    }
}, { immediate: true });

// Category colors palette
const categoryColors = [
    '#3B82F6', // blue
    '#10B981', // green
    '#F59E0B', // amber
    '#EF4444', // red
    '#8B5CF6', // purple
    '#EC4899', // pink
    '#06B6D4', // cyan
    '#F97316', // orange
    '#6366F1', // indigo
    '#14B8A6', // teal
];

const getCategoryColor = (index) => {
    return categoryColors[index % categoryColors.length];
};

const getProductCategoryColor = (product) => {
    const idx = props.categories.findIndex(c => c.id === product.category_id);
    return idx >= 0 ? getCategoryColor(idx) : categoryColors[0];
};

const filteredProducts = computed(() => {
    let products = [];

    if (props.selectedCategory === null) {
        props.categories.forEach(cat => {
            products = products.concat(cat.products || []);
        });
    } else {
        const category = props.categories.find(c => c.id === props.selectedCategory);
        products = category?.products || [];
    }

    if (props.searchQuery) {
        const query = props.searchQuery.toLowerCase();
        products = products.filter(p => p.name.toLowerCase().includes(query));
    }

    return products;
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('ru-RU').format(price || 0);
};

// Handle click on simple product
const handleProductClick = (product) => {
    if (!product.is_available) return;

    // If product has modifiers, open panel
    if (product.modifiers?.length) {
        openModifierPanel(product);
        return;
    }

    // Otherwise emit directly
    emit('addItem', {
        dish: product,
        variant: null,
        modifiers: []
    });
};

// Handle click on variant button - always add directly
const handleVariantClick = (product, variant) => {
    if (!product.is_available || variant.is_available === false) return;

    // Клик на размер - сразу добавляем в заказ
    emit('addItem', {
        dish: product,
        variant: variant,
        modifiers: []
    });
};

// Open modifier panel
const openModifierPanel = (dish, variant = null) => {
    // Reset editing state - we're adding new, not editing
    editingOrderItem.value = null;

    modifierDish.value = dish;
    selectedVariant.value = variant;
    selectedModifiers.value = {};

    // If parent with variants and no variant selected, select first available
    if (dish.product_type === 'parent' && dish.variants?.length && !variant) {
        const firstAvailable = dish.variants.find(v => v.is_available !== false);
        if (firstAvailable) {
            selectedVariant.value = firstAvailable;
        }
    }

    // Set default modifiers
    dish.modifiers?.forEach(mod => {
        if (mod.type === 'single') {
            const defaultOpt = mod.options?.find(o => o.is_default);
            if (defaultOpt) {
                selectedModifiers.value[mod.id] = defaultOpt.id;
            }
        } else {
            selectedModifiers.value[mod.id] = [];
        }
    });

    showModifierPanel.value = true;
};

// Close modifier panel
const closeModifierPanel = () => {
    showModifierPanel.value = false;
    modifierDish.value = null;
    selectedVariant.value = null;
    selectedModifiers.value = {};
    editingOrderItem.value = null;
    emit('clearEditingItem');
};

// Open dish detail panel
const openDishDetail = (dish) => {
    // Find category name for the dish
    const category = props.categories.find(c => c.id === dish.category_id);
    detailDish.value = {
        ...dish,
        category: category ? { name: category.name } : null
    };
    showDetailPanel.value = true;
};

// Close dish detail panel
const closeDishDetail = () => {
    showDetailPanel.value = false;
    setTimeout(() => {
        detailDish.value = null;
    }, 300);
};

// Open modifier panel for editing existing order item
const openModifierPanelForEdit = (item) => {
    const dish = item.parentDish;
    editingOrderItem.value = item;
    modifierDish.value = dish;
    selectedVariant.value = null;
    selectedModifiers.value = {};

    // Pre-select existing modifiers
    dish.modifiers?.forEach(mod => {
        if (mod.type === 'single') {
            const selected = item.modifiers?.find(m =>
                m.modifier_id === mod.id || m.id === mod.id
            );
            if (selected) {
                selectedModifiers.value[mod.id] = selected.option_id || selected.id;
            }
        } else {
            selectedModifiers.value[mod.id] = item.modifiers
                ?.filter(m => m.modifier_id === mod.id)
                .map(m => m.option_id || m.id) || [];
        }
    });

    showModifierPanel.value = true;
};

// Toggle modifier option
const toggleModifierOption = (modifier, optionId) => {
    if (modifier.type === 'single') {
        // For single-type: allow deselect if not required
        if (selectedModifiers.value[modifier.id] === optionId && !modifier.is_required) {
            selectedModifiers.value[modifier.id] = null;
        } else {
            selectedModifiers.value[modifier.id] = optionId;
        }
    } else {
        // For multiple-type
        const current = selectedModifiers.value[modifier.id] || [];
        const index = current.indexOf(optionId);

        if (index > -1) {
            current.splice(index, 1);
        } else {
            if (!modifier.max_selections || current.length < modifier.max_selections) {
                current.push(optionId);
            }
        }
        selectedModifiers.value[modifier.id] = current;
    }
};

// Check if modifier option is selected
const isModifierSelected = (modifierId, optionId) => {
    const sel = selectedModifiers.value[modifierId];
    if (Array.isArray(sel)) {
        return sel.includes(optionId);
    }
    return sel === optionId;
};

// Calculate total price with modifiers
const totalWithModifiers = computed(() => {
    let total = selectedVariant.value?.price || modifierDish.value?.price || 0;

    modifierDish.value?.modifiers?.forEach(mod => {
        const sel = selectedModifiers.value[mod.id];
        if (Array.isArray(sel)) {
            sel.forEach(optId => {
                const opt = mod.options?.find(o => o.id === optId);
                if (opt) total += opt.price || 0;
            });
        } else if (sel) {
            const opt = mod.options?.find(o => o.id === sel);
            if (opt) total += opt.price || 0;
        }
    });

    return total;
});

// Confirm and add to cart or update existing item
const confirmModifiers = () => {
    if (!modifierDish.value) return;

    // Collect selected modifiers
    const modifiers = [];
    modifierDish.value.modifiers?.forEach(mod => {
        const sel = selectedModifiers.value[mod.id];
        if (Array.isArray(sel)) {
            sel.forEach(optId => {
                const opt = mod.options?.find(o => o.id === optId);
                if (opt) {
                    modifiers.push({
                        modifier_id: mod.id,
                        modifier_name: mod.name,
                        option_id: opt.id,
                        option_name: opt.name,
                        price: opt.price || 0
                    });
                }
            });
        } else if (sel) {
            const opt = mod.options?.find(o => o.id === sel);
            if (opt) {
                modifiers.push({
                    modifier_id: mod.id,
                    modifier_name: mod.name,
                    option_id: opt.id,
                    option_name: opt.name,
                    price: opt.price || 0
                });
            }
        }
    });

    // Check if we're editing existing item or adding new
    // editingOrderItem must have an id to be a real existing item
    if (editingOrderItem.value && editingOrderItem.value.id) {
        emit('updateItemModifiers', {
            item: editingOrderItem.value,
            modifiers: modifiers
        });
    } else {
        // Reset editingOrderItem just in case
        editingOrderItem.value = null;
        emit('addItem', {
            dish: modifierDish.value,
            variant: selectedVariant.value,
            modifiers: modifiers
        });
    }

    closeModifierPanel();
};
</script>

<style scoped>
/* Custom scrollbar for categories */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}

/* Slide panel animation */
.slide-panel-enter-active,
.slide-panel-leave-active {
    transition: transform 0.3s ease;
}

.slide-panel-enter-from,
.slide-panel-leave-to {
    transform: translateX(100%);
}

/* Fade animation */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
