<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Notifications\EnquiryReceivedNotification;
use App\Support\Http\ApiResponse;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/** Corporate & bulk gifting enquiry form (public, rate limited, honeypot). */
class EnquiryController extends Controller
{
    public function store(Request $request, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9 ]{7,17}$/'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'budget' => ['nullable', 'string', 'max:60'],
            'occasion' => ['nullable', 'string', 'max:80'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'string', 'max:0'], // honeypot: real people never fill it
        ]);
        unset($data['website']);

        $enquiry = Enquiry::create([...$data, 'type' => 'corporate_gifting', 'status' => 'new', 'ip_address' => $request->ip()]);

        $inbox = $settings->get('store.support_email') ?: config('mail.from.address');
        if ($inbox) {
            Notification::route('mail', $inbox)->notify(new EnquiryReceivedNotification($enquiry, forStore: true));
        }
        Notification::route('mail', $enquiry->email)->notify(new EnquiryReceivedNotification($enquiry, forStore: false));
        Log::channel('orders')->info('Gifting enquiry received.', ['enquiry' => $enquiry->uuid]);

        return ApiResponse::success(['uuid' => $enquiry->uuid], 'Thank you — we will be in touch soon.', status: 201);
    }
}
