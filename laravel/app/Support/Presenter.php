<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use Carbon\CarbonInterface;

class Presenter
{
    public static function iso(?CarbonInterface $date): ?string
    {
        return $date?->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function category(ShopCategory $category): array
    {
        $result = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'title' => $category->title,
            'subtitle' => $category->subtitle,
            'shortName' => $category->short_name,
            'sort' => (int) $category->sort,
            'isActive' => (bool) $category->is_active,
            'createdAt' => self::iso($category->created_at),
            'updatedAt' => self::iso($category->updated_at),
        ];

        if (isset($category->products_count)) {
            $result['_count'] = ['products' => (int) $category->products_count];
        }

        return $result;
    }

    public static function product(ShopProduct $product): array
    {
        $result = [
            'id' => $product->id,
            'categoryId' => (int) $product->category_id,
            'slug' => $product->slug,
            'name' => $product->name,
            'price' => (int) $product->price,
            'oldPrice' => $product->old_price === null ? null : (int) $product->old_price,
            'badge' => $product->badge,
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'specs' => array_values(array_map(
                fn ($spec) => ['name' => (string) ($spec['name'] ?? ''), 'value' => (string) ($spec['value'] ?? '')],
                is_array($product->specs) ? $product->specs : [],
            )),
            'imageUrl' => $product->image_url,
            'tone' => $product->tone ?? '',
            'zoom' => $product->zoom === null ? null : (float) $product->zoom,
            'isFeatured' => (bool) $product->is_featured,
            'isActive' => (bool) $product->is_active,
            'sort' => (int) $product->sort,
            'createdAt' => self::iso($product->created_at),
            'updatedAt' => self::iso($product->updated_at),
        ];

        if ($product->relationLoaded('category') && $product->category) {
            $result['categorySlug'] = $product->category->slug;
            $result['categoryName'] = $product->category->name;
            $result['categoryShortName'] = $product->category->short_name;
        }

        return $result;
    }

    public static function order(ShopOrder $order, bool $withItems = true): array
    {
        $result = [
            'id' => $order->id,
            'code' => $order->code,
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'customerEmail' => $order->customer_email,
            'deliveryMethod' => $order->delivery_method,
            'city' => $order->city,
            'address' => $order->address,
            'comment' => $order->comment,
            'payment' => $order->payment,
            'subtotal' => (int) $order->subtotal,
            'deliveryPrice' => (int) $order->delivery_price,
            'total' => (int) $order->total,
            'status' => $order->status,
            'adminNote' => $order->admin_note,
            'createdAt' => self::iso($order->created_at),
            'updatedAt' => self::iso($order->updated_at),
        ];

        if ($order->relationLoaded('items')) {
            $items = $order->items->map(fn ($item) => [
                'id' => $item->id,
                'orderId' => (int) $item->order_id,
                'productId' => $item->product_id === null ? null : (int) $item->product_id,
                'slug' => $item->slug,
                'name' => $item->name,
                'price' => (int) $item->price,
                'qty' => (int) $item->qty,
                'total' => (int) $item->total,
                'imageUrl' => $item->image_url,
            ])->values()->all();

            $result['itemsLabel'] = implode(', ', array_map(
                fn ($item) => $item['name'].' × '.$item['qty'],
                $items,
            ));
            $result['itemsCount'] = array_sum(array_column($items, 'qty'));

            if ($withItems) {
                $result['items'] = $items;
            }
        }

        return $result;
    }

    public static function lead(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'type' => $lead->type,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'message' => $lead->message,
            'meta' => $lead->meta,
            'status' => $lead->status,
            'createdAt' => self::iso($lead->created_at),
            'updatedAt' => self::iso($lead->updated_at),
        ];
    }
}
