/**
 * API Response Schemas
 *
 * Schema definitions and validation for API responses.
 *
 * @module kitchen/utils/apiSchemas
 */

import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('ApiSchemas');

/**
 * @typedef {Object} ValidationResult
 * @property {boolean} valid - Whether validation passed
 * @property {string[]} errors - List of validation errors
 */

/**
 * Validate value against schema
 * @param {*} value - Value to validate
 * @param {Object} schema - Schema definition
 * @param {string} [path=''] - Current path for error messages
 * @returns {ValidationResult}
 */
export function validate(value, schema, path = '') {
    const errors = [];

    // Check type
    if (schema.type) {
        const actualType = Array.isArray(value) ? 'array' : typeof value;
        if (schema.type === 'array' && !Array.isArray(value)) {
            errors.push(`${path}: expected array, got ${actualType}`);
        } else if (schema.type !== 'array' && actualType !== schema.type) {
            if (!(schema.nullable && value === null)) {
                errors.push(`${path}: expected ${schema.type}, got ${actualType}`);
            }
        }
    }

    // Check required properties
    if (schema.required && typeof value === 'object' && value !== null) {
        for (const prop of schema.required) {
            if (!(prop in value)) {
                errors.push(`${path}.${prop}: required property missing`);
            }
        }
    }

    // Validate object properties
    if (schema.properties && typeof value === 'object' && value !== null) {
        for (const [propName, propSchema] of Object.entries(schema.properties)) {
            if (propName in value) {
                const propResult = validate(value[propName], propSchema, `${path}.${propName}`);
                errors.push(...propResult.errors);
            }
        }
    }

    // Validate array items
    if (schema.items && Array.isArray(value)) {
        value.forEach((item, index) => {
            const itemResult = validate(item, schema.items, `${path}[${index}]`);
            errors.push(...itemResult.errors);
        });
    }

    return {
        valid: errors.length === 0,
        errors,
    };
}

/**
 * Order Item Schema
 */
export const OrderItemSchema = {
    type: 'object',
    required: ['id', 'name', 'quantity', 'status'],
    properties: {
        id: { type: 'number' },
        name: { type: 'string' },
        quantity: { type: 'number' },
        status: { type: 'string' },
        comment: { type: 'string', nullable: true },
        notes: { type: 'string', nullable: true },
        guest_number: { type: 'number', nullable: true },
        cooking_started_at: { type: 'string', nullable: true },
        modifiers: { type: 'array', nullable: true },
        dish: {
            type: 'object',
            nullable: true,
            properties: {
                id: { type: 'number' },
                name: { type: 'string' },
                image: { type: 'string', nullable: true },
                category: {
                    type: 'object',
                    nullable: true,
                    properties: {
                        name: { type: 'string' },
                    },
                },
            },
        },
    },
};

/**
 * Order Schema
 */
export const OrderSchema = {
    type: 'object',
    required: ['id', 'order_number', 'status'],
    properties: {
        id: { type: 'number' },
        order_number: { type: 'number' },
        status: { type: 'string' },
        type: { type: 'string' },
        created_at: { type: 'string' },
        updated_at: { type: 'string' },
        scheduled_at: { type: 'string', nullable: true },
        is_asap: { type: 'boolean', nullable: true },
        cooking_started_at: { type: 'string', nullable: true },
        ready_at: { type: 'string', nullable: true },
        notes: { type: 'string', nullable: true },
        items: {
            type: 'array',
            items: OrderItemSchema,
        },
        table: {
            type: 'object',
            nullable: true,
            properties: {
                id: { type: 'number' },
                number: { type: 'number' },
                name: { type: 'string', nullable: true },
            },
        },
        waiter: {
            type: 'object',
            nullable: true,
            properties: {
                id: { type: 'number' },
                name: { type: 'string' },
            },
        },
    },
};

/**
 * Orders Array Schema
 */
export const OrdersArraySchema = {
    type: 'array',
    items: OrderSchema,
};

/**
 * Device Schema
 */
export const DeviceSchema = {
    type: 'object',
    required: ['device_id'],
    properties: {
        device_id: { type: 'string' },
        status: { type: 'string' },
        timezone: { type: 'string', nullable: true },
        kitchen_station: {
            type: 'object',
            nullable: true,
            properties: {
                id: { type: 'number' },
                name: { type: 'string' },
                slug: { type: 'string' },
                icon: { type: 'string', nullable: true },
                notification_sound: { type: 'string', nullable: true },
            },
        },
    },
};

/**
 * API Response Schema
 */
export const ApiResponseSchema = {
    type: 'object',
    required: ['success'],
    properties: {
        success: { type: 'boolean' },
        data: { type: 'object', nullable: true },
        message: { type: 'string', nullable: true },
        error_code: { type: 'string', nullable: true },
    },
};

/**
 * Validate API response
 * @param {Object} response - API response
 * @param {Object} [dataSchema] - Schema for the data field
 * @returns {ValidationResult}
 */
export function validateApiResponse(response, dataSchema = null) {
    const result = validate(response, ApiResponseSchema, 'response');

    if (result.valid && dataSchema && response.data) {
        const dataResult = validate(response.data, dataSchema, 'response.data');
        result.errors.push(...dataResult.errors);
        result.valid = dataResult.errors.length === 0;
    }

    return result;
}

/**
 * Validate orders response
 * @param {Object} response - API response
 * @returns {ValidationResult}
 */
export function validateOrdersResponse(response) {
    return validateApiResponse(response, OrdersArraySchema);
}

/**
 * Validate device response
 * @param {Object} response - API response
 * @returns {ValidationResult}
 */
export function validateDeviceResponse(response) {
    return validateApiResponse(response, DeviceSchema);
}

/**
 * Safe validate - logs warnings in development, doesn't throw
 * @param {*} value - Value to validate
 * @param {Object} schema - Schema definition
 * @param {string} context - Context for logging
 * @returns {boolean} Whether validation passed
 */
export function safeValidate(value, schema, context = '') {
    const result = validate(value, schema, context);

    if (!result.valid && import.meta.env?.DEV) {
        log.warn(`${context}:`, result.errors);
    }

    return result.valid;
}
