<?php

namespace App\Http\Controllers\Api;

use App\Services\ShopOrderService;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopOrderController
{
    public function __construct(private readonly ShopOrderService $orders)
    {
    }

    public function store(Request $request): JsonResponse
    {
        if (trim((string) $request->input('website', '')) !== '') {
            return response()->json(['ok' => true, 'code' => null], 201);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'deliveryMethod' => ['required', Rule::in(['delivery', 'pickup'])],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:300'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'payment' => ['required', Rule::in(['card', 'transfer', 'cash'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'consent' => ['accepted'],
        ]);

        $phone = \App\Support\Phone::normalize($data['phone']);
        if (! \App\Support\Phone::isValid($phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => 'Некорректный телефон',
            ]);
        }

        if ($data['deliveryMethod'] === 'delivery') {
            if (empty($data['city'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['city' => 'Укажите город']);
            }
            if (empty($data['address'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['address' => 'Укажите адрес доставки']);
            }
        }

        $order = $this->orders->create([
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            'delivery_method' => $data['deliveryMethod'],
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'comment' => $data['comment'] ?? null,
            'payment' => $data['payment'],
            'items' => $data['items'],
        ]);

        return response()->json(Presenter::order($order), 201);
    }
}
