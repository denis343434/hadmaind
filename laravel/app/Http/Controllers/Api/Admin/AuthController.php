<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::query()->where('email', $data['email'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'password' => 'Неверный email или пароль',
            ]);
        }

        $admin->tokens()->where('expires_at', '<=', now())->delete();

        $plain = Str::random(64);
        $admin->tokens()->create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'token' => $plain,
            'admin' => $this->adminData($admin),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->adminData($request->attributes->get('admin')));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $admin = $request->attributes->get('admin');
        if (! Hash::check($data['currentPassword'], $admin->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'Текущий пароль неверен',
            ]);
        }

        $admin->password = Hash::make($data['newPassword']);
        $admin->save();

        $current = $request->attributes->get('admin_token');
        $admin->tokens()->when($current, fn ($query) => $query->where('id', '!=', $current->id))->delete();

        return response()->json(['ok' => true]);
    }

    private function adminData(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'email' => $admin->email,
            'name' => $admin->name,
        ];
    }
}
