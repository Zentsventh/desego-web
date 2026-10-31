from pathlib import Path
files={
'app/Http/Controllers/Restaurant/StoreController.php':r'''<?php
namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
class StoreController extends Controller
{
    public function index(RestaurantPortal $portal): Response
    {
        return Inertia::render('Restaurant/Store', ['catalog'=>$portal->catalog()]);
    }
    public function checkout(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result=$portal->checkout($request->all());
        return back()->with('result',['type'=>'order',...$result])->with('success','Tu pedido ya esta en nuestro restaurante.');
    }
    public function quote(Request $request, LegacyEndpoint $legacy): RedirectResponse
    {
        $result=$legacy->call('storefront',[...$request->all(),'action'=>'quote'],[],'POST');
        return back()->with('result',['type'=>'quote',...$result]);
    }
    public function reserve(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        return back()->with('result',['type'=>'reservation',...$portal->reserve($request->all())])->with('success','Recibimos tu solicitud de reserva.');
    }
}
''',
'app/Http/Controllers/Restaurant/SessionController.php':r'''<?php
namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use function App\Support\Desego\current_user;
class SessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Restaurant/Auth',['register'=>false,'staff'=>$request->boolean('staff')]);
    }
    public function registration(): Response
    {
        return Inertia::render('Restaurant/Auth',['register'=>true,'staff'=>false]);
    }
    public function login(Request $request,RestaurantPortal $portal): RedirectResponse
    {
        $portal->login($request->all());
        $user=current_user();
        return redirect($user['role_code']==='customer' ? '/cuenta' : '/admin');
    }
    public function register(Request $request,RestaurantPortal $portal): RedirectResponse
    {
        $portal->register($request->all());
        return redirect('/cuenta')->with('success','Tu cuenta ya esta lista.');
    }
    public function logout(RestaurantPortal $portal): RedirectResponse
    {
        $portal->logout();
        return redirect('/');
    }
}
''',
'app/Http/Controllers/Restaurant/CustomerController.php':r'''<?php
namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use function App\Support\Desego\current_user;
class CustomerController extends Controller
{
    public function index(RestaurantPortal $portal): Response|RedirectResponse
    {
        if (! current_user()) { return redirect()->route('restaurant.login'); }
        return Inertia::render('Restaurant/Account',['account'=>$portal->account()]);
    }
    public function profile(Request $request,RestaurantPortal $portal): RedirectResponse
    {
        $portal->saveProfile($request->all());
        return back()->with('success','Perfil actualizado.');
    }
    public function address(Request $request,RestaurantPortal $portal): RedirectResponse
    {
        $portal->saveAddress($request->all());
        return back()->with('success','Direccion guardada.');
    }
    public function deleteAddress(int $id,RestaurantPortal $portal): RedirectResponse
    {
        $portal->deleteAddress($id); return back()->with('success','Direccion eliminada.');
    }
    public function cancelOrder(int $id,RestaurantPortal $portal): RedirectResponse
    {
        $portal->cancelOrder($id); return back()->with('success','Pedido cancelado.');
    }
    public function cancelReservation(int $id,RestaurantPortal $portal): RedirectResponse
    {
        $portal->cancelReservation($id); return back()->with('success','Reserva cancelada.');
    }
}
''',
'app/Http/Controllers/Restaurant/AdminController.php':r'''<?php
namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use function App\Support\Desego\current_user;
class AdminController extends Controller
{
    public function index(RestaurantPortal $portal,LegacyEndpoint $legacy,string $section='dashboard'): Response
    {
        $sections=$portal->sections();
        abort_unless(isset($sections[$section]),404);
        $item=$sections[$section]; $user=current_user();
        abort_unless(in_array($item['permission'],$user['permissions'],true),403);
        $data=$item['module'] ? $legacy->call($item['module'],[],isset($item['view'])?['view'=>$item['view']]:[]) : $portal->dashboard();
        $navigation=[];
        foreach ($sections as $key=>$candidate) {
            if (in_array($candidate['permission'],$user['permissions'],true)) { $navigation[]=['key'=>$key,'title'=>$candidate['title']]; }
        }
        $catalog=in_array('orders.create',$user['permissions'],true)||in_array('products.manage',$user['permissions'],true) ? $portal->catalog() : null;
        return Inertia::render('Restaurant/Admin',['section'=>$section,'title'=>$item['title'],'module'=>$item['module'],'data'=>$data,'navigation'=>$navigation,'catalog'=>$catalog]);
    }
    public function action(Request $request,string $module,LegacyEndpoint $legacy): RedirectResponse
    {
        abort_if(in_array($module,['auth','storefront','external','password-reset'],true),404);
        $result=$legacy->call($module,$request->all(),[],'POST');
        return back()->with('success',$result['message'] ?? 'Operacion completada.')->with('result',$result);
    }
}
''',
'app/Http/Controllers/Restaurant/MobileController.php':r'''<?php
namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use function App\Support\Desego\current_user;
class MobileController extends Controller
{
    public function health(): JsonResponse
    {
        DB::select('SELECT 1');
        return response()->json(['service'=>'desego','database'=>'connected']);
    }
    public function session(): JsonResponse
    {
        $user=current_user();
        return response()->json(['ok'=>true,'authenticated'=>$user!==null,'user'=>$user,'csrfToken'=>session()->token()]);
    }
    public function catalog(RestaurantPortal $portal): JsonResponse { return response()->json($portal->catalog()); }
    public function login(Request $request,RestaurantPortal $portal): JsonResponse { return response()->json($portal->login($request->all())); }
    public function register(Request $request,RestaurantPortal $portal): JsonResponse { return response()->json($portal->register($request->all()),201); }
    public function logout(RestaurantPortal $portal): JsonResponse { return response()->json($portal->logout()); }
    public function checkout(Request $request,RestaurantPortal $portal): JsonResponse { return response()->json($portal->checkout($request->all())); }
    public function quote(Request $request,LegacyEndpoint $legacy): JsonResponse { return response()->json($legacy->call('storefront',[...$request->all(),'action'=>'quote'],[],'POST')); }
    public function account(RestaurantPortal $portal): JsonResponse { return response()->json(['ok'=>true,...$portal->account()]); }
    public function profile(Request $request,RestaurantPortal $portal): JsonResponse { return response()->json($portal->saveProfile($request->all())); }
    public function address(Request $request,RestaurantPortal $portal): JsonResponse { return response()->json($portal->saveAddress($request->all())); }
    public function deleteAddress(int $id,RestaurantPortal $portal): JsonResponse { return response()->json($portal->deleteAddress($id)); }
    public function reserve(Request $request,RestaurantPortal $portal): JsonResponse { return response()->json($portal->reserve($request->all())); }
    public function cancelOrder(int $id,RestaurantPortal $portal): JsonResponse { return response()->json($portal->cancelOrder($id)); }
    public function cancelReservation(int $id,RestaurantPortal $portal): JsonResponse { return response()->json($portal->cancelReservation($id)); }
    public function dashboard(RestaurantPortal $portal): JsonResponse
    {
        $user=current_user(); abort_unless($user && in_array('dashboard.view',$user['permissions'],true),403);
        return response()->json(['ok'=>true,...$portal->dashboard()]);
    }
}
''',
'routes/web.php':r'''<?php
use App\Http\Controllers\Restaurant\StoreController;
use App\Http\Controllers\Restaurant\SessionController;
use App\Http\Controllers\Restaurant\CustomerController;
use App\Http\Controllers\Restaurant\AdminController;
use App\Http\Controllers\Restaurant\MobileController;
use App\Http\Controllers\Desego\PageController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireRestaurantStaff;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(HandleInertiaRequests::class)->group(function (): void {
    Route::get('/',[StoreController::class,'index'])->name('store.home');
    Route::get('/ingresar',[SessionController::class,'create'])->name('restaurant.login');
    Route::get('/registro',[SessionController::class,'registration']);
    Route::post('/ingresar',[SessionController::class,'login'])->middleware('throttle:10,1');
    Route::post('/registro',[SessionController::class,'register'])->middleware('throttle:5,1');
    Route::post('/salir',[SessionController::class,'logout']);
    Route::post('/pedido',[StoreController::class,'checkout'])->middleware('throttle:10,1');
    Route::post('/carrito/cotizar',[StoreController::class,'quote'])->middleware('throttle:60,1');
    Route::post('/reservar',[StoreController::class,'reserve'])->middleware('throttle:10,1');
    Route::get('/cuenta',[CustomerController::class,'index']);
    Route::post('/cuenta/perfil',[CustomerController::class,'profile']);
    Route::post('/cuenta/direcciones',[CustomerController::class,'address']);
    Route::delete('/cuenta/direcciones/{id}',[CustomerController::class,'deleteAddress']);
    Route::post('/cuenta/pedidos/{id}/cancelar',[CustomerController::class,'cancelOrder']);
    Route::post('/cuenta/reservas/{id}/cancelar',[CustomerController::class,'cancelReservation']);
    Route::middleware(RequireRestaurantStaff::class)->group(function (): void {
        Route::get('/admin',[AdminController::class,'index'])->name('admin.home');
        Route::get('/admin/herramientas',fn () => Inertia::render('Admin/Index',['csrf_token'=>csrf_token()]));
        Route::get('/admin/seccion/{section}',[AdminController::class,'index']);
        Route::post('/admin/acciones/{module}',[AdminController::class,'action']);
    });
});
Route::get('/health.php',[MobileController::class,'health']);
Route::get('/api/health',[MobileController::class,'health']);
Route::prefix('api/mobile')->group(function (): void {
    Route::get('/session',[MobileController::class,'session']);
    Route::get('/catalog',[MobileController::class,'catalog']);
    Route::post('/login',[MobileController::class,'login'])->middleware('throttle:10,1');
    Route::post('/register',[MobileController::class,'register'])->middleware('throttle:5,1');
    Route::post('/logout',[MobileController::class,'logout']);
    Route::post('/checkout',[MobileController::class,'checkout'])->middleware('throttle:10,1');
    Route::post('/quote',[MobileController::class,'quote']);
    Route::get('/account',[MobileController::class,'account']);
    Route::post('/profile',[MobileController::class,'profile']);
    Route::post('/addresses',[MobileController::class,'address']);
    Route::delete('/addresses/{id}',[MobileController::class,'deleteAddress']);
    Route::post('/reservations',[MobileController::class,'reserve']);
    Route::post('/orders/{id}/cancel',[MobileController::class,'cancelOrder']);
    Route::post('/reservations/{id}/cancel',[MobileController::class,'cancelReservation']);
    Route::get('/dashboard',[MobileController::class,'dashboard']);
});
Route::get('/api/mobile.php', [MobileController::class,'session']);
Route::get('/admin/legacy',[PageController::class,'admin'])->middleware(RequireRestaurantStaff::class)->name('desego.admin');
Route::get('/index.php',fn()=>redirect('/admin'));
Route::get('/tienda',fn()=>redirect('/'))->name('desego.store');
Route::get('/tienda.php',fn()=>redirect('/'));
foreach (config('desego_routes',[]) as $name=>$controller) {
    Route::any('/api/'.$name.'.php',$controller)->name('desego.api.'.$name);
    Route::any('/api/'.$name,$controller);
}
'''
}
for name,s in files.items():Path(name).write_text(s,encoding='utf-8')
p=Path('bootstrap/app.php');s=p.read_text(encoding='utf-8');a=s.find('        $middleware->validateCsrfTokens(')
if a>=0:
 b=s.index('        $middleware->web(',a);s=s[:a]+s[b:]
p.write_text(s,encoding='utf-8')
p=Path('app/Services/RestaurantPortal.php');s=p.read_text(encoding='utf-8').replace("'/media/menu/food_'.$image.'.png'", "'/images/menu/food_'.$image.'.jpg'");p.write_text(s,encoding='utf-8')
print('Inertia web routes, protected administration and session-based Android endpoints ready.')
