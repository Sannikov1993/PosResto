/**
 * Waiter App - Validators
 * Input validation utilities
 */

// === PIN Validation ===

/**
 * Validate PIN code
 */
export function isValidPin(pin: string): boolean {
  return /^\d{4,6}$/.test(pin);
}

/**
 * Get PIN validation error
 */
export function getPinError(pin: string): string | null {
  if (!pin) {
    return 'Введите PIN-код';
  }
  if (!/^\d+$/.test(pin)) {
    return 'PIN должен содержать только цифры';
  }
  if (pin.length < 4) {
    return 'PIN должен быть не менее 4 цифр';
  }
  if (pin.length > 6) {
    return 'PIN должен быть не более 6 цифр';
  }
  return null;
}

// === Email Validation ===

/**
 * Validate email
 */
export function isValidEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

/**
 * Get email validation error
 */
export function getEmailError(email: string): string | null {
  if (!email) {
    return 'Введите email';
  }
  if (!isValidEmail(email)) {
    return 'Некорректный email';
  }
  return null;
}

// === Phone Validation ===

/**
 * Validate phone number
 */
export function isValidPhone(phone: string): boolean {
  const cleaned = phone.replace(/\D/g, '');
  return cleaned.length === 10 || cleaned.length === 11;
}

/**
 * Get phone validation error
 */
export function getPhoneError(phone: string): string | null {
  if (!phone) {
    return 'Введите номер телефона';
  }
  if (!isValidPhone(phone)) {
    return 'Некорректный номер телефона';
  }
  return null;
}

// === Number Validation ===

/**
 * Validate positive number
 */
export function isPositiveNumber(value: number | string): boolean {
  const num = typeof value === 'string' ? parseFloat(value) : value;
  return !isNaN(num) && num > 0;
}

/**
 * Validate non-negative number
 */
export function isNonNegativeNumber(value: number | string): boolean {
  const num = typeof value === 'string' ? parseFloat(value) : value;
  return !isNaN(num) && num >= 0;
}

/**
 * Validate integer in range
 */
export function isInRange(value: number, min: number, max: number): boolean {
  return value >= min && value <= max;
}

/**
 * Get number validation error
 */
export function getNumberError(
  value: number | string,
  options: {
    min?: number;
    max?: number;
    required?: boolean;
    label?: string;
  } = {}
): string | null {
  const { min, max, required = true, label = 'Значение' } = options;
  const num = typeof value === 'string' ? parseFloat(value) : value;

  if (required && (value === '' || value === null || value === undefined)) {
    return `${label} обязательно`;
  }

  if (isNaN(num)) {
    return `${label} должно быть числом`;
  }

  if (min !== undefined && num < min) {
    return `${label} должно быть не менее ${min}`;
  }

  if (max !== undefined && num > max) {
    return `${label} должно быть не более ${max}`;
  }

  return null;
}

// === Text Validation ===

/**
 * Validate required string
 */
export function isRequired(value: string | null | undefined): boolean {
  return value !== null && value !== undefined && value.trim().length > 0;
}

/**
 * Validate string length
 */
export function isValidLength(value: string, min: number, max: number): boolean {
  const len = value?.length || 0;
  return len >= min && len <= max;
}

/**
 * Get text validation error
 */
export function getTextError(
  value: string,
  options: {
    minLength?: number;
    maxLength?: number;
    required?: boolean;
    label?: string;
  } = {}
): string | null {
  const { minLength, maxLength, required = true, label = 'Поле' } = options;

  if (required && !isRequired(value)) {
    return `${label} обязательно`;
  }

  if (!required && !value) {
    return null;
  }

  if (minLength !== undefined && value.length < minLength) {
    return `${label} должно быть не менее ${minLength} символов`;
  }

  if (maxLength !== undefined && value.length > maxLength) {
    return `${label} должно быть не более ${maxLength} символов`;
  }

  return null;
}

// === Guests Count Validation ===

/**
 * Validate guests count
 */
export function isValidGuestsCount(count: number): boolean {
  return Number.isInteger(count) && count >= 1 && count <= 50;
}

/**
 * Get guests count validation error
 */
export function getGuestsCountError(count: number): string | null {
  if (!Number.isInteger(count)) {
    return 'Количество гостей должно быть целым числом';
  }
  if (count < 1) {
    return 'Минимум 1 гость';
  }
  if (count > 50) {
    return 'Максимум 50 гостей';
  }
  return null;
}

// === Quantity Validation ===

/**
 * Validate item quantity
 */
export function isValidQuantity(quantity: number): boolean {
  return Number.isInteger(quantity) && quantity >= 1 && quantity <= 99;
}

/**
 * Get quantity validation error
 */
export function getQuantityError(quantity: number): string | null {
  if (!Number.isInteger(quantity)) {
    return 'Количество должно быть целым числом';
  }
  if (quantity < 1) {
    return 'Минимум 1 позиция';
  }
  if (quantity > 99) {
    return 'Максимум 99 позиций';
  }
  return null;
}

// === Payment Validation ===

/**
 * Validate payment amount
 */
export function isValidPaymentAmount(amount: number, total: number): boolean {
  return amount >= total && amount > 0;
}

/**
 * Get payment amount validation error
 */
export function getPaymentAmountError(amount: number, total: number): string | null {
  if (amount <= 0) {
    return 'Сумма должна быть больше 0';
  }
  if (amount < total) {
    return 'Сумма меньше итого';
  }
  return null;
}

// === Form Validation Helper ===

/**
 * Validate form fields
 */
export function validateForm<T extends Record<string, any>>(
  data: T,
  rules: Record<keyof T, (value: unknown) => string | null>
): { isValid: boolean; errors: Partial<Record<keyof T, string>> } {
  const errors: Partial<Record<keyof T, string>> = {};
  let isValid = true;

  for (const field in rules) {
    const error = rules[field](data[field]);
    if (error) {
      errors[field] = error;
      isValid = false;
    }
  }

  return { isValid, errors };
}
