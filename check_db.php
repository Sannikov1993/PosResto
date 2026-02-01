<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// List all tables and row counts
$tables = Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
echo "=== Tables in DB ===" . PHP_EOL;
foreach($tables as $t) {
    $count = Illuminate\Support\Facades\DB::select("SELECT COUNT(*) as cnt FROM \"{$t->name}\"");
    echo $t->name . ': ' . $count[0]->cnt . ' rows' . PHP_EOL;
}

// Check users table schema
echo PHP_EOL . "=== Users table schema ===" . PHP_EOL;
$cols = Illuminate\Support\Facades\DB::select("PRAGMA table_info(users)");
foreach($cols as $c) {
    echo $c->name . ' (' . $c->type . ')' . PHP_EOL;
}
