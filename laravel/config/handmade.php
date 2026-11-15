<?php

return [
    'preset' => env('SITE_PRESET', 'shop'),
    'modules' => ['leads', 'media', 'shop'],
    'timezone' => env('TIMEZONE', 'Europe/Moscow'),

    'admin_mail_to' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAIL_ADMIN_TO', '')),
    ))),

    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'telegram_chat_id' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TELEGRAM_CHAT_ID', '')),
    ))),
];
