/**
 * v-can Directive - Permission-based visibility control
 *
 * @module shared/directives/can
 */

import type { App, Directive, DirectiveBinding } from 'vue';
import { usePermissionsStore } from '../stores/permissions.js';

interface CanElement extends HTMLElement {
    _vCanBinding?: DirectiveBinding;
    _vCanDisplay?: string;
}

function checkPermission(store: ReturnType<typeof usePermissionsStore>, value: string | string[], arg?: string): boolean {
    if (!value) return true;

    if (arg === 'any') {
        return store.canAny(Array.isArray(value) ? value : [value]);
    }

    if (arg === 'all') {
        return store.canAll(Array.isArray(value) ? value : [value]);
    }

    if (Array.isArray(value)) {
        return store.canAny(value);
    }

    return store.can(value);
}

function applyState(el: CanElement, hasPermission: boolean, arg?: string): void {
    if (arg === 'disable') {
        if (!hasPermission) {
            el.setAttribute('disabled', 'disabled');
            el.classList.add('v-can-disabled');
            el.style.opacity = '0.5';
            el.style.pointerEvents = 'none';
        } else {
            el.removeAttribute('disabled');
            el.classList.remove('v-can-disabled');
            el.style.opacity = '';
            el.style.pointerEvents = '';
        }
    } else {
        if (!hasPermission) {
            el._vCanDisplay = el.style.display;
            el.style.display = 'none';
        } else {
            if (el._vCanDisplay !== undefined) {
                el.style.display = el._vCanDisplay;
                delete el._vCanDisplay;
            }
        }
    }
}

export const vCan: Directive<CanElement> = {
    mounted(el: CanElement, binding: DirectiveBinding) {
        const store = usePermissionsStore();
        const hasPermission = checkPermission(store, binding.value, binding.arg);
        applyState(el, hasPermission, binding.arg);
        el._vCanBinding = binding;
    },

    updated(el: CanElement, binding: DirectiveBinding) {
        const store = usePermissionsStore();
        const hasPermission = checkPermission(store, binding.value, binding.arg);
        applyState(el, hasPermission, binding.arg);
        el._vCanBinding = binding;
    },

    beforeUnmount(el: CanElement) {
        delete el._vCanBinding;
        delete el._vCanDisplay;
    },
};

export function registerCanDirective(app: App): void {
    app.directive('can', vCan);
}

export default vCan;
