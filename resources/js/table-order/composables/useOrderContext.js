/**
 * Order Context — provide/inject for Parent → GuestPanel → GuestSection communication
 *
 * Replaces 27 event pass-throughs and 6 drilled props with provide/inject.
 * Parent calls provideOrderActions() + provideOrderState().
 * Children call useOrderActions() + useOrderState().
 */
import { provide, inject } from 'vue';

const ORDER_ACTIONS_KEY = Symbol('OrderActions');
const ORDER_STATE_KEY = Symbol('OrderState');

/**
 * Provide order actions from parent component.
 * @param {Object} actions - object with action methods
 */
export function provideOrderActions(actions) {
    provide(ORDER_ACTIONS_KEY, actions);
}

/**
 * Provide shared display state (previously drilled through props).
 * @param {Object} state - { selectMode, selectModeGuest, selectedItems, guestColors, categories, roundAmounts }
 */
export function provideOrderState(state) {
    provide(ORDER_STATE_KEY, state);
}

/**
 * Inject order actions in child component.
 */
export function useOrderActions() {
    const actions = inject(ORDER_ACTIONS_KEY);
    if (!actions) {
        throw new Error('useOrderActions() requires a parent that calls provideOrderActions()');
    }
    return actions;
}

/**
 * Inject shared order state in child component.
 */
export function useOrderState() {
    const state = inject(ORDER_STATE_KEY);
    if (!state) {
        throw new Error('useOrderState() requires a parent that calls provideOrderState()');
    }
    return state;
}
