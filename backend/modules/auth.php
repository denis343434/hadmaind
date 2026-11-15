<?php
declare(strict_types=1);

namespace App;

/**
 * Первый вход: если админов в базе нет, создаётся админ из ADMIN_EMAIL / ADMIN_PASSWORD (.env).
 * Так база, залитая через phpMyAdmin, работает без отдельного скрипта установки.
 */
function ensureFirstAdmin(): void
{
    $email = strtolower(env('ADMIN_EMAIL'));
    if ($email === '' || env('ADMIN_PASSWORD') === '' || Db::value('SELECT COUNT(*) FROM admins') > 0) return;
    Db::insert('admins', ['email' => $email, 'name' => 'Администратор', 'passwordHash' => Auth::hashPassword(env('ADMIN_PASSWORD'))]);
}

return [
    'admin' => function (Router $r) {
        $r->post('/auth/login', function (Request $req) {
            $data = V::parse($req->body(), [
                'email' => V::email(),
                'password' => V::str()->min(1),
            ]);
            ensureFirstAdmin();
            $admin = Db::one('SELECT * FROM admins WHERE email = ?', [$data['email']]);
            if (!$admin || !Auth::checkPassword($data['password'], $admin['passwordHash'])) {
                throw HttpError::unauthorized('Неверный email или пароль');
            }
            return [
                'token' => Auth::sign($admin['id'], $admin['email']),
                'admin' => ['id' => $admin['id'], 'email' => $admin['email'], 'name' => $admin['name']],
            ];
        }, [RateLimit::class, 'login']);

        $r->get('/auth/me', function (Request $req) {
            $admin = Db::one('SELECT id, email, name, createdAt FROM admins WHERE id = ?', [$req->admin['sub']]);
            if (!$admin) throw HttpError::unauthorized();
            return cast($admin, ['createdAt' => 'date']);
        }, [Auth::class, 'requireAdmin']);

        $r->post('/auth/change-password', function (Request $req) {
            $data = V::parse($req->body(), [
                'currentPassword' => V::str()->min(1),
                'newPassword' => V::str()->min(8, 'Минимум 8 символов'),
            ]);
            $admin = Db::findOrFail('admins', $req->admin['sub']);
            if (!Auth::checkPassword($data['currentPassword'], $admin['passwordHash'])) {
                throw HttpError::badRequest('Текущий пароль неверен');
            }
            Db::update('admins', $admin['id'], ['passwordHash' => Auth::hashPassword($data['newPassword'])]);
            return ['ok' => true];
        }, [Auth::class, 'requireAdmin']);
    },
];
