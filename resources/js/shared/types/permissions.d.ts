/**
 * TypeScript type definitions for Permission System
 *
 * @module shared/types/permissions
 */

/**
 * User limits from role configuration
 */
export interface UserLimits {
    /** Maximum discount percentage user can apply (0-100) */
    max_discount_percent: number;
    /** Maximum refund amount user can process */
    max_refund_amount: number;
    /** Maximum order amount user can cancel */
    max_cancel_amount: number;
}

/**
 * Interface access flags
 */
export interface InterfaceAccess {
    /** Can access POS application */
    can_access_pos: boolean;
    /** Can access Backoffice application */
    can_access_backoffice: boolean;
    /** Can access Kitchen display */
    can_access_kitchen: boolean;
    /** Can access Delivery management */
    can_access_delivery: boolean;
}

/**
 * Available POS modules (Level 2 access)
 */
export type PosModule =
    | 'cash'
    | 'orders'
    | 'delivery'
    | 'customers'
    | 'warehouse'
    | 'stoplist'
    | 'writeoffs'
    | 'settings';

/**
 * Available Backoffice modules (Level 2 access)
 */
export type BackofficeModule =
    | 'dashboard'
    | 'menu'
    | 'pricelists'
    | 'hall'
    | 'staff'
    | 'attendance'
    | 'inventory'
    | 'customers'
    | 'loyalty'
    | 'delivery'
    | 'finance'
    | 'analytics'
    | 'integrations'
    | 'settings';

/**
 * Permission categories
 */
export type PermissionCategory =
    | 'orders'
    | 'menu'
    | 'inventory'
    | 'staff'
    | 'customers'
    | 'finance'
    | 'reports'
    | 'settings'
    | 'loyalty'
    | 'delivery';

/**
 * Common permission string format: "category.action"
 * Examples: "orders.create", "menu.edit", "staff.delete"
 */
export type Permission = `${PermissionCategory}.${string}` | '*';

/**
 * User roles in the system
 */
export type UserRole =
    | 'super_admin'
    | 'owner'
    | 'manager'
    | 'cashier'
    | 'waiter'
    | 'chef'
    | 'courier'
    | 'hostess';

/**
 * Permissions store initialization data
 */
export interface PermissionsInitData {
    /** Array of permission strings */
    permissions: string[];
    /** User limits */
    limits: Partial<UserLimits>;
    /** Interface access flags */
    interfaceAccess: Partial<InterfaceAccess>;
    /** Available POS modules */
    posModules: PosModule[];
    /** Available Backoffice modules */
    backofficeModules: BackofficeModule[];
    /** User role */
    role: UserRole | string | null;
}

/**
 * Permissions store state and methods
 */
export interface PermissionsStore {
    // State
    permissions: string[];
    limits: UserLimits;
    interfaceAccess: InterfaceAccess;
    posModules: PosModule[];
    backofficeModules: BackofficeModule[];
    userRole: string | null;
    initialized: boolean;

    // Computed
    maxDiscountPercent: number;
    maxRefundAmount: number;
    maxCancelAmount: number;
    isAdmin: boolean;

    // Permission checks (Level 3)
    can(permission: string): boolean;
    canAny(permissions: string[]): boolean;
    canAll(permissions: string[]): boolean;

    // Limit checks
    canApplyDiscount(percent: number): boolean;
    canRefund(amount: number): boolean;
    canCancel(amount: number): boolean;

    // Interface access (Level 1)
    canAccessInterface(name: string): boolean;

    // Module access (Level 2)
    canAccessPosModule(module: PosModule | string): boolean;
    canAccessBackofficeModule(module: BackofficeModule | string): boolean;
    getAvailablePosModules(): PosModule[];
    getAvailableBackofficeModules(): BackofficeModule[];

    // Actions
    init(data: Partial<PermissionsInitData>): void;
    reset(): void;
    updatePermissions(permissions: string[]): void;
    updateLimits(limits: Partial<UserLimits>): void;
    getPermissions(): string[];
}

/**
 * usePermissions composable return type
 */
export interface UsePermissionsReturn {
    // Reactive refs (use .value to access)
    permissions: { value: string[] };
    limits: { value: UserLimits };
    interfaceAccess: { value: InterfaceAccess };
    posModules: { value: PosModule[] };
    backofficeModules: { value: BackofficeModule[] };
    userRole: { value: string | null };
    isAdmin: { value: boolean };
    initialized: { value: boolean };
    maxDiscountPercent: { value: number };
    maxRefundAmount: { value: number };
    maxCancelAmount: { value: number };

    // Permission check methods (Level 3)
    can(permission: string): boolean;
    canAny(permissions: string[]): boolean;
    canAll(permissions: string[]): boolean;
    canApplyDiscount(percent: number): boolean;
    canRefund(amount: number): boolean;
    canCancel(amount: number): boolean;

    // Interface access (Level 1)
    canAccessInterface(name: string): boolean;

    // Module access (Level 2)
    canAccessPosModule(module: PosModule | string): boolean;
    canAccessBackofficeModule(module: BackofficeModule | string): boolean;
    getAvailablePosModules(): PosModule[];
    getAvailableBackofficeModules(): BackofficeModule[];
}

/**
 * v-can directive binding value types
 */
export type VCanValue = string | string[];

/**
 * v-can directive argument types
 */
export type VCanArg = 'disable' | 'any' | 'all' | undefined;

/**
 * Tab permission configuration for NavigationStore
 */
export interface TabPermissionConfig {
    /** Tab identifier */
    id: string;
    /** Required permissions (any match grants access) */
    permissions: string[];
    /** Required interface access flag */
    interfaceAccess?: keyof InterfaceAccess;
}
