<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

try {
    $count = User::count();
    echo "Total users: " . $count . "\n";
    if ($count > 0) {
        $user = User::first();
        echo "First user - Username: " . $user->username . ", Email: " . $user->email . ", Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}