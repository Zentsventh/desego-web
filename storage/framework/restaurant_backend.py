from pathlib import Path
files={
'app/Support/Desego/LegacyEndpoint.php':r'''<?php
namespace App\Support\Desego;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
class LegacyEndpoint
{
    /** @return array<string, mixed> */
    public function call(string $module, array $data = [], array $query = [], string $method = 'GET'): array
    {
        $controller = config('desego_routes.'.$module);
        abort_unless(is_string($controller), 404);
        $original = request();
        $server = $original->server->all();
        $server['HTTP_X_CSRF_TOKEN'] = $original->session()->token();
        $server['CONTENT_TYPE'] = 'application/json';
        $synthetic = Request::create($original->url(), $method, [], $original->cookies->all(), [], $server, json_encode($data, JSON_THROW_ON_ERROR));
        $synthetic->query->replace($query);
        $synthetic->setLaravelSession($original->session());
        app()->instance('request', $synthetic);
        try {
            try { $response = app($controller)(); }
            catch (HttpResponseException $exception) { $response = $exception->getResponse(); }
            $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if ($response->getStatusCode() >= 400) {
                if (in_array($response->getStatusCode(), [401, 403, 404], true)) { abort($response->getStatusCode(), $payload['message'] ?? 'Acceso no disponible.'); }
                throw ValidationException::withMessages(['request' => $payload['message'] ?? 'No se pudo completar la solicitud.']);
            }
            return $payload;
        } finally { app()->instance('request', $original); }
    }
}
''',
'app/Http/Middleware/RequireRestaurantStaff.php':r'''<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function App\Support\Desego\current_user;
class RequireRestaurantStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = current_user();
        if (! $user) { return redirect()->route('restaurant.login', ['staff' => 1]); }
        abort_if($user['role_code'] === 'customer' || empty($user['permissions']), 403);
        return $next($request);
    }
}
''',
'app/Http/Middleware/HandleInertiaRequests.php':r'''<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;
use function App\Support\Desego\current_user;
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';
    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => ['user' => fn () => current_user()],
            'csrfToken' => fn () => $request->session()->token(),
            'flash' => ['success' => fn () => $request->session()->get('success'), 'result' => fn () => $request->session()->get('result')],
        ];
    }
}
''',
'app/Services/RestaurantPortal.php':r'''<?php
namespace App\Services;
use App\Support\Desego\LegacyEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use function App\Support\Desego\current_user;
use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_add_adjustment_cost_layer;
class RestaurantPortal
{
    public function __construct(private LegacyEndpoint $legacy) {}
    public function catalog(): array
    {
        $catalog = $this->legacy->call('storefront');
        $stock = DB::select('SELECT pi.product_id, MIN(FLOOR(i.stock_quantity / NULLIF(pi.quantity,0))) AS portions, MIN(i.status = ?) AS ingredients_active FROM product_ingredients pi JOIN ingredients i ON i.id=pi.ingredient_id GROUP BY pi.product_id', ['active']);
        $available = collect($stock)->keyBy('product_id');
        $reviews = $this->legacy->call('reviews')['summary'] ?? [];
        $catalog['products'] = array_map(function (array $product) use ($available, $reviews): array {
            $inventory = $available->get($product['id']);
            $product['can_order'] = $product['can_order'] && $inventory && $inventory->ingredients_active && $inventory->portions > 0;
            $product['image'] = $this->photo($product['name']);
            $product['review'] = $reviews[$product['id']] ?? ['count' => 0, 'average' => null];
            return $product;
        }, $catalog['products']);
        $catalog['branch'] = DB::table('branches')->where('status', 'active')->orderByDesc('is_main')->first(['name','address','district','phone','email','opening_time','closing_time']);
        return $catalog;
    }
    public function photo(string $name): string
    {
        $value = mb_strtolower($name);
        $image = match (true) {
            str_contains($value, 'arroz') => 'arroz',
            str_contains($value, 'chicha') || str_contains($value, 'bebida') => 'chicha',
            str_contains($value, 'chocolate') || str_contains($value, 'torta') => 'chocolate',
            str_contains($value, 'frito') || str_contains($value, 'pescado') && ! str_contains($value, 'ceviche') => 'pescado',
            default => 'ceviche',
        };
        return '/media/menu/food_'.$image.'.png';
    }
    public function customer(bool $required = true): ?object
    {
        $user = current_user();
        if (! $user) { abort_if($required, 401); return null; }
        $customer = DB::table('customers')->where('user_id', $user['id'])->where('status', 'active')->first();
        abort_if($required && ! $customer, 403, 'Esta cuenta no tiene un perfil de cliente.');
        if ($customer) { session()->put('storefront_customer_id', $customer->id); }
        return $customer;
    }
    public function register(array $input): array
    {
        $data = Validator::make($input, ['name'=>'required|string|min:3|max:100','email'=>'required|email|max:190|unique:users,email','password'=>'required|string|min:10|max:4096|confirmed','phone'=>'nullable|string|max:30'])->validate();
        DB::transaction(function () use ($data): void {
            DB::table('roles')->insertOrIgnore(['code'=>'customer','name'=>'Cliente','description'=>'Cuenta de cliente de la tienda','is_system'=>1,'created_at'=>now(),'updated_at'=>now()]);
            $id = DB::table('users')->insertGetId(['name'=>$data['name'],'email'=>mb_strtolower($data['email']),'password_hash'=>Hash::make($data['password']),'role_id'=>DB::table('roles')->where('code','customer')->value('id'),'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
            $customerId = DB::table('customers')->insertGetId(['user_id'=>$id,'full_name'=>$data['name'],'email'=>mb_strtolower($data['email']),'phone'=>$data['phone'] ?? null,'document_type'=>'SIN_DOCUMENTO','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
            DB::table('customers')->where('id',$customerId)->update(['customer_code'=>'CLI-'.str_pad((string)$customerId,6,'0',STR_PAD_LEFT)]);
            session()->regenerate(); session()->put('user_id',$id); session()->put('storefront_customer_id',$customerId); session()->put('csrf_token',session()->token());
        });
        return ['ok'=>true,'user'=>current_user(),'csrfToken'=>session()->token()];
    }
    public function login(array $input): array
    {
        $result = $this->legacy->call('auth', $input, ['action'=>'login'], 'POST');
        $this->customer(false);
        return $result;
    }
    public function logout(): array
    {
        $result = $this->legacy->call('auth', [], ['action'=>'logout'], 'POST');
        session()->forget(['storefront_customer_id','storefront_last_order_id']);
        return $result;
    }
    public function checkout(array $input): array
    {
        $customer = $this->customer(false);
        if ($customer) {
            $input['email'] = $customer->email;
            $input['customer_name'] = $input['customer_name'] ?? $customer->full_name;
        }
        $result = $this->legacy->call('storefront', [...$input, 'action'=>'checkout'], [], 'POST');
        if ($customer && isset($result['order_id'])) {
            $order = DB::table('orders')->where('id',$result['order_id'])->first();
            if ($order && $order->customer_id) {
                $owner = DB::table('customers')->where('id',$order->customer_id)->value('user_id');
                if ($owner === null) { DB::table('customers')->where('id',$order->customer_id)->update(['user_id'=>current_user()['id']]); }
            }
        }
        return $result;
    }
    public function account(): array
    {
        $customer = $this->customer();
        $orders = DB::table('orders')->where('customer_id',$customer->id)->orderByDesc('id')->limit(50)->get();
        foreach ($orders as $order) { $order->items = DB::table('order_items')->where('order_id',$order->id)->get(); }
        return ['customer'=>$customer,'orders'=>$orders,'addresses'=>DB::table('customer_addresses')->where('customer_id',$customer->id)->where('status','active')->orderByDesc('is_default')->get(),
            'reservations'=>DB::table('reservations')->where('customer_id',$customer->id)->orderByDesc('reservation_at')->limit(30)->get(),
            'loyalty'=>DB::table('loyalty_accounts')->where('customer_id',$customer->id)->first()];
    }
    public function saveProfile(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input,['name'=>'required|string|min:3|max:100','phone'=>'required|string|max:30'])->validate();
        DB::transaction(function () use ($customer,$data): void {
            DB::table('customers')->where('id',$customer->id)->update(['full_name'=>$data['name'],'phone'=>$data['phone'],'updated_at'=>now()]);
            DB::table('users')->where('id',$customer->user_id)->update(['name'=>$data['name'],'updated_at'=>now()]);
        });
        return ['ok'=>true,'message'=>'Perfil actualizado.'];
    }
    public function saveAddress(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input,['label'=>'required|string|max:40','address_line'=>'required|string|min:5|max:255','district'=>'required|string|max:100','reference'=>'nullable|string|max:255','phone'=>'nullable|string|max:30'])->validate();
        $id = DB::table('customer_addresses')->insertGetId([...$data,'customer_id'=>$customer->id,'recipient_name'=>$customer->full_name,'province'=>'Lima','department'=>'Lima','status'=>'active','is_default'=>0,'created_at'=>now(),'updated_at'=>now()]);
        return ['ok'=>true,'id'=>$id,'message'=>'Direccion guardada.'];
    }
    public function deleteAddress(int $id): array
    {
        $customer = $this->customer();
        $address = DB::table('customer_addresses')->where('id',$id)->where('customer_id',$customer->id)->first();
        abort_unless($address,404);
        DB::table('customer_addresses')->where('id',$id)->update(['status'=>'inactive','is_default'=>0,'updated_at'=>now()]);
        return ['ok'=>true];
    }
    public function reserve(array $input): array
    {
        $data = Validator::make($input,['customer_name'=>'required|string|min:3|max:100','phone'=>'required|string|max:30','email'=>'nullable|email|max:190','date'=>'required|date_format:Y-m-d','time'=>'required|date_format:H:i','people'=>'required|integer|min:1|max:24','notes'=>'nullable|string|max:500'])->validate();
        $at = CarbonImmutable::createFromFormat('Y-m-d H:i',$data['date'].' '.$data['time'],'America/Lima');
        if ($at->lessThan(now('America/Lima'))) { throw ValidationException::withMessages(['date'=>'Elige una fecha y hora futura.']); }
        $customer = $this->customer(false);
        $actor = $customer?->user_id ?? DB::table('users')->join('roles','roles.id','=','users.role_id')->where('roles.code','admin')->where('users.status','active')->value('users.id');
        abort_unless($actor,503,'El restaurante aun no recibe reservas.');
        return DB::transaction(function () use ($data,$at,$customer,$actor): array {
            $tables = DB::table('restaurant_tables')->where('is_active',1)->where('operational_status','<>','maintenance')->where('capacity','>=',$data['people'])->orderBy('capacity')->orderBy('id')->lockForUpdate()->get();
            $start = $at->utc(); $end = $start->addMinutes(120); $table = null;
            foreach ($tables as $candidate) {
                $busy = DB::table('reservations')->where('table_id',$candidate->id)->whereIn('status',['Pendiente','Confirmada','Sentada'])->where('reservation_at','<',$end)->whereRaw('DATE_ADD(reservation_at, INTERVAL duration_minutes MINUTE) > ?',[$start])->exists();
                if (! $busy) { $table = $candidate; break; }
            }
            if (! $table) { throw ValidationException::withMessages(['time'=>'No hay mesas disponibles para ese horario y cantidad.']); }
            $id = DB::table('reservations')->insertGetId(['customer_name'=>$data['customer_name'],'customer_id'=>$customer?->id,'customer_phone'=>$data['phone'],'customer_email'=>$data['email'] ?? null,'table_id'=>$table->id,'guest_count'=>$data['people'],'reservation_at'=>$start,'duration_minutes'=>120,'status'=>'Pendiente','notes'=>$data['notes'] ?? null,'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            $code = 'RES-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            DB::table('reservations')->where('id',$id)->update(['reservation_code'=>$code]);
            audit_log(desego_db(),(int)$actor,'reservations.store_created',['reservation_id'=>$id]);
            return ['ok'=>true,'id'=>$id,'reservation_code'=>$code,'message'=>'Solicitud recibida. El restaurante confirmara tu reserva.'];
        });
    }
    public function cancelReservation(int $id): array
    {
        $customer=$this->customer();
        $record=DB::table('reservations')->where('id',$id)->where('customer_id',$customer->id)->first();
        abort_unless($record,404);
        if (! in_array($record->status,['Pendiente','Confirmada'],true) || CarbonImmutable::parse($record->reservation_at,'UTC')->isPast()) { throw ValidationException::withMessages(['reservation'=>'Esta reserva ya no se puede cancelar.']); }
        DB::table('reservations')->where('id',$id)->update(['status'=>'Cancelada','updated_at'=>now()]);
        return ['ok'=>true];
    }
    public function cancelOrder(int $id): array
    {
        $customer=$this->customer();
        return DB::transaction(function () use($customer,$id): array {
            $order=DB::table('orders')->where('id',$id)->where('customer_id',$customer->id)->lockForUpdate()->first();
            abort_unless($order,404);
            if ($order->status!=='Pendiente' || $order->payment_status!=='Pendiente') { throw ValidationException::withMessages(['order'=>'Solo puedes cancelar un pedido pendiente y sin cobrar.']); }
            foreach (DB::table('order_ingredients')->where('order_id',$id)->whereNull('restored_at')->orderBy('ingredient_id')->get() as $line) {
                $ingredient=DB::table('ingredients')->where('id',$line->ingredient_id)->lockForUpdate()->first();
                DB::table('ingredients')->where('id',$line->ingredient_id)->update(['stock_quantity'=>$ingredient->stock_quantity+$line->consumed_quantity,'updated_at'=>now()]);
                desego_add_adjustment_cost_layer(desego_db(),(int)$line->ingredient_id,(float)$line->consumed_quantity,(float)$line->unit_cost,'Cancelacion de pedido '.$order->order_number);
                DB::table('order_ingredients')->where('order_id',$id)->where('ingredient_id',$line->ingredient_id)->update(['restored_at'=>now()]);
            }
            DB::table('orders')->where('id',$id)->update(['status'=>'Cancelado','cancelled_at'=>now(),'updated_at'=>now()]);
            audit_log(desego_db(),(int)$customer->user_id,'orders.customer_cancelled',['order_id'=>$id]);
            return ['ok'=>true,'message'=>'Pedido cancelado.'];
        });
    }
    public function sections(): array
    {
        return [
            'dashboard'=>['title'=>'Vista general','module'=>null,'permission'=>'dashboard.view'],
            'pedidos'=>['title'=>'Pedidos','module'=>'orders','permission'=>'orders.view'],
            'carta'=>['title'=>'Carta y productos','module'=>'catalog','permission'=>'menu.view'],
            'inventario'=>['title'=>'Inventario','module'=>'inventory','permission'=>'inventory.view'],
            'clientes'=>['title'=>'Clientes','module'=>'customers','permission'=>'customers.view'],
            'reservas'=>['title'=>'Reservas y mesas','module'=>'reservations','permission'=>'reservations.view'],
            'caja'=>['title'=>'Caja y cobros','module'=>'cash','permission'=>'cash.view'],
            'cocina'=>['title'=>'Cocina','module'=>'operations','view'=>'kitchen','permission'=>'kitchen.view'],
            'delivery'=>['title'=>'Delivery','module'=>'operations','view'=>'delivery','permission'=>'delivery.view'],
            'compras'=>['title'=>'Compras y proveedores','module'=>'purchasing','permission'=>'purchases.view'],
            'promociones'=>['title'=>'Promociones','module'=>'promotions','permission'=>'promotions.view'],
            'fidelizacion'=>['title'=>'Fidelizacion','module'=>'loyalty','permission'=>'loyalty.view'],
            'equipo'=>['title'=>'Equipo','module'=>'workforce','view'=>'employees','permission'=>'staff.view'],
            'roles'=>['title'=>'Roles y permisos','module'=>'roles','permission'=>'roles.view'],
            'reportes'=>['title'=>'Reportes','module'=>'billing-reports','view'=>'reports','permission'=>'reports.view'],
            'auditoria'=>['title'=>'Auditoria','module'=>'admin-tools','view'=>'audit','permission'=>'audit.view'],
            'backups'=>['title'=>'Copias de seguridad','module'=>'admin-tools','view'=>'backups','permission'=>'backups.view'],
            'soporte'=>['title'=>'Soporte','module'=>'communications','view'=>'tickets','permission'=>'support.view'],
        ];
    }
    public function dashboard(): array
    {
        $user=current_user(); $permissions=$user['permissions'] ?? [];
        $canOrders=in_array('orders.view',$permissions,true);
        $canInventory=in_array('inventory.view',$permissions,true);
        $start=now('America/Lima')->startOfDay()->utc();
        return [
            'sales'=>$canOrders ? (float)DB::table('orders')->where('payment_status','Pagado')->where('paid_at','>=',$start)->sum('total') : null,
            'orders_today'=>$canOrders ? DB::table('orders')->where('created_at','>=',$start)->count() : null,
            'pending'=>$canOrders ? DB::table('orders')->whereIn('status',['Pendiente','Preparando','Listo','En reparto'])->count() : null,
            'customers'=>in_array('customers.view',$permissions,true) ? DB::table('customers')->where('status','active')->count() : null,
            'recent_orders'=>$canOrders ? DB::table('orders')->orderByDesc('id')->limit(8)->get(['id','order_number','customer_name','order_type','status','payment_status','total','created_at']) : [],
            'low_stock'=>$canInventory ? DB::table('ingredients')->where('status','active')->whereColumn('stock_quantity','<=','min_stock')->get(['id','name','stock_quantity','min_stock','unit']) : [],
            'daily_sales'=>$canOrders ? DB::table('orders')->selectRaw('DATE(DATE_SUB(paid_at, INTERVAL 5 HOUR)) AS date, SUM(total) AS total')->where('payment_status','Pagado')->where('paid_at','>=',now()->subDays(7))->groupByRaw('DATE(DATE_SUB(paid_at, INTERVAL 5 HOUR))')->orderBy('date')->get() : [],
        ];
    }
}
'''
}
for name,s in files.items():
 p=Path(name);p.parent.mkdir(parents=True,exist_ok=True);p.write_text(s,encoding='utf-8')
