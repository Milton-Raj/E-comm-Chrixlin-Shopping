<?php

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Context;

/**
 * The single place that shapes API responses (API.md §1.2, PRD §69).
 */
class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'success' => true,
            'data' => $data ?? (object) [],
            'message' => $message,
            'meta' => (object) $meta,
        ], $status);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource, array $meta = []): JsonResponse
    {
        return self::success(
            $resource::collection($paginator->items())->resolve(request()),
            meta: [
                ...$meta,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $headers
     */
    public static function error(
        string $message,
        int $status,
        array $errors = [],
        ?string $code = null,
        array $headers = [],
    ): JsonResponse {
        if ($code !== null) {
            $errors = [...$errors, 'code' => $code]; // the machine-readable code always wins over a field named "code"
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
            'meta' => ['request_id' => Context::get('request_id')],
        ], $status, $headers);
    }
}
