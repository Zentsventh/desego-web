<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon_desego.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <title>Resultado de pago · DeSego</title>
    <style>
        body { margin: 0; padding: 32px 16px; background: #f5f7fa; color: #20344f; font: 15px/1.7 system-ui, sans-serif; }
        main { max-width: 450px; margin: 8vh auto; padding: 30px; border: 1px solid #e3e9f1; border-radius: 22px; background: #fff; text-align: center; }
        img { width: 160px; height: 60px; object-fit: contain; }
        h1 { font-size: 25px; }
        a, button { display: inline-block; padding: 12px 20px; margin-top: 16px; border: 0; border-radius: 10px; background: #004797; color: #fff; text-decoration: none; font: inherit; cursor: pointer; }
        button { background: #edf3fb; color: #004797; }
        @media print { a, button { display: none; } }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/logodesego-1.png') }}" alt="DeSego Cevichería" width="800" height="300">
    <h1>{{ $paid ? 'Pago confirmado' : ($receipt->status === 'declined' ? 'Pago no autorizado' : ($receipt->status === 'review' ? 'Estado de tu pago' : 'Confirmando tu pago con Niubiz')) }}</h1>
    <p role="status">{{ $message }}</p>
    @if ($paid)
        <p>Operación #{{ $receipt->purchase_number }}<br><strong>S/ {{ number_format($receipt->amount_cents / 100, 2) }} · PEN</strong><br>Referencia Niubiz: {{ $receipt->provider_reference }}</p>
        <small>{{ \Illuminate\Support\Carbon::parse($receipt->updated_at)->timezone('America/Lima')->format('d/m/Y H:i') }}</small>
        <p><small>Guarda esta constancia de pago. Tu comprobante del pedido está disponible en tu cuenta.</small></p>
    @endif
    <a href="{{ url('/cuenta') }}">Volver a mi cuenta</a>
    @if ($statusUrl)
        <a href="{{ $statusUrl }}">Consultar estado en Niubiz</a>
    @endif
    @if ($paid)
        <button type="button" onclick="window.print()">Guardar constancia</button>
    @endif
</main>
</body>
</html>
