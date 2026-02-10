/**
 * Order Context — provide/inject for Parent → GuestPanel → GuestSection communication
 *
 * Replaces 27 event pass-throughs and 6 drilled props with provide/inject.
 * Parent calls provideOrderActions() + provideOrderState().
 * Children call useOrderActions() + useOrderState().
 */
import { provide, inject, type InjectionKey } from 'vue';

export interface OrderActions {
    [key: string]: (...args: unknown[]) => unknown;
}

export interface OrderState {
    [key: string]: unknown;
}

const ORDER_ACTIONS_KEY: InjectionKey<OrderActions> = Symbol('OrderActions');
const ORDER_STATE_KEY: InjectionKey<OrderState> = Symbol('OrderState');

export function provideOrderActions(actions: OrderActions): void {
    provide(ORDER_ACTIONS_KEY, actions);
}

export function provideOrderState(state: OrderState): void {
    provide(ORDER_STATE_KEY, state);
}

export function useOrderActions(): OrderActions {
    const actions = inject(ORDER_ACTIONS_KEY);
    if (!actions) {
        throw new Error('useOrderActions() requires a parent that calls provideOrderActions()');
    }
    return actions;
}

export function useOrderState(): OrderState {
    const state = inject(ORDER_STATE_KEY);
    if (!state) {
        throw new Error('useOrderState() requires a parent that calls provideOrderState()');
    }
    return state;
}
