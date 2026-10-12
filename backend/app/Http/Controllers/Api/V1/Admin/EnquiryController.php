<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Enquiries: corporate & bulk gifting leads. */
class EnquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(Enquiry::STATUSES)], 'page' => ['nullable', 'integer', 'min:1']]);
        $page = Enquiry::query()->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25);

        return ApiResponse::success(
            collect($page->items())->map(fn (Enquiry $e) => $this->present($e))->values(),
            meta: ['pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
                'counts' => Enquiry::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')],
        );
    }

    public function update(Request $request, string $enquiry): JsonResponse
    {
        $enquiry = Enquiry::query()->where('uuid', $enquiry)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(Enquiry::STATUSES)], 'notes' => ['nullable', 'string', 'max:5000']]);
        $before = $enquiry->only(['status', 'notes']);
        $enquiry->forceFill([...$data, 'updated_by' => $request->user()->getKey()])->save();
        Audit::record('enquiry.updated', $enquiry, $before, $enquiry->only(['status', 'notes']), $request->user());

        return ApiResponse::success($this->present($enquiry), 'Enquiry updated.');
    }

    /** @return array<string, mixed> */
    private function present(Enquiry $e): array
    {
        return [
            'uuid' => $e->uuid, 'name' => $e->name, 'company' => $e->company, 'email' => $e->email, 'phone' => $e->phone,
            'quantity' => $e->quantity, 'budget' => $e->budget, 'occasion' => $e->occasion, 'needed_by' => $e->needed_by?->toDateString(),
            'message' => $e->message, 'status' => $e->status, 'notes' => $e->notes, 'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
