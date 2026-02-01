<template>
  <div class="h-screen flex flex-col bg-gray-900 text-white select-none">
    <!-- Шапка -->
    <header class="flex items-center justify-between px-6 py-3 bg-gray-800 border-b border-gray-700 shrink-0">
      <div class="flex items-center gap-4">
        <div class="text-2xl font-bold tracking-wide">
          <span class="text-orange-400">Pos</span><span class="text-white">Resto</span>
        </div>
        <div class="h-6 w-px bg-gray-600"></div>
        <h1 class="text-lg font-semibold text-gray-200">Табло заказов</h1>
      </div>

      <div class="flex items-center gap-6">
        <!-- Счётчики -->
        <div class="flex items-center gap-4 text-sm">
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-orange-400"></span>
            <span class="text-gray-300">Готовится:</span>
            <span class="font-bold text-orange-400">{{ cookingOrders.length }}</span>
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
            <span class="text-gray-300">Готово:</span>
            <span class="font-bold text-emerald-400">{{ readyOrders.length }}</span>
          </span>
        </div>

        <!-- Часы -->
        <div class="text-xl font-mono text-gray-300 tabular-nums">
          {{ currentTime }}
        </div>

        <!-- Звук -->
        <button
          @click="toggleSound"
          class="p-2 rounded-lg hover:bg-gray-700 transition-colors"
          :title="soundEnabled ? 'Выключить звук' : 'Включить звук'"
        >
          <svg v-if="soundEnabled" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072M17.95 6.05a8 8 0 010 11.9M11 5L6 9H2v6h4l5 4V5z" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707A1 1 0 0112 5v14a1 1 0 01-1.707.707L5.586 15z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
          </svg>
        </button>

        <!-- Полный экран -->
        <button
          @click="toggleFullscreen"
          class="p-2 rounded-lg hover:bg-gray-700 transition-colors"
          title="Полный экран"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5" />
          </svg>
        </button>
      </div>
    </header>

    <!-- Основная область — две колонки -->
    <main class="flex-1 grid grid-cols-2 gap-0 overflow-hidden">
      <!-- Колонка ГОТОВИТСЯ -->
      <div class="flex flex-col border-r border-gray-700">
        <div class="px-6 py-3 bg-orange-500/10 border-b border-gray-700">
          <h2 class="text-xl font-bold text-orange-400 uppercase tracking-wider text-center">
            Готовится
          </h2>
        </div>
        <div class="flex-1 overflow-hidden p-4">
          <div
            v-if="cookingOrders.length === 0"
            class="h-full flex flex-col items-center justify-center text-gray-600"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-lg">Нет заказов в готовке</p>
          </div>
          <TransitionGroup
            v-else
            name="order"
            tag="div"
            :class="cookingGridClass"
          >
            <div
              v-for="order in cookingOrders"
              :key="order.id"
              class="bg-gray-800 border-2 border-orange-500/40 rounded-2xl p-4 flex flex-col items-center justify-center gap-2"
            >
              <span class="text-2xl">{{ orderTypeIcon(order.type) }}</span>
              <span class="text-6xl font-black text-orange-400 tabular-nums leading-none">
                {{ shortNumber(order.daily_number) }}
              </span>
              <span class="text-xs text-gray-500 uppercase">{{ orderTypeLabel(order.type) }}</span>
            </div>
          </TransitionGroup>
        </div>
      </div>

      <!-- Колонка ГОТОВО -->
      <div class="flex flex-col">
        <div class="px-6 py-3 bg-emerald-500/10 border-b border-gray-700">
          <h2 class="text-xl font-bold text-emerald-400 uppercase tracking-wider text-center">
            Готово
          </h2>
        </div>
        <div class="flex-1 overflow-hidden p-4">
          <div
            v-if="readyOrders.length === 0"
            class="h-full flex flex-col items-center justify-center text-gray-600"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-lg">Готовые заказы появятся здесь</p>
          </div>
          <TransitionGroup
            v-else
            name="order"
            tag="div"
            :class="readyGridClass"
          >
            <div
              v-for="order in readyOrders"
              :key="order.id"
              class="order-card-ready bg-gray-800 border-2 border-emerald-500/40 rounded-2xl p-4 flex flex-col items-center justify-center gap-2"
            >
              <span class="text-2xl">{{ orderTypeIcon(order.type) }}</span>
              <span class="text-6xl font-black text-emerald-400 tabular-nums leading-none">
                {{ shortNumber(order.daily_number) }}
              </span>
              <span class="text-xs text-gray-500 uppercase">{{ orderTypeLabel(order.type) }}</span>
            </div>
          </TransitionGroup>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

// ==================== Конфигурация ====================

const API_BASE = '/api';
const POLL_INTERVAL = 3000;
const READY_DISPLAY_MINUTES = 5;

// ==================== Состояние ====================

const orders = ref([]);
const soundEnabled = ref(false);
const currentTime = ref('');
const audioCtx = ref(null);

// Трекинг времени появления "ready" заказов для автоочистки
const readyTimestamps = ref({});

// Предыдущие ID заказов со статусом "cooking" — для детекции перехода в "ready"
const prevCookingIds = ref(new Set());

