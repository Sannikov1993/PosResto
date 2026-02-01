<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Users in DB ===" . PHP_EOL;

$users = App\Models\User::all();
echo "Total users: " . $users->count() . PHP_EOL;

foreach($users as $u) {
    $pwdInfo = '';
    $pwd = $u->password ?? '';
    if (str_starts_with($pwd, '$2y$') || str_starts_with($pwd, '$2a$')) {
        $pwdInfo = 'bcrypt_hash';
    } elseif (empty($pwd)) {
        $pwdInfo = 'EMPTY';
    } else {
        $pwdInfo = 'other:' . substr($pwd, 0, 20);
    }
    echo $u->id . ' | ' . ($u->name ?? 'NULL') . ' | email:' . ($u->email ?? 'NULL') . ' | login:' . ($u->login ?? 'NULL') . ' | role:' . ($u->role ?? 'NULL') . ' | active:' . ($u->is_active ? '1' : '0') . ' | pwd:' . $pwdInfo . PHP_EOL;
}

// Test password check for admin
echo PHP_EOL . "=== Password test ===" . PHP_EOL;
$admin = App\Models\User::where('role', 'admin')->orWhere('role', 'owner')->first();
if ($admin) {
    echo "Admin found: " . $admin->email . " (id=" . $admin->id . ")" . PHP_EOL;
    echo "Password hash: " . substr($admin->password ?? 'NULL', 0, 30) . PHP_EOL;
    echo "Check 'admin123': " . (Illuminate\Support\Facades\Hash::check('admin123', $admin->password) ? 'YES' : 'NO') . PHP_EOL;
    echo "Check 'password': " . (Illuminate\Support\Facades\Hash::check('password', $admin->password) ? 'YES' : 'NO') . PHP_EOL;
    echo "Check '123456': " . (Illuminate\Support\Facades\Hash::check('123456', $admin->password) ? 'YES' : 'NO') . PHP_EOL;
} else {
    echo "No admin/owner user found!" . PHP_EOL;
}
