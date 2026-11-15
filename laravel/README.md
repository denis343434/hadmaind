# Handmade на Laravel 12

Laravel-версия сайта мастерской: тот же фронт (главная, каталог, товар, корзина, оформление заказа и админка), но бэкенд переписан на Laravel — маршруты, контроллеры, Eloquent-модели, миграции и сидеры.

Папка Laravel-версии: `laravel/`. Старая версия на чистом PHP осталась в корне проекта для сравнения.

## Быстрый старт

Нужны **PHP 8.3+** и запущенный **MySQL 8 / MariaDB**.

1. Проверьте `.env` (файл уже создан): база `handmade_laravel`, пользователь `root`, пароль пустой, админ `admin@handmade.ru` / `handmade2026`.
2. Запустите `setup.cmd` — создаст базу, таблицы, каталог, контакты и администратора.
3. Запустите `start.cmd` — сайт откроется на <http://127.0.0.1:8002>, админка на <http://127.0.0.1:8002/admin>.

`start.cmd` использует локальный `php.ini` и встроенный сервер PHP. Глобальный PHP не меняется.

Для artisan-команд используйте `artisan.cmd` (например, `artisan migrate`, `artisan route:clear`) — он сам подхватывает `php.ini`.

### Если PHP установлен в другом месте

В `php.ini` укажите свой `extension_dir` и включите расширения:

```
extension_dir = "C:\путь\к\php\ext"
extension = openssl
extension = mbstring
extension = curl
extension = fileinfo
extension = zip
extension = pdo_mysql
```

Либо включите их в глобальном `php.ini` и запускайте `php artisan serve`.

## Структура

```
laravel/
  app/Http/Controllers/Api/       публичный API и админские контроллеры
  app/Models/                     Admin, Lead, Setting, ShopCategory, ShopProduct, ShopOrder…
  app/Services/ShopOrderService   расчёт заказа, номер, уведомления
  app/Support/                    Presenter (JSON-формат), Phone, ShopConfig, Notifier
  database/migrations/            таблицы
  database/seeders/               админ, контакты, доставка, категории и товары
  routes/api.php                  /api/*
  routes/web.php                  главная и /admin
  public/                         фронт: index.html, catalog.html, product.html, checkout.html,
                                  style.css, script.js, products.js, api-client.js, admin/, uploads/
  server.php                      роутер встроенного сервера (статика + Laravel)
  setup.cmd / setup.php           создание базы и миграции
  start.cmd                       локальный запуск
```

## API

Контракты сохранены — фронт не пришлось переписывать.

| Метод | Путь | Назначение |
|---|---|---|
| GET | `/api/health` | проверка БД |
| GET | `/api/site` | контакты и соцсети |
| GET | `/api/shop/catalog` | категории, товары, настройки доставки |
| GET | `/api/shop/config` | доставка |
| GET | `/api/shop/products/{slug}` | товар |
| POST | `/api/shop/orders` | заказ (цены и доставка считаются на сервере) |
| POST | `/api/leads` | заявка |
| POST | `/api/admin/auth/login` | вход администратора |
| GET/PUT/PATCH | `/api/admin/settings/site` | контакты и SEO |
| GET/PUT | `/api/admin/shop/settings` | доставка и самовывоз |
| GET/POST/PATCH/DELETE | `/api/admin/shop/categories` | категории |
| GET/POST/PATCH/DELETE | `/api/admin/shop/products` | товары |
| GET/PATCH/DELETE | `/api/admin/shop/orders` | заказы |
| GET/POST/DELETE | `/api/admin/media` | загрузка изображений |

Админские запросы — с заголовком `Authorization: Bearer <token>`. Токен живёт 7 дней и хранится в `admin_tokens` в виде SHA-256-хеша.

## База данных

- Все деньги — в копейках.
- Даты в БД — UTC; часовой пояс сайта задаётся `TIMEZONE` (по умолчанию `Europe/Moscow`).
- Позиции заказа хранят копию названия и цены, поэтому история не меняется при редактировании товара.
- Полный сброс: `php artisan migrate:fresh --seed` (удалит заказы и заявки).
- Повторный `setup.cmd` не дублирует данные.

## Выкладка на хостинг

1. Document root — `laravel/public` (важно: не корень Laravel).
2. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://домен`, новый `APP_KEY`.
3. Дать права на запись: `storage/`, `bootstrap/cache/`, `public/uploads/`.
4. Выполнить `php artisan migrate --seed --force`, затем `php artisan optimize`.
5. Настроить SMTP (`MAIL_MAILER=smtp`, `MAIL_HOST`, …) и `MAIL_ADMIN_TO` — иначе письма о заказах уходят только в лог. Telegram включается `TELEGRAM_BOT_TOKEN` и `TELEGRAM_CHAT_ID`.
6. После изменения маршрутов — `php artisan route:clear` (локально кэш маршрутов уже очищен).

Для Apache в `public/.htaccess` уже есть front controller. Для nginx:

```nginx
root /path/to/laravel/public;
index index.html index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

## Уведомления

Заказ и заявка отправляют уведомление администратору через `Notifier` (mail + Telegram). Письмо клиенту о заказе уходит на его email. В локальной среде `MAIL_MAILER=log`, поэтому письма попадают в `storage/logs/laravel.log`.
