<?php

namespace App\Http\Controllers\Api\Admin;

use App\Support\ShopConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopSettingController
{
    public function show(): JsonResponse
    {
        return response()->json(ShopConfig::all());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deliveryFee' => ['sometimes', 'integer', 'min:0'],
            'freeFrom' => ['sometimes', 'integer', 'min:0'],
            'pickupAddress' => ['sometimes', 'nullable', 'string', 'max:255'],
            'deliveryNote' => ['sometimes', 'nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'max:10'],
        ]);

        $config = array_merge(ShopConfig::all(), $data);
        ShopConfig::save($config);

        return response()->json($config);
    }
}
