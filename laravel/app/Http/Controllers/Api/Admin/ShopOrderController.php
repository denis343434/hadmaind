<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ShopOrder;
use App\Support\Presenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopOrderController
{
    private const STATUSES = ['new', 'confirmed', 'shipped', 'done', 'cancelled', 'spam'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $page = (int) ($data['page'] ?? 1);
        $limit = (int) ($data['limit'] ?? 30);

        $query = ShopOrder::query()->with('items');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $like = '%'.addcslashes($data['q'], '%_\\').'%';
            $query->where(function ($where) use ($like) {
                $where->where('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $limit)
            ->get()
            ->map(fn ($order) => Presenter::order($order, false))
            ->values();

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = ShopOrder::query()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        return response()->json((object) $stats);
    }

    public function dashboard(): JsonResponse
    {
        $timezone = config('handmade.timezone');
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = (clone $start)->addDay();

        $today = ShopOrder::query()
            ->whereNotIn('status', ['cancelled', 'spam'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        return response()->json([
            'newCount' => ShopOrder::query()->where('status', 'new')->count(),
            'todayOrders' => (int) (optional($today)->orders ?? 0),
            'todayRevenue' => (int) (optional($today)->revenue ?? 0),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $order = ShopOrder::query()->with('items')->findOrFail($id);

        return response()->json(Presenter::order($order));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'adminNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $order = ShopOrder::query()->findOrFail($id);
        $fill = [];
        if (array_key_exists('status', $data)) {
            $fill['status'] = $data['status'];
        }
        if (array_key_exists('adminNote', $data)) {
            $fill['admin_note'] = $data['adminNote'];
        }
        if ($fill) {
            $order->update($fill);
        }

        $order = ShopOrder::query()->with('items')->findOrFail($id);

        return response()->json(Presenter::order($order));
    }

    public function destroy(int $id): JsonResponse
    {
        ShopOrder::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
