<template>
    <Transition name="slide-down">
        <div
            v-if="warning"
            :class="[
                'session-warning',
                { 'session-warning--critical': warning.critical }
            ]"
        >
            <div class="session-warning__content">
                <div class="session-warning__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            fill-rule="evenodd"
                            d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
                <div class="session-warning__text">
                    <span class="session-warning__message">{{ warning.message }}</span>
                </div>
                <div class="session-warning__actions">
                    <button
                        class="session-warning__btn session-warning__btn--extend"
                        @click="handleExtend"
                    >
                        Продлить сессию
                    </button>
                    <button
                        class="session-warning__btn session-warning__btn--dismiss"
                        @click="handleDismiss"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();

const warning = computed(() => authStore.expirationWarning);

function handleExtend() {
    authStore.extendSession();
}

function handleDismiss() {
    authStore.dismissExpirationWarning();
}
</script>

<style scoped>
.session-warning {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 12px 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.session-warning--critical {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    animation: pulse-bg 2s ease-in-out infinite;
}

@keyframes pulse-bg {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

.session-warning__content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    max-width: 1200px;
    margin: 0 auto;
}

.session-warning__icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
}

.session-warning__icon svg {
    width: 100%;
    height: 100%;
}

.session-warning__text {
    flex: 1;
    text-align: center;
}

.session-warning__message {
    font-size: 14px;
    font-weight: 500;
}

.session-warning__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.session-warning__btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.session-warning__btn--extend {
    background: white;
    color: #d97706;
}

.session-warning--critical .session-warning__btn--extend {
    color: #dc2626;
}

.session-warning__btn--extend:hover {
    background: #f3f4f6;
    transform: scale(1.02);
}

.session-warning__btn--dismiss {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 6px;
}

.session-warning__btn--dismiss:hover {
    background: rgba(255, 255, 255, 0.3);
}

.session-warning__btn--dismiss svg {
    width: 20px;
    height: 20px;
}

/* Transition */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    transform: translateY(-100%);
    opacity: 0;
}
</style>
