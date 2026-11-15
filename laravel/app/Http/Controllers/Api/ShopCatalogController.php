<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Support\Presenter;
use App\Support\ShopConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopCatalogController
{
    public function catalog(Request $request): JsonResponse
    {
        $cat = trim((string) $request->query('cat', ''));
        $q = trim((string) $request->query('q', ''));

        $categories = ShopCategory::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn ($category) => Presenter::category($category))
            ->values();

        $query = ShopProduct::query()
            ->with('category')
            ->join('shop_categories as c', 'c.id', '=', 'shop_products.category_id')
            ->select('shop_products.*')
            ->where('shop_products.is_active', true)
            ->where('c.is_active', true)
            ->orderBy('c.sort')
            ->orderBy('shop_products.sort')
            ->orderBy('shop_products.id');

        if ($cat !== '') {
            $query->where('c.slug', $cat);
        }

        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                $like = '%'.$q.'%';
                $where->where('shop_products.name', 'like', $like)
                    ->orWhere('shop_products.short_description', 'like', $like)
                    ->orWhere('shop_products.description', 'like', $like)
                    ->orWhere('c.name', 'like', $like);
            });
        }

        $products = $query->get()->map(fn ($product) => Presenter::product($product))->values();

        return response()->json([
            'categories' => $categories,
            'products' => $products,
            'config' => ShopConfig::all(),
        ]);
    }

    public function config(): JsonResponse
    {
        return response()->json(ShopConfig::all());
    }

    public function product(string $slug): JsonResponse
    {
        $product = ShopProduct::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        return response()->json(Presenter::product($product));
    }
}
