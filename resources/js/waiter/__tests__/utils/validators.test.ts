/**
 * Validators Utility Unit Tests
 */

import { describe, it, expect } from 'vitest';
import {
  isValidPin,
  getPinError,
  isValidEmail,
  getEmailError,
  isValidPhone,
  getPhoneError,
  isPositiveNumber,
  isNonNegativeNumber,
  isInRange,
  getNumberError,
  isRequired,
  isValidLength,
  getTextError,
  isValidGuestsCount,
  getGuestsCountError,
  isValidQuantity,
  getQuantityError,
  isValidPaymentAmount,
  getPaymentAmountError,
  validateForm,
} from '@/waiter/utils/validators';

describe('Validators Utility', () => {
  describe('PIN Validation', () => {
    describe('isValidPin', () => {
      it('should accept 4-digit PIN', () => {
        expect(isValidPin('1234')).toBe(true);
      });

      it('should accept 5-digit PIN', () => {
        expect(isValidPin('12345')).toBe(true);
      });

      it('should accept 6-digit PIN', () => {
        expect(isValidPin('123456')).toBe(true);
      });

      it('should reject short PIN', () => {
        expect(isValidPin('123')).toBe(false);
      });

      it('should reject long PIN', () => {
        expect(isValidPin('1234567')).toBe(false);
      });

      it('should reject non-digit characters', () => {
        expect(isValidPin('123a')).toBe(false);
        expect(isValidPin('abcd')).toBe(false);
      });
    });

    describe('getPinError', () => {
      it('should return null for valid PIN', () => {
        expect(getPinError('1234')).toBeNull();
      });

      it('should return error for empty PIN', () => {
        expect(getPinError('')).toBe('Введите PIN-код');
      });

      it('should return error for non-digit characters', () => {
        expect(getPinError('12ab')).toBe('PIN должен содержать только цифры');
      });

      it('should return error for short PIN', () => {
        expect(getPinError('123')).toBe('PIN должен быть не менее 4 цифр');
      });

      it('should return error for long PIN', () => {
        expect(getPinError('1234567')).toBe('PIN должен быть не более 6 цифр');
      });
    });
  });

  describe('Email Validation', () => {
    describe('isValidEmail', () => {
      it('should accept valid email', () => {
        expect(isValidEmail('test@example.com')).toBe(true);
        expect(isValidEmail('user.name@domain.org')).toBe(true);
      });

      it('should reject invalid email', () => {
        expect(isValidEmail('invalid')).toBe(false);
        expect(isValidEmail('invalid@')).toBe(false);
        expect(isValidEmail('@domain.com')).toBe(false);
        expect(isValidEmail('test@domain')).toBe(false);
      });
    });

    describe('getEmailError', () => {
      it('should return null for valid email', () => {
        expect(getEmailError('test@example.com')).toBeNull();
      });

      it('should return error for empty email', () => {
        expect(getEmailError('')).toBe('Введите email');
      });

      it('should return error for invalid email', () => {
        expect(getEmailError('invalid')).toBe('Некорректный email');
      });
    });
  });

  describe('Phone Validation', () => {
    describe('isValidPhone', () => {
      it('should accept 10-digit phone', () => {
        expect(isValidPhone('9991234567')).toBe(true);
      });

      it('should accept 11-digit phone', () => {
        expect(isValidPhone('79991234567')).toBe(true);
      });

      it('should accept formatted phone', () => {
        expect(isValidPhone('+7 (999) 123-45-67')).toBe(true);
      });

      it('should reject invalid phone', () => {
        expect(isValidPhone('123')).toBe(false);
        expect(isValidPhone('123456789012')).toBe(false);
      });
    });

    describe('getPhoneError', () => {
      it('should return null for valid phone', () => {
        expect(getPhoneError('9991234567')).toBeNull();
      });

      it('should return error for empty phone', () => {
        expect(getPhoneError('')).toBe('Введите номер телефона');
      });

      it('should return error for invalid phone', () => {
        expect(getPhoneError('123')).toBe('Некорректный номер телефона');
      });
    });
  });

  describe('Number Validation', () => {
    describe('isPositiveNumber', () => {
      it('should return true for positive numbers', () => {
        expect(isPositiveNumber(1)).toBe(true);
        expect(isPositiveNumber(0.1)).toBe(true);
        expect(isPositiveNumber('5')).toBe(true);
      });

      it('should return false for zero and negative', () => {
        expect(isPositiveNumber(0)).toBe(false);
        expect(isPositiveNumber(-1)).toBe(false);
      });

      it('should return false for NaN', () => {
        expect(isPositiveNumber('abc')).toBe(false);
      });
    });

    describe('isNonNegativeNumber', () => {
      it('should return true for zero and positive', () => {
        expect(isNonNegativeNumber(0)).toBe(true);
        expect(isNonNegativeNumber(1)).toBe(true);
      });

      it('should return false for negative', () => {
        expect(isNonNegativeNumber(-1)).toBe(false);
      });
    });

    describe('isInRange', () => {
      it('should return true for value in range', () => {
        expect(isInRange(5, 1, 10)).toBe(true);
        expect(isInRange(1, 1, 10)).toBe(true);
        expect(isInRange(10, 1, 10)).toBe(true);
      });

      it('should return false for value out of range', () => {
        expect(isInRange(0, 1, 10)).toBe(false);
        expect(isInRange(11, 1, 10)).toBe(false);
      });
    });

    describe('getNumberError', () => {
      it('should return null for valid number', () => {
        expect(getNumberError(5)).toBeNull();
      });

      it('should return error for required empty value', () => {
        expect(getNumberError('', { required: true, label: 'Сумма' })).toBe('Сумма обязательно');
      });

      it('should return error for below minimum', () => {
        expect(getNumberError(0, { min: 1, label: 'Количество' })).toBe('Количество должно быть не менее 1');
      });

      it('should return error for above maximum', () => {
        expect(getNumberError(100, { max: 50, label: 'Скидка' })).toBe('Скидка должно быть не более 50');
      });

      it('should return error for NaN', () => {
        expect(getNumberError('abc', { label: 'Сумма' })).toBe('Сумма должно быть числом');
      });
    });
  });

  describe('Text Validation', () => {
    describe('isRequired', () => {
      it('should return true for non-empty string', () => {
        expect(isRequired('text')).toBe(true);
      });

      it('should return false for empty or whitespace', () => {
        expect(isRequired('')).toBe(false);
        expect(isRequired('   ')).toBe(false);
        expect(isRequired(null)).toBe(false);
        expect(isRequired(undefined)).toBe(false);
      });
    });

    describe('isValidLength', () => {
      it('should return true for valid length', () => {
        expect(isValidLength('test', 1, 10)).toBe(true);
      });

      it('should return false for invalid length', () => {
        expect(isValidLength('a', 2, 10)).toBe(false);
        expect(isValidLength('this is too long', 1, 5)).toBe(false);
      });
    });

    describe('getTextError', () => {
      it('should return null for valid text', () => {
        expect(getTextError('test')).toBeNull();
      });

      it('should return error for required empty text', () => {
        expect(getTextError('', { required: true, label: 'Имя' })).toBe('Имя обязательно');
      });

      it('should return error for short text', () => {
        expect(getTextError('ab', { minLength: 3, label: 'Пароль' })).toBe('Пароль должно быть не менее 3 символов');
      });

      it('should return error for long text', () => {
        expect(getTextError('verylongtext', { maxLength: 5, label: 'Код' })).toBe('Код должно быть не более 5 символов');
      });

      it('should return null for optional empty text', () => {
        expect(getTextError('', { required: false })).toBeNull();
      });
    });
  });

  describe('Guests Count Validation', () => {
    describe('isValidGuestsCount', () => {
      it('should accept valid counts', () => {
        expect(isValidGuestsCount(1)).toBe(true);
        expect(isValidGuestsCount(10)).toBe(true);
        expect(isValidGuestsCount(50)).toBe(true);
      });

      it('should reject invalid counts', () => {
        expect(isValidGuestsCount(0)).toBe(false);
        expect(isValidGuestsCount(51)).toBe(false);
        expect(isValidGuestsCount(1.5)).toBe(false);
      });
    });

    describe('getGuestsCountError', () => {
      it('should return null for valid count', () => {
        expect(getGuestsCountError(5)).toBeNull();
      });

      it('should return error for non-integer', () => {
        expect(getGuestsCountError(2.5)).toBe('Количество гостей должно быть целым числом');
      });

      it('should return error for less than 1', () => {
        expect(getGuestsCountError(0)).toBe('Минимум 1 гость');
      });

      it('should return error for more than 50', () => {
        expect(getGuestsCountError(51)).toBe('Максимум 50 гостей');
      });
    });
  });

  describe('Quantity Validation', () => {
    describe('isValidQuantity', () => {
      it('should accept valid quantities', () => {
        expect(isValidQuantity(1)).toBe(true);
        expect(isValidQuantity(50)).toBe(true);
        expect(isValidQuantity(99)).toBe(true);
      });

      it('should reject invalid quantities', () => {
        expect(isValidQuantity(0)).toBe(false);
        expect(isValidQuantity(100)).toBe(false);
        expect(isValidQuantity(1.5)).toBe(false);
      });
    });

    describe('getQuantityError', () => {
      it('should return null for valid quantity', () => {
        expect(getQuantityError(5)).toBeNull();
      });

      it('should return error for non-integer', () => {
        expect(getQuantityError(2.5)).toBe('Количество должно быть целым числом');
      });

      it('should return error for less than 1', () => {
        expect(getQuantityError(0)).toBe('Минимум 1 позиция');
      });

      it('should return error for more than 99', () => {
        expect(getQuantityError(100)).toBe('Максимум 99 позиций');
      });
    });
  });

  describe('Payment Validation', () => {
    describe('isValidPaymentAmount', () => {
      it('should accept valid amount', () => {
        expect(isValidPaymentAmount(1000, 1000)).toBe(true);
        expect(isValidPaymentAmount(1500, 1000)).toBe(true);
      });

      it('should reject insufficient amount', () => {
        expect(isValidPaymentAmount(500, 1000)).toBe(false);
      });

      it('should reject zero or negative', () => {
        expect(isValidPaymentAmount(0, 0)).toBe(false);
        expect(isValidPaymentAmount(-100, 1000)).toBe(false);
      });
    });

    describe('getPaymentAmountError', () => {
      it('should return null for valid amount', () => {
        expect(getPaymentAmountError(1500, 1000)).toBeNull();
      });

      it('should return error for zero or negative', () => {
        expect(getPaymentAmountError(0, 1000)).toBe('Сумма должна быть больше 0');
      });

      it('should return error for insufficient amount', () => {
        expect(getPaymentAmountError(500, 1000)).toBe('Сумма меньше итого');
      });
    });
  });

  describe('validateForm', () => {
    it('should validate all fields and return errors', () => {
      const data = {
        name: '',
        email: 'invalid',
        age: 15,
      };

      const rules = {
        name: (v: unknown) => (!v ? 'Имя обязательно' : null),
        email: (v: unknown) => (!isValidEmail(v as string) ? 'Некорректный email' : null),
        age: (v: unknown) => ((v as number) < 18 ? 'Минимум 18 лет' : null),
      };

      const result = validateForm(data, rules);

      expect(result.isValid).toBe(false);
      expect(result.errors.name).toBe('Имя обязательно');
      expect(result.errors.email).toBe('Некорректный email');
      expect(result.errors.age).toBe('Минимум 18 лет');
    });

    it('should return valid for correct data', () => {
      const data = {
        name: 'John',
        email: 'john@example.com',
      };

      const rules = {
        name: (v: unknown) => (!v ? 'Required' : null),
        email: (v: unknown) => (!isValidEmail(v as string) ? 'Invalid' : null),
      };

      const result = validateForm(data, rules);

      expect(result.isValid).toBe(true);
      expect(Object.keys(result.errors)).toHaveLength(0);
    });
  });
});
