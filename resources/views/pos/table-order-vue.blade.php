<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $linkedTableNumbers = '';
        if (!empty($linkedTableIds)) {
            $linkedTables = \App\Models\Table::whereIn('id', $linkedTableIds)->orderBy('number')->get();
            $linkedTableNumbers = $linkedTables->map(fn($t) => $t->name ?: $t->number)->implode(' + ');
        } else {
            $linkedTableNumbers = $table->name ?: $table->number;
        }
    @endphp
    <title>MenuLab - Стол {{ $linkedTableNumbers }}</title>
    <link rel="icon" type="image/svg+xml" href="/images/logo/menulab_favicon.svg">
    <link rel="apple-touch-icon" href="/images/logo/menulab_icon.svg">
    @vite(['resources/js/table-order/table-order.js'])
</head>
<body class="h-screen overflow-hidden">
    <div id="table-order-app"></div>

    <script id="table-order-data" type="application/json">
        {!! json_encode([
            'table' => $table,
            'orders' => $orders,
            'categories' => $categories,
            'reservation' => $reservation,
            'linkedTableIds' => $linkedTableIds,
            'linkedTableNumbers' => $linkedTableNumbers,
            'initialGuests' => $initialGuests,
        ]) !!}
    </script>
</body>
</html>