p=next(Path('database/migrations').glob('*_link_customer_accounts_to_users.php'));p.write_text(r'''<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function(Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->unique('user_id','uq_customers_user');
            $table->foreign('user_id','fk_customers_user')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('customers', function(Blueprint $table): void {
            $table->dropForeign('fk_customers_user');
            $table->dropUnique('uq_customers_user');
            $table->dropColumn('user_id');
        });
    }
};
''',encoding='utf-8')
# Extend the existing, transactional checkout to accept collection at the restaurant.
p=Path('app/Http/Controllers/Desego/StorefrontController.php');s=p.read_text(encoding='utf-8')
s=s.replace("            $name = trim((string) ($body['customer_name'] ?? ''));", "            $isDelivery = ($body['order_type'] ?? 'Delivery') === 'Delivery';\n            if (! in_array($body['order_type'] ?? 'Delivery', ['Delivery', 'Para llevar'], true)) { throw new StorefrontProblem('Tipo de entrega no valido.'); }\n            $name = trim((string) ($body['customer_name'] ?? ''));")
s=s.replace("if ($savedAddressId === null && (text_length($addressLine)","if ($isDelivery && $savedAddressId === null && (text_length($addressLine)")
a=s.index("                    $pdo->prepare('UPDATE customer_addresses SET is_default")
b=s.index("                    $address = [",a)
s=s[:a]+"                    if ($isDelivery) {\n"+s[a:b]+"                    }\n"+s[b:]
s=s.replace('$deliveryFee = self::DESEGO_WEB_DELIVERY_FEE;', '$deliveryFee = $isDelivery ? self::DESEGO_WEB_DELIVERY_FEE : 0.0;')
s=s.replace(":customer_id, 'Delivery', 'web'",":customer_id, :order_type, 'web'")
s=s.replace("'customer_name' => $name, 'customer_id' => $customerId, 'checkout_key' => $checkoutKey,", "'customer_name' => $name, 'customer_id' => $customerId, 'checkout_key' => $checkoutKey, 'order_type' => $isDelivery ? 'Delivery' : 'Para llevar',")
a=s.index('                $deliveryInsert = $pdo->prepare(');b=s.index('                $itemInsert = ',a)
s=s[:a]+'                if ($isDelivery) {\n'+s[a:b]+'                }\n'+s[b:]
# Quote also calculates the correct collection fee.
s=s.replace('$fee = self::DESEGO_WEB_DELIVERY_FEE;', "$fee = ($body['order_type'] ?? 'Delivery') === 'Para llevar' ? 0.0 : self::DESEGO_WEB_DELIVERY_FEE;")
p.write_text(s,encoding='utf-8')
print('Shared portal services, customer accounts and delivery/collection checkout implemented.')
