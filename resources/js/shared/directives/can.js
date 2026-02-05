/**
 * v-can Directive - Permission-based visibility control
 *
 * Hides or disables elements based on user permissions.
 *
 * @module shared/directives/can
 *
 * @example
 * <!-- Hide if no permission -->
 * <button v-can="'orders.create'">Create</button>
 *
 * <!-- Disable if no permission -->
 * <button v-can:disable="'orders.discount'">Discount</button>
 *
 * <!-- Check any of permissions -->
 * <div v-can:any="['orders.view', 'orders.create']">...</div>
 *
 * <!-- Check all permissions -->
 * <div v-can:all="['orders.view', 'orders.edit']">...</div>
 */

import { usePermissionsStore } from '../stores/permissions.js';

/**
 * Check if user has permission based on directive arguments
 * @param {Object} store - Permissions store
 * @param {string|string[]} value - Permission(s) to check
 * @param {string} arg - Directive argument (disable, any, all)
 * @returns {boolean}
 */
function checkPermission(store, value, arg) {
    if (!value) return true;

    // Handle array with arg
    if (arg === 'any') {
        return store.canAny(Array.isArray(value) ? value : [value]);
    }

    if (arg === 'all') {
        return store.canAll(Array.isArray(value) ? value : [value]);
    }

    // Single permission check
    if (Array.isArray(value)) {
        // Default: check any for arrays
        return store.canAny(value);
    }

    return store.can(value);
}

/**
 * Apply visibility/disabled state to element
 * @param {HTMLElement} el - Element to modify
 * @param {boolean} hasPermission - Whether user has permission
 * @param {string} arg - Directive argument
 */
function applyState(el, hasPermission, arg) {
    if (arg === 'disable') {
        // Disable mode: add disabled attribute and class
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
        // Default: hide element
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

/**
 * v-can directive definition
 */
export const vCan = {
    mounted(el, binding) {
        const store = usePermissionsStore();
        const hasPermission = checkPermission(store, binding.value, binding.arg);
        applyState(el, hasPermission, binding.arg);

        // Store for updates
        el._vCanBinding = binding;
    },

    updated(el, binding) {
        const store = usePermissionsStore();
        const hasPermission = checkPermission(store, binding.value, binding.arg);
        applyState(el, hasPermission, binding.arg);

        // Update stored binding
        el._vCanBinding = binding;
    },

    beforeUnmount(el) {
        // Cleanup
        delete el._vCanBinding;
        delete el._vCanDisplay;
    },
};

/**
 * Register v-can directive on Vue app
 * @param {Object} app - Vue app instance
 */
export function registerCanDirective(app) {
    app.directive('can', vCan);
}

export default vCan;
