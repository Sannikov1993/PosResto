/**
 * Waiter App - Formatters
 * Money, date, time, and status formatting utilities
 */

// === Money Formatting ===

/**
 * Format amount as Russian rubles
 */
export function formatMoney(amount: number | null | undefined): string {
  const value = Math.floor(amount || 0);
  return value.toLocaleString('ru-RU') + ' \u20BD';
}

/**
 * Format amount as short form (1К, 1М)
 */
export function formatMoneyShort(amount: number): string {
  if (amount >= 1000000) {
    return (amount / 1000000).toFixed(1).replace('.0', '') + 'М';
  }
  if (amount >= 1000) {
    return (amount / 1000).toFixed(1).replace('.0', '') + 'К';
  }
  return amount.toString() + ' \u20BD';
}

/**
 * Format amount without currency symbol
 */
export function formatNumber(amount: number | null | undefined): string {
  const value = Math.floor(amount || 0);
  return value.toLocaleString('ru-RU');
}

/**
 * Parse formatted money string to number
 */
export function parseMoney(value: string): number {
  const cleaned = value.replace(/[^\d]/g, '');
  return parseInt(cleaned, 10) || 0;
}

// === Time Formatting ===

/**
 * Format date to time string (HH:MM)
 */
export function formatTime(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Format date to short date string (DD MMM)
 */
export function formatDate(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'short',
  });
}

/**
 * Format date to full date string (DD.MM.YYYY)
 */
export function formatDateFull(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  return d.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

/**
 * Format date to date and time string
 */
export function formatDateTime(date: string | Date): string {
  return `${formatDate(date)} ${formatTime(date)}`;
}

/**
 * Format date to relative time (5 мин назад)
 */
export function formatRelativeTime(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();
  const diffMins = Math.floor(diffMs / 60000);

  if (diffMins < 1) return 'только что';
  if (diffMins === 1) return '1 мин назад';
  if (diffMins < 5) return `${diffMins} мин назад`;
  if (diffMins < 60) return `${diffMins} мин назад`;

  const diffHours = Math.floor(diffMins / 60);
  if (diffHours === 1) return '1 час назад';
  if (diffHours < 5) return `${diffHours} часа назад`;
  if (diffHours < 24) return `${diffHours} часов назад`;

  return formatDate(d);
}

/**
 * Format duration in minutes
 */
export function formatDuration(minutes: number): string {
  if (minutes < 60) {
    return `${minutes} мин`;
  }
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  if (mins === 0) {
    return `${hours} ч`;
  }
  return `${hours} ч ${mins} мин`;
}

// === Status Labels ===

export const ORDER_STATUS_LABELS: Record<string, string> = {
  new: 'Новый',
  pending: 'Ожидает',
  cooking: 'Готовится',
  ready: 'Готов',
  served: 'Подан',
  paid: 'Оплачен',
  cancelled: 'Отменён',
};

export const ORDER_ITEM_STATUS_LABELS: Record<string, string> = {
  new: 'Новый',
  pending: 'Ожидает',
  cooking: 'Готовится',
  ready: 'Готов',
  served: 'Подан',
  cancelled: 'Отменён',
};

export const TABLE_STATUS_LABELS: Record<string, string> = {
  free: 'Свободен',
  occupied: 'Занят',
  reserved: 'Бронь',
  bill_requested: 'Счёт',
};

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash: 'Наличные',
  card: 'Карта',
  mixed: 'Смешанная',
};

/**
 * Get order status label
 */
export function getOrderStatusLabel(status: string): string {
  return ORDER_STATUS_LABELS[status] || status;
}

/**
 * Get order item status label
 */
export function getOrderItemStatusLabel(status: string): string {
  return ORDER_ITEM_STATUS_LABELS[status] || status;
}

/**
 * Get table status label
 */
export function getTableStatusLabel(status: string): string {
  return TABLE_STATUS_LABELS[status] || status;
}

