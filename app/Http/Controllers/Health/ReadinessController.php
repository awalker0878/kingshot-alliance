<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseIsReady(),
            'cache' => $this->cacheIsReady(),
        ];

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'version' => config('operations.version'),
            'release_sha' => config('operations.release_sha'),
            'request_id' => request()->attributes->get('request_id'),
        ], $ready ? 200 : 503);
    }

    private function databaseIsReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function cacheIsReady(): bool
    {
        $key = 'health:ready:'.request()->attributes->get('request_id');

        try {
            Cache::put($key, true, 10);
            $ready = Cache::get($key) === true;
            Cache::forget($key);

            return $ready;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
