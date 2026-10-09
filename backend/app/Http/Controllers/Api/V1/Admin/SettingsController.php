<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Identity\PermissionCatalog;
use App\Domain\Payments\GatewayManager;
use App\Domain\Shipping\Courier\ShiprocketClient;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminAccess;
use App\Models\ShippingMethod;
use App\Models\TaxClass;
use App\Models\User;
use App\Notifications\SecuritySettingChangedNotification;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/** Store, tax and shipping settings (no secrets — those stay in the environment). */
class SettingsController extends Controller
{
    public function show(Settings $settings): JsonResponse
    {
        return ApiResponse::success([
            'store' => [
                'name' => $settings->get('store.name'),
                'currency' => $settings->get('store.currency'),
                'state_code' => $settings->get('store.state_code', 'TN'),
                'support_email' => $settings->get('store.support_email'),
                'legal_name' => $settings->get('store.legal_name'),
                'gstin' => $settings->get('store.gstin'),
                'address' => $settings->get('store.address'),
            ],
            'tax_classes' => TaxClass::query()->orderBy('rate_bps')->get(['uuid', 'name', 'rate_bps', 'is_default']),
            'shipping_methods' => ShippingMethod::query()->orderBy('zone')->orderBy('sort_order')->get(['uuid', 'code', 'zone', 'name', 'amount', 'free_over', 'days_min', 'days_max', 'is_active']),
            'payments' => app(GatewayManager::class)->available(),
            'integrations' => [
                'razorpay' => [
                    'configured' => filled(config('payments.razorpay.key_id')) && filled(config('payments.razorpay.key_secret')),
                    'webhook_configured' => filled(config('payments.razorpay.webhook_secret')),
                    'test_mode' => str_starts_with((string) config('payments.razorpay.key_id'), 'rzp_test_'),
                    'webhook_url' => route('webhooks.payment', ['provider' => 'razorpay']),
                ],
                'test_gateway' => app(GatewayManager::class)->testEnabled(),
                'shiprocket' => [
                    'configured' => app(ShiprocketClient::class)->configured(),
                    'webhook_configured' => filled(config('shipping.shiprocket.webhook_token')),
                    'pickup_location' => config('shipping.shiprocket.pickup_location'),
                    'test_mode' => (bool) config('shipping.shiprocket.test_mode'),
                    'webhook_url' => route('webhooks.courier'),
                ],
            ],
            'security' => [
                'admin_requires_2fa' => EnsureAdminAccess::twoFactorRequired(),
                'locked' => false,
                'you_have_2fa' => request()->user()->hasTwoFactorEnabled(),
            ],
        ]);
    }

    /** Logs in to Shiprocket with the configured API user and lists its pickup addresses. */
    public function testShiprocket(ShiprocketClient $client): JsonResponse
    {
        $client->forgetToken();
        $locations = $client->pickupLocations();
        $configured = (string) config('shipping.shiprocket.pickup_location');

        return ApiResponse::success([
            'pickup_locations' => $locations,
            'pickup_location_found' => in_array($configured, $locations, true),
        ], in_array($configured, $locations, true)
            ? 'Connected to Shiprocket.'
            : "Connected, but no pickup address is named \"{$configured}\" in Shiprocket. Set SHIPROCKET_PICKUP_LOCATION to one of the listed names.");
    }

    /**
     * Turns the staff 2FA requirement on/off. Requires the current password; refuses to
     * lock the current admin out. Turning it off emails a security alert to owners/administrators.
     */
    public function security(Request $request, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'admin_require_2fa' => ['required', 'boolean'],
            'password' => ['required', 'string', 'current_password:web'],
        ]);
        $enable = (bool) $data['admin_require_2fa'];

        if ($enable && ! $request->user()->hasTwoFactorEnabled()) {
            throw new ApiException('Set up two-factor authentication on your own account first, or you would be locked out of the admin.', 409, 'two_factor_setup_required');
        }

        $before = ['admin_require_2fa' => EnsureAdminAccess::twoFactorRequired()];
        $settings->set('security.admin_require_2fa', $enable, $request->user()->getKey());
        Audit::record('settings.security_2fa_'.($enable ? 'enabled' : 'disabled'), null, $before, ['admin_require_2fa' => $enable], $request->user());
        if ($before['admin_require_2fa'] !== $enable) {
            $owners = User::query()->role([PermissionCatalog::SUPER_ADMIN, 'administrator'])->where('is_active', true)->get();
            Notification::sendNow($owners, new SecuritySettingChangedNotification($enable, $request->user()->name, (string) $request->ip()));
        }

        return ApiResponse::success(['admin_requires_2fa' => $enable], $enable ? 'Staff two-factor authentication is now required.' : 'Staff two-factor authentication is no longer required.');
    }

    public function update(Request $request, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'store.name' => ['required', 'string', 'max:120'],
            'store.state_code' => ['required', 'string', 'max:8'],
            'store.support_email' => ['nullable', 'email', 'max:255'],
            // Printed on every tax invoice as the seller.
            'store.legal_name' => ['nullable', 'string', 'max:160'],
            'store.address' => ['nullable', 'string', 'max:400'],
            'store.gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z][Zz][0-9A-Za-z]$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $state = strtoupper((string) $request->input('store.state_code'));
                    $code = config("regions.gst_state_codes.{$state}");
                    if ($value && $code && ! str_starts_with((string) $value, (string) $code)) {
                        $fail("A GSTIN registered in this business state starts with {$code}.");
                    }
                }],
            'tax_classes' => ['array'],
            'tax_classes.*.uuid' => ['required', 'uuid'],
            'tax_classes.*.rate_bps' => ['required', 'integer', 'min:0', 'max:5000'],
            'shipping_methods' => ['array'],
            'shipping_methods.*.uuid' => ['required', 'uuid'],
            'shipping_methods.*.amount' => ['required', 'integer', 'min:0'],
            'shipping_methods.*.free_over' => ['nullable', 'integer', 'min:0'],
            'shipping_methods.*.is_active' => ['boolean'],
            'shipping_methods.*.days_min' => ['required', 'integer', 'min:0', 'max:60'],
            'shipping_methods.*.days_max' => ['required', 'integer', 'min:0', 'max:90', 'gte:shipping_methods.*.days_min'],
        ]);

        foreach ($data['store'] as $key => $value) {
            $settings->set("store.{$key}", in_array($key, ['state_code', 'gstin'], true) && $value !== null ? strtoupper((string) $value) : $value, $request->user()->getKey());
        }
        foreach ($data['tax_classes'] ?? [] as $row) {
            TaxClass::query()->where('uuid', $row['uuid'])->update(['rate_bps' => $row['rate_bps']]);
        }
        foreach ($data['shipping_methods'] ?? [] as $row) {
            ShippingMethod::query()->where('uuid', $row['uuid'])->update([
                'amount' => $row['amount'], 'free_over' => $row['free_over'] ?? null, 'is_active' => $row['is_active'] ?? true,
                'days_min' => $row['days_min'], 'days_max' => $row['days_max'],
                'description' => $row['days_min'] === $row['days_max'] ? "{$row['days_min']} working days" : "{$row['days_min']}–{$row['days_max']} working days",
            ]);
        }
        Audit::record('settings.updated', null, null, $data, $request->user());

        return ApiResponse::success(message: 'Settings saved.');
    }
}
