<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficialCatalogSeeder extends Seeder
{
    /**
     * Catálogo oficial de DeSego Cevichería: 55 platos y bebidas organizados en 9 categorías.
     */
    public function run(): void
    {
        $db = DB::connection();

        // 1. CATEGORÍAS OFICIALES (9)
        $categoriesData = [
            [
                'slug' => 'entradas',
                'name' => 'Entradas',
                'description' => 'Entradas y porciones marinas para empezar o compartir.',
            ],
            [
                'slug' => 'ceviches',
                'name' => 'Ceviches',
                'description' => 'El auténtico sabor mundial del ceviche peruano y especialidades frescas.',
            ],
            [
                'slug' => 'duos-trios-marinos',
                'name' => 'Dúos y Tríos Marinos',
                'description' => 'Combina lo mejor de nuestra carta en un solo plato.',
            ],
            [
                'slug' => 'sopas',
                'name' => 'Sopas',
                'description' => 'Parihuelas, sudados y caldos reconfortantes de pura esencia marina.',
            ],
            [
                'slug' => 'arroces',
                'name' => 'Arroces',
                'description' => 'Arroces marinos y chaufas salteados al fuego vivo.',
            ],
            [
                'slug' => 'pastas',
                'name' => 'Pastas',
                'description' => 'Descubre la fusión marino italiana en Desego',
            ],
            [
                'slug' => 'frituras',
                'name' => 'Frituras',
                'description' => 'Chicharrones y jaleas doradas y crocantes al punto exacto.',
            ],
            [
                'slug' => 'bebidas',
                'name' => 'Bebidas',
                'description' => 'Bebidas tradicionales y refrescantes para acompañar tu mesa.',
            ],
            [
                'slug' => 'cervezas',
                'name' => 'Cervezas',
                'description' => 'Cervezas en botella bien heladas disponibles en el restaurante.',
            ],
        ];

        $db->table('categories')
            ->whereNotIn('slug', array_column($categoriesData, 'slug'))
            ->update(['status' => 'inactive', 'updated_at' => now()]);

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

        // 2. PRODUCTOS OFICIALES DE DESEGO CEVICHERÍA (55 PRODUCTOS EN 9 CATEGORÍAS)
        $officialProducts = [
            // --- ENTRADAS (7) ---
            [
                'code' => 'EN001',
                'name' => 'Causa',
                'slug' => 'causa',
                'category_slug' => 'entradas',
                'price' => 12.00,
                'description' => 'Tradicional causa limeña de papa amarilla con toque cítrico y relleno criollo.',
                'emoji' => '🥔',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'EN002',
                'name' => 'Causa acevichada',
                'slug' => 'causa-acevichada',
                'category_slug' => 'entradas',
                'price' => 25.00,
                'description' => 'Cremosa causa rellena coronada con ceviche fresco de pescado y salsa acevichada.',
                'emoji' => '🥔',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'EN003',
                'name' => 'Leche de Tigre Mixta',
                'slug' => 'leche-de-tigre-mixta',
                'category_slug' => 'entradas',
                'price' => 18.00,
                'description' => 'Potente concentrado marino con trozos de pescado y mixtura de mariscos recién extraídos.',
                'emoji' => '🥛',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'EN004',
                'name' => 'Leche de Tigre',
                'slug' => 'leche-de-tigre',
                'category_slug' => 'entradas',
                'price' => 15.00,
                'description' => 'El clásico jugo vigorizante de ceviche fresco con choclo y canchita serrana.',
                'emoji' => '🥛',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'EN005',
                'name' => 'Yucas Fritas',
                'slug' => 'yucas-fritas',
                'category_slug' => 'entradas',
                'price' => 7.00,
                'description' => 'Porción de crocantes y doradas yucas fritas servidas con salsas de la casa.',
                'emoji' => '🍟',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'EN006',
                'name' => 'Choritos a la Chalaca',
                'slug' => 'choritos-a-la-chalaca',
                'category_slug' => 'entradas',
                'price' => 15.00,
                'description' => 'Choritos jugosos y suculentos acompañados de los mejores ingredientes del mercado.',
                'emoji' => '🦪',
                'status' => 'unavailable',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'EN007',
                'name' => 'Conchitas a la Chalaca',
                'slug' => 'conchitas-a-la-chalaca',
                'category_slug' => 'entradas',
                'price' => 15.00,
                'description' => 'Conchitas jugosas y suculentas acompañadas de los mejores ingredientes del mercado.',
                'emoji' => '🦪',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- CEVICHES (9) ---
            [
                'code' => 'CV001',
                'name' => 'Ceviche Clásico de Pescado',
                'slug' => 'ceviche-clasico-de-pescado',
                'category_slug' => 'ceviches',
                'price' => 25.00,
                'description' => 'El clásico sabor mundial del ceviche',
                'emoji' => '🐟',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'CV002',
                'name' => 'Ceviche Mixto',
                'slug' => 'ceviche-mixto',
                'category_slug' => 'ceviches',
                'price' => 32.00,
                'description' => 'Ceviche clásico con mixtura de mariscos recién extraídos del mar',
                'emoji' => '🦐',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'CV003',
                'name' => 'Ceviche de Pota',
                'slug' => 'ceviche-de-pota',
                'category_slug' => 'ceviches',
                'price' => 22.00,
                'description' => 'Delicioso y jugoso ceviche de pota fresco y con bastante sabor marino.',
                'emoji' => '🦑',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'CV004',
                'name' => 'Ceviche de Langostinos',
                'slug' => 'ceviche-de-langostinos',
                'category_slug' => 'ceviches',
                'price' => 39.00,
                'description' => 'Frescos y seleccionados langostinos marinados al limón peruano con sazón DeSego.',
                'emoji' => '🦐',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'CV005',
                'name' => 'Ceviche Desego',
                'slug' => 'ceviche-desego',
                'category_slug' => 'ceviches',
                'price' => 32.00,
                'description' => 'EL plato de la casa, un sabor sinigual',
                'emoji' => '👑',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'CV006',
                'name' => 'Ceviche de Conchas Negras',
                'slug' => 'ceviche-de-conchas-negras',
                'category_slug' => 'ceviches',
                'price' => 38.00,
                'description' => 'Deliciosa y jugosa conchas negras',
                'emoji' => '🐚',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'CV007',
                'name' => 'Leche de Pantera',
                'slug' => 'leche-de-pantera',
                'category_slug' => 'ceviches',
                'price' => 38.00,
                'description' => 'Concentrado potente de conchas negras y pesca marina con puro poder afrodisíaco.',
                'emoji' => '🖤',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'CV008',
                'name' => 'Tiradito en Crema de Rocoto',
                'slug' => 'tiradito-en-crema-de-rocoto',
                'category_slug' => 'ceviches',
                'price' => 35.00,
                'description' => 'Finas láminas de pescado blanco del día, meticulosamente cortadas al estilo sashimi, bañadas en una cremosa emulsión de rocoto ahumado y cítricos. Acompañado de cubos de camote glaseado y tierno choclo desgranado. Una entrada fresca, vibrante y con el picor justo de nuestra tierra.',
                'emoji' => '🌶️',
                'status' => 'unavailable',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'CV009',
                'name' => 'Tiradito en Salsa de Ají Amarillo',
                'slug' => 'tiradito-en-salsa-de-aji-amarillo',
                'category_slug' => 'ceviches',
                'price' => 35.00,
                'description' => 'Finas láminas de pesca del día marinadas al momento en una sedosa emulsión cítrica de ají amarillo aromático, acompañadas de choclo desgranado, texturas de camote dulce y un toque sutil de culantro orgánico. Una joya de frescura y balance.',
                'emoji' => '🟡',
                'status' => 'unavailable',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- DÚOS Y TRÍOS MARINOS (9) ---
            [
                'code' => 'DT001',
                'name' => 'Trío Marino',
                'slug' => 'trio-marino',
                'category_slug' => 'duos-trios-marinos',
                'price' => 35.00,
                'description' => 'Arroz con mariscos + Ceviche + chicharrón',
                'included_items' => 'Arroz con mariscos + Ceviche + chicharrón',
                'emoji' => '🍱',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT002',
                'name' => 'Trío Chaufa',
                'slug' => 'trio-chaufa',
                'category_slug' => 'duos-trios-marinos',
                'price' => 35.00,
                'description' => 'Arroz Chaufa de Mariscos + Ceviche y Chicharrón.',
                'included_items' => 'Arroz Chaufa de Mariscos + Ceviche + Chicharrón',
                'emoji' => '🍱',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT003',
                'name' => 'Dúo Marino',
                'slug' => 'duo-marino',
                'category_slug' => 'duos-trios-marinos',
                'price' => 30.00,
                'description' => 'Arroz con Mariscos + ceviche de pescado',
                'included_items' => 'Arroz con Mariscos + ceviche de pescado',
                'emoji' => '🍱',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT004',
                'name' => 'Festín Marino',
                'slug' => 'festin-marino',
                'category_slug' => 'duos-trios-marinos',
                'price' => 38.00,
                'description' => 'Elige varias opciones en un solo plato',
                'included_items' => 'Combinación especial a elección',
                'emoji' => '🎉',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT005',
                'name' => 'Dúo Chaufa',
                'slug' => 'duo-chaufa',
                'category_slug' => 'duos-trios-marinos',
                'price' => 30.00,
                'description' => 'Arroz Chaufa de Mariscos + ceviche',
                'included_items' => 'Arroz Chaufa de Mariscos + ceviche',
                'emoji' => '🍱',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'DT006',
                'name' => 'Dúo Ceviche',
                'slug' => 'duo-ceviche',
                'category_slug' => 'duos-trios-marinos',
                'price' => 30.00,
                'description' => 'Ceviche de pescado + chicharrón a elección',
                'included_items' => 'Ceviche de pescado + chicharrón a elección',
                'emoji' => '🍱',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'DT007',
                'name' => 'Ronda Marina',
                'slug' => 'ronda-marina',
                'category_slug' => 'duos-trios-marinos',
                'price' => 79.00,
                'description' => 'Plato Colosal que incluye Arroz con Mariscos + Ceviche + Chicharrón de Pescado + Arroz chaufa de mariscos + Leche de Tigre',
                'included_items' => 'Arroz con Mariscos + Ceviche + Chicharrón de Pescado + Arroz chaufa de mariscos + Leche de Tigre',
                'emoji' => '👑',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'DT008',
                'name' => 'Duo Selva',
                'slug' => 'duo-selva',
                'category_slug' => 'duos-trios-marinos',
                'price' => 35.00,
                'description' => 'Delicioso Arroz chaufa de cecina con ceviche',
                'included_items' => 'Arroz chaufa de cecina con ceviche',
                'emoji' => '🌴',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'DT009',
                'name' => 'Duo Marino 2',
                'slug' => 'duo-marino-2',
                'category_slug' => 'duos-trios-marinos',
                'price' => 30.00,
                'description' => 'Arroz con Mariscos + Chicharrón',
                'included_items' => 'Arroz con Mariscos + Chicharrón',
                'emoji' => '🍱',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- SOPAS (5) ---
            [
                'code' => 'SO001',
                'name' => 'Sudado de Pescado',
                'slug' => 'sudado-de-pescado',
                'category_slug' => 'sopas',
                'price' => 29.00,
                'description' => 'sudado',
                'emoji' => '🍲',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'SO002',
                'name' => 'Parihuela de Pescado',
                'slug' => 'parihuela-de-pescado',
                'category_slug' => 'sopas',
                'price' => 29.00,
                'description' => 'Sopa de pura esencia con un pescado entero del dia.',
                'emoji' => '🍲',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'SO003',
                'name' => 'Chilcano de Pescado',
                'slug' => 'chilcano-de-pescado',
                'category_slug' => 'sopas',
                'price' => 12.00,
                'description' => 'Concentrado de caldo de pescado acompañado de ceviche y chicharrón',
                'emoji' => '🥣',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'SO004',
                'name' => 'Parihuela Mixta',
                'slug' => 'parihuela-mixta',
                'category_slug' => 'sopas',
                'price' => 25.00,
                'description' => 'Reconfortante y sustanciosa sopa marina con variedad de mariscos frescos.',
                'emoji' => '🍲',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'SO005',
                'name' => 'Parihuela Fileteada',
                'slug' => 'parihuela-fileteada',
                'category_slug' => 'sopas',
                'price' => 29.00,
                'description' => 'Deliciosa parihuela marina servida con filete de pescado fresco sin espinas.',
                'emoji' => '🍲',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- ARROCES (6) ---
            [
                'code' => 'AR001',
                'name' => 'Arroz Con Mariscos',
                'slug' => 'arroz-con-mariscos',
                'category_slug' => 'arroces',
                'price' => 28.00,
                'description' => 'Arroz criollo tradicional cocido en caldo de mariscos con ají amarillo y vino blanco.',
                'emoji' => '🍚',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'AR002',
                'name' => 'Arroz con pato',
                'slug' => 'arroz-con-pato',
                'category_slug' => 'arroces',
                'price' => 30.00,
                'description' => 'Arroz verde norteño al culantro y cerveza negra, servido con tierna presa de pato.',
                'emoji' => '🦆',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'AR003',
                'name' => 'Arroz Chaufa de Mariscos',
                'slug' => 'arroz-chaufa-de-mariscos',
                'category_slug' => 'arroces',
                'price' => 22.00,
                'description' => 'Arroz chaufa salteado a fuego alto con mixtura de mariscos y aroma al wok.',
                'emoji' => '🥡',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'AR004',
                'name' => 'Arroz Chaufa de Langostinos',
                'slug' => 'arroz-chaufa-de-langostinos',
                'category_slug' => 'arroces',
                'price' => 32.00,
                'description' => 'Chaufa oriental salteado al wok con langostinos enteros y cebollita china.',
                'emoji' => '🦐',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'AR005',
                'name' => 'Arroz Chaufa de Pescado',
                'slug' => 'arroz-chaufa-de-pescado',
                'category_slug' => 'arroces',
                'price' => 23.00,
                'description' => 'Arroz chaufa salteado al wok con trozos dorados de pescado y sazón de casa.',
                'emoji' => '🥡',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'AR006',
                'name' => 'Arroz Chaufa de Cecina',
                'slug' => 'arroz-chaufa-de-cecina',
                'category_slug' => 'arroces',
                'price' => 28.00,
                'description' => 'Exquisita fusión amazónica de arroz chaufa salteado con auténtica cecina de la selva.',
                'emoji' => '🥓',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],

            // --- PASTAS (3) ---
            [
                'code' => 'PA001',
                'name' => 'Spaghetti en salsa de mariscos',
                'slug' => 'spaghetti-en-salsa-de-mariscos',
                'category_slug' => 'pastas',
                'price' => 28.00,
                'description' => 'Pasta al dente en salsa marina cremosa y concentrada con mariscos seleccionados.',
                'emoji' => '🍝',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PA002',
                'name' => 'Tallarín Saltado de Pescado',
                'slug' => 'tallarin-saltado-de-pescado',
                'category_slug' => 'pastas',
                'price' => 28.00,
                'description' => 'Tallarines criollos salteados al wok con pescado, cebolla, tomate y ají amarillo.',
                'emoji' => '🥢',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'PA003',
                'name' => 'Spaghetti de langostinos, salchicha ahumada en salsa blanca',
                'slug' => 'spaghetti-de-langostinos-salchicha-ahumada-en-salsa-blanca',
                'category_slug' => 'pastas',
                'price' => 32.00,
                'description' => 'Spaghetti cremoso en salsa blanca artesanal con langostinos y salchicha ahumada.',
                'emoji' => '🍝',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- FRITURAS (7) ---
            [
                'code' => 'FR001',
                'name' => 'Chicharrón de Pescado',
                'slug' => 'chicharron-de-pescado',
                'category_slug' => 'frituras',
                'price' => 25.00,
                'description' => 'Trozos de pescado marinados y fritos al punto dorado y crocante, servidos con yuca y sarza criolla.',
                'emoji' => '🐟',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'FR002',
                'name' => 'Chicharrón de Pota',
                'slug' => 'chicharron-de-pota',
                'category_slug' => 'frituras',
                'price' => 24.00,
                'description' => 'Aros de pota tierna y crocante apanada, acompañada de yucas fritas y crema tártara.',
                'emoji' => '🦑',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'FR003',
                'name' => 'Jalea Mixta',
                'slug' => 'jalea-mixta',
                'category_slug' => 'frituras',
                'price' => 43.00,
                'description' => 'Colosal bandeja de mariscos y pescado crocante con chifles, yucas doradas y sarza criolla.',
                'emoji' => '🍤',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'FR004',
                'name' => 'Jalea de Pescado',
                'slug' => 'jalea-de-pescado',
                'category_slug' => 'frituras',
                'price' => 39.00,
                'description' => 'Filetes y trozos de pescado crocante apanado servidos con yucas y sarza criolla.',
                'emoji' => '🐟',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'FR005',
                'name' => 'Chicharrón Mixto',
                'slug' => 'chicharron-mixto',
                'category_slug' => 'frituras',
                'price' => 30.00,
                'description' => 'Combinación crocante de pescado y mixtura de mariscos fritos con tártara y ají de casa.',
                'emoji' => '🍤',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'FR006',
                'name' => 'Chicharrón de Langostinos',
                'slug' => 'chicharron-de-langostinos',
                'category_slug' => 'frituras',
                'price' => 34.00,
                'description' => 'Langostinos seleccionados apanados y dorados servidos con yucas fritas y salsas caseras.',
                'emoji' => '🦐',
                'status' => 'available',
                'is_featured' => true,
                'is_popular' => true,
            ],
            [
                'code' => 'FR007',
                'name' => 'Chicharrón de Calamar',
                'slug' => 'chicharron-de-calamar',
                'category_slug' => 'frituras',
                'price' => 32.00,
                'description' => 'Deliciosa y crocante anillos de calamar fresco sazonado en ajo, mostaza, huevo y chuño.',
                'emoji' => '🦑',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- BEBIDAS (5) ---
            [
                'code' => 'BE001',
                'name' => 'Bebidas Refrescantes',
                'slug' => 'bebidas-refrescantes',
                'category_slug' => 'bebidas',
                'price' => 9.90,
                'description' => 'Bebidas naturales gasificadas S/9.90',
                'emoji' => '🥤',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'BE002',
                'name' => 'Chicha Morada',
                'slug' => 'chicha-morada',
                'category_slug' => 'bebidas',
                'price' => 7.00,
                'description' => 'Elaborada con maíz morado, piña, manzana, canela y clavo de olor.',
                'emoji' => '🍷',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'code' => 'BE003',
                'name' => 'Chicha de Jora',
                'slug' => 'chicha-de-jora',
                'category_slug' => 'bebidas',
                'price' => 9.00,
                'description' => 'Bebida ancestral fermentada de maíz con sabor tradicional andino.',
                'emoji' => '🏺',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'BE004',
                'name' => 'Limonada',
                'slug' => 'limonada',
                'category_slug' => 'bebidas',
                'price' => 9.00,
                'description' => 'Refrescante limonada natural recién exprimida.',
                'emoji' => '🍋',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'BE005',
                'name' => 'Maracuyá',
                'slug' => 'maracuya',
                'category_slug' => 'bebidas',
                'price' => 7.00,
                'description' => 'Jugo natural de maracuyá fresco, cítrico y equilibrado.',
                'emoji' => '🍹',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],

            // --- CERVEZAS (4) ---
            [
                'code' => 'CZ001',
                'name' => 'Artesanales',
                'slug' => 'artesanales',
                'category_slug' => 'cervezas',
                'price' => 15.00,
                'description' => 'Botella 330ml s/15.00',
                'emoji' => '🍺',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'CZ002',
                'name' => 'Heineken',
                'slug' => 'heineken',
                'category_slug' => 'cervezas',
                'price' => 9.00,
                'description' => 'Botella 330ml',
                'emoji' => '🍺',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'CZ003',
                'name' => 'Corona',
                'slug' => 'corona',
                'category_slug' => 'cervezas',
                'price' => 9.00,
                'description' => 'Botella 330ml',
                'emoji' => '🍺',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'code' => 'CZ004',
                'name' => 'Flying Fish',
                'slug' => 'flying-fish',
                'category_slug' => 'cervezas',
                'price' => 9.00,
                'description' => 'Combina el cuerpo tradicional de la cerveza con un toque cítrico y dulce de lima-limón.',
                'emoji' => '🍺',
                'status' => 'available',
                'is_featured' => false,
                'is_popular' => false,
            ],
        ];

        foreach ($officialProducts as $p) {
            $catId = $categoryIds[$p['category_slug']];
            $existing = $db->table('products')->where('slug', $p['slug'])->first();

            $data = [
                'code' => $p['code'],
                'category_id' => $catId,
                'name' => $p['name'],
                'description' => $p['description'],
                'price' => $p['price'],
                'emoji' => $p['emoji'],
                'status' => $p['status'],
                'is_featured' => $p['is_featured'],
                'is_popular' => $p['is_popular'],
                'is_new' => false,
                'included_items' => $p['included_items'] ?? null,
                'updated_at' => now(),
            ];

            if ($existing) {
                $db->table('products')->where('id', $existing->id)->update($data);

                continue;
            }

            $db->table('products')->insert(array_merge($data, [
                'slug' => $p['slug'],
                'created_at' => now(),
            ]));
        }

        // 3. DATOS OFICIALES DEL RESTAURANTE (SEDE PRINCIPAL HUAYCÁN, ATE)
        $this->insertMissing('branches', ['is_main' => 1],
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
            $this->insertMissing('business_hours',
                ['day_of_week' => $num],
                [
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
            $this->insertMissing('delivery_zones',
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
            'restaurant_latitude' => '-12.009536180504373',
            'restaurant_longitude' => '-76.82422937768825',
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
            $this->insertMissing('system_settings',
                ['setting_key' => $key],
                [
                    'setting_value' => $val,
                    'updated_at' => now(),
                ]
            );
        }
    }

    /** @param array<string, mixed> $identity
     * @param  array<string, mixed>  $values
     */
    private function insertMissing(string $table, array $identity, array $values): void
    {
        if (! DB::table($table)->where($identity)->exists()) {
            DB::table($table)->insert(array_merge($identity, $values));
        }
    }
}
