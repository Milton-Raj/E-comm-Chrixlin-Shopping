<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Orders\OrderAlertSettings;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use App\Support\Settings\Settings;
use App\Support\WhatsApp\WhatsAppClient;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin → Settings → Order alerts on WhatsApp (`settings.manage`). */
class OrderAlertController extends Controller
{
    public function __construct(private readonly OrderAlertSettings $alerts, private readonly WhatsAppClient $whatsapp) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->payload());
    }

    public function update(Request $request, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'whatsapp_enabled' => ['required', 'boolean'],
            // With the country code, e.g. +91 98765 43210.
            'whatsapp_number' => ['nullable', 'required_if:whatsapp_enabled,true', 'string', 'regex:/^\+?[0-9][0-9 ()-]{7,19}$/'],
        ], ['whatsapp_number.required_if' => 'Enter the WhatsApp number to send alerts to.', 'whatsapp_number.regex' => 'Enter the number with its country code, e.g. +91 98765 43210.']);

        $digits = preg_replace('/\D+/', '', (string) ($data['whatsapp_number'] ?? ''));
        if ($digits !== '' && (strlen($digits) < 10 || strlen($digits) > 15)) {
            throw new ApiException('Validation failed', 422, null, ['whatsapp_number' => ['Enter the number with its country code, e.g. +91 98765 43210.']]);
        }

        $before = $this->audited();
        $settings->set('alerts.whatsapp_enabled', (bool) $data['whatsapp_enabled'], $request->user()->getKey());
        $settings->set('alerts.whatsapp_number', $digits !== '' ? $digits : null, $request->user()->getKey());
        Audit::record('settings.order_alerts_updated', null, $before, $this->audited(), $request->user());

        return ApiResponse::success($this->payload(), 'Order alert settings saved.');
    }

    /** Sends a sample alert to the saved number with the real template, so the owner sees it arrive. */
    public function test(Request $request): JsonResponse
    {
        $to = $this->alerts->whatsappNumber() ?? throw new ApiException('Save a WhatsApp number first.', 422, 'number_missing');
        try {
            $this->whatsapp->sendTemplate($to, (string) config('services.whatsapp.order_template'), (string) config('services.whatsapp.template_language'), [
                'TEST-0001', '₹1,234.00', '1 item', 'Chennai, Tamil Nadu', rtrim((string) config('commerce.frontend_url'), '/').'/admin/orders',
            ]);
        } catch (WhatsAppException $e) {
            throw new ApiException('WhatsApp did not send the test: '.$e->getMessage(), 422, 'whatsapp_failed');
        }
        Audit::record('settings.order_alerts_tested', null, null, ['to' => WhatsAppClient::mask($to)], $request->user());

        return ApiResponse::success($this->payload(), 'Test message sent to '.WhatsAppClient::mask($to).'. It should arrive within a few seconds.');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $number = $this->alerts->whatsappNumber();

        return [
            'whatsapp_enabled' => (bool) app(Settings::class)->get('alerts.whatsapp_enabled', false),
            'whatsapp_number' => $number !== null ? '+'.$number : null,
            'connected' => $this->whatsapp->configured(),
            'template' => (string) config('services.whatsapp.order_template'),
            'template_language' => (string) config('services.whatsapp.template_language'),
        ];
    }

    /** @return array{whatsapp_enabled: bool, whatsapp_number: string|null} */
    private function audited(): array
    {
        $number = $this->alerts->whatsappNumber();

        return ['whatsapp_enabled' => (bool) app(Settings::class)->get('alerts.whatsapp_enabled', false), 'whatsapp_number' => $number !== null ? WhatsAppClient::mask($number) : null];
    }
}
