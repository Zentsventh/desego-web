<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatbotTools
{
    /**
     * @return list<array<string, mixed>>
     */
    public function declarations(): array
    {
        return [
            $this->declaration('list_menu_categories', 'Lista las categorías activas de la carta.', []),
            $this->declaration('search_menu', 'Busca platos por nombre, categoría o descripción; consulta siempre para responder sobre productos, precio o disponibilidad.', [
                'query' => ['type' => 'STRING', 'description' => 'Nombre, ingrediente o categoría que busca el cliente.'],
            ], ['query']),
            $this->declaration('get_product_details', 'Devuelve precio, disponibilidad y opciones actuales de un plato específico.', [
                'product_name' => ['type' => 'STRING', 'description' => 'Nombre o parte del nombre del plato.'],
            ], ['product_name']),
            $this->declaration('list_current_promotions', 'Lista promociones vigentes y sus condiciones.', []),
            $this->declaration('get_business_information', 'Consulta dirección, contacto, servicios y horario vigente del local.', []),
            $this->declaration('check_delivery_coverage', 'Consulta zonas y condiciones de reparto. Si se reciben coordenadas, verifica si el punto está dentro de una zona activa.', [
                'district' => ['type' => 'STRING', 'description' => 'Distrito del cliente, si lo conoce.'],
                'latitude' => ['type' => 'NUMBER', 'description' => 'Latitud del punto, opcional.'],
                'longitude' => ['type' => 'NUMBER', 'description' => 'Longitud del punto, opcional.'],
            ]),
            $this->declaration('get_reservation_information', 'Consulta si se reciben reservas y las condiciones generales. No crea ni confirma reservas.', []),
            $this->declaration('check_reservation_availability', 'Consulta disponibilidad pública para una fecha, hora y número de personas. Nunca revela quién reservó ni crea reservas.', [
                'date' => ['type' => 'STRING', 'description' => 'Fecha solicitada con formato YYYY-MM-DD.'],
                'time' => ['type' => 'STRING', 'description' => 'Hora solicitada con formato HH:MM.'],
                'guests' => ['type' => 'INTEGER', 'description' => 'Cantidad de personas, entre 1 y 40.'],
            ], ['date', 'time', 'guests']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        return match ($name) {
            'list_menu_categories' => $this->categories(),
            'search_menu' => $this->searchMenuTool($arguments),
            'get_product_details' => $this->productDetailsTool($arguments),
            'list_current_promotions' => $this->promotions(),
            'get_business_information' => $this->businessInformation(),
            'check_delivery_coverage' => $this->deliveryCoverage($arguments),
            'get_reservation_information' => $this->reservationInformation(),
            'check_reservation_availability' => $this->reservationAvailability($arguments),
            default => ['error' => 'La consulta solicitada no está disponible.'],
        };
    }

    public function fallbackReply(string $message): string
    {
        $query = Str::lower(Str::ascii($message));

        if (Str::contains($query, ['alerg', 'gluten', 'celiac', 'ingrediente', 'intoleran'])) {
            return 'No puedo garantizar ingredientes, ausencia de alérgenos ni contaminación cruzada. Confirma directamente con el personal antes de pedir.';
        }

        if (Str::contains($query, ['gracias', 'muchas gracias', 'agradecid', 'mil gracias'])) {
            return '¡Con gusto! 😊 Si necesitas algo más, aquí estaré para ayudarte.';
        }

        $trimmed = trim($query, " \t\n\r\0\x0B.!?¡¿");
        if (in_array($trimmed, ['hola', 'buenas', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'saludos', 'que tal'], true)) {
            return '¡Hola! 😊 Bienvenido a DeSego Cevichería. ¿En qué te puedo ayudar hoy?';
        }

        if (Str::contains($query, ['reser', 'mesa'])) {
            $information = $this->reservationInformation();

            return $information['message'].' Para enviar una solicitud, abre la sección Reservas; queda sujeta a la confirmación del local.';
        }

        if (Str::contains($query, ['delivery', 'domicilio', 'envio', 'reparto', 'recojo', 'recoger', 'llevar', 'cobertura'])) {
            $delivery = $this->deliveryCoverage([]);
            $zones = $delivery['enabled']
                ? collect($delivery['zones'] ?? [])->map(fn (array $zone): string => sprintf(
                    '%s: delivery %s, pedido mínimo S/ %s',
                    $zone['name'],
                    $zone['fee_confirmed'] ? 'S/ '.number_format((float) $zone['delivery_fee'], 2) : 'tarifa por confirmar',
                    number_format((float) $zone['min_order_amount'], 2),
                ))->implode('; ')
                : '';
            $pickupEnabled = $this->businessInformation()['services']['pickup'];
            $coverageMessage = $delivery['enabled']
                ? ($zones !== '' ? 'Zonas configuradas (valida la cobertura de tu dirección): '.$zones.'. ' : 'Consulta la cobertura con el local. ')
                : '';

            return ($delivery['enabled'] ? 'Sí, contamos con delivery. ' : 'El delivery no está disponible por el momento.')
                .$coverageMessage
                .($pickupEnabled ? 'También puedes elegir recojo en el carrito.' : '');
        }

        if (Str::contains($query, ['promocion', 'promoción', 'oferta', 'descuento', 'cupon', 'cupón'])) {
            $promotions = $this->promotions()['promotions'];
            if ($promotions === []) {
                return 'No hay promociones vigentes publicadas en este momento.';
            }

            return 'Promociones vigentes: '.collect($promotions)->map(fn (array $promotion): string => $promotion['name']
                .(! empty($promotion['code']) ? ' (código '.$promotion['code'].')' : '')
                .(! empty($promotion['description']) ? ': '.$promotion['description'] : ''))->implode('; ').'. Revisa las condiciones antes de aplicar.';
        }

        $hasHorario = Str::contains($query, ['horario', 'abren', 'cierran', 'atienden', 'hora']);
        $hasDireccion = Str::contains($query, ['direccion', 'donde estan', 'donde queda', 'donde se ubican', 'ubic', 'como llego', 'donde los encuentro']);
        $hasTelefono = Str::contains($query, ['telefono', 'whatsapp', 'celular', 'llamar', 'numero']);

        if ($hasHorario && ! $hasDireccion && ! $hasTelefono) {
            $information = $this->businessInformation();

            return $this->formatHoursSummary($information['hours'] ?? []);
        }

        if ($hasDireccion && ! $hasHorario && ! $hasTelefono) {
            $information = $this->businessInformation();
            $address = $information['address'] !== '' ? $information['address'] : 'nuestro local en Huaycán';
            $district = $information['district'] !== '' ? ', '.$information['district'] : '';

            return 'Estamos en '.$address.$district.'. 📍';
        }

        if ($hasTelefono && ! $hasHorario && ! $hasDireccion) {
            $information = $this->businessInformation();
            $phone = $information['phone'] !== '' ? $information['phone'] : '+51 922 119 238';

            return 'Puedes contactarnos directamente al WhatsApp '.$phone.'. 📲';
        }

        if (Str::contains($query, ['horario', 'abren', 'cierran', 'direccion', 'ubic', 'telefono', 'contacto', 'donde', 'local'])) {
            $information = $this->businessInformation();

            return $information['message'];
        }

        $categories = $this->categories()['categories'];
        foreach ($categories as $category) {
            if (Str::contains($query, Str::lower(Str::ascii($category['name']))) && mb_strlen($category['name']) >= 4) {
                $result = $this->searchMenu($category['name']);

                return $this->formatProducts($result['products'], $result['has_more']);
            }
        }

        $terms = collect(preg_split('/\s+/', $query) ?: [])
            ->reject(fn (string $term): bool => mb_strlen($term) < 4)
            ->reject(fn (string $term): bool => in_array($term, ['quiero', 'tienen', 'tiene', 'cuanto', 'cuesta', 'precio', 'plato', 'carta', 'menu', 'recomienda'], true))
            ->take(4);
        foreach ($terms as $term) {
            $result = $this->searchMenu($term);
            if ($result['products'] !== []) {
                return $this->formatProducts($result['products'], $result['has_more']);
            }
        }

        if (Str::contains($query, ['carta', 'menu', 'plato', 'precio', 'cuesta', 'comida', 'pedir'])) {
            $result = $this->searchMenu('');

            return $this->formatProducts($result['products'], $result['has_more']);
        }

        return 'Soy el asistente de DESEGO. Puedo ayudarte con platos y precios actuales, promociones, delivery, horarios y reservas. ¿Qué te gustaría consultar?';
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function declaration(string $name, string $description, array $properties, array $required = []): array
    {
        $parameters = ['type' => 'OBJECT', 'properties' => $properties];
        if ($required !== []) {
            $parameters['required'] = $required;
        }

        return ['name' => $name, 'description' => $description, 'parameters' => $parameters];
    }

    /**
     * @return array<string, mixed>
     */
    private function categories(): array
    {
        return ['categories' => DB::table('categories')->where('status', 'active')->orderBy('name')->get(['name', 'description'])->map(
            fn (object $category): array => ['name' => $category->name, 'description' => $category->description],
        )->all()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function searchMenuTool(array $arguments): array
    {
        $query = $this->requiredString($arguments, 'query');
        if ($query === '') {
            return ['error' => 'Indica un plato o categoría para buscar.'];
        }

        return $this->searchMenu($query);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function productDetailsTool(array $arguments): array
    {
        $name = $this->requiredString($arguments, 'product_name');
        if ($name === '') {
            return ['error' => 'Indica el nombre del plato que deseas consultar.'];
        }

        return $this->productDetails($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function searchMenu(string $search): array
    {
        $query = DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('c.status', 'active')
            ->select(['p.id', 'p.name', 'p.description', 'p.price', 'p.included_items', 'p.status', 'c.name as category'])
            ->orderBy('c.name')->orderBy('p.name');

        $search = trim($search);
        if ($search !== '') {
            $terms = collect(preg_split('/\s+/', Str::lower(Str::ascii($search))) ?: [])
                ->map(fn (string $term): string => mb_strlen($term) > 4 ? rtrim($term, 's') : $term)
                ->reject(fn (string $term): bool => mb_strlen($term) < 3)
                ->reject(fn (string $term): bool => in_array($term, ['que', 'cual', 'cuales', 'tiene', 'tienen', 'quiero', 'para', 'con', 'del', 'por', 'precio', 'cuanto', 'cuesta'], true))
                ->unique()->values();

            $query->where(function ($builder) use ($search, $terms): void {
                $builder->where('p.name', 'like', '%'.$search.'%')
                    ->orWhere('p.description', 'like', '%'.$search.'%')
                    ->orWhere('p.included_items', 'like', '%'.$search.'%')
                    ->orWhere('c.name', 'like', '%'.$search.'%');
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $builder->orWhere('p.name', 'like', $like)
                        ->orWhere('p.description', 'like', $like)
                        ->orWhere('p.included_items', 'like', $like)
                        ->orWhere('c.name', 'like', $like);
                }
            });
        }

        $total = (clone $query)->reorder()->count('p.id');
        $products = $query->limit(20)->get();
        $inventory = DB::table('product_ingredients as pi')
            ->join('ingredients as i', 'i.id', '=', 'pi.ingredient_id')
            ->groupBy('pi.product_id')
            ->selectRaw('pi.product_id, MIN(FLOOR(i.stock_quantity / NULLIF(pi.quantity, 0))) AS portions, MIN(i.status = ?) AS ingredients_active', ['active'])
            ->whereIn('pi.product_id', $products->pluck('id'))
            ->get()->keyBy('product_id');

        return [
            'products' => $products->map(function (object $product) use ($inventory): array {
                $stock = $inventory->get($product->id);

                return [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'included_items' => $product->included_items,
                    'available' => $product->status === 'available' && ($stock === null || ((bool) $stock->ingredients_active && (int) $stock->portions > 0)),
                ];
            })->all(),
            'total' => $total,
            'has_more' => $total > $products->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productDetails(string $name): array
    {
        $result = $this->searchMenu($name);
        $products = $result['products'];
        $normalizedName = Str::lower(Str::ascii($name));
        $exactProduct = collect($products)->first(
            fn (array $product): bool => Str::lower(Str::ascii($product['name'])) === $normalizedName,
        );
        if ($exactProduct !== null) {
            $products = [$exactProduct];
        }
        if (count($products) !== 1) {
            return ['products' => $products];
        }

        $product = $products[0];
        $product['option_groups'] = DB::table('product_option_groups as g')
            ->leftJoin('product_options as o', function ($join): void {
                $join->on('o.group_id', '=', 'g.id')->where('o.is_available', true);
            })
            ->where('g.product_id', $product['id'])->where('g.is_active', true)
            ->orderBy('g.sort_order')->orderBy('o.sort_order')
            ->get(['g.name as group_name', 'g.is_required', 'g.max_selections', 'o.name as option_name', 'o.price_delta'])
            ->groupBy('group_name')
            ->map(fn ($options, string $group): array => [
                'name' => $group,
                'required' => (bool) $options->first()->is_required,
                'max_selections' => (int) $options->first()->max_selections,
                'options' => $options->filter(fn (object $option): bool => $option->option_name !== null)->map(
                    fn (object $option): array => ['name' => $option->option_name, 'additional_price' => (float) $option->price_delta],
                )->values()->all(),
            ])->values()->all();

        return ['product' => $product];
    }

    /**
     * @return array<string, mixed>
     */
    private function promotions(): array
    {
        $now = now();
        $promotions = DB::table('promotions')
            ->where('status', 'active')->where('starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'))
            ->orderBy('ends_at')->limit(20)
            ->get(['name', 'description', 'promotion_type', 'code', 'discount_type', 'discount_value', 'max_discount', 'min_subtotal', 'ends_at'])
            ->map(fn (object $promotion): array => [
                'name' => $promotion->name,
                'description' => $promotion->description,
                'type' => $promotion->promotion_type,
                'code' => $promotion->code,
                'discount_type' => $promotion->discount_type,
                'discount_value' => (float) $promotion->discount_value,
                'maximum_discount' => $promotion->max_discount === null ? null : (float) $promotion->max_discount,
                'minimum_subtotal' => (float) $promotion->min_subtotal,
                'ends_at' => $promotion->ends_at,
            ])->all();

        return ['promotions' => $promotions];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessInformation(): array
    {
        $settings = $this->publicSettings();
        $branch = DB::table('branches')->where('status', 'active')->orderByDesc('is_main')->first();
        $hours = DB::table('business_hours')->orderBy('day_of_week')->get(['day_name', 'open_time', 'close_time', 'is_closed'])
            ->map(fn (object $day): array => [
                'day' => $day->day_name,
                'closed' => (bool) $day->is_closed,
                'opens' => substr((string) $day->open_time, 0, 5),
                'closes' => substr((string) $day->close_time, 0, 5),
            ])->all();
        if ($hours === [] && $branch?->opening_time && $branch?->closing_time) {
            $hours[] = [
                'day' => 'Horario general del local',
                'closed' => false,
                'opens' => substr((string) $branch->opening_time, 0, 5),
                'closes' => substr((string) $branch->closing_time, 0, 5),
            ];
        }
        $address = (string) ($settings['restaurant_address'] ?? $branch->address ?? '');
        $district = (string) ($settings['restaurant_district'] ?? $branch->district ?? '');
        $phone = (string) ($settings['restaurant_whatsapp'] ?? $branch->phone ?? '');
        $services = [
            'delivery' => ($settings['delivery_enabled'] ?? '1') === '1',
            'pickup' => ($settings['pickup_enabled'] ?? '1') === '1',
            'dine_in' => ($settings['dine_in_enabled'] ?? '1') === '1',
        ];
        $schedule = $hours === []
            ? 'El horario no está publicado; confirma con el local.'
            : collect($hours)->map(fn (array $day): string => $day['day'].': '.($day['closed'] ? 'cerrado' : $day['opens'].'–'.$day['closes']))->implode('; ');
        $message = 'Dirección: '.($address !== '' ? $address : 'no disponible')
            .($district !== '' ? ', '.$district : '').'. Teléfono/WhatsApp: '.($phone !== '' ? $phone : 'no disponible')
            .'. Horarios: '.$schedule.'. Servicios: '.collect($services)->filter()->keys()->implode(', ').'.';

        return [
            'address' => $address,
            'district' => $district,
            'phone' => $phone,
            'hours' => $hours,
            'schedule_summary' => $this->formatHoursSummary($hours),
            'services' => $services,
            'message' => $message,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $hours
     */
    private function formatHoursSummary(array $hours): string
    {
        if ($hours === []) {
            return '¡Hola! 😊 Atendemos todos los días de 9:00 a. m. a 5:00 p. m.';
        }

        $openDays = collect($hours)->reject(fn (array $d): bool => (bool) $d['closed'])->values();
        if ($openDays->isEmpty()) {
            return 'Por el momento no tenemos horarios de atención activos.';
        }

        $first = $openDays->first();
        $allSame = $openDays->count() === count($hours)
            && $openDays->every(fn (array $d): bool => $d['opens'] === $first['opens'] && $d['closes'] === $first['closes']);

        if ($allSame) {
            $open = $this->formatTime((string) $first['opens']);
            $close = $this->formatTime((string) $first['closes']);

            return "¡Hola! 😊 Atendemos todos los días de {$open} a {$close}.";
        }

        $summary = $openDays->map(fn (array $d): string => $d['day'].': '.$this->formatTime((string) $d['opens']).' a '.$this->formatTime((string) $d['closes']))->implode(', ');

        return "Nuestro horario de atención es: {$summary}. 😊";
    }

    private function formatTime(string $time): string
    {
        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $suffix = $h >= 12 ? 'p. m.' : 'a. m.';
        $h12 = $h % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }
        $mFormatted = $m > 0 ? sprintf(':%02d', $m) : ':00';

        return "{$h12}{$mFormatted} {$suffix}";
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deliveryCoverage(array $arguments): array
    {
        $settings = DB::table('system_settings')->where('setting_key', 'delivery_enabled')->value('setting_value');
        $enabled = $settings === null || $settings === '1';
        $zones = DB::table('delivery_zones')->where('is_active', true)
            ->when(is_string($arguments['district'] ?? null) && trim($arguments['district']) !== '', fn ($query) => $query->where('district', 'like', '%'.trim($arguments['district']).'%'))
            ->orderBy('district')->orderBy('name')
            ->limit(50)
            ->get(['name', 'district', 'zone_sector', 'center_latitude', 'center_longitude', 'radius_km', 'polygon_coordinates', 'delivery_fee', 'fee_confirmed', 'min_order_amount', 'estimated_minutes_min', 'estimated_minutes_max'])
            ->map(fn (object $zone): array => [
                'name' => $zone->name,
                'district' => $zone->district,
                'sector' => $zone->zone_sector,
                'center_latitude' => (float) $zone->center_latitude,
                'center_longitude' => (float) $zone->center_longitude,
                'radius_km' => (float) $zone->radius_km,
                'polygon' => json_decode((string) $zone->polygon_coordinates, true),
                'delivery_fee' => (float) $zone->delivery_fee,
                'fee_confirmed' => (bool) $zone->fee_confirmed,
                'min_order_amount' => (float) $zone->min_order_amount,
                'estimated_minutes_min' => (int) $zone->estimated_minutes_min,
                'estimated_minutes_max' => (int) $zone->estimated_minutes_max,
            ])->all();

        $hasAnyCoordinate = array_key_exists('latitude', $arguments) || array_key_exists('longitude', $arguments);
        $hasCoordinates = isset($arguments['latitude'], $arguments['longitude']);
        if ($hasAnyCoordinate && (! $hasCoordinates || ! is_numeric($arguments['latitude']) || ! is_numeric($arguments['longitude'])
            || abs((float) $arguments['latitude']) > 90 || abs((float) $arguments['longitude']) > 180)) {
            return ['enabled' => $enabled, 'covered' => false, 'error' => 'Las coordenadas no son válidas.', 'zones' => []];
        }
        $matched = null;
        if ($hasCoordinates) {
            foreach ($zones as $zone) {
                if ($this->pointInPolygon((float) $arguments['latitude'], (float) $arguments['longitude'], $zone['polygon'])
                    || $this->distanceKm((float) $arguments['latitude'], (float) $arguments['longitude'], $zone['center_latitude'], $zone['center_longitude']) <= $zone['radius_km'] * 1.15) {
                    $matched = collect($zone)->except(['center_latitude', 'center_longitude', 'radius_km', 'polygon'])->all();
                    break;
                }
            }
        }

        return [
            'enabled' => $enabled,
            'covered' => $hasCoordinates ? $enabled && $matched !== null : null,
            'matched_zone' => $matched,
            'zones' => collect($zones)->map(fn (array $zone): array => collect($zone)->except(['center_latitude', 'center_longitude', 'radius_km', 'polygon'])->all())->all(),
            'message' => $hasCoordinates
                ? ($matched !== null ? 'La ubicación está dentro de una zona registrada; confirma cobertura y tarifa al finalizar el pedido.' : 'No se encontró una zona registrada para esas coordenadas; confirma la cobertura con el local.')
                : 'La tarifa y cobertura exactas dependen de la zona de entrega; solicita el distrito o valida la ubicación en el mapa.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationInformation(): array
    {
        $config = DB::table('reservation_configurations')->orderBy('id')->first();
        $settings = $this->publicSettings();
        $enabled = ($settings['reservations_enabled'] ?? '1') === '1'
            && ($config === null || (bool) $config->reservations_enabled);

        return [
            'enabled' => $enabled,
            'advance_amount' => $config === null ? null : (float) $config->advance_amount,
            'cancellation_deadline_hours' => $config === null ? null : (int) $config->cancellation_deadline_hours,
            'message' => $enabled
                ? 'Se reciben solicitudes de reserva desde la sección Reservas. El envío no confirma la reserva; espera la respuesta del local.'
                : 'Las reservas están temporalmente deshabilitadas. Consulta directamente con el local.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reservationAvailability(array $arguments): array
    {
        $date = $arguments['date'] ?? null;
        $time = $arguments['time'] ?? null;
        $guests = $arguments['guests'] ?? null;
        if (! is_string($date) || ! is_string($time) || ! is_numeric($guests)
            || ! Validator::make(
                ['date' => $date, 'time' => $time, 'guests' => $guests],
                ['date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i', 'guests' => 'required|integer|min:1|max:40'],
            )->passes()) {
            return ['error' => 'Necesito una fecha, hora y cantidad de personas válidas para consultar disponibilidad.'];
        }

        $config = DB::table('reservation_configurations')->orderBy('id')->first();
        if (! $config) {
            return ['error' => 'La disponibilidad no está configurada en este momento; consulta con el local.'];
        }
        if (! (bool) $config->reservations_enabled || ($this->publicSettings()['reservations_enabled'] ?? '1') !== '1') {
            return ['error' => 'Las reservas están temporalmente deshabilitadas. Consulta directamente con el local.'];
        }

        try {
            $availability = app(ReservationAvailabilityService::class)->availability($date, $time, (int) $guests);
        } catch (ValidationException $exception) {
            return ['error' => collect($exception->errors())->flatten()->first() ?? 'No se pudo consultar esa fecha.'];
        }

        if ($availability['time'] === null) {
            return ['date' => $date, 'time' => $time, 'available' => false, 'message' => 'Ese horario no está dentro de los horarios de reserva publicados.'];
        }

        $eligibleTables = collect($availability['tables'])
            ->filter(fn (array $table): bool => $table['available'] && $table['capacity'] >= (int) $guests);

        return [
            'date' => $date,
            'time' => $time,
            'guests' => (int) $guests,
            'duration_minutes' => $availability['duration_minutes'],
            'available' => $eligibleTables->isNotEmpty(),
            'available_individual_tables' => $eligibleTables->count(),
            'message' => $eligibleTables->isNotEmpty()
                ? 'Se encontró al menos una mesa individual compatible; envía la solicitud en Reservas y espera la confirmación del local.'
                : 'No se encontró una mesa individual con esa capacidad en ese horario. Consulta con el local por si puede ofrecer una combinación de mesas.',
        ];
    }

    /**
     * @param  array<mixed>|null  $polygon
     */
    private function pointInPolygon(float $latitude, float $longitude, ?array $polygon): bool
    {
        if (! is_array($polygon) || count($polygon) < 3) {
            return false;
        }
        $inside = false;
        for ($i = 0, $j = count($polygon) - 1; $i < count($polygon); $j = $i++) {
            if (! is_array($polygon[$i]) || ! is_array($polygon[$j]) || count($polygon[$i]) < 2 || count($polygon[$j]) < 2) {
                continue;
            }
            $yi = (float) $polygon[$i][0];
            $xi = (float) $polygon[$i][1];
            $yj = (float) $polygon[$j][0];
            $xj = (float) $polygon[$j][1];
            if (($yi > $latitude) !== ($yj > $latitude)
                && $longitude < ($xj - $xi) * ($latitude - $yi) / (($yj - $yi) ?: 0.0000001) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function distanceKm(float $latitude, float $longitude, float $centerLatitude, float $centerLongitude): float
    {
        $latitudeDelta = deg2rad($centerLatitude - $latitude);
        $longitudeDelta = deg2rad($centerLongitude - $longitude);
        $a = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad($centerLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    /**
     * @return array<string, string>
     */
    private function publicSettings(): array
    {
        return DB::table('system_settings')
            ->whereIn('setting_key', [
                'restaurant_address',
                'restaurant_district',
                'restaurant_whatsapp',
                'delivery_enabled',
                'pickup_enabled',
                'dine_in_enabled',
                'reservations_enabled',
            ])
            ->pluck('setting_value', 'setting_key')
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function formatProducts(array $products, bool $hasMore = false): string
    {
        if ($products === []) {
            return 'No encontré platos que coincidan en la carta publicada. Puedes consultar la carta completa en la tienda.';
        }

        $formatted = collect($products)->map(fn (array $product): string => sprintf(
            '%s (%s): S/ %s — %s%s',
            $product['name'],
            $product['category'] ?? 'Carta',
            number_format((float) $product['price'], 2),
            $product['available'] ? 'disponible' : 'agotado',
            ! empty($product['description']) ? '. '.$product['description'] : '',
        ))->implode("\n");

        return $formatted.($hasMore ? "\nLa carta tiene más resultados; dime una categoría o un plato para acotar la búsqueda." : '');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function requiredString(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? null;
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 120) {
            return '';
        }

        return trim($value);
    }
}
