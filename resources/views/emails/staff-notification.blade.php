<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); padding: 32px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 8px;">{{ $icon }}</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                {{ $notification->title }}
                            </h1>
                            <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.8); font-size: 14px;">
                                {{ $typeLabel }}
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            @if($user)
                            <p style="margin: 0 0 24px 0; color: #374151; font-size: 16px;">
                                Здравствуйте, <strong>{{ $user->name }}</strong>!
                            </p>
                            @endif

                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                                <p style="margin: 0; color: #1f2937; font-size: 16px; line-height: 1.6; white-space: pre-line;">{{ $notification->message }}</p>
                            </div>

                            @if($notification->data && count($notification->data) > 0)
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                @foreach($notification->data as $key => $value)
                                @if(!is_array($value))
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px; border-bottom: 1px solid #e5e7eb;">
                                        {{ ucfirst(str_replace('_', ' ', $key)) }}
                                    </td>
                                    <td style="padding: 8px 0; color: #1f2937; font-size: 14px; text-align: right; border-bottom: 1px solid #e5e7eb; font-weight: 500;">
                                        {{ is_numeric($value) ? number_format($value, 0, ',', ' ') : $value }}
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            </table>
                            @endif

                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ url('/staff-login') }}"
                                           style="display: inline-block; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 14px;">
                                            Открыть PosResto
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 32px; border-top: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color: #9ca3af; font-size: 12px;">
                                        <p style="margin: 0 0 8px 0;">
                                            Это автоматическое уведомление от системы PosResto.
                                        </p>
                                        <p style="margin: 0;">
                                            Чтобы изменить настройки уведомлений, перейдите в
                                            <a href="{{ url('/staff-login') }}" style="color: #f97316;">личный кабинет</a>.
                                        </p>
                                    </td>
                                    <td align="right" style="vertical-align: top;">
                                        <img src="{{ url('/images/logo/posresto_icon.svg') }}" alt="PosResto" width="32" height="32" style="opacity: 0.5;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Unsubscribe -->
                <p style="margin: 24px 0 0 0; color: #9ca3af; font-size: 11px; text-align: center;">
                    {{ date('d.m.Y H:i') }} | {{ $notification->type }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
