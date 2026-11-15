<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        DB::select('select 1');

        return response()->json([
            'ok' => true,
            'preset' => config('handmade.preset'),
            'modules' => config('handmade.modules'),
        ]);
    }
}
