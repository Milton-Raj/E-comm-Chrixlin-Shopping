<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public const SCHEDULER_HEARTBEAT_KEY = 'health:scheduler_heartbeat';

    /**
     * Public: liveness only. With a valid X-Health-Token: database, queue lag and
     * scheduler heartbeat for monitoring (DEPLOYMENT.md §8).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = ['status' => 'ok', 'version' => config('app.version', 'dev')];

        $token = config('commerce.health_token');
        if (is_string($token) && $token !== '' && hash_equals($token, (string) $request->header('X-Health-Token'))) {
            $data += $this->details();
            if ($data['database'] !== 'ok') {
                $data['status'] = 'degraded';
            }
        }

        return ApiResponse::success($data, status: $data['status'] === 'ok' ? 200 : 503);
    }

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        try {
            DB::select('select 1');
            $database = 'ok';
            $oldestJob = DB::table('jobs')->min('available_at');
            $pendingJobs = DB::table('jobs')->count();
        } catch (Throwable) {
            $database = 'unavailable';
            $oldestJob = null;
            $pendingJobs = null;
        }

        $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        return [
            'database' => $database,
            'queue_pending' => $pendingJobs,
            'queue_lag_seconds' => $oldestJob ? max(0, now()->timestamp - (int) $oldestJob) : 0,
            'scheduler_last_run_seconds_ago' => is_int($heartbeat) ? now()->timestamp - $heartbeat : null,
        ];
    }
}
