<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficialCatalogSeeder extends Seeder
{
    /**
     * Catálogo oficial completo de DeSego Cevichería:
     * 35 productos en 4 categorías oficiales + 1 categoría Bebidas vacía y administrable.
     * Cumple estrictamente con no inventar ingredientes específicos, gramajes, alérgenos,
     * combinaciones de dúos/tríos ni tarifas comerciales de reparto no confirmadas.
     */
    public function run(): void
    {
        $db = DB::connection();

        // 1. CATEGORÍAS OFICIALES
        $categoriesData = [
            [
                'slug' => 'platos-frescos',
                'name' => 'Platos frescos',
                'description' => 'Ceviches, leches de tigre y causas marinas preparadas al momento.',
            ],
            [
                'slug' => 'platos-calientes',
                'name' => 'Platos calientes',
                'description' => 'Arroces marinos, chaufas, pastas, sudados y caldos tradicionales de nuestra cocina.',
            ],
            [
                'slug' => 'frituras',
                'name' => 'Frituras',
                'description' => 'Chicharrones y jaleas marinas preparadas al punto dorado y crocante.',
            ],
            [
                'slug' => 'duos-trios-rondas',
                'name' => 'Dúos, tríos y rondas marinas',
                'description' => 'Combinaciones marinas de la casa ideales para disfrutar o compartir.',
            ],
            [
                'slug' => 'bebidas',
                'name' => 'Bebidas',
                'description' => 'Bebidas del restaurante administrables desde el panel de control.',
            ],
        ];

        $categoryIds = [];
        foreach ($categoriesData as $cat) {
            $existing = $db->table('categories')->where('slug', $cat['slug'])->first();
            if ($existing) {
                $db->table('categories')->where('id', $existing->id)->update([
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
                $categoryIds[$cat['slug']] = $existing->id;
            } else {
                $categoryIds[$cat['slug']] = $db->table('categories')->insertGetId([
                    'slug' => $cat['slug'],
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. LOS 35 PRODUCTOS OFICIALES DE DESEGO CEVICHERÍA
        $officialProducts = [
            // --- CATEGORÍA 1: PLATOS FRESCOS (8) ---
            [
                'code' => 'PF001',
                'name' => 'Ceviche de pescado',
                'slug' => 'ceviche-de-pescado',
                'category_slug' => 'platos-frescos',
                'price' => 22.00,
                'description' => 'Clásico ceviche de pescado fresco preparado al momento con el toque marino tradicional de DeSego.',
                'emoji' => '🐟',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'PF002',
                'name' => 'Ceviche mixto',
                'slug' => 'ceviche-mixto',
                'category_slug' => 'platos-frescos',
                'price' => 28.00,
                'description' => 'Especialidad fresca de pescado y selección de mariscos preparada al instante con sazón peruana.',
                'emoji' => '🦐',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'PF003',
                'name' => 'Ceviche de conchas negras',
                'slug' => 'ceviche-de-conchas-negras',
                'category_slug' => 'platos-frescos',
                'price' => 29.00,
                'description' => 'Ceviche tradicional de conchas negras preparado al momento con carácter intenso y sabor costeño.',
                'emoji' => '🦪',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'PF004',
                'name' => 'Ceviche DeSego',
                'slug' => 'ceviche-desego',
                'category_slug' => 'platos-frescos',
                'price' => 32.00,
                'description' => 'El plato emblemático de la casa, preparado al momento con nuestra propuesta especial marina.',
                'emoji' => '👑',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'PF005',
                'name' => 'Leche DeSego',
                'slug' => 'leche-desego',
                'category_slug' => 'platos-frescos',
                'price' => 14.00,
                'description' => 'Leche de tigre especial con el sello de la casa, fresca, intensa y preparada al instante.',
                'emoji' => '🥤',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'PF006',
                'name' => 'Leche de tigre mixto',
                'slug' => 'leche-de-tigre-mixto',
                'category_slug' => 'platos-frescos',
                'price' => 17.00,
                'description' => 'Concentrado marino fresco con selección mixta del mar, servido al momento.',
                'emoji' => '⚡',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'PF007',
                'name' => 'Causa acevichada',
                'slug' => 'causa-acevichada',
                'category_slug' => 'platos-frescos',
                'price' => 20.00,
                'description' => 'Suave causa peruana coronada con el toque fresco y cítrico de nuestra barra cevichera.',
                'emoji' => '🥔',
                'is_featured' => true,
                'is_popular' => false,
            ],
            [
                'code' => 'PF008',
                'name' => 'Causa rellena',
                'slug' => 'causa-rellena',
                'category_slug' => 'platos-frescos',
                'price' => 12.00,
                'description' => 'Entrada tradicional de la cocina peruana, suave, fresca y servida con presentación de casa.',
                'emoji' => '🍽️',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- CATEGORÍA 2: PLATOS CALIENTES (11) ---
            [
                'code' => 'PC001',
                'name' => 'Arroz con mariscos',
                'slug' => 'arroz-con-mariscos',
                'category_slug' => 'platos-calientes',
                'price' => 25.00,
                'description' => 'Clásico arroz marino caliente preparado con sazón criolla y selección del mar.',
                'emoji' => '🥘',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'PC002',
                'name' => 'Arroz chaufa de mariscos',
                'slug' => 'arroz-chaufa-de-mariscos',
                'category_slug' => 'platos-calientes',
                'price' => 20.00,
                'description' => 'Arroz chaufa salteado a fuego vivo con mariscos y el aroma tradicional de nuestra cocina.',
                'emoji' => '🍚',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'PC003',
                'name' => 'Arroz chaufa de pescado',
                'slug' => 'arroz-chaufa-de-pescado',
                'category_slug' => 'platos-calientes',
                'price' => 22.00,
                'description' => 'Arroz chaufa salteado al momento con pescado en su punto y sazón de la casa.',
                'emoji' => '🍛',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PC004',
                'name' => 'Arroz chaufa de langostinos',
                'slug' => 'arroz-chaufa-de-langostinos',
                'category_slug' => 'platos-calientes',
                'price' => 28.00,
                'description' => 'Especialidad caliente salteada a fuego alto con langostinos y toque oriental peruano.',
                'emoji' => '🦐',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'PC005',
                'name' => 'Spaghetti en salsa de mariscos',
                'slug' => 'spaghetti-en-salsa-de-mariscos',
                'category_slug' => 'platos-calientes',
                'price' => 28.00,
                'description' => 'Pasta caliente servida en generosa salsa marina preparada al momento.',
                'emoji' => '🍝',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PC006',
                'name' => 'Tallarín saltado de pescado',
                'slug' => 'tallarin-saltado-de-pescado',
                'category_slug' => 'platos-calientes',
                'price' => 28.00,
                'description' => 'Tallarines salteados al instante con pescado y el sabor clásico del salteado peruano.',
                'emoji' => '🥢',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PC007',
                'name' => 'Parihuela de pescado',
                'slug' => 'parihuela-de-pescado',
                'category_slug' => 'platos-calientes',
                'price' => 28.00,
                'description' => 'Sustanciosa parihuela caliente de pescado con todo el sabor tradicional de nuestra costa.',
                'emoji' => '🍲',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'PC008',
                'name' => 'Sudado de pescado',
                'slug' => 'sudado-de-pescado',
                'category_slug' => 'platos-calientes',
                'price' => 29.00,
                'description' => 'Pescado preparado en cocción lenta y jugosa con la receta caliente tradicional de la casa.',
                'emoji' => '🐟',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PC009',
                'name' => 'Parihuela mixta',
                'slug' => 'parihuela-mixta',
                'category_slug' => 'platos-calientes',
                'price' => 23.00,
                'description' => 'Reconfortante sopa marina caliente con combinación de pescado y mariscos.',
                'emoji' => '🥣',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PC010',
                'name' => 'Chaufa de cecina',
                'slug' => 'chaufa-de-cecina',
                'category_slug' => 'platos-calientes',
                'price' => 23.00,
                'description' => 'Sabroso chaufa caliente con cecina salteado al momento al estilo peruano.',
                'emoji' => '🥩',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'PC011',
                'name' => 'Chilcano especial',
                'slug' => 'chilcano-especial',
                'category_slug' => 'platos-calientes',
                'price' => 12.00,
                'description' => 'Caldo marino caliente y concentrado preparado con la sazón tradicional de DeSego.',
                'emoji' => '☕',
                'is_featured' => false,
                'is_popular' => true,
            ],

            // --- CATEGORÍA 3: FRITURAS (6) ---
            [
                'code' => 'FR001',
                'name' => 'Chicharrón de pescado',
                'slug' => 'chicharron-de-pescado',
                'category_slug' => 'frituras',
                'price' => 24.00,
                'description' => 'Chicharrón de pescado dorado y crujiente preparado al instante.',
                'emoji' => '🍟',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'FR002',
                'name' => 'Chicharrón de pota',
                'slug' => 'chicharron-de-pota',
                'category_slug' => 'frituras',
                'price' => 24.00,
                'description' => 'Crocante chicharrón de pota recién hecho, ideal para disfrutar al centro o individual.',
                'emoji' => '🦑',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'FR003',
                'name' => 'Jalea de pescado',
                'slug' => 'jalea-de-pescado',
                'category_slug' => 'frituras',
                'price' => 39.00,
                'description' => 'Generosa jalea de pescado crocante preparada al momento para disfrutar y compartir.',
                'emoji' => '🐡',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'FR004',
                'name' => 'Jalea mixta',
                'slug' => 'jalea-mixta',
                'category_slug' => 'frituras',
                'price' => 43.00,
                'description' => 'Especialidad marina crocante de pescado y mariscos servida recién dorada.',
                'emoji' => '🦞',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'FR005',
                'name' => 'Chicharrón mixto',
                'slug' => 'chicharron-mixto',
                'category_slug' => 'frituras',
                'price' => 28.00,
                'description' => 'Combinación crocante de pescado y mariscos fritos al punto con sazón de la casa.',
                'emoji' => '🦀',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'FR006',
                'name' => 'Chicharrón de langostinos',
                'slug' => 'chicharron-de-langostinos',
                'category_slug' => 'frituras',
                'price' => 34.00,
                'description' => 'Langostinos crocantes preparados al momento con el toque dorado de DeSego.',
                'emoji' => '🍤',
                'is_featured' => true,
                'is_popular' => false,
            ],

            // --- CATEGORÍA 4: DÚOS, TRÍOS Y RONDAS MARINAS (10) ---
            // Sin combinaciones inventadas: el campo included_items queda editable para el administrador.
            [
                'code' => 'DT001',
                'name' => 'Trío marino 1',
                'slug' => 'trio-marino-1',
                'category_slug' => 'duos-trios-rondas',
                'price' => 35.00,
                'description' => 'Combinación triple de especialidades marinas de DeSego preparada al momento.',
                'emoji' => '🍱',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT002',
                'name' => 'Trío marino 2',
                'slug' => 'trio-marino-2',
                'category_slug' => 'duos-trios-rondas',
                'price' => 35.00,
                'description' => 'Segunda propuesta de trío marino de la casa para disfrutar variedad de sabores costeños.',
                'emoji' => '🍱',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'DT003',
                'name' => 'Trío chaufero 1',
                'slug' => 'trio-chaufero-1',
                'category_slug' => 'duos-trios-rondas',
                'price' => 35.00,
                'description' => 'Combinación triple chaufera y marina servida al instante con sazón de la casa.',
                'emoji' => '🍛',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT004',
                'name' => 'Trío chaufero 2',
                'slug' => 'trio-chaufero-2',
                'category_slug' => 'duos-trios-rondas',
                'price' => 35.00,
                'description' => 'Segunda alternativa de trío chaufero marino preparado al momento.',
                'emoji' => '🍛',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'DT005',
                'name' => 'Dúo arrocero',
                'slug' => 'duo-arrocero',
                'category_slug' => 'duos-trios-rondas',
                'price' => 30.00,
                'description' => 'Dúo marino arrocero preparado al momento para quienes buscan doble sabor en un plato.',
                'emoji' => '🥘',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT006',
                'name' => 'Dúo chaufero',
                'slug' => 'duo-chaufero',
                'category_slug' => 'duos-trios-rondas',
                'price' => 30.00,
                'description' => 'Dúo chaufero con el equilibrio entre cocina caliente al wok y frescura marina.',
                'emoji' => '🍚',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'DT007',
                'name' => 'Dúo cevichero 1',
                'slug' => 'duo-cevichero-1',
                'category_slug' => 'duos-trios-rondas',
                'price' => 30.00,
                'description' => 'Primera combinación doble cevichera de la casa preparada al instante.',
                'emoji' => '🐟',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT008',
                'name' => 'Dúo cevichero 2',
                'slug' => 'duo-cevichero-2',
                'category_slug' => 'duos-trios-rondas',
                'price' => 30.00,
                'description' => 'Segunda opción de dúo cevichero con todo el carácter marino de DeSego.',
                'emoji' => '🦑',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'DT009',
                'name' => 'Dúo selva',
                'slug' => 'duo-selva',
                'category_slug' => 'duos-trios-rondas',
                'price' => 30.00,
                'description' => 'Combinación especial que une sabores amazónicos y marinos en una sola propuesta.',
                'emoji' => '🌴',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'DT010',
                'name' => 'Ronda marina',
                'slug' => 'ronda-marina',
                'category_slug' => 'duos-trios-rondas',
                'price' => 79.00,
                'description' => 'Fuente marina especial de DeSego pensada para compartir en la mesa.',
                'emoji' => '🌟',
                'is_featured' => true,
                'is_popular' => true,
            ],
        ];

        $legacyInventedCombos = [
            'Ceviche de pescado + Arroz con mariscos + Chicharrón de pescado',
            'Ceviche mixto + Arroz chaufa de mariscos + Chicharrón de pota',
            'Arroz chaufa de mariscos + Ceviche de pescado + Chicharrón de pescado',
            'Arroz chaufa de pescado + Ceviche mixto + Chicharrón mixto',
            'Arroz con mariscos + Ceviche de pescado',
            'Arroz chaufa de mariscos + Ceviche de pescado',
            'Ceviche de pescado + Chicharrón de pescado',
            'Ceviche mixto + Chicharrón de pota',
            'Chaufa de cecina + Ceviche de pescado',
            'Ceviche mixto + Arroz con mariscos + Jalea mixta + Causa acevichada',
        ];

        foreach ($officialProducts as $p) {
            $catId = $categoryIds[$p['category_slug']];
            $existingByCode = $db->table('products')->where('code', $p['code'])->first();
            $existingBySlug = ! $existingByCode ? $db->table('products')->where('slug', $p['slug'])->first() : null;
            $existing = $existingByCode ?? $existingBySlug;

            $preservedIncluded = null;
            if ($existingByCode && ! empty($existingByCode->included_items) && ! in_array($existingByCode->included_items, $legacyInventedCombos, true)) {
                $preservedIncluded = $existingByCode->included_items;
            }

            $data = [
                'code' => $p['code'],
                'category_id' => $catId,
                'slug' => $p['slug'],
                'name' => $p['name'],
                'description' => $p['description'],
                'price' => $p['price'],
                'emoji' => $p['emoji'],
                'status' => $existingByCode ? ($existingByCode->status ?: 'available') : 'available',
                'is_featured' => $p['is_featured'],
                'is_popular' => $p['is_popular'],
                'is_new' => $existingByCode ? (bool) ($existingByCode->is_new ?? false) : false,
                'included_items' => $preservedIncluded,
                'updated_at' => now(),
            ];

            if ($existing) {
                $db->table('products')->where('id', $existing->id)->update($data);
                if ($existingBySlug) {
                    // Limpiar recetas de demostración heredadas de reference-data.sql cuando se promueve un slug antiguo
                    $db->table('product_ingredients')->where('product_id', $existingBySlug->id)->delete();
                }
            } else {
                $data['created_at'] = now();
                $db->table('products')->insert($data);
            }
        }

        // Desactivar categorías y productos de demostración antiguos que no pertenezcan al catálogo oficial
        $db->table('categories')->whereIn('slug', ['entradas', 'postres', 'platos'])->update(['status' => 'inactive']);
        $db->table('products')->whereNull('code')->update(['status' => 'unavailable']);

        // 3. DATOS OFICIALES DEL RESTAURANTE (SEDE PRINCIPAL HUAYCÁN, ATE)
        $db->table('branches')->updateOrInsert(
            ['id' => 1],
            [
                'code' => 'DES-HUAYCAN',
                'name' => 'DeSego Cevichería',
                'tax_id' => null,
                'phone' => '+51 922 119 238',
                'email' => null,
                'address' => 'UCV 19, Lote 13, Zona A, Huaycán',
                'district' => 'Ate',
                'province' => 'Lima',
                'region' => 'Lima',
                'opening_time' => '09:00:00',
                'closing_time' => '17:00:00',
                'is_main' => 1,
                'status' => 'active',
                'updated_at' => now(),
            ]
        );

        // 4. HORARIOS OFICIALES DE ATENCIÓN (LUNES A DOMINGO 9:00 AM - 5:00 PM)
        $days = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        foreach ($days as $num => $name) {
            $db->table('business_hours')->updateOrInsert(
                ['day_of_week' => $num],
                [
                    'day_name' => $name,
                    'open_time' => '09:00:00',
                    'close_time' => '17:00:00',
                    'is_closed' => false,
                    'updated_at' => now(),
                ]
            );
        }

        // 5. ZONAS DE COBERTURA DE DELIVERY (SIN TARIFAS INVENTADAS HASTA CONFIRMACIÓN OFICIAL)
        $hasFeeConfirmed = Schema::hasColumn('delivery_zones', 'fee_confirmed');
        $hasPolygon = Schema::hasColumn('delivery_zones', 'polygon_coordinates');

        $zones = [
            [
                'name' => 'Huaycán - Zona A (Inmediaciones del local)',
                'district' => 'Ate',
                'zone_sector' => 'Zona A',
                'center_latitude' => -12.01250000,
                'center_longitude' => -76.82850000,
                'radius_km' => 2.50,
                'delivery_fee' => 0.00,
                'min_order_amount' => 0.00,
                'estimated_minutes_min' => 25,
                'estimated_minutes_max' => 40,
                'is_active' => true,
                'fee_confirmed' => false,
                'polygon_coordinates' => json_encode([
                    [-12.0050, -76.8370],
                    [-12.0050, -76.8190],
                    [-12.0200, -76.8190],
                    [-12.0200, -76.8370],
                ]),
            ],
            [
                'name' => 'Huaycán - Zona B y Centro',
                'district' => 'Ate',
                'zone_sector' => 'Zona B / Centro',
                'center_latitude' => -12.01500000,
                'center_longitude' => -76.82500000,
                'radius_km' => 4.50,
                'delivery_fee' => 0.00,
                'min_order_amount' => 0.00,
                'estimated_minutes_min' => 35,
                'estimated_minutes_max' => 50,
                'is_active' => true,
                'fee_confirmed' => false,
                'polygon_coordinates' => json_encode([
                    [-11.9980, -76.8450],
                    [-11.9980, -76.8080],
                    [-12.0320, -76.8080],
                    [-12.0320, -76.8450],
                ]),
            ],
            [
                'name' => 'Ate Vitarte / Cobertura Extendida',
                'district' => 'Ate',
                'zone_sector' => 'Ate',
                'center_latitude' => -12.02500000,
                'center_longitude' => -76.84000000,
                'radius_km' => 8.50,
                'delivery_fee' => 0.00,
                'min_order_amount' => 0.00,
                'estimated_minutes_min' => 45,
                'estimated_minutes_max' => 65,
                'is_active' => true,
                'fee_confirmed' => false,
                'polygon_coordinates' => null,
            ],
        ];

        foreach ($zones as $zone) {
            if (! $hasFeeConfirmed) {
                unset($zone['fee_confirmed']);
            }
            if (! $hasPolygon) {
                unset($zone['polygon_coordinates']);
            }
            $db->table('delivery_zones')->updateOrInsert(
                ['name' => $zone['name']],
                array_merge($zone, ['updated_at' => now()])
            );
        }

        // 6. CONFIGURACIÓN DEL RESTAURANTE Y REDES SOCIALES OFICIALES
        $settings = [
            'restaurant_trade_name' => 'DeSego Cevichería',
            'restaurant_address' => 'UCV 19, Lote 13, Zona A, Huaycán, Ate',
            'restaurant_postal_code' => '15479',
            'restaurant_country' => 'Perú',
            'restaurant_city' => 'Lima',
            'restaurant_district' => 'Ate',
            'restaurant_zone' => 'Huaycán',
            'restaurant_latitude' => '-12.0125000',
            'restaurant_longitude' => '-76.8285000',
            'restaurant_whatsapp' => '+51 922 119 238',
            'restaurant_whatsapp_link' => 'https://wa.me/51922119238',
            'restaurant_facebook' => 'https://facebook.com/desegocevicheria',
            'restaurant_instagram' => 'https://instagram.com/desegocevicheria',
            'restaurant_tiktok' => 'https://www.tiktok.com/@desegocevicheria/',
            'restaurant_currency' => 'PEN',
            'restaurant_schedule' => 'Lunes a Domingo: 9:00 a. m. – 5:00 p. m.',
            'restaurant_payment_methods' => 'Efectivo,Yape/Plin,Tarjeta al recibir',
            'delivery_enabled' => '1',
            'pickup_enabled' => '1',
            'dine_in_enabled' => '1',
            'reservations_enabled' => '1',
        ];

        foreach ($settings as $key => $val) {
            $db->table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $val,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
