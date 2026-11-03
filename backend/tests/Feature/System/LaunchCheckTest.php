<?php

use App\Domain\Identity\PermissionCatalog;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

function launchCheck(string $options = ''): array
{
    $code = Artisan::call(trim('app:launch-check '.$options));

    return [$code, Artisan::output()];
}

it('fails without payments or a running scheduler', function () {
    config(['payments.razorpay.key_id' => null, 'payments.razorpay.key_secret' => null]);

    [$code, $out] = launchCheck();

    expect($code)->toBe(1)
        ->and($out)->toContain('Razorpay keys are missing')
        ->and($out)->toContain('has never run');
});

it('flags demo accounts and staff without two-factor authentication', function () {
    User::factory()->create(['email' => 'admin@example.test'])->assignRole(PermissionCatalog::SUPER_ADMIN);
    User::factory()->staff()->create(['email' => 'owner@chrixlin.test']);

    [$code, $out] = launchCheck();

    expect($code)->toBe(1)
        ->and($out)->toContain('| FAIL   | No demo accounts')
        ->and($out)->toContain('not set up yet: admin@example.test')
        ->and($out)->not->toContain('not set up yet: owner@chrixlin.test');
});

it('passes the scheduler check after a heartbeat and never prints secrets', function () {
    Cache::forever(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->timestamp);
    config(['payments.razorpay.key_id' => 'rzp_test_abc', 'payments.razorpay.key_secret' => 'sk-unique-value', 'payments.razorpay.webhook_secret' => 'wh-unique-value']);

    [, $out] = launchCheck();

    expect($out)->toContain('Razorpay TEST keys')
        ->and($out)->toContain('| PASS   | Scheduler (cron)')
        ->and($out)->not->toContain('sk-unique-value')
        ->and($out)->not->toContain('wh-unique-value')
        ->and($out)->not->toContain('rzp_test_abc');
});

it('sends a test email only when asked', function () {
    Mail::fake();

    launchCheck();
    Mail::assertNothingOutgoing();

    [, $out] = launchCheck('--mail-to=owner@chrixlin.test');
    expect($out)->toContain('sent to owner@chrixlin.test');
});
