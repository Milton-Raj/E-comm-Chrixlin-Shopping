<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp Business Platform (Meta Cloud API). Sends approved template messages only —
 * the one kind a business may start a conversation with. The token never leaves the server
 * and is never logged.
 */
class WhatsAppClient
{
    public function configured(): bool
    {
        return filled(config('services.whatsapp.access_token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * @param  string  $to  international number, digits only (e.g. 919876543210)
     * @param  list<string>  $bodyParameters  the template's {{1}}, {{2}}, … in order
     * @return string WhatsApp message id
     */
    public function sendTemplate(string $to, string $template, string $language, array $bodyParameters): string
    {
        if (! $this->configured()) {
            throw new WhatsAppException('WhatsApp is not connected on the server.', retryable: false);
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', config('services.whatsapp.api_version'), config('services.whatsapp.phone_number_id'));
        try {
            $response = Http::withToken((string) config('services.whatsapp.access_token'))->acceptJson()->timeout(10)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $language],
                    'components' => [[
                        'type' => 'body',
                        // WhatsApp rejects empty parameters and new lines inside them.
                        'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => trim(preg_replace('/\s+/', ' ', $text) ?: '-')], $bodyParameters),
                    ]],
                ],
            ]);
        } catch (Throwable $e) {
            throw new WhatsAppException('Could not reach WhatsApp.', retryable: true);
        }

        if ($response->successful() && is_string($id = $response->json('messages.0.id'))) {
            return $id;
        }

        $code = $response->json('error.code');
        $message = (string) ($response->json('error.error_data.details') ?: $response->json('error.message') ?: 'WhatsApp refused the message.');
        // Rate limits and Meta's own outages are worth retrying; token, number and template problems are not.
        $retryable = $response->status() >= 500 || $response->status() === 429 || in_array($code, [4, 80007, 130429, 131000, 131016], true);
        Log::channel('orders')->warning('WhatsApp send failed.', ['status' => $response->status(), 'code' => $code, 'to' => self::mask($to)]);

        throw new WhatsAppException($message, $retryable, is_int($code) ? $code : null);
    }

    /** "919876543210" → "+91•••••3210" for logs and screens. */
    public static function mask(string $number): string
    {
        return '+'.substr($number, 0, 2).str_repeat('•', max(0, strlen($number) - 6)).substr($number, -4);
    }
}
