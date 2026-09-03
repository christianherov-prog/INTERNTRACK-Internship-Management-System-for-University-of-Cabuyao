<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Standard list envelope for INTERNTRACK APIs:
 * { data: [...], meta: { current_page, last_page, per_page, total } }
 */
class ApiResponse
{
    public static function list(mixed $items, int $defaultPerPage = 15): JsonResponse
    {
        if ($items instanceof LengthAwarePaginator) {
            return response()->json([
                'data' => array_values($items->items()),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page'    => $items->lastPage(),
                    'per_page'     => $items->perPage(),
                    'total'        => $items->total(),
                ],
            ]);
        }

        $collection = $items instanceof Collection ? $items : collect($items);
        $total = $collection->count();

        return response()->json([
            'data' => $collection->values()->all(),
            'meta' => [
                'current_page' => 1,
                'last_page'    => 1,
                'per_page'     => $total > 0 ? $total : $defaultPerPage,
                'total'        => $total,
            ],
        ]);
    }

    /** Grouped lists (e.g. pending + completed) under a single data object. */
    public static function groups(array $groups): JsonResponse
    {
        return response()->json(['data' => $groups]);
    }
}
