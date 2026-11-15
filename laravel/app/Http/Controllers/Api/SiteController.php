<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SiteController
{
    public function __invoke(): JsonResponse
    {
        $site = Setting::read('site') ?? ['name' => ''];

        return response()->json(array_merge($site, [
            'preset' => config('handmade.preset'),
            'modules' => config('handmade.modules'),
            'timezone' => config('handmade.timezone'),
        ]));
    }
}
