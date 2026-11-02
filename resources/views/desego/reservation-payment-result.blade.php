<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resultado del pago · DESEGO</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 28px 16px;
            display: grid;
            place-items: center;
            background: #f4f7fb;
            color: #17243b;
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        main {
            width: min(100%, 560px);
            padding: 32px;
            border: 1px solid #e1e9f2;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 20px 55px -32px #183a6555;
            text-align: center;
        }
        .status {
            display: inline-grid;
            width: 58px;
            height: 58px;
            place-items: center;
            border-radius: 50%;
            background: {{ $result['paid'] ? '#eafaf2' : '#fff4e5' }};
            color: {{ $result['paid'] ? '#078451' : '#a95b00' }};
            font-size: 30px;
        }
        .eyebrow { color: #0752a2; font-size: 11px; font-weight: 800; letter-spacing: .14em; }
        h1 { margin: 12px 0 8px; color: #132540; font-size: 28px; line-height: 1.2; }
        p { color: #53647a; }
        .code {
            display: inline-grid;
            gap: 2px;
            margin: 10px 0 20px;
            padding: 12px 20px;
            border: 1px dashed #0752a2;
            border-radius: 14px;
            background: #f5f9ff;
            color: #06499b;
            font-weight: 800;
        }
        .code small { font-size: 10px; letter-spacing: .1em; text-transform: uppercase; }
        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 20px;
            padding: 18px;
            border: 1px solid #e4ebf3;
            border-radius: 14px;
            background: #f8fafc;
            text-align: left;
        }
        .details span { display: block; color: #64748b; font-size: 12px; }
        .details strong { color: #1d2c43; font-size: 14px; }
        .button {
            display: inline-block;
            margin-top: 22px;
            padding: 12px 20px;
            border-radius: 999px;
            background: #06499b;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }
        @media (max-width: 420px) { main { padding: 24px 18px; } .details { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <span class="status" aria-hidden="true">{{ $result['paid'] ? '✓' : '!' }}</span>
    <p class="eyebrow">DESEGO · NIUBIZ</p>
    <h1>{{ $result['paid'] ? '¡Reserva confirmada!' : 'No pudimos confirmar el pago' }}</h1>
    <p>{{ $result['message'] }}</p>

    @if ($result['paid'] && ! empty($result['reservation']))
        @php($reservation = $result['reservation'])
        <div class="code">
            <small>Código de reserva</small>
            <span>{{ $reservation['reservation_code'] }}</span>
        </div>
        <div class="details">
            <div>
                <span>Cliente</span>
                <strong>{{ $reservation['customer_name'] }}</strong>
            </div>
            <div>
                <span>Personas</span>
                <strong>{{ $reservation['guest_count'] }}</strong>
            </div>
            <div>
                <span>Fecha y hora</span>
                <strong>{{ \Carbon\CarbonImmutable::parse($reservation['reservation_at'])->setTimezone('America/Lima')->format('d/m/Y · H:i') }}</strong>
            </div>
            <div>
                <span>Mesas</span>
                <strong>{{ collect($reservation['tables'])->pluck('label')->join(' + ') }}</strong>
            </div>
            <div>
                <span>Anticipo verificado</span>
                <strong>S/ {{ number_format($reservation['advance_amount'], 2) }}</strong>
            </div>
        </div>
    @else
        <p>No vuelvas a pagar si el resultado aparece como pendiente de verificación. Contacta al restaurante antes de iniciar otro intento.</p>
    @endif

    <a class="button" href="{{ route('store.home') }}">Volver a DESEGO</a>
</main>
</body>
</html>
