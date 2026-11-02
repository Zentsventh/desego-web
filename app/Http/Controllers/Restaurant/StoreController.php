<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\NiubizOrderPayment;
use App\Services\ReservationAvailability;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(Request $request, RestaurantPortal $portal, LegacyEndpoint $legacy): Response
    {
        return Inertia::render('Restaurant/Store', [
            'catalog' => $portal->catalog(),
            'lastOrder' => $legacy->call('storefront', [], ['view' => 'order'])['order'] ?? null,
            'canonicalUrl' => $request->url(),
        ]);
    }

    public function checkout(Request $request, RestaurantPortal $portal, NiubizOrderPayment $payments): \Symfony\Component\HttpFoundation\Response
    {
        $usesNiubiz = $request->input('payment_preference') === 'Niubiz';
        $customer = null;
        if ($usesNiubiz) {
            $customer = $portal->customer(false);
            abort_unless($payments->available(), 503, 'Niubiz no está disponible. Elige otro medio de pago.');
        }
        $result = $portal->checkout($request->all());

        if ($usesNiubiz) {
            $url = $payments->start(
                (int) $result['order_id'],
                $customer ? (int) $customer->id : null,
                (string) $request->ip(),
                $customer ? null : (string) $request->input('checkout_key'),
            );
            $request->session()->flash('niubiz_clear_cart', true);

            return $request->header('X-Inertia') ? Inertia::location($url) : redirect()->to($url);
        }

        return back()->with('result', ['type' => 'order', ...$result])->with('success', 'Tu pedido ya está en nuestro restaurante.');
    }

    public function quote(Request $request, LegacyEndpoint $legacy): RedirectResponse
    {
        $result = $legacy->call('storefront', [...$request->all(), 'action' => 'quote'], [], 'POST');

        return back()->with('result', ['type' => 'quote', ...$result]);
    }

    public function reserve(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $request->validate(['table_ids' => 'required|array|min:1']);

        return back()->with('result', ['type' => 'reservation', ...$portal->reserve($request->all())])->with('success', 'Recibimos tu solicitud de reserva.');
    }

    public function availability(Request $request, ReservationAvailability $availability): JsonResponse
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'time' => 'nullable|date_format:H:i']);

        return response()->json($availability->availability($data['date'], $data['time'] ?? null))->header('Cache-Control', 'no-store');
    }

    public function productReviews(int $productId, LegacyEndpoint $legacy): JsonResponse
    {
        $result = $legacy->call('reviews', [], ['view' => 'product', 'product_id' => $productId]);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function sitemap(Request $request): HttpResponse
    {
        $base = rtrim($request->getSchemeAndHttpHost(), '/');
        $today = now('America/Lima')->format('Y-m-d');
        $urls = [
            ['loc' => $base.'/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => $base.'/carta', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $base.'/nuestra-esencia', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => $base.'/reservar', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $base.'/ubicacion', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $base.'/ingresar', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => $base.'/registro', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ];

        $categories = DB::table('categories')->where('status', 'active')->get(['slug', 'updated_at']);
        foreach ($categories as $cat) {
            $urls[] = [
                'loc' => $base.'/?categoria='.urlencode((string) $cat->slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8')."</loc>\n";
            $xml .= '    <lastmod>'.$today."</lastmod>\n";
            $xml .= '    <changefreq>'.$u['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$u['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
