<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;

echo "Available roles:\n";
$roles = Role::all()->pluck('name');
foreach($roles as $role) {
    echo "- " . $role . "\n";
}
