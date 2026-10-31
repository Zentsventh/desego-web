<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use function App\Support\Desego\current_user;

class AdminController extends Controller
{
    public function index(RestaurantPortal $portal, LegacyEndpoint $legacy, string $section = 'dashboard'): Response
    {
        $sections = $portal->sections();
        abort_unless(isset($sections[$section]), 404);
        $item = $sections[$section];
        $user = current_user();
        abort_unless(in_array($item['permission'], $user['permissions'], true), 403);
        $needsCatalog = in_array($section, ['pedidos', 'carta'], true)
            && (in_array('orders.create', $user['permissions'], true) || in_array('products.manage', $user['permissions'], true));
        $needsIngredients = $section === 'carta' && in_array('products.manage', $user['permissions'], true);

        return Inertia::render('Restaurant/Admin', [
            'section' => $section,
            'title' => $item['title'],
            'module' => $item['module'],
            'data' => fn () => $item['module'] ? $legacy->call($item['module'], [], isset($item['view']) ? ['view' => $item['view']] : []) : $portal->dashboard(),
            'navigation' => fn () => $this->navigation($portal, $user['permissions']),
            'catalog' => fn () => $needsCatalog ? $portal->catalog() : null,
            'ingredients' => fn () => $needsIngredients ? $legacy->call('catalog', [], ['view' => 'ingredients'])['ingredients'] : [],
        ]);
    }

    public function tools(RestaurantPortal $portal): Response
    {
        $user = current_user();

        return Inertia::render('Restaurant/Admin', [
            'section' => 'herramientas',
            'title' => 'Herramientas',
            'data' => [],
            'navigation' => $this->navigation($portal, $user['permissions']),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     * @return list<array{key: string, title: string}>
     */
    private function navigation(RestaurantPortal $portal, array $permissions): array
    {
        $navigation = [];
        foreach ($portal->sections() as $key => $candidate) {
            if (in_array($candidate['permission'], $permissions, true)) {
                $navigation[] = ['key' => $key, 'title' => $candidate['title']];
            }
        }

        return $navigation;
    }

    public function action(Request $request, string $module, LegacyEndpoint $legacy): RedirectResponse
    {
        abort_if(in_array($module, ['auth', 'storefront', 'external', 'password-reset'], true), 404);
        $result = $legacy->call($module, $request->all(), [], 'POST');

        return back()->with('success', $result['message'] ?? 'Operacion completada.')->with('result', $result);
    }
}
