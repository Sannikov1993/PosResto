<template>
  <div class="h-full flex flex-col items-center justify-center p-6 bg-dark-900">
    <div class="text-center mb-8">
      <img src="/images/logo/menulab_logo_dark_bg.svg" alt="MenuLab" class="h-16 mx-auto mb-4" />
      <p class="text-gray-500 text-lg">Официант</p>
    </div>

    <!-- PIN Mode -->
    <template v-if="mode === 'pin'">
      <PinPad
        v-model="pin"
        :error="error"
        :disabled="isLoading"
        @complete="handlePinComplete"
      />

      <button
        @click="mode = 'password'"
        class="mt-4 text-orange-500 text-sm"
      >
        Войти по логину и паролю
      </button>
    </template>

    <!-- Password Mode -->
    <template v-else>
      <form @submit.prevent="handlePasswordLogin" class="w-full max-w-sm space-y-4">
        <div>
          <input
            v-model="form.email"
            type="text"
            placeholder="Логин"
            required
            :disabled="isLoading"
            class="w-full px-4 py-3 rounded-xl bg-dark-800 border border-gray-700 focus:border-orange-500 focus:outline-none disabled:opacity-50"
          />
        </div>

        <div>
          <input
            v-model="form.password"
            type="password"
            placeholder="Пароль"
            required
            :disabled="isLoading"
            class="w-full px-4 py-3 rounded-xl bg-dark-800 border border-gray-700 focus:border-orange-500 focus:outline-none disabled:opacity-50"
          />
        </div>

        <div class="flex items-center">
          <input
            v-model="form.rememberDevice"
            type="checkbox"
            id="remember"
            class="mr-2"
          />
          <label for="remember" class="text-sm text-gray-400">
            Запомнить это устройство
          </label>
        </div>

        <p v-if="error" class="text-red-500 text-center text-sm">{{ error }}</p>

        <button
          type="submit"
          :disabled="isLoading"
          class="w-full py-3 rounded-xl bg-orange-500 font-semibold hover:bg-orange-600 transition disabled:opacity-50"
        >
          {{ isLoading ? 'Вход...' : 'Войти' }}
        </button>
      </form>

      <button
        v-if="hasDeviceToken"
        @click="mode = 'pin'"
        class="mt-4 text-orange-500 text-sm"
      >
        ← Назад к PIN
      </button>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useAuth } from '@/waiter/composables';
import PinPad from './PinPad.vue';

type LoginMode = 'pin' | 'password';

const { loginWithPin, loginWithEmail, isLoading, error: authError } = useAuth();

const hasDeviceToken = ref(false);
const mode = ref<LoginMode>('password');
const pin = ref('');
const error = ref('');

const form = reactive({
  email: '',
  password: '',
  rememberDevice: true,
});

onMounted(() => {
  hasDeviceToken.value = !!localStorage.getItem('device_token');
  mode.value = hasDeviceToken.value ? 'pin' : 'password';
});

async function handlePinComplete(pinValue: string): Promise<void> {
  error.value = '';
  const success = await loginWithPin(pinValue);

  if (!success) {
    error.value = authError.value || 'Неверный PIN-код';
    pin.value = '';
  }
}

async function handlePasswordLogin(): Promise<void> {
  error.value = '';
  const success = await loginWithEmail(form.email, form.password);

  if (!success) {
    error.value = authError.value || 'Неверный логин или пароль';
  }
}
</script>
