<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ShopProduct;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShopProductController
{
    public function index(Request $request): JsonResponse
    {
        $categoryId = (int) $request->query('categoryId', 0);

        $query = ShopProduct::query()->with('category');
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('category_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn ($product) => Presenter::product($product))
            ->values();

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $fill = $this->fillable($data);
        $fill['specs'] ??= [];

        $product = ShopProduct::query()->create($fill);

        return response()->json(Presenter::product($product), 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = Validator::make(['items' => $request->all()], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:shop_products,id'],
            'items.*.sort' => ['required', 'integer'],
        ])->validate();

        foreach ($data['items'] as $item) {
            ShopProduct::query()->whereKey($item['id'])->update(['sort' => $item['sort']]);
        }

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = ShopProduct::query()->findOrFail($id);
        $data = $request->validate($this->rules($product->id));

        $product->fill($this->fillable($data))->save();

        return response()->json(Presenter::product($product->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        ShopProduct::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    private function rules(?int $ignoreId = null): array
    {
        $required = $ignoreId === null ? 'required' : 'sometimes';
        $slugUnique = Rule::unique('shop_products', 'slug');
        if ($ignoreId !== null) {
            $slugUnique->ignore($ignoreId);
        }

        return [
            'categoryId' => [$required, 'integer', 'exists:shop_categories,id'],
            'slug' => [
                $required,
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                $slugUnique,
            ],
            'name' => [$required, 'string', 'max:255'],
            'price' => [$required, 'integer', 'min:0'],
            'oldPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:50'],
            'shortDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'specs' => ['sometimes', 'array'],
            'specs.*.name' => ['required', 'string', 'max:255'],
            'specs.*.value' => ['required', 'string', 'max:500'],
            'imageUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tone' => ['sometimes', Rule::in(['', 'photo', 'neutral'])],
            'zoom' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'isFeatured' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function fillable(array $data): array
    {
        $map = [
            'categoryId' => 'category_id',
            'slug' => 'slug',
            'name' => 'name',
            'price' => 'price',
            'oldPrice' => 'old_price',
            'badge' => 'badge',
            'shortDescription' => 'short_description',
            'description' => 'description',
            'specs' => 'specs',
            'imageUrl' => 'image_url',
            'tone' => 'tone',
            'zoom' => 'zoom',
            'isFeatured' => 'is_featured',
            'isActive' => 'is_active',
            'sort' => 'sort',
        ];

        $result = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $result[$column] = $data[$input];
            }
        }

        if (array_key_exists('specs', $result) && $result['specs'] === null) {
            $result['specs'] = [];
        }

        if (array_key_exists('tone', $result) && $result['tone'] === null) {
            $result['tone'] = '';
        }

        return $result;
    }
}
