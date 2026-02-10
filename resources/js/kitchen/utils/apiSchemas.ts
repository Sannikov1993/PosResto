/**
 * API Response Schemas
 *
 * Schema definitions and validation for API responses.
 *
 * @module kitchen/utils/apiSchemas
 */

import { createLogger } from '../../shared/services/logger.js';
import type { ValidationResult, SchemaDefinition } from '../types/index.js';

const log = createLogger('ApiSchemas');

export function validate(value: unknown, schema: SchemaDefinition, path = ''): ValidationResult {
    const errors: string[] = [];

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

    if (schema.required && typeof value === 'object' && value !== null) {
        for (const prop of schema.required) {
            if (!(prop in (value as Record<string, any>))) {
                errors.push(`${path}.${prop}: required property missing`);
            }
        }
    }

    if (schema.properties && typeof value === 'object' && value !== null) {
        for (const [propName, propSchema] of Object.entries(schema.properties)) {
            if (propName in (value as Record<string, any>)) {
                const propResult = validate((value as Record<string, any>)[propName], propSchema, `${path}.${propName}`);
                errors.push(...propResult.errors);
            }
        }
    }

    if (schema.items && Array.isArray(value)) {
        value.forEach((item: any, index: any) => {
            const itemResult = validate(item, schema.items!, `${path}[${index}]`);
            errors.push(...itemResult.errors);
        });
    }

    return { valid: errors.length === 0, errors };
}

export const OrderItemSchema: SchemaDefinition = {
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

export const OrderSchema: SchemaDefinition = {
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

export const OrdersArraySchema: SchemaDefinition = {
    type: 'array',
    items: OrderSchema,
};

export const DeviceSchema: SchemaDefinition = {
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

export const ApiResponseSchema: SchemaDefinition = {
    type: 'object',
    required: ['success'],
    properties: {
        success: { type: 'boolean' },
        data: { type: 'object', nullable: true },
        message: { type: 'string', nullable: true },
        error_code: { type: 'string', nullable: true },
    },
};

export function validateApiResponse(response: unknown, dataSchema: SchemaDefinition | null = null): ValidationResult {
    const result = validate(response, ApiResponseSchema, 'response');

    if (result.valid && dataSchema && (response as any).data) {
        const dataResult = validate((response as any).data, dataSchema, 'response.data');
        result.errors.push(...dataResult.errors);
        result.valid = dataResult.errors.length === 0;
    }

    return result;
}

export function validateOrdersResponse(response: unknown): ValidationResult {
    return validateApiResponse(response, OrdersArraySchema);
}

export function validateDeviceResponse(response: unknown): ValidationResult {
    return validateApiResponse(response, DeviceSchema);
}

export function safeValidate(value: unknown, schema: SchemaDefinition, context = ''): boolean {
    const result = validate(value, schema, context);

    if (!result.valid && import.meta.env?.DEV) {
        log.warn(`${context}:`, result.errors);
    }

    return result.valid;
}
