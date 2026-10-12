<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Content\SiteContent;
use App\Domain\Marketing\MarketingSettings;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/** Owner-edited storefront text; keys missing here use the storefront's default copy. */
class SiteContentController extends Controller
{
    public function __invoke(SiteContent $content, MarketingSettings $marketing): JsonResponse
    {
        return ApiResponse::success([
            'texts' => (object) $content->texts(),
            'editorial_image' => $content->editorialImageUrl(),
            'whatsapp_number' => $marketing->whatsappNumber(),
        ]);
    }
}
