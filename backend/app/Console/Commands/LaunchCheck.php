<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Payments\GatewayManager;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Http\Middleware\EnsureAdminAccess;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Go-live readiness report (DEPLOYMENT.md §9) for the items only the server can see.
 * Read-only unless --mail-to is given; never prints secrets.
 */
#[Signature('app:launch-check {--mail-to= : Also send a test email to this address}')]
#[Description('Report production readiness: config, accounts, 2FA, payments, shipping, mail, scheduler and queue')]
class LaunchCheck extends Command
{
    private const string PASS = 'PASS';

    private const string WARN = 'WARN';

    private const string FAIL = 'FAIL';

    /** @var list<array{string, string, string}> */
    private array $rows = [];

    public function handle(GatewayManager $gateways): int
    {
        $this->checkApp();
        $this->checkAccounts();
        $this->checkPayments($gateways);
        $this->checkShipping();
        $this->checkMail();
        $this->checkBackgroundWork();
        $this->checkCatalog();

        $this->table(['Result', 'Check', 'Detail'], $this->rows);

        $results = array_column($this->rows, 0);
        $failed = count(array_keys($results, self::FAIL, true));
        $warned = count(array_keys($results, self::WARN, true));
        $this->line("{$failed} failed, {$warned} warnings.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkApp(): void
    {
        $this->add(app()->isProduction() ? self::PASS : self::FAIL, 'Environment', 'APP_ENV='.app()->environment());
        $this->add(config('app.debug') ? self::FAIL : self::PASS, 'Debug mode', config('app.debug') ? 'APP_DEBUG is on' : 'off');
        $this->add(str_starts_with((string) config('app.url'), 'https://') ? self::PASS : self::FAIL, 'App URL uses HTTPS', (string) config('app.url'));
        $this->add(filled(config('commerce.health_token')) ? self::PASS : self::WARN, 'Health token', filled(config('commerce.health_token')) ? 'set' : 'HEALTH_TOKEN is empty, so monitoring cannot see the scheduler or queue');
    }

    private function checkAccounts(): void
    {
        $demo = User::query()->where(fn ($q) => $q->where('email', 'like', '%@example.test')->orWhere('email', 'like', '%@example.com'))->pluck('email');
        $this->add($demo->isEmpty() ? self::PASS : self::FAIL, 'No demo accounts', $demo->isEmpty() ? 'none found' : $demo->implode(', '));

        $required = EnsureAdminAccess::twoFactorRequired();
        $this->add($required ? self::PASS : self::WARN, 'Staff 2FA required', $required ? 'on' : 'switched off in Settings → Security');

        $staff = User::query()->where('is_active', true)->with('roles')->get()->filter(fn (User $u) => $u->isStaff());
        $missing = $staff->reject(fn (User $u) => $u->hasTwoFactorEnabled())->pluck('email');
        $this->add($missing->isEmpty() ? self::PASS : self::WARN, 'Staff with 2FA set up', $missing->isEmpty()
            ? "all {$staff->count()} active staff"
            : 'not set up yet: '.$missing->implode(', '));
    }

    private function checkPayments(GatewayManager $gateways): void
    {
        $methods = collect($gateways->available())->pluck('key');
        if (! $methods->contains('razorpay')) {
            $this->add(self::FAIL, 'Payments', 'Razorpay keys are missing, so checkout cannot take payment');

            return;
        }

        $this->add($gateways->razorpayTestMode() ? self::WARN : self::PASS, 'Payments', $gateways->razorpayTestMode() ? 'Razorpay TEST keys (no real money)' : 'Razorpay live keys');
        $this->add(filled(config('payments.razorpay.webhook_secret')) ? self::PASS : self::FAIL, 'Razorpay webhook secret', filled(config('payments.razorpay.webhook_secret')) ? 'set' : 'RAZORPAY_WEBHOOK_SECRET is empty');
    }

    private function checkShipping(): void
    {
        $test = (bool) config('shipping.shiprocket.test_mode');
        $this->add($test ? self::WARN : self::PASS, 'Shiprocket', $test ? 'test mode: no courier is booked' : 'live mode');
    }

    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');
        $this->add(in_array($mailer, ['log', 'array'], true) ? self::FAIL : self::PASS, 'Mailer', $mailer.' from '.config('mail.from.address'));

        $to = $this->option('mail-to');
        if (! is_string($to) || $to === '') {
            return;
        }
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->add(self::FAIL, 'Test email', 'invalid address');

            return;
        }

        try {
            Mail::raw('This is a test email from the Chrixlin launch check. No action is needed.', fn ($m) => $m->to($to)->subject('Chrixlin launch check'));
            $this->add(self::PASS, 'Test email', "sent to {$to}; check the inbox and spam folder");
        } catch (Throwable $e) {
            $this->add(self::FAIL, 'Test email', 'sending failed: '.class_basename($e));
        }
    }

    private function checkBackgroundWork(): void
    {
        $beat = Cache::get(HealthController::SCHEDULER_HEARTBEAT_KEY);
        $age = is_int($beat) ? now()->timestamp - $beat : null;
        $this->add($age !== null && $age <= 180 ? self::PASS : self::FAIL, 'Scheduler (cron)', $age === null ? 'has never run: add the cron job for schedule:run' : "last ran {$age}s ago");

        $oldest = DB::table('jobs')->min('available_at');
        $lag = $oldest ? max(0, now()->timestamp - (int) $oldest) : 0;
        $this->add($lag <= 300 ? self::PASS : self::WARN, 'Queue', DB::table('jobs')->count()." waiting, oldest {$lag}s");

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $this->add($failed === 0 ? self::PASS : self::WARN, 'Failed jobs (24 h)', (string) $failed);
    }

    private function checkCatalog(): void
    {
        $tests = Product::query()->where('status', ProductStatus::Active)->where('name', 'like', 'TEST %')->pluck('name');
        $this->add($tests->isEmpty() ? self::PASS : self::WARN, 'No visible TEST products', $tests->isEmpty() ? 'none' : $tests->implode(', '));
    }

    private function add(string $result, string $check, string $detail): void
    {
        $this->rows[] = [$result, $check, $detail];
    }
}
