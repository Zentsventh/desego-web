<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon_desego.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <title>Pago seguro con Niubiz · DeSego</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background: #f5f7fa; color: #20344f; font: 15px/1.7 system-ui, sans-serif; }
        main { max-width: 490px; margin: 4vh auto; padding: 30px; border: 1px solid #e3e9f1; border-radius: 22px; background: #fff; }
        .logo { display: block; width: 160px; height: 60px; object-fit: contain; margin: 0 auto 26px; }
        .eyebrow { color: #74859a; font-size: 10px; letter-spacing: 1.5px; }
        h1 { font-size: 27px; line-height: 1.3; margin: 10px 0 16px; }
        .amount { font-size: 38px; font-weight: 700; color: #004797; margin: 12px 0 22px; }
        .notice { background: #edf4fc; border-radius: 10px; padding: 12px 14px; font-size: 12px; }
        a { color: #004797; }
        .back { display: block; text-align: center; margin-top: 24px; font-size: 13px; }
        small { color: #7d8a9c; font-size: 12px; }
    </style>
</head>
<body>
@if (session()->pull('niubiz_clear_cart', false))
    <script>try { localStorage.removeItem('desego.cart.v1'); } catch {}</script>
@endif
<main>
    <img class="logo" src="{{ asset('images/logodesego-1.png') }}" alt="DeSego Cevichería" width="800" height="300">
    <span class="eyebrow">DESEGO · PAGO SEGURO CON NIUBIZ</span>
    <h1>Tu pedido, a un paso de estar pagado.</h1>
    @if ($sandbox)
        <p class="notice">Entorno de pruebas Niubiz. Utiliza únicamente tarjetas de prueba del proveedor.</p>
    @endif
    <small>Operación #{{ $attempt->purchase_number }} · Moneda PEN</small>
    <p class="amount">S/ {{ number_format($attempt->amount_cents / 100, 2) }}</p>
    <form action="{{ $callback }}" method="post">
        <script src="{{ $script }}" data-sessiontoken="{{ $sessionKey }}" data-channel="web"
            data-merchantid="{{ $merchant }}" data-purchasenumber="{{ $attempt->purchase_number }}"
            data-amount="{{ number_format($attempt->amount_cents / 100, 2, '.', '') }}" data-expirationminutes="10"
            data-timeouturl="{{ url('/cuenta') }}" data-merchantname="DESEGO" data-buttonsize="LARGE"
            data-buttoncolor="NAVY" data-formbuttoncolor="#004797" data-showamount="TRUE"></script>
    </form>
    <p><small>Ingresa tu tarjeta exclusivamente en el formulario de Niubiz. DeSego no guarda el número de tu tarjeta ni el código de seguridad.</small></p>
    <a class="back" href="{{ url('/cuenta') }}">Volver a mi cuenta</a>
</main>
</body>
</html>