let pollTimer = null;
let clockTimer = null;

// ==================== Параметры из URL ====================

const urlParams = new URLSearchParams(window.location.search);
const restaurantId = urlParams.get('restaurant_id') || 1;

// ==================== Computed ====================

const cookingOrders = computed(() =>
  orders.value.filter(o => o.status === 'cooking')
);

const readyOrders = computed(() => {
  const now = Date.now();
  return orders.value.filter(o => {
    if (o.status !== 'ready') return false;
    const ts = readyTimestamps.value[o.id];
    if (ts && (now - ts) > READY_DISPLAY_MINUTES * 60 * 1000) return false;
    return true;
  });
});

const cookingGridClass = computed(() => gridClass(cookingOrders.value.length));
const readyGridClass = computed(() => gridClass(readyOrders.value.length));

// ==================== Методы ====================

function gridClass(count) {
  if (count <= 4) return 'grid grid-cols-2 gap-4 auto-rows-min';
  if (count <= 9) return 'grid grid-cols-3 gap-3 auto-rows-min';
  return 'grid grid-cols-4 gap-2 auto-rows-min';
}

function shortNumber(dailyNumber) {
  if (!dailyNumber) return '???';
  const parts = dailyNumber.replace('#', '').split('-');
  return parts.length > 1 ? parts[parts.length - 1] : parts[0];
}

function orderTypeIcon(type) {
  const icons = {
    dine_in: '\u{1F37D}\uFE0F',  // 🍽️
    pickup: '\u{1F3C3}',         // 🏃
    delivery: '\u{1F6F5}',       // 🛵
    aggregator: '\u{1F4F1}',     // 📱
  };
  return icons[type] || '\u{1F4CB}'; // 📋
}

function orderTypeLabel(type) {
  const labels = {
    dine_in: 'В зале',
    pickup: 'Самовывоз',
    delivery: 'Доставка',
    aggregator: 'Агрегатор',
  };
  return labels[type] || type;
}

// ==================== Polling ====================

async function fetchOrders() {
  try {
    const res = await fetch(`${API_BASE}/order-board?restaurant_id=${restaurantId}`);
    if (!res.ok) return;
    const json = await res.json();
    if (!json.success) return;

    const newOrders = json.data;

    // Детекция новых "ready" заказов (были cooking — стали ready)
    const newReadyIds = [];
    for (const o of newOrders) {
      if (o.status === 'ready' && prevCookingIds.value.has(o.id)) {
        newReadyIds.push(o.id);
      }
    }

    // Обновить prevCookingIds
    prevCookingIds.value = new Set(
      newOrders.filter(o => o.status === 'cooking').map(o => o.id)
    );

    // Трекинг readyTimestamps
    const now = Date.now();
    for (const o of newOrders) {
      if (o.status === 'ready' && !readyTimestamps.value[o.id]) {
        readyTimestamps.value[o.id] = now;
      }
    }
    // Очистка timestamps для заказов, которых больше нет
    const currentIds = new Set(newOrders.map(o => o.id));
    for (const id of Object.keys(readyTimestamps.value)) {
      if (!currentIds.has(Number(id))) {
        delete readyTimestamps.value[id];
      }
    }

    orders.value = newOrders;

    // Играть звук для новых ready заказов
    if (newReadyIds.length > 0 && soundEnabled.value) {
      playChime();
    }
  } catch (e) {
    // Тихо игнорируем ошибки сети — табло продолжает работать
  }
}

function startPolling() {
  fetchOrders();
  pollTimer = setInterval(() => {
    if (!document.hidden) {
      fetchOrders();
    }
  }, POLL_INTERVAL);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

// Обновление при возврате видимости вкладки
function handleVisibilityChange() {
  if (!document.hidden) {
    fetchOrders();
  }
}

// ==================== Часы ====================

function updateClock() {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

// ==================== Звук (Web Audio API) ====================

function initAudio() {
  if (audioCtx.value) return;
  audioCtx.value = new (window.AudioContext || window.webkitAudioContext)();
}

function playChime() {
  if (!audioCtx.value) return;
  const ctx = audioCtx.value;
  const now = ctx.currentTime;

  // Мажорный аккорд C6-E6-G6
  const frequencies = [1046.5, 1318.5, 1568.0];
  const duration = 0.3;

  frequencies.forEach((freq, i) => {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.15, now + i * 0.1);
    gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.1 + duration);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(now + i * 0.1);
    osc.stop(now + i * 0.1 + duration);
  });
}

function toggleSound() {
  if (!audioCtx.value) {
    initAudio();
  }
  soundEnabled.value = !soundEnabled.value;
  // Воспроизводим тестовый звук при включении
  if (soundEnabled.value) {
    playChime();
  }
}

// ==================== Полный экран ====================

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(() => {});
  } else {
    document.exitFullscreen().catch(() => {});
  }
}

// ==================== Жизненный цикл ====================

onMounted(() => {
  updateClock();
  clockTimer = setInterval(updateClock, 1000);
  startPolling();
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

onUnmounted(() => {
  stopPolling();
  if (clockTimer) clearInterval(clockTimer);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>
