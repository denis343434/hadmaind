<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Lead;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController
{
    private const TYPES = ['contact', 'callback', 'event', 'custom'];

    private const STATUSES = ['new', 'in_progress', 'done', 'spam'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'type' => ['sometimes', Rule::in(self::TYPES)],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $page = (int) ($data['page'] ?? 1);
        $limit = (int) ($data['limit'] ?? 20);

        $query = Lead::query();
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (! empty($data['q'])) {
            $like = '%'.addcslashes($data['q'], '%_\\').'%';
            $query->where(function ($where) use ($like) {
                $where->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $limit)
            ->get()
            ->map(fn ($lead) => Presenter::lead($lead))
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
        $stats = Lead::query()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        return response()->json((object) $stats);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Presenter::lead(Lead::query()->findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $lead = Lead::query()->findOrFail($id);
        $lead->update(['status' => $data['status']]);

        return response()->json(Presenter::lead($lead->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        Lead::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
