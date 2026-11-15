<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ShopCategory;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShopCategoryController
{
    public function index(): JsonResponse
    {
        $categories = ShopCategory::query()
            ->withCount('products')
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn ($category) => Presenter::category($category))
            ->values();

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:shop_categories,slug'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'shortName' => ['nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $category = ShopCategory::query()->create($this->fillable($data));

        return response()->json(Presenter::category($category), 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = Validator::make(['items' => $request->all()], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:shop_categories,id'],
            'items.*.sort' => ['required', 'integer'],
        ])->validate();

        foreach ($data['items'] as $item) {
            ShopCategory::query()->whereKey($item['id'])->update(['sort' => $item['sort']]);
        }

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = ShopCategory::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('shop_categories', 'slug')->ignore($category->id)],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'shortName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $category->fill($this->fillable($data))->save();

        return response()->json(Presenter::category($category->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        ShopCategory::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    private function fillable(array $data): array
    {
        $map = [
            'name' => 'name',
            'slug' => 'slug',
            'title' => 'title',
            'subtitle' => 'subtitle',
            'shortName' => 'short_name',
            'sort' => 'sort',
            'isActive' => 'is_active',
        ];

        $result = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $result[$column] = $data[$input];
            }
        }

        return $result;
    }
}
