<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .message {
            color: #666;
            margin-bottom: 25px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
        }
        .button:hover {
            opacity: 0.9;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
            font-size: 14px;
            color: #92400e;
        }
        .link-fallback {
            margin-top: 20px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            word-break: break-all;
        }
        .link-fallback a {
            color: #f97316;
        }
        .footer {
            text-align: center;
            padding: 20px 30px;
            background: #f9fafb;
            color: #999;
            font-size: 13px;
        }
        .footer a {
            color: #f97316;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>MenuLab</h1>
            </div>

            <div class="content">
                <p class="greeting">Здравствуйте, {{ $userName }}!</p>

                <p class="message">
                    Мы получили запрос на восстановление пароля для вашего аккаунта.
                    Нажмите кнопку ниже, чтобы создать новый пароль:
                </p>

                <div class="button-container">
                    <a href="{{ $resetUrl }}" class="button">Сбросить пароль</a>
                </div>

                <div class="warning">
                    Ссылка действительна в течение <strong>1 часа</strong>.
                    Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.
                </div>

                <div class="link-fallback">
                    Если кнопка не работает, скопируйте и вставьте эту ссылку в браузер:<br>
                    <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
                </div>
            </div>

            <div class="footer">
                <p>
                    Это автоматическое сообщение от системы MenuLab.<br>
                    Пожалуйста, не отвечайте на это письмо.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
