<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\ChatbotConversations;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use function App\Support\Desego\current_user;

class AdminController extends Controller
{
    public function index(RestaurantPortal $portal, LegacyEndpoint $legacy, string $section = 'dashboard'): Response
    {
        $user = current_user();
        if (! $user) {
            return Inertia::render('Restaurant/Auth', ['register' => false, 'staff' => true]);
        }
        abort_if(($user['role_code'] ?? null) === 'customer' || empty($user['permissions']), 403);

        $sections = $portal->sections();
        abort_unless(isset($sections[$section]), 404);
        $item = $sections[$section];
        abort_unless(in_array($item['permission'], $user['permissions'], true), 403);
        $needsCatalog = in_array($section, ['pedidos', 'carta'], true)
            && (in_array('orders.create', $user['permissions'], true) || in_array('products.manage', $user['permissions'], true));
        $needsIngredients = $section === 'carta' && in_array('products.manage', $user['permissions'], true);

        return Inertia::render('Restaurant/Admin', [
            'section' => $section,
            'title' => $item['title'],
            'module' => $item['module'],
            'data' => fn () => $section === 'chatbot'
                ? app(ChatbotConversations::class)->listing(request())
                : ($item['module'] ? $legacy->call($item['module'], [], isset($item['view']) ? ['view' => $item['view']] : []) : $portal->dashboard()),
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

        return back()->with('success', $result['message'] ?? 'Operación completada.')->with('result', $result);
    }

    public function uploadImage(Request $request, RestaurantPortal $portal): JsonResponse
    {
        $user = current_user();
        abort_unless($user && (($user['role_code'] ?? '') === 'admin' || in_array('products.manage', $user['permissions'] ?? [], true) || in_array('settings.manage', $user['permissions'] ?? [], true)), 403);
        $file = $request->file('image') ?? $request->file('file');
        abort_unless($file !== null, 422, 'Debes enviar un archivo de imagen válido.');
        $request->validate([
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
        $url = $portal->storeUploadedFile($file, 'products', ['jpg', 'jpeg', 'png', 'webp'], 4096);

        return response()->json(['ok' => true, 'url' => $url]);
    }
}
