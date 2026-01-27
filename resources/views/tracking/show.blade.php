<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Заказ {{ $order->order_number }} - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            padding-bottom: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .header .order-number {
            font-size: 24px;
            font-weight: 700;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }

        .status-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .status-info h2 {
            font-size: 20px;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .status-info p {
            color: #6b7280;
            font-size: 14px;
        }

        .eta-badge {
            display: inline-block;
            background: #ecfdf5;
            color: #059669;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 8px;
        }

        .progress-container {
            padding: 20px 0;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 30px;
            right: 30px;
            height: 3px;
            background: #e5e7eb;
        }

        .progress-line-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.5s ease;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #9ca3af;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .step.completed .step-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .step.current .step-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.3); }
            50% { box-shadow: 0 0 0 8px rgba(102, 126, 234, 0.1); }
        }

        .step-label {
            font-size: 11px;
            color: #6b7280;
            text-align: center;
            max-width: 60px;
        }

        .step.completed .step-label,
        .step.current .step-label {
            color: #1f2937;
            font-weight: 500;
        }

        .step-time {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .info-card h3 {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .courier-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .courier-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .courier-details h4 {
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .courier-details a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .order-items {
            list-style: none;
        }

        .order-items li {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .order-items li:last-child {
            border-bottom: none;
        }

        .item-name {
            color: #1f2937;
        }

        .item-qty {
            color: #6b7280;
            font-size: 14px;
        }

        .item-price {
            color: #1f2937;
            font-weight: 500;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 2px solid #e5e7eb;
            margin-top: 8px;
        }

        .order-total span:first-child {
            font-weight: 600;
            color: #1f2937;
        }

        .order-total span:last-child {
            font-weight: 700;
            font-size: 18px;
            color: #1f2937;
        }

        .address-text {
            color: #1f2937;
            line-height: 1.5;
        }

        .back-link {
            display: block;
            text-align: center;
            color: #667eea;
            text-decoration: none;
            padding: 16px;
            font-weight: 500;
        }

        .cancelled-banner {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 16px;
        }

        .cancelled-banner i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .completed-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #059669;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 16px;
        }

        .completed-banner i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .update-time {
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            margin-top: 20px;
        }

        @media (max-width: 400px) {
            .step-label {
                font-size: 10px;
                max-width: 50px;
            }

            .step-icon {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Заказ</h1>
        <div class="order-number">{{ $order->order_number }}</div>
    </div>

    <div class="container">
        @if($order->status === 'cancelled')
            <div class="cancelled-banner">
                <i class="fas fa-times-circle"></i>
                <strong>Заказ отменён</strong>
            </div>
        @elseif($order->status === 'completed')
            <div class="completed-banner">
                <i class="fas fa-check-circle"></i>
                <strong>Заказ доставлен!</strong>
                <p>Спасибо, что выбрали нас!</p>
            </div>
        @else
            <div class="status-card">
                <div class="status-header">
                    <div class="status-icon" id="statusIcon" style="background: {{ $statusSteps[array_search(true, array_column($statusSteps, 'current'))]['completed'] ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : '#e5e7eb' }}">
                        @php
                            $currentStep = collect($statusSteps)->firstWhere('current', true);
                            $icons = [
                                'clipboard-check' => 'fa-clipboard-check',
                                'fire' => 'fa-fire',
                                'check-circle' => 'fa-check-circle',
                                'truck' => 'fa-truck',
                                'home' => 'fa-home',
                            ];
                        @endphp
                        <i class="fas {{ $icons[$currentStep['icon']] ?? 'fa-box' }}" style="color: white;"></i>
                    </div>
                    <div class="status-info">
                        <h2 id="statusLabel">
                            @switch($order->status)
                                @case('new') Заказ принят @break
                                @case('cooking') Готовится на кухне @break
                                @case('ready') Готов к отправке @break
                                @case('delivering') Курьер в пути @break
                                @default {{ $order->status }}
                            @endswitch
                        </h2>
                        <p id="statusTime">Обновлено: {{ now()->format('H:i') }}</p>
                        @if(!in_array($order->status, ['completed', 'cancelled']))
                            <div class="eta-badge" id="etaBadge">
                                <i class="fas fa-clock"></i>
                                ~{{ $order->estimated_delivery_minutes ?? $order->deliveryZone?->estimated_time ?? 45 }} мин
                            </div>
                        @endif
                    </div>
                </div>

                <div class="progress-container">
                    <div class="progress-steps">
                        <div class="progress-line">
                            @php
                                $progress = match($order->status) {
                                    'new' => 0,
                                    'cooking' => 25,
                                    'ready' => 50,
                                    'delivering' => 75,
                                    'completed' => 100,
                                    default => 0,
                                };
                            @endphp
                            <div class="progress-line-fill" id="progressFill" style="width: {{ $progress }}%"></div>
                        </div>

                        @foreach($statusSteps as $step)
                            <div class="step {{ $step['completed'] ? 'completed' : '' }} {{ $step['current'] ? 'current' : '' }}">
                                <div class="step-icon">
                                    <i class="fas {{ $icons[$step['icon']] ?? 'fa-circle' }}"></i>
                                </div>
                                <span class="step-label">{{ $step['label'] }}</span>
                                @if($step['time'])
                                    <span class="step-time">{{ $step['time'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if($order->courier && in_array($order->status, ['delivering']))
            <div class="info-card" id="courierCard">
                <h3><i class="fas fa-motorcycle"></i> Курьер</h3>
                <div class="courier-info">
                    <div class="courier-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="courier-details">
                        <h4>{{ $order->courier->name }}</h4>
                        @if($order->courier->phone)
                            <a href="tel:{{ $order->courier->phone }}">
                                <i class="fas fa-phone"></i> {{ $order->courier->phone }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="info-card">
            <h3><i class="fas fa-map-marker-alt"></i> Адрес доставки</h3>
            <p class="address-text">{{ $order->delivery_address }}</p>
        </div>

        @if($order->items && $order->items->count() > 0)
            <div class="info-card">
                <h3><i class="fas fa-shopping-bag"></i> Состав заказа</h3>
                <ul class="order-items">
                    @foreach($order->items as $item)
                        <li>
                            <span>
                                <span class="item-name">{{ $item->name }}</span>
                                <span class="item-qty"> x {{ $item->quantity }}</span>
                            </span>
                            <span class="item-price">{{ number_format($item->price * $item->quantity, 0, ',', ' ') }} ₽</span>
                        </li>
                    @endforeach
                </ul>
                <div class="order-total">
                    <span>Итого</span>
                    <span>{{ number_format($order->total, 0, ',', ' ') }} ₽</span>
                </div>
            </div>
        @endif

        <a href="/track" class="back-link">
            <i class="fas fa-arrow-left"></i> Отследить другой заказ
        </a>

        <p class="update-time" id="updateTime">
            Автообновление каждые 30 сек
        </p>
    </div>

    @if(!in_array($order->status, ['completed', 'cancelled']))
    <script>
        const orderNumber = '{{ $order->order_number }}';
        let updateInterval;

        async function updateStatus() {
            try {
                const response = await fetch(`/api/track/${encodeURIComponent(orderNumber)}/status`);
                const data = await response.json();

                if (data.error) return;

                // Обновляем статус
                const statusLabel = document.getElementById('statusLabel');
                if (statusLabel) {
                    statusLabel.textContent = data.status_label;
                }

                // Обновляем прогресс
                const progressFill = document.getElementById('progressFill');
                if (progressFill) {
                    progressFill.style.width = data.progress + '%';
                }

                // Обновляем ETA
                const etaBadge = document.getElementById('etaBadge');
                if (etaBadge && data.eta) {
                    etaBadge.innerHTML = `<i class="fas fa-clock"></i> ${data.eta}`;
                }

                // Обновляем время
                const updateTime = document.getElementById('updateTime');
                if (updateTime) {
                    const now = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
                    updateTime.textContent = `Обновлено в ${now}`;
                }

                // Если заказ завершён - перезагружаем страницу
                if (data.is_completed) {
                    clearInterval(updateInterval);
                    setTimeout(() => location.reload(), 1000);
                }

            } catch (error) {
                console.error('Ошибка обновления статуса:', error);
            }
        }

        // Обновляем каждые 30 секунд
        updateInterval = setInterval(updateStatus, 30000);

        // Первое обновление через 30 секунд
        setTimeout(updateStatus, 30000);
    </script>
    @endif
</body>
</html>
