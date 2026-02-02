/**
 * Formatters Utility Unit Tests
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  formatMoney,
  formatMoneyShort,
  formatNumber,
  parseMoney,
  formatTime,
  formatDate,
  formatDateFull,
  formatDateTime,
  formatRelativeTime,
  formatDuration,
  getOrderStatusLabel,
  getTableStatusLabel,
  getPaymentMethodLabel,
  getOrderStatusColor,
  getTableStatusColor,
  pluralize,
  formatGuestsCount,
  formatItemsCount,
  formatOrdersCount,
  formatTablesCount,
  formatPhone,
  cleanPhone,
} from '@/waiter/utils/formatters';

describe('Formatters Utility', () => {
  describe('formatMoney', () => {
    it('should format positive numbers', () => {
      // Note: toLocaleString uses non-breaking space (U+00A0) as separator
      expect(formatMoney(1500)).toMatch(/1.500.*₽/);
      expect(formatMoney(100)).toMatch(/100.*₽/);
    });

    it('should handle zero', () => {
      expect(formatMoney(0)).toMatch(/0.*₽/);
    });

    it('should handle null and undefined', () => {
      expect(formatMoney(null)).toMatch(/0.*₽/);
      expect(formatMoney(undefined)).toMatch(/0.*₽/);
    });

    it('should floor decimal values', () => {
      expect(formatMoney(1500.99)).toMatch(/1.500.*₽/);
      expect(formatMoney(1500.01)).toMatch(/1.500.*₽/);
    });

    it('should format large numbers with separators', () => {
      expect(formatMoney(1000000)).toContain('1');
      expect(formatMoney(1000000)).toContain('000');
    });
  });

  describe('formatMoneyShort', () => {
    it('should format millions', () => {
      expect(formatMoneyShort(1500000)).toBe('1.5М');
      expect(formatMoneyShort(1000000)).toBe('1М');
    });

    it('should format thousands', () => {
      expect(formatMoneyShort(1500)).toBe('1.5К');
      expect(formatMoneyShort(1000)).toBe('1К');
    });

    it('should format small numbers normally', () => {
      expect(formatMoneyShort(500)).toBe('500 ₽');
    });
  });

  describe('formatNumber', () => {
    it('should format numbers without currency', () => {
      // Note: toLocaleString uses non-breaking space (U+00A0) as separator
      expect(formatNumber(1500)).toMatch(/1.500/);
    });

    it('should handle null and undefined', () => {
      expect(formatNumber(null)).toBe('0');
      expect(formatNumber(undefined)).toBe('0');
    });
  });

  describe('parseMoney', () => {
    it('should parse formatted money string', () => {
      expect(parseMoney('1 500 ₽')).toBe(1500);
      expect(parseMoney('1500')).toBe(1500);
    });

    it('should handle empty string', () => {
      expect(parseMoney('')).toBe(0);
    });

    it('should extract only digits', () => {
      expect(parseMoney('abc123def')).toBe(123);
    });
  });

  describe('Time Formatting', () => {
    const testDate = new Date('2024-01-15T14:30:00');

    describe('formatTime', () => {
      it('should format Date object to time', () => {
        const result = formatTime(testDate);
        expect(result).toMatch(/14:30/);
      });

      it('should format date string to time', () => {
        const result = formatTime('2024-01-15T14:30:00');
        expect(result).toMatch(/\d{2}:\d{2}/);
      });
    });

    describe('formatDate', () => {
      it('should format Date object to short date', () => {
        const result = formatDate(testDate);
        expect(result).toContain('15');
      });

      it('should format date string to short date', () => {
        const result = formatDate('2024-01-15T14:30:00');
        expect(result).toContain('15');
      });
    });

    describe('formatDateFull', () => {
      it('should format to full date', () => {
        const result = formatDateFull(testDate);
        expect(result).toMatch(/\d{2}\.\d{2}\.\d{4}/);
      });
    });

    describe('formatDateTime', () => {
      it('should combine date and time', () => {
        const result = formatDateTime(testDate);
        expect(result).toContain('15');
        expect(result).toMatch(/\d{2}:\d{2}/);
      });
    });
  });

  describe('formatRelativeTime', () => {
    let realDate: typeof Date;

    beforeEach(() => {
      realDate = global.Date;
      const mockDate = new Date('2024-01-15T15:00:00');
      vi.useFakeTimers();
      vi.setSystemTime(mockDate);
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('should return "только что" for less than 1 minute', () => {
      const result = formatRelativeTime('2024-01-15T15:00:00');
      expect(result).toBe('только что');
    });

    it('should return "1 мин назад" for 1 minute', () => {
      const result = formatRelativeTime('2024-01-15T14:59:00');
      expect(result).toBe('1 мин назад');
    });

    it('should return minutes for less than 60 minutes', () => {
      const result = formatRelativeTime('2024-01-15T14:30:00');
      expect(result).toBe('30 мин назад');
    });

    it('should return hours for more than 60 minutes', () => {
      const result = formatRelativeTime('2024-01-15T13:00:00');
      expect(result).toContain('час');
    });
  });

  describe('formatDuration', () => {
    it('should format minutes only', () => {
      expect(formatDuration(30)).toBe('30 мин');
    });

    it('should format hours only', () => {
      expect(formatDuration(60)).toBe('1 ч');
      expect(formatDuration(120)).toBe('2 ч');
    });

    it('should format hours and minutes', () => {
      expect(formatDuration(90)).toBe('1 ч 30 мин');
    });
  });

  describe('Status Labels', () => {
    describe('getOrderStatusLabel', () => {
      it('should return correct labels', () => {
        expect(getOrderStatusLabel('new')).toBe('Новый');
        expect(getOrderStatusLabel('paid')).toBe('Оплачен');
        expect(getOrderStatusLabel('cancelled')).toBe('Отменён');
      });

      it('should return original for unknown status', () => {
        expect(getOrderStatusLabel('unknown')).toBe('unknown');
      });
    });

    describe('getTableStatusLabel', () => {
      it('should return correct labels', () => {
        expect(getTableStatusLabel('free')).toBe('Свободен');
        expect(getTableStatusLabel('occupied')).toBe('Занят');
        expect(getTableStatusLabel('reserved')).toBe('Бронь');
        expect(getTableStatusLabel('bill_requested')).toBe('Счёт');
      });
    });

    describe('getPaymentMethodLabel', () => {
      it('should return correct labels', () => {
        expect(getPaymentMethodLabel('cash')).toBe('Наличные');
        expect(getPaymentMethodLabel('card')).toBe('Карта');
        expect(getPaymentMethodLabel('mixed')).toBe('Смешанная');
      });
    });
  });

  describe('Status Colors', () => {
    describe('getOrderStatusColor', () => {
      it('should return color classes', () => {
        expect(getOrderStatusColor('new')).toContain('blue');
        expect(getOrderStatusColor('ready')).toContain('green');
        expect(getOrderStatusColor('cancelled')).toContain('red');
      });

      it('should return default for unknown status', () => {
        expect(getOrderStatusColor('unknown')).toContain('gray');
      });
    });

    describe('getTableStatusColor', () => {
      it('should return color classes', () => {
        expect(getTableStatusColor('free')).toContain('green');
        expect(getTableStatusColor('occupied')).toContain('orange');
        expect(getTableStatusColor('reserved')).toContain('blue');
        expect(getTableStatusColor('bill_requested')).toContain('red');
      });
    });
  });

  describe('pluralize', () => {
    const forms: [string, string, string] = ['позиция', 'позиции', 'позиций'];

    it('should return first form for 1', () => {
      expect(pluralize(1, forms)).toBe('позиция');
      expect(pluralize(21, forms)).toBe('позиция');
      expect(pluralize(101, forms)).toBe('позиция');
    });

    it('should return second form for 2-4', () => {
      expect(pluralize(2, forms)).toBe('позиции');
      expect(pluralize(3, forms)).toBe('позиции');
      expect(pluralize(4, forms)).toBe('позиции');
      expect(pluralize(22, forms)).toBe('позиции');
    });

    it('should return third form for 5-20 and 0', () => {
      expect(pluralize(0, forms)).toBe('позиций');
      expect(pluralize(5, forms)).toBe('позиций');
      expect(pluralize(11, forms)).toBe('позиций');
      expect(pluralize(19, forms)).toBe('позиций');
      expect(pluralize(100, forms)).toBe('позиций');
    });
  });

  describe('Count Formatters', () => {
    describe('formatGuestsCount', () => {
      it('should format with correct word', () => {
        expect(formatGuestsCount(1)).toBe('1 гость');
        expect(formatGuestsCount(2)).toBe('2 гостя');
        expect(formatGuestsCount(5)).toBe('5 гостей');
      });
    });

    describe('formatItemsCount', () => {
      it('should format with correct word', () => {
        expect(formatItemsCount(1)).toBe('1 позиция');
        expect(formatItemsCount(2)).toBe('2 позиции');
        expect(formatItemsCount(5)).toBe('5 позиций');
      });
    });

    describe('formatOrdersCount', () => {
      it('should format with correct word', () => {
        expect(formatOrdersCount(1)).toBe('1 заказ');
        expect(formatOrdersCount(2)).toBe('2 заказа');
        expect(formatOrdersCount(5)).toBe('5 заказов');
      });
    });

    describe('formatTablesCount', () => {
      it('should format with correct word', () => {
        expect(formatTablesCount(1)).toBe('1 стол');
        expect(formatTablesCount(2)).toBe('2 стола');
        expect(formatTablesCount(5)).toBe('5 столов');
      });
    });
  });

  describe('Phone Formatting', () => {
    describe('formatPhone', () => {
      it('should format 11-digit phone starting with 7', () => {
        expect(formatPhone('79991234567')).toBe('+7 (999) 123-45-67');
      });

      it('should format 10-digit phone', () => {
        expect(formatPhone('9991234567')).toBe('+7 (999) 123-45-67');
      });

      it('should return original for invalid format', () => {
        expect(formatPhone('123')).toBe('123');
      });
    });

    describe('cleanPhone', () => {
      it('should remove non-digit characters', () => {
        expect(cleanPhone('+7 (999) 123-45-67')).toBe('79991234567');
      });

      it('should convert 8 to 7 for Russian numbers', () => {
        expect(cleanPhone('89991234567')).toBe('79991234567');
      });
    });
  });
});
