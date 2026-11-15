<?php

namespace App\Support;

use App\Models\Setting;

class ShopConfig
{
    public const DEFAULTS = [
        'deliveryFee' => 39000,
        'freeFrom' => 300000,
        'pickupAddress' => '',
        'deliveryNote' => '',
        'currency' => 'RUB',
    ];

    public static function all(): array
    {
        $saved = Setting::read('shop') ?? [];
        $config = array_merge(self::DEFAULTS, $saved);
        $config['deliveryFee'] = (int) $config['deliveryFee'];
        $config['freeFrom'] = (int) $config['freeFrom'];

        return $config;
    }

    public static function save(array $config): void
    {
        Setting::write('shop', $config);
    }
}
