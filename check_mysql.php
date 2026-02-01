<?php
$pdo = new PDO("mysql:host=127.0.1.28;port=3306", 'root', '', [
    PDO::ATTR_TIMEOUT => 2,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Check all databases
$dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
echo "Databases: " . implode(', ', $dbs) . PHP_EOL . PHP_EOL;

foreach($dbs as $db) {
    if (in_array($db, ['information_schema', 'mysql', 'performance_schema', 'sys'])) continue;

    $pdo->exec("USE `$db`");

    // Check if users table exists
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "DB '$db' -> users: $count rows" . PHP_EOL;
        if ($count > 0) {
            $users = $pdo->query("SELECT id, name, email, role, is_active, LEFT(password, 12) as pwd FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach($users as $u) {
                echo "  " . $u['id'] . ' | ' . $u['name'] . ' | ' . $u['email'] . ' | role:' . $u['role'] . ' | active:' . $u['is_active'] . ' | pwd:' . $u['pwd'] . PHP_EOL;
            }
        }

        // Also check tables count
        $tables_count = $pdo->query("SELECT COUNT(*) FROM tables")->fetchColumn();
        $orders_count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $zones_count = $pdo->query("SELECT COUNT(*) FROM zones")->fetchColumn();
        echo "  tables: $tables_count, orders: $orders_count, zones: $zones_count" . PHP_EOL;
    } catch(Exception $e) {
        // Check what tables exist
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "DB '$db' -> " . count($tables) . " tables: " . implode(', ', array_slice($tables, 0, 10)) . (count($tables) > 10 ? '...' : '') . PHP_EOL;
    }
    echo PHP_EOL;
}
