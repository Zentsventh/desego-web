<?php

namespace App\Support\Desego;

use Illuminate\Support\Facades\DB;

class OrderItemOptions
{
    /** @return list<array{id: ?int, group_name: string, name: string, price_delta: float}> */
    public static function forItem(int $orderItemId): array
    {
        return DB::table('order_item_options')->where('order_item_id', $orderItemId)->orderBy('position')->get()
            ->map(fn (object $option): array => [
                'id' => $option->option_id === null ? null : (int) $option->option_id,
                'group_name' => $option->group_name,
                'name' => $option->name,
                'price_delta' => (float) $option->price_delta,
            ])->all();
    }
}
