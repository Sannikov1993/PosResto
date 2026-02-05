<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Бронирование отменено</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); padding: 32px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 8px;">❌</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                Бронирование отменено
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
                                К сожалению, ваше бронирование было отменено.
                            </p>

                            <!-- Cancelled Reservation Details -->
                            <div style="background-color: #FEF2F2; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 2px solid #FECACA;">
                                <h3 style="margin: 0 0 16px 0; color: #991B1B; font-size: 16px; font-weight: 600;">
                                    Отменённое бронирование
                                </h3>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 8px 0; color: #991B1B; font-size: 14px;">Дата</td>
                                        <td style="padding: 8px 0; color: #991B1B; font-size: 14px; text-align: right; font-weight: 500; text-decoration: line-through;">
                                            {{ $reservation->date->format('d.m.Y') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #991B1B; font-size: 14px; border-top: 1px solid #FECACA;">Время</td>
                                        <td style="padding: 8px 0; color: #991B1B; font-size: 14px; text-align: right; font-weight: 500; border-top: 1px solid #FECACA; text-decoration: line-through;">
                                            {{ $reservation->time_range }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #991B1B; font-size: 14px; border-top: 1px solid #FECACA;">Гостей</td>
                                        <td style="padding: 8px 0; color: #991B1B; font-size: 14px; text-align: right; font-weight: 500; border-top: 1px solid #FECACA;">
                                            {{ $reservation->guests_count }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            @if($reservation->cancellation_reason)
                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                                <p style="margin: 0; color: #374151; font-size: 14px;">
                                    <strong>Причина отмены:</strong> {{ $reservation->cancellation_reason }}
                                </p>
                            </div>
                            @endif

                            @if($depositRefunded && $reservation->deposit > 0)
                            <div style="background-color: #ECFDF5; border-radius: 12px; padding: 16px; margin-bottom: 24px; border: 1px solid #10B981;">
                                <p style="margin: 0; color: #065F46; font-size: 14px;">
                                    <strong>💰 Депозит возвращён:</strong> {{ number_format($reservation->deposit, 0, ',', ' ') }} ₽
                                </p>
                            </div>
                            @elseif($reservation->deposit > 0 && $reservation->deposit_status === 'paid')
                            <div style="background-color: #FEF3C7; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                                <p style="margin: 0; color: #92400E; font-size: 14px;">
                                    <strong>Депозит:</strong> {{ number_format($reservation->deposit, 0, ',', ' ') }} ₽ - свяжитесь с рестораном для возврата
                                </p>
                            </div>
                            @endif

                            <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                Будем рады видеть вас снова! Вы всегда можете сделать новое бронирование.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 32px; border-top: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color: #9ca3af; font-size: 12px;">
                                        @if($restaurant->phone ?? null)
                                        <p style="margin: 0;">
                                            Телефон для связи: <a href="tel:{{ $restaurant->phone }}" style="color: #EF4444;">{{ $restaurant->phone }}</a>
                                        </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin: 24px 0 0 0; color: #9ca3af; font-size: 11px; text-align: center;">
                    Бронирование #{{ $reservation->id }} | {{ now()->format('d.m.Y H:i') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
