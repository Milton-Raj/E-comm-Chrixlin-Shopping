<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Reports\Export\StoreExports;
use App\Domain\Reports\Export\WorkbookBuilder;
use App\Domain\Reports\SalesReport;
use App\Http\Controllers\Controller;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /admin/exports/{type} — Excel workbooks for each admin area, and the full report.
 * Needs `exports.run` plus view permission for the area; every export is audited because
 * the files can contain customer personal data.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request, string $type, StoreExports $exports): StreamedResponse
    {
        $area = StoreExports::AREAS[$type] ?? null;
        abort_unless($area !== null || $type === 'report', 404);
        Gate::authorize($type === 'report' ? 'reports.view' : $area[0]);

        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $user = $request->user();
        $currency = (string) config('commerce.store.currency');
        $label = $type === 'report' ? 'Store report' : Str::headline($type);
        $book = new WorkbookBuilder((string) config('commerce.store.name')." — {$label}", $user->name, $currency);

        $range = null;
        if ($type === 'report' || isset($data['from']) || isset($data['to'])) {
            $range = SalesReport::range($data['from'] ?? null, $data['to'] ?? null);
        }

        match ($type) {
            'report' => $exports->report($book, $range[0], $range[1], $user),
            'orders' => $exports->orders($book, $range[0] ?? null, $range[1] ?? null),
            default => $exports->{$type}($book),
        };

        Audit::record('export.downloaded', null, null, ['type' => $type, 'from' => $range ? $range[0]->toDateString() : null, 'to' => $range ? $range[1]->toDateString() : null], $user);

        $suffix = $range ? $range[0]->format('Y-m-d').'_to_'.$range[1]->format('Y-m-d') : now()->format('Y-m-d');

        return $book->download(Str::slug((string) config('commerce.store.name'))."-{$type}-{$suffix}.xlsx");
    }
}
