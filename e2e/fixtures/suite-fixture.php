<?php

use App\Models\User;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

try {
    $root = getenv('HB_E2E_APP_ROOT') ?: getcwd();
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $db = config('database.connections.'.config('database.default'));
    if (! app()->environment('local') || ! in_array($db['host'] ?? '', ['127.0.0.1', 'localhost'], true)
        || ($db['database'] ?? '') !== 'hbiz2_qa' || config('database.connections.whip.driver') !== 'sqlite') {
        throw new RuntimeException('Suite fixtures require the local QA database and fake WHIP.');
    }

    $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $run = $data['run'] ?? '';
    if (! is_string($run) || ! preg_match('/^e2e-[a-z0-9]+(?:-[a-z0-9]+)*$/', $run)) {
        throw new InvalidArgumentException('Invalid run identifier.');
    }

    $action = $data['action'] ?? ($argv[1] ?? '');
    if ($action === 'setup') {
        // Generated test signing data; the browser intercepts the sandbox POST.
        $gateway = app(Ipay88Settings::class);
        $gateway->merchant_code = 'E2E';
        $gateway->merchant_key = bin2hex(random_bytes(24));
        $gateway->sandbox = true;
        $gateway->save();
        if (! is_string($data['password'] ?? null) || strlen($data['password']) < 16) {
            throw new InvalidArgumentException('A generated password is required.');
        }
        foreach (['buyer', 'admin', 'seller'] as $role) {
            $user = User::create([
                'name' => $run.' '.$role,
                'email' => $run.'-'.$role.'@halalbizs.test',
                'password' => $data['password'],
            ]);
            $user->forceFill([
                'email_verified_at' => now(),
                'two_factor_method' => $role === 'admin' ? 'email' : null,
                'is_superadmin' => $role === 'admin',
            ])->save();
            $user->assignRole($role);
            if ($role === 'admin') {
                $user->syncPermissions(RoleSeeder::ADMIN_PERMISSIONS);
            }
            if ($role === 'buyer') {
                $user->addresses()->create([
                    'label' => 'Home', 'recipient_name' => 'E2E Buyer', 'phone' => '0123456789',
                    'line1' => '1 Test Road', 'postcode' => '50000', 'city' => 'Kuala Lumpur',
                    'state' => 'Selangor', 'country' => 'MY', 'is_default' => true,
                ]);
            }
        }
    } elseif ($action === 'password') {
        $email = $data['email'] ?? '';
        if (! is_string($email) || ! str_starts_with($email, $run.'-') || ! str_ends_with($email, '@halalbizs.test')
            || ! is_string($data['password'] ?? null) || strlen($data['password']) < 16) {
            throw new InvalidArgumentException('Invalid run account or password.');
        }
        User::where('email', $email)->firstOrFail()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();
    } elseif ($action === 'store') {
        $store = User::where('email', $run.'-seller@halalbizs.test')->firstOrFail()->store;
        echo json_encode(['slug' => $store->slug]);
    } elseif ($action === 'cleanup') {
        // Orders and other journeys can retain foreign keys to users. The caller
        // restores the local DB snapshot after the suite; do not cascade into
        // records that were not created by this run.
        User::where('email', 'like', $run.'-%@halalbizs.test')->get()->each(function ($user) {
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });
    } else {
        throw new InvalidArgumentException('Unknown action.');
    }
} catch (Throwable $error) {
    // Exception messages and SQL bindings can contain credentials.
    fwrite(STDERR, "Suite fixture failed.\n");
    exit(1);
}
