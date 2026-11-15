<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController
{
    public function show(): JsonResponse
    {
        return response()->json(Setting::read('site') ?? new \stdClass());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        Setting::write('site', $data);

        return response()->json($data);
    }

    public function patch(Request $request): JsonResponse
    {
        $site = Setting::read('site') ?? [];
        $data = array_replace_recursive($site, $request->all());
        Setting::write('site', $data);

        return response()->json($data);
    }
}
