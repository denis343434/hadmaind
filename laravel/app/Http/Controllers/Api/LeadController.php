<?php

namespace App\Http\Controllers\Api;

use App\Models\Lead;
use App\Support\Notifier;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController
{
    private const TYPES = ['contact', 'callback', 'event', 'custom'];

    public function store(Request $request): JsonResponse
    {
        if (trim((string) $request->input('website', '')) !== '') {
            return response()->json(['ok' => true], 201);
        }

        $data = $request->validate([
            'type' => ['sometimes', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'meta' => ['nullable', 'array'],
            'consent' => ['accepted'],
        ]);

        $phone = null;
        if (! empty($data['phone'])) {
            $phone = Phone::normalize($data['phone']);
            if (! Phone::isValid($phone)) {
                throw ValidationException::withMessages(['phone' => 'Некорректный телефон']);
            }
        }

        if (! $phone && empty($data['email'])) {
            throw ValidationException::withMessages(['phone' => 'Укажите телефон или email']);
        }

        $meta = $data['meta'] ?? [];
        foreach ($meta as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                throw ValidationException::withMessages([
                    "meta.$key" => 'Ожидалась строка, число или true/false',
                ]);
            }
        }

        $lead = Lead::query()->create([
            'type' => $data['type'] ?? 'contact',
            'name' => $data['name'],
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            'message' => $data['message'] ?? null,
            'meta' => $meta,
        ]);

        Notifier::admin('Новая заявка с сайта', [
            'Имя' => $lead->name,
            'Телефон' => $lead->phone,
            'Email' => $lead->email,
            'Сообщение' => $lead->message,
            ...$meta,
        ]);

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }
}