/**
 * Get payment method label
 */
export function getPaymentMethodLabel(method: string): string {
  return PAYMENT_METHOD_LABELS[method] || method;
}

// === Status Colors ===

/**
 * Get order status color classes
 */
export function getOrderStatusColor(status: string): string {
  switch (status) {
    case 'new':
      return 'bg-blue-100 text-blue-800';
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'cooking':
      return 'bg-orange-100 text-orange-800';
    case 'ready':
      return 'bg-green-100 text-green-800';
    case 'served':
      return 'bg-gray-100 text-gray-800';
    case 'paid':
      return 'bg-emerald-100 text-emerald-800';
    case 'cancelled':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}

/**
 * Get table status color classes
 */
export function getTableStatusColor(status: string): string {
  switch (status) {
    case 'free':
      return 'bg-green-100 border-green-300 text-green-800';
    case 'occupied':
      return 'bg-orange-100 border-orange-300 text-orange-800';
    case 'reserved':
      return 'bg-blue-100 border-blue-300 text-blue-800';
    case 'bill_requested':
      return 'bg-red-100 border-red-300 text-red-800';
    default:
      return 'bg-gray-100 border-gray-300 text-gray-800';
  }
}

/**
 * Get order item status color classes
 */
export function getOrderItemStatusColor(status: string): string {
  switch (status) {
    case 'new':
      return 'bg-blue-100 text-blue-700';
    case 'pending':
      return 'bg-yellow-100 text-yellow-700';
    case 'cooking':
      return 'bg-orange-100 text-orange-700';
    case 'ready':
      return 'bg-green-100 text-green-700';
    case 'served':
      return 'bg-gray-100 text-gray-700';
    case 'cancelled':
      return 'bg-red-100 text-red-700';
    default:
      return 'bg-gray-100 text-gray-700';
  }
}

// === Text Formatting ===

/**
 * Pluralize Russian word
 */
export function pluralize(count: number, forms: [string, string, string]): string {
  const n = Math.abs(count) % 100;
  const n1 = n % 10;

  if (n > 10 && n < 20) {
    return forms[2];
  }
  if (n1 > 1 && n1 < 5) {
    return forms[1];
  }
  if (n1 === 1) {
    return forms[0];
  }
  return forms[2];
}

/**
 * Format guests count with word
 */
export function formatGuestsCount(count: number): string {
  const word = pluralize(count, ['гость', 'гостя', 'гостей']);
  return `${count} ${word}`;
}

/**
 * Format items count with word
 */
export function formatItemsCount(count: number): string {
  const word = pluralize(count, ['позиция', 'позиции', 'позиций']);
  return `${count} ${word}`;
}

/**
 * Format orders count with word
 */
export function formatOrdersCount(count: number): string {
  const word = pluralize(count, ['заказ', 'заказа', 'заказов']);
  return `${count} ${word}`;
}

/**
 * Format tables count with word
 */
export function formatTablesCount(count: number): string {
  const word = pluralize(count, ['стол', 'стола', 'столов']);
  return `${count} ${word}`;
}

// === Phone Formatting ===

/**
 * Format phone number
 */
export function formatPhone(phone: string): string {
  const cleaned = phone.replace(/\D/g, '');

  if (cleaned.length === 11 && cleaned.startsWith('7')) {
    return `+7 (${cleaned.slice(1, 4)}) ${cleaned.slice(4, 7)}-${cleaned.slice(7, 9)}-${cleaned.slice(9)}`;
  }

  if (cleaned.length === 10) {
    return `+7 (${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6, 8)}-${cleaned.slice(8)}`;
  }

  return phone;
}

/**
 * Clean phone number (only digits)
 */
export function cleanPhone(phone: string): string {
  let cleaned = phone.replace(/\D/g, '');

  if (cleaned.startsWith('8') && cleaned.length === 11) {
    cleaned = '7' + cleaned.slice(1);
  }

  return cleaned;
}
