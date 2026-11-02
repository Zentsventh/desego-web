<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categorías oficiales solicitadas
        $categories = [
            'entradas' => [
                'name' => 'Entradas',
                'description' => 'Entradas y porciones marinas para empezar o compartir.',
            ],
            'ceviches' => [
                'name' => 'Ceviches',
                'description' => 'El auténtico sabor mundial del ceviche peruano y especialidades frescas.',
            ],
            'duos-trios-marinos' => [
                'name' => 'Dúos y Tríos Marinos',
                'description' => 'Combina lo mejor de nuestra carta en un solo plato.',
            ],
            'sopas' => [
                'name' => 'Sopas',
                'description' => 'Parihuelas, sudados y caldos reconfortantes de pura esencia marina.',
            ],
            'arroces' => [
                'name' => 'Arroces',
                'description' => 'Arroces marinos y chaufas salteados al fuego vivo.',
            ],
            'pastas' => [
                'name' => 'Pastas',
                'description' => 'Descubre la fusión marino italiana en Desego',
            ],
            'frituras' => [
                'name' => 'Frituras',
                'description' => 'Chicharrones y jaleas doradas y crocantes al punto exacto.',
            ],
            'bebidas' => [
                'name' => 'Bebidas',
                'description' => 'Bebidas tradicionales y refrescantes para acompañar tu mesa.',
            ],
            'cervezas' => [
                'name' => 'Cervezas',
                'description' => 'Cervezas en botella bien heladas disponibles en el restaurante.',
            ],
        ];

        // Desactivar categorías obsoletas
        DB::table('categories')
            ->whereNotIn('slug', array_keys($categories))
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        // Asegurar que las 9 categorías oficiales existan y estén activas
        $catIds = [];
        foreach ($categories as $slug => $data) {
            $existing = DB::table('categories')->where('slug', $slug)->first();
            if ($existing) {
                DB::table('categories')->where('id', $existing->id)->update([
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
                $catIds[$slug] = $existing->id;
            } else {
                $catIds[$slug] = DB::table('categories')->insertGetId([
                    'slug' => $slug,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Mapeo completo de los productos oficiales según lo solicitado
        $catalog = [
            'entradas' => [
                [
                    'match_slugs' => ['causa', 'causa-rellena'],
                    'code' => 'EN001',
                    'name' => 'Causa',
                    'slug' => 'causa',
                    'price' => 12.00,
                    'description' => 'Tradicional causa limeña de papa amarilla con toque cítrico y relleno criollo.',
                    'status' => 'available',
                    'emoji' => '🥔',
                ],
                [
                    'match_slugs' => ['causa-acevichada'],
                    'code' => 'EN002',
                    'name' => 'Causa acevichada',
                    'slug' => 'causa-acevichada',
                    'price' => 25.00,
                    'description' => 'Cremosa causa rellena coronada con ceviche fresco de pescado y salsa acevichada.',
                    'status' => 'available',
                    'emoji' => '🥔',
                ],
                [
                    'match_slugs' => ['leche-de-tigre-mixta', 'leche-de-tigre-mixto'],
                    'code' => 'EN003',
                    'name' => 'Leche de Tigre Mixta',
                    'slug' => 'leche-de-tigre-mixta',
                    'price' => 18.00,
                    'description' => 'Potente concentrado marino con trozos de pescado y mixtura de mariscos recién extraídos.',
                    'status' => 'available',
                    'emoji' => '🥛',
                ],
                [
                    'match_slugs' => ['leche-de-tigre', 'leche-desego'],
                    'code' => 'EN004',
                    'name' => 'Leche de Tigre',
                    'slug' => 'leche-de-tigre',
                    'price' => 15.00,
                    'description' => 'El clásico jugo vigorizante de ceviche fresco con choclo y canchita serrana.',
                    'status' => 'available',
                    'emoji' => '🥛',
                ],
                [
                    'match_slugs' => ['yucas-fritas'],
                    'code' => 'EN005',
                    'name' => 'Yucas Fritas',
                    'slug' => 'yucas-fritas',
                    'price' => 7.00,
                    'description' => 'Porción de crocantes y doradas yucas fritas servidas con salsas de la casa.',
                    'status' => 'available',
                    'emoji' => '🍟',
                ],
                [
                    'match_slugs' => ['choritos-a-la-chalaca'],
                    'code' => 'EN006',
                    'name' => 'Choritos a la Chalaca',
                    'slug' => 'choritos-a-la-chalaca',
                    'price' => 15.00,
                    'description' => 'Choritos jugosos y suculentos acompañados de los mejores ingredientes del mercado.',
                    'status' => 'unavailable',
                    'emoji' => '🦪',
                ],
                [
                    'match_slugs' => ['conchitas-a-la-chalaca'],
                    'code' => 'EN007',
                    'name' => 'Conchitas a la Chalaca',
                    'slug' => 'conchitas-a-la-chalaca',
                    'price' => 15.00,
                    'description' => 'Conchitas jugosas y suculentas acompañadas de los mejores ingredientes del mercado.',
                    'status' => 'available',
                    'emoji' => '🦪',
                ],
            ],

            'ceviches' => [
                [
                    'match_slugs' => ['ceviche-clasico-de-pescado', 'ceviche-de-pescado', 'ceviche-clasico'],
                    'code' => 'CV001',
                    'name' => 'Ceviche Clásico de Pescado',
                    'slug' => 'ceviche-clasico-de-pescado',
                    'price' => 25.00,
                    'description' => 'El clásico sabor mundial del ceviche',
                    'status' => 'available',
                    'emoji' => '🐟',
                ],
                [
                    'match_slugs' => ['ceviche-mixto'],
                    'code' => 'CV002',
                    'name' => 'Ceviche Mixto',
                    'slug' => 'ceviche-mixto',
                    'price' => 32.00,
                    'description' => 'Ceviche clásico con mixtura de mariscos recién extraídos del mar',
                    'status' => 'available',
                    'emoji' => '🦐',
                ],
                [
                    'match_slugs' => ['ceviche-de-pota'],
                    'code' => 'CV003',
                    'name' => 'Ceviche de Pota',
                    'slug' => 'ceviche-de-pota',
                    'price' => 22.00,
                    'description' => 'Delicioso y jugoso ceviche de pota fresco y con bastante sabor marino.',
                    'status' => 'available',
                    'emoji' => '🦑',
                ],
                [
                    'match_slugs' => ['ceviche-de-langostinos'],
                    'code' => 'CV004',
                    'name' => 'Ceviche de Langostinos',
                    'slug' => 'ceviche-de-langostinos',
                    'price' => 39.00,
                    'description' => 'Frescos y seleccionados langostinos marinados al limón peruano con sazón DeSego.',
                    'status' => 'available',
                    'emoji' => '🦐',
                ],
                [
                    'match_slugs' => ['ceviche-desego'],
                    'code' => 'CV005',
                    'name' => 'Ceviche Desego',
                    'slug' => 'ceviche-desego',
                    'price' => 32.00,
                    'description' => 'EL plato de la casa, un sabor sinigual',
                    'status' => 'available',
                    'emoji' => '👑',
                ],
                [
                    'match_slugs' => ['ceviche-de-conchas-negras'],
                    'code' => 'CV006',
                    'name' => 'Ceviche de Conchas Negras',
                    'slug' => 'ceviche-de-conchas-negras',
                    'price' => 38.00,
                    'description' => 'Deliciosa y jugosa conchas negras',
                    'status' => 'available',
                    'emoji' => '🐚',
                ],
                [
                    'match_slugs' => ['leche-de-pantera'],
                    'code' => 'CV007',
                    'name' => 'Leche de Pantera',
                    'slug' => 'leche-de-pantera',
                    'price' => 38.00,
                    'description' => 'Concentrado potente de conchas negras y pesca marina con puro poder afrodisíaco.',
                    'status' => 'available',
                    'emoji' => '🖤',
                ],
                [
                    'match_slugs' => ['tiradito-en-crema-de-rocoto'],
                    'code' => 'CV008',
                    'name' => 'Tiradito en Crema de Rocoto',
                    'slug' => 'tiradito-en-crema-de-rocoto',
                    'price' => 35.00,
                    'description' => 'Finas láminas de pescado blanco del día, meticulosamente cortadas al estilo sashimi, bañadas en una cremosa emulsión de rocoto ahumado y cítricos. Acompañado de cubos de camote glaseado y tierno choclo desgranado. Una entrada fresca, vibrante y con el picor justo de nuestra tierra.',
                    'status' => 'unavailable',
                    'emoji' => '🌶️',
                ],
                [
                    'match_slugs' => ['tiradito-en-salsa-de-aji-amarillo'],
                    'code' => 'CV009',
                    'name' => 'Tiradito en Salsa de Ají Amarillo',
                    'slug' => 'tiradito-en-salsa-de-aji-amarillo',
                    'price' => 35.00,
                    'description' => 'Finas láminas de pesca del día marinadas al momento en una sedosa emulsión cítrica de ají amarillo aromático, acompañadas de choclo desgranado, texturas de camote dulce y un toque sutil de culantro orgánico. Una joya de frescura y balance.',
                    'status' => 'unavailable',
                    'emoji' => '🟡',
                ],
            ],

            'duos-trios-marinos' => [
                [
                    'match_slugs' => ['trio-marino', 'trio-marino-1'],
                    'code' => 'DT001',
                    'name' => 'Trío Marino',
                    'slug' => 'trio-marino',
                    'price' => 35.00,
                    'description' => 'Arroz con mariscos + Ceviche + chicharrón',
                    'included_items' => 'Arroz con mariscos + Ceviche + chicharrón',
                    'status' => 'available',
                    'emoji' => '🍱',
                ],
                [
                    'match_slugs' => ['trio-chaufa', 'trio-chaufero-1'],
                    'code' => 'DT002',
                    'name' => 'Trío Chaufa',
                    'slug' => 'trio-chaufa',
                    'price' => 35.00,
                    'description' => 'Arroz Chaufa de Mariscos + Ceviche y Chicharrón.',
                    'included_items' => 'Arroz Chaufa de Mariscos + Ceviche + Chicharrón',
                    'status' => 'available',
                    'emoji' => '🍱',
                ],
                [
                    'match_slugs' => ['duo-marino', 'duo-arrocero'],
                    'code' => 'DT003',
                    'name' => 'Dúo Marino',
                    'slug' => 'duo-marino',
                    'price' => 30.00,
                    'description' => 'Arroz con Mariscos + ceviche de pescado',
                    'included_items' => 'Arroz con Mariscos + ceviche de pescado',
                    'status' => 'available',
                    'emoji' => '🍱',
                ],
                [
                    'match_slugs' => ['festin-marino'],
                    'code' => 'DT004',
                    'name' => 'Festín Marino',
                    'slug' => 'festin-marino',
                    'price' => 38.00,
                    'description' => 'Elige varias opciones en un solo plato',
                    'status' => 'available',
                    'emoji' => '🎉',
                ],
                [
                    'match_slugs' => ['duo-chaufa', 'duo-chaufero'],
                    'code' => 'DT005',
                    'name' => 'Dúo Chaufa',
                    'slug' => 'duo-chaufa',
                    'price' => 30.00,
                    'description' => 'Arroz Chaufa de Mariscos + ceviche',
                    'included_items' => 'Arroz Chaufa de Mariscos + ceviche',
                    'status' => 'available',
                    'emoji' => '🍱',
                ],
                [
                    'match_slugs' => ['duo-ceviche', 'duo-cevichero-1'],
                    'code' => 'DT006',
                    'name' => 'Dúo Ceviche',
                    'slug' => 'duo-ceviche',
                    'price' => 30.00,
                    'description' => 'Ceviche de pescado + chicharrón a elección',
                    'included_items' => 'Ceviche de pescado + chicharrón a elección',
                    'status' => 'available',
                    'emoji' => '🍱',
                ],
                [
                    'match_slugs' => ['ronda-marina'],
                    'code' => 'DT007',
                    'name' => 'Ronda Marina',
                    'slug' => 'ronda-marina',
                    'price' => 79.00,
                    'description' => 'Plato Colosal que incluye Arroz con Mariscos + Ceviche + Chicharrón de Pescado + Arroz chaufa de mariscos + Leche de Tigre',
                    'included_items' => 'Arroz con Mariscos + Ceviche + Chicharrón de Pescado + Arroz chaufa de mariscos + Leche de Tigre',
                    'status' => 'available',
                    'emoji' => '👑',
                ],
                [
                    'match_slugs' => ['duo-selva'],
                    'code' => 'DT008',
                    'name' => 'Duo Selva',
                    'slug' => 'duo-selva',
                    'price' => 35.00,
                    'description' => 'Delicioso Arroz chaufa de cecina con ceviche',
                    'included_items' => 'Arroz chaufa de cecina con ceviche',
                    'status' => 'available',
                    'emoji' => '🌴',
                ],
                [
                    'match_slugs' => ['duo-marino-2', 'trio-marino-2'],
                    'code' => 'DT009',
                    'name' => 'Duo Marino 2',
                    'slug' => 'duo-marino-2',
                    'price' => 30.00,
                    'description' => 'Arroz con Mariscos + Chicharrón',
                    'included_items' => 'Arroz con Mariscos + Chicharrón',
                    'status' => 'available',
                    'emoji' => '🍱',
                ],
            ],

            'sopas' => [
                [
                    'match_slugs' => ['sudado-de-pescado'],
                    'code' => 'SO001',
                    'name' => 'Sudado de Pescado',
                    'slug' => 'sudado-de-pescado',
                    'price' => 29.00,
                    'description' => 'sudado',
                    'status' => 'available',
                    'emoji' => '🍲',
                ],
                [
                    'match_slugs' => ['parihuela-de-pescado'],
                    'code' => 'SO002',
                    'name' => 'Parihuela de Pescado',
                    'slug' => 'parihuela-de-pescado',
                    'price' => 29.00,
                    'description' => 'Sopa de pura esencia con un pescado entero del dia.',
                    'status' => 'available',
                    'emoji' => '🍲',
                ],
                [
                    'match_slugs' => ['chilcano-de-pescado', 'chilcano-especial'],
                    'code' => 'SO003',
                    'name' => 'Chilcano de Pescado',
                    'slug' => 'chilcano-de-pescado',
                    'price' => 12.00,
                    'description' => 'Concentrado de caldo de pescado acompañado de ceviche y chicharrón',
                    'status' => 'available',
                    'emoji' => '🥣',
                ],
                [
                    'match_slugs' => ['parihuela-mixta'],
                    'code' => 'SO004',
                    'name' => 'Parihuela Mixta',
                    'slug' => 'parihuela-mixta',
                    'price' => 25.00,
                    'description' => 'Reconfortante y sustanciosa sopa marina con variedad de mariscos frescos.',
                    'status' => 'available',
                    'emoji' => '🍲',
                ],
                [
                    'match_slugs' => ['parihuela-fileteada'],
                    'code' => 'SO005',
                    'name' => 'Parihuela Fileteada',
                    'slug' => 'parihuela-fileteada',
                    'price' => 29.00,
                    'description' => 'Deliciosa parihuela marina servida con filete de pescado fresco sin espinas.',
                    'status' => 'available',
                    'emoji' => '🍲',
                ],
            ],

            'arroces' => [
                [
                    'match_slugs' => ['arroz-con-mariscos'],
                    'code' => 'AR001',
                    'name' => 'Arroz Con Mariscos',
                    'slug' => 'arroz-con-mariscos',
                    'price' => 28.00,
                    'description' => 'Arroz criollo tradicional cocido en caldo de mariscos con ají amarillo y vino blanco.',
                    'status' => 'available',
                    'emoji' => '🍚',
                ],
                [
                    'match_slugs' => ['arroz-con-pato'],
                    'code' => 'AR002',
                    'name' => 'Arroz con pato',
                    'slug' => 'arroz-con-pato',
                    'price' => 30.00,
                    'description' => 'Arroz verde norteño al culantro y cerveza negra, servido con tierna presa de pato.',
                    'status' => 'available',
                    'emoji' => '🦆',
                ],
                [
                    'match_slugs' => ['arroz-chaufa-de-mariscos'],
                    'code' => 'AR003',
                    'name' => 'Arroz Chaufa de Mariscos',
                    'slug' => 'arroz-chaufa-de-mariscos',
                    'price' => 22.00,
                    'description' => 'Arroz chaufa salteado a fuego alto con mixtura de mariscos y aroma al wok.',
                    'status' => 'available',
                    'emoji' => '🥡',
                ],
                [
                    'match_slugs' => ['arroz-chaufa-de-langostinos'],
                    'code' => 'AR004',
                    'name' => 'Arroz Chaufa de Langostinos',
                    'slug' => 'arroz-chaufa-de-langostinos',
                    'price' => 32.00,
                    'description' => 'Chaufa oriental salteado al wok con langostinos enteros y cebollita china.',
                    'status' => 'available',
                    'emoji' => '🦐',
                ],
                [
                    'match_slugs' => ['arroz-chaufa-de-pescado'],
                    'code' => 'AR005',
                    'name' => 'Arroz Chaufa de Pescado',
                    'slug' => 'arroz-chaufa-de-pescado',
                    'price' => 23.00,
                    'description' => 'Arroz chaufa salteado al wok con trozos dorados de pescado y sazón de casa.',
                    'status' => 'available',
                    'emoji' => '🥡',
                ],
                [
                    'match_slugs' => ['arroz-chaufa-de-cecina', 'chaufa-de-cecina'],
                    'code' => 'AR006',
                    'name' => 'Arroz Chaufa de Cecina',
                    'slug' => 'arroz-chaufa-de-cecina',
                    'price' => 28.00,
                    'description' => 'Exquisita fusión amazónica de arroz chaufa salteado con auténtica cecina de la selva.',
                    'status' => 'available',
                    'emoji' => '🥓',
                ],
            ],

            'pastas' => [
                [
                    'match_slugs' => ['spaghetti-en-salsa-de-mariscos'],
                    'code' => 'PA001',
                    'name' => 'Spaghetti en salsa de mariscos',
                    'slug' => 'spaghetti-en-salsa-de-mariscos',
                    'price' => 28.00,
                    'description' => 'Pasta al dente en salsa marina cremosa y concentrada con mariscos seleccionados.',
                    'status' => 'available',
                    'emoji' => '🍝',
                ],
                [
                    'match_slugs' => ['tallarin-saltado-de-pescado'],
                    'code' => 'PA002',
                    'name' => 'Tallarín Saltado de Pescado',
                    'slug' => 'tallarin-saltado-de-pescado',
                    'price' => 28.00,
                    'description' => 'Tallarines criollos salteados al wok con pescado, cebolla, tomate y ají amarillo.',
                    'status' => 'available',
                    'emoji' => '🥢',
                ],
                [
                    'match_slugs' => ['spaghetti-de-langostinos-salchicha-ahumada-en-salsa-blanca'],
                    'code' => 'PA003',
                    'name' => 'Spaghetti de langostinos, salchicha ahumada en salsa blanca',
                    'slug' => 'spaghetti-de-langostinos-salchicha-ahumada-en-salsa-blanca',
                    'price' => 32.00,
                    'description' => 'Spaghetti cremoso en salsa blanca artesanal con langostinos y salchicha ahumada.',
                    'status' => 'available',
                    'emoji' => '🍝',
                ],
            ],

            'frituras' => [
                [
                    'match_slugs' => ['chicharron-de-pescado'],
                    'code' => 'FR001',
                    'name' => 'Chicharrón de Pescado',
                    'slug' => 'chicharron-de-pescado',
                    'price' => 25.00,
                    'description' => 'Trozos de pescado marinados y fritos al punto dorado y crocante, servidos con yuca y sarza criolla.',
                    'status' => 'available',
                    'emoji' => '🐟',
                ],
                [
                    'match_slugs' => ['chicharron-de-pota'],
                    'code' => 'FR002',
                    'name' => 'Chicharrón de Pota',
                    'slug' => 'chicharron-de-pota',
                    'price' => 24.00,
                    'description' => 'Aros de pota tierna y crocante apanada, acompañada de yucas fritas y crema tártara.',
                    'status' => 'available',
                    'emoji' => '🦑',
                ],
                [
                    'match_slugs' => ['jalea-mixta'],
                    'code' => 'FR003',
                    'name' => 'Jalea Mixta',
                    'slug' => 'jalea-mixta',
                    'price' => 43.00,
                    'description' => 'Colosal bandeja de mariscos y pescado crocante con chifles, yucas doradas y sarza criolla.',
                    'status' => 'available',
                    'emoji' => '🍤',
                ],
                [
                    'match_slugs' => ['jalea-de-pescado'],
                    'code' => 'FR004',
                    'name' => 'Jalea de Pescado',
                    'slug' => 'jalea-de-pescado',
                    'price' => 39.00,
                    'description' => 'Filetes y trozos de pescado crocante apanado servidos con yucas y sarza criolla.',
                    'status' => 'available',
                    'emoji' => '🐟',
                ],
                [
                    'match_slugs' => ['chicharron-mixto'],
                    'code' => 'FR005',
                    'name' => 'Chicharrón Mixto',
                    'slug' => 'chicharron-mixto',
                    'price' => 30.00,
                    'description' => 'Combinación crocante de pescado y mixtura de mariscos fritos con tártara y ají de casa.',
                    'status' => 'available',
                    'emoji' => '🍤',
                ],
                [
                    'match_slugs' => ['chicharron-de-langostinos'],
                    'code' => 'FR006',
                    'name' => 'Chicharrón de Langostinos',
                    'slug' => 'chicharron-de-langostinos',
                    'price' => 34.00,
                    'description' => 'Langostinos seleccionados apanados y dorados servidos con yucas fritas y salsas caseras.',
                    'status' => 'available',
                    'emoji' => '🦐',
                ],
                [
                    'match_slugs' => ['chicharron-de-calamar'],
                    'code' => 'FR007',
                    'name' => 'Chicharrón de Calamar',
                    'slug' => 'chicharron-de-calamar',
                    'price' => 32.00,
                    'description' => 'Deliciosa y crocante anillos de calamar fresco sazonado en ajo, mostaza, huevo y chuño.',
                    'status' => 'available',
                    'emoji' => '🦑',
                ],
            ],

            'bebidas' => [
                [
                    'match_slugs' => ['bebidas-refrescantes'],
                    'code' => 'BE001',
                    'name' => 'Bebidas Refrescantes',
                    'slug' => 'bebidas-refrescantes',
                    'price' => 9.90,
                    'description' => 'Bebidas naturales gasificadas S/9.90',
                    'status' => 'available',
                    'emoji' => '🥤',
                ],
                [
                    'match_slugs' => ['chicha-morada'],
                    'code' => 'BE002',
                    'name' => 'Chicha Morada',
                    'slug' => 'chicha-morada',
                    'price' => 7.00,
                    'description' => 'Elaborada con maíz morado, piña, manzana, canela y clavo de olor.',
                    'status' => 'available',
                    'emoji' => '🍷',
                ],
                [
                    'match_slugs' => ['chicha-de-jora'],
                    'code' => 'BE003',
                    'name' => 'Chicha de Jora',
                    'slug' => 'chicha-de-jora',
                    'price' => 9.00,
                    'description' => 'Bebida ancestral fermentada de maíz con sabor tradicional andino.',
                    'status' => 'available',
                    'emoji' => '🏺',
                ],
                [
                    'match_slugs' => ['limonada'],
                    'code' => 'BE004',
                    'name' => 'Limonada',
                    'slug' => 'limonada',
                    'price' => 9.00,
                    'description' => 'Refrescante limonada natural recién exprimida.',
                    'status' => 'available',
                    'emoji' => '🍋',
                ],
                [
                    'match_slugs' => ['maracuya'],
                    'code' => 'BE005',
                    'name' => 'Maracuyá',
                    'slug' => 'maracuya',
                    'price' => 7.00,
                    'description' => 'Jugo natural de maracuyá fresco, cítrico y equilibrado.',
                    'status' => 'available',
                    'emoji' => '🍹',
                ],
            ],

            'cervezas' => [
                [
                    'match_slugs' => ['artesanales'],
                    'code' => 'CZ001',
                    'name' => 'Artesanales',
                    'slug' => 'artesanales',
                    'price' => 15.00,
                    'description' => 'Botella 330ml s/15.00',
                    'status' => 'available',
                    'emoji' => '🍺',
                ],
                [
                    'match_slugs' => ['heineken'],
                    'code' => 'CZ002',
                    'name' => 'Heineken',
                    'slug' => 'heineken',
                    'price' => 9.00,
                    'description' => 'Botella 330ml',
                    'status' => 'available',
                    'emoji' => '🍺',
                ],
                [
                    'match_slugs' => ['corona'],
                    'code' => 'CZ003',
                    'name' => 'Corona',
                    'slug' => 'corona',
                    'price' => 9.00,
                    'description' => 'Botella 330ml',
                    'status' => 'available',
                    'emoji' => '🍺',
                ],
                [
                    'match_slugs' => ['flying-fish'],
                    'code' => 'CZ004',
                    'name' => 'Flying Fish',
                    'slug' => 'flying-fish',
                    'price' => 9.00,
                    'description' => 'Combina el cuerpo tradicional de la cerveza con un toque cítrico y dulce de lima-limón.',
                    'status' => 'available',
                    'emoji' => '🍺',
                ],
            ],
        ];

        $updatedProductIds = [];

        // Temporarily reset codes to prevent unique constraint collisions during re-indexing
        DB::table('products')->update(['code' => DB::raw("CONCAT('TMP_', id)")]);

        foreach ($catalog as $catSlug => $items) {
            $catId = $catIds[$catSlug];

            foreach ($items as $item) {
                $targetSlug = $item['slug'];
                $matchSlugs = $item['match_slugs'];

                // Buscar por slug principal o slugs antiguos
                $product = DB::table('products')->whereIn('slug', $matchSlugs)->first();

                // Si no se encuentra por slug, buscar por nombre exacto
                if (! $product) {
                    $product = DB::table('products')->where('name', $item['name'])->first();
                }

                $productData = [
                    'category_id' => $catId,
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'slug' => $targetSlug,
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'emoji' => $item['emoji'],
                    'status' => $item['status'],
                    'included_items' => $item['included_items'] ?? null,
                    'updated_at' => now(),
                ];

                if ($product) {
                    // Si el slug cambió y el nuevo slug ya existe en otro registro, mantener slug único
                    $slugConflict = DB::table('products')->where('slug', $targetSlug)->where('id', '!=', $product->id)->exists();
                    if ($slugConflict) {
                        $productData['slug'] = $product->slug;
                    }
                    DB::table('products')->where('id', $product->id)->update($productData);
                    $updatedProductIds[] = $product->id;
                } else {
                    $productData['created_at'] = now();
                    $newId = DB::table('products')->insertGetId($productData);
                    $updatedProductIds[] = $newId;
                }
            }
        }

        // Marcar productos no incluidos en el nuevo catálogo como unavailable
        DB::table('products')
            ->whereNotIn('id', $updatedProductIds)
            ->update(['status' => 'unavailable', 'updated_at' => now()]);
    }

    public function down(): void {}
};
