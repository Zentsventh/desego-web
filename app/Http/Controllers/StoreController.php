<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StoreController extends Controller
{
    /**
     * Imagen de cada plato del menú (carpeta public/images/menu).
     *
     * @var array<string, string>
     */
    private const PRODUCT_IMAGES = [
        'ceviche-desego' => 'food_ceviche',
        'ceviche-clasico' => 'food_ceviche',
        'pescado-frito' => 'food_pescado',
        'arroz-con-mariscos' => 'food_arroz',
        'chicha-morada' => 'food_chicha',
        'torta-de-chocolate' => 'food_chocolate',
    ];

    /**
     * Tienda pública: carta, categorías, mesas disponibles y promociones activas.
     */
    public function home(): Response
    {
        $categories = [];
        $products = [];
        $promotions = [];
        $tables = [];

        try {
            $categories = DB::table('categories')
                ->where('status', 'active')
                ->orderBy('id')
                ->get(['id', 'name', 'slug'])
                ->map(fn (object $cat): array => [
                    'id' => (int) $cat->id,
                    'name' => (string) $cat->name,
                    'slug' => (string) $cat->slug,
                ])
                ->all();

            $allergenMap = [];
            $allergenRows = DB::table('product_ingredients as pi')
                ->join('ingredient_allergens as ia', 'ia.ingredient_id', '=', 'pi.ingredient_id')
                ->join('allergens as a', function ($join): void {
                    $join->on('a.id', '=', 'ia.allergen_id')
                        ->where('a.status', '=', 'active');
                })
                ->orderBy('a.name')
                ->get(['pi.product_id', 'a.name']);

            foreach ($allergenRows as $row) {
                $pid = (int) $row->product_id;
                $allergenMap[$pid][$row->name] = (string) $row->name;
            }

            $products = DB::table('products as p')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->where('p.status', 'available')
                ->where('c.status', 'active')
                ->orderBy('c.id')
                ->orderBy('p.name')
                ->select([
                    'p.id',
                    'p.slug',
                    'p.name',
                    'p.description',
                    'p.price',
                    'p.emoji',
                    'p.category_id',
                    'c.name as category',
                    DB::raw('(SELECT COUNT(*) FROM product_ingredients pi WHERE pi.product_id = p.id) AS recipe_count'),
                ])
                ->get()
                ->map(function (object $product) use ($allergenMap): array {
                    $id = (int) $product->id;
                    $allergens = isset($allergenMap[$id]) ? implode(', ', array_values($allergenMap[$id])) : '';

                    return [
                        'id' => $id,
                        'slug' => (string) $product->slug,
                        'name' => (string) $product->name,
                        'description' => (string) ($product->description ?? ''),
                        'price' => (float) $product->price,
                        'emoji' => (string) ($product->emoji ?? '🍽️'),
                        'category_id' => (int) $product->category_id,
                        'category' => (string) $product->category,
                        'allergens' => $allergens,
                        'can_order' => (int) $product->recipe_count > 0,
                        'image' => isset(self::PRODUCT_IMAGES[$product->slug])
                            ? '/images/menu/'.self::PRODUCT_IMAGES[$product->slug].'.jpg'
                            : null,
                    ];
                })
                ->all();

            $now = gmdate('Y-m-d H:i:s');
            $promotions = DB::table('promotions')
                ->where('status', 'active')
                ->where('starts_at', '<=', $now)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now);
                })
                ->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'name', 'description', 'promotion_type', 'code', 'discount_type', 'discount_value', 'min_subtotal'])
                ->map(fn (object $promo): array => [
                    'id' => (int) $promo->id,
                    'name' => (string) $promo->name,
                    'description' => (string) ($promo->description ?? ''),
                    'promotion_type' => (string) $promo->promotion_type,
                    'code' => $promo->code ? (string) $promo->code : null,
                    'discount_type' => (string) $promo->discount_type,
                    'discount_value' => (float) $promo->discount_value,
                    'min_subtotal' => (float) $promo->min_subtotal,
                ])
                ->all();

            $tables = DB::table('restaurant_tables')
                ->where('is_active', 1)
                ->where('operational_status', 'available')
                ->orderBy('zone')
                ->orderBy('table_number')
                ->get(['id', 'table_number', 'label', 'zone', 'capacity'])
                ->map(fn (object $table): array => [
                    'id' => (int) $table->id,
                    'table_number' => (int) $table->table_number,
                    'label' => (string) $table->label,
                    'zone' => (string) $table->zone,
                    'capacity' => (int) $table->capacity,
                ])
                ->all();
        } catch (Throwable) {
            // Entornos de prueba sin migraciones cargadas reciben colecciones vacías.
        }

        return Inertia::render('Store/Home', [
            'categories' => $categories,
            'products' => $products,
            'promotions' => $promotions,
            'tables' => $tables,
            'deliveryFee' => 5.00,
        ]);
    }
}
