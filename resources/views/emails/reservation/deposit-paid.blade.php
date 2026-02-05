<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Депозит оплачен</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); padding: 32px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 8px;">💰</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                Депозит оплачен
                            </h1>
                            <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.8); font-size: 14px;">
                                {{ $restaurant->name ?? 'Ресторан' }}
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 24px 0; color: #374151; font-size: 16px;">
                                Здравствуйте, <strong>{{ $guestName }}</strong>!
                            </p>

                            <p style="margin: 0 0 24px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                Депозит по вашему бронированию успешно оплачен. Спасибо!
                            </p>

                            <!-- Payment Details -->
                            <div style="background-color: #ECFDF5; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 2px solid #10B981;">
                                <h3 style="margin: 0 0 16px 0; color: #065F46; font-size: 16px; font-weight: 600;">
                                    ✅ Подтверждение оплаты
                                </h3>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 8px 0; color: #065F46; font-size: 14px;">Сумма депозита</td>
                                        <td style="padding: 8px 0; color: #065F46; font-size: 18px; text-align: right; font-weight: 700;">
                                            {{ number_format($reservation->deposit, 0, ',', ' ') }} ₽
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #065F46; font-size: 14px; border-top: 1px solid #A7F3D0;">Способ оплаты</td>
                                        <td style="padding: 8px 0; color: #065F46; font-size: 14px; text-align: right; font-weight: 500; border-top: 1px solid #A7F3D0;">
                                            @switch($reservation->deposit_payment_method)
                                                @case('cash')
                                                    Наличные
                                                    @break
                                                @case('card')
                                                    Банковская карта
                                                    @break
                                                @case('transfer')
                                                    Перевод
                                                    @break
                                                @default
                                                    {{ $reservation->deposit_payment_method ?? 'Не указан' }}
                                            @endswitch
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #065F46; font-size: 14px; border-top: 1px solid #A7F3D0;">Дата оплаты</td>
                                        <td style="padding: 8px 0; color: #065F46; font-size: 14px; text-align: right; font-weight: 500; border-top: 1px solid #A7F3D0;">
                                            {{ $reservation->deposit_paid_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i') }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Reservation Details -->
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                                <h3 style="margin: 0 0 16px 0; color: #1f2937; font-size: 16px; font-weight: 600;">
                                    Ваше бронирование
                                </h3>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Дата</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; text-align: right; font-weight: 500;">
                                            {{ $reservation->date->format('d.m.Y') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; border-top: 1px solid #e5e7eb;">Время</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; text-align: right; font-weight: 500; border-top: 1px solid #e5e7eb;">
                                            {{ $reservation->time_range }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #6b7280; font-size: 14px; border-top: 1px solid #e5e7eb;">Гостей</td>
                                        <td style="padding: 8px 0; color: #1f2937; font-size: 14px; text-align: right; font-weight: 500; border-top: 1px solid #e5e7eb;">
                                            {{ $reservation->guests_count }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                Депозит будет учтён в счёте вашего визита. До встречи!
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 32px; border-top: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color: #9ca3af; font-size: 12px;">
                                        <p style="margin: 0 0 8px 0;">
                                            Сохраните это письмо как подтверждение оплаты.
                                        </p>
                                        @if($restaurant->phone ?? null)
                                        <p style="margin: 0;">
                                            Телефон для связи: <a href="tel:{{ $restaurant->phone }}" style="color: #10B981;">{{ $restaurant->phone }}</a>
                                        </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin: 24px 0 0 0; color: #9ca3af; font-size: 11px; text-align: center;">
                    Бронирование #{{ $reservation->id }} | Депозит оплачен {{ now()->format('d.m.Y H:i') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
