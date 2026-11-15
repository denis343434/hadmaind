<?php

namespace App\Services;

use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Support\Notifier;
use App\Support\ShopConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopOrderService
{
    public function create(array $data): ShopOrder
    {
        $wanted = [];
        foreach ($data['items'] as $item) {
            $wanted[$item['slug']] = min(99, ($wanted[$item['slug']] ?? 0) + (int) $item['qty']);
        }

        $products = ShopProduct::query()
            ->with('category')
            ->whereIn('slug', array_keys($wanted))
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get()
            ->keyBy('slug');

        foreach (array_keys($wanted) as $slug) {
            if (! $products->has($slug)) {
                throw ValidationException::withMessages([
                    'items' => 'Товар «'.$slug.'» больше недоступен',
                ]);
            }
        }

        $config = ShopConfig::all();
        $subtotal = 0;
        $items = [];

        foreach ($wanted as $slug => $qty) {
            $product = $products->get($slug);
            $lineTotal = (int) $product->price * $qty;
            $subtotal += $lineTotal;
            $items[] = [
                'product_id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'price' => (int) $product->price,
                'qty' => $qty,
                'total' => $lineTotal,
                'image_url' => $product->image_url,
            ];
        }

        $deliveryPrice = $data['delivery_method'] === 'pickup' || $subtotal >= $config['freeFrom']
            ? 0
            : (int) $config['deliveryFee'];
        $total = $subtotal + $deliveryPrice;
        $code = $this->code();

        $order = DB::transaction(function () use ($data, $items, $subtotal, $deliveryPrice, $total, $code) {
            $order = ShopOrder::query()->create([
                'code' => $code,
                'customer_name' => $data['name'],
                'customer_phone' => $data['phone'],
                'customer_email' => $data['email'] ?? null,
                'delivery_method' => $data['delivery_method'],
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'comment' => $data['comment'] ?? null,
                'payment' => $data['payment'],
                'subtotal' => $subtotal,
                'delivery_price' => $deliveryPrice,
                'total' => $total,
            ]);

            $order->items()->createMany($items);

            return $order;
        });

        $order->load('items');
        $this->notify($order, $config);

        return $order;
    }

    private function code(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $code = 'HM-';
            for ($i = 0; $i < 5; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (ShopOrder::query()->where('code', $code)->exists());

        return $code;
    }

    private function notify(ShopOrder $order, array $config): void
    {
        $itemsLabel = $order->items
            ->map(fn ($item) => $item->name.' × '.$item->qty)
            ->implode(', ');

        $delivery = $order->delivery_method === 'pickup'
            ? 'самовывоз'.($config['pickupAddress'] ? ': '.$config['pickupAddress'] : '')
            : trim(implode(', ', array_filter([$order->city, $order->address])));

        $payments = ['card' => 'картой онлайн', 'transfer' => 'переводом', 'cash' => 'при получении'];

        Notifier::admin('Новый заказ №'.$order->code, [
            'Имя' => $order->customer_name,
            'Телефон' => $order->customer_phone,
            'Email' => $order->customer_email,
            'Состав' => $itemsLabel,
            'Товары' => $this->money((int) $order->subtotal),
            'Доставка' => $order->delivery_price > 0 ? $this->money((int) $order->delivery_price) : 'бесплатно',
            'Итого' => $this->money((int) $order->total),
            'Получение' => $delivery,
            'Оплата' => $payments[$order->payment] ?? $order->payment,
            'Комментарий' => $order->comment,
        ]);

        if ($order->customer_email) {
            Notifier::customer($order->customer_email, 'Заказ №'.$order->code.' принят', [
                'Имя' => $order->customer_name,
                'Состав' => $itemsLabel,
                'Сумма' => $this->money((int) $order->total),
                'Что дальше' => 'Я свяжусь с вами, чтобы подтвердить детали и оплату.',
            ]);
        }
    }

    private function money(int $kopecks): string
    {
        return number_format($kopecks / 100, 0, ',', ' ').' ₽';
    }
}
