<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pago seguro de reserva · DESEGO</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 32px 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #f4f8fc, #e8f1fc);
            color: #152238;
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        main {
            width: min(100%, 520px);
            padding: 32px;
            border: 1px solid #dce7f3;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 24px 60px -32px #123b6855;
        }
        .eyebrow {
            color: #0752a2;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        h1 { margin: 8px 0; color: #10233d; font-size: 28px; line-height: 1.2; }
        .amount {
            margin: 20px 0;
            padding-bottom: 16px;
            border-bottom: 1px solid #e8edf4;
            color: #06499b;
            font-size: 34px;
            font-weight: 800;
        }
        .operation { color: #64748b; font-size: 13px; font-weight: 500; }
        .sandbox {
            margin: 20px 0;
            padding: 16px;
            border: 1px dashed #7ba7d8;
            border-radius: 16px;
            background: #f5f9ff;
            font-size: 13px;
        }
        .sandbox strong { display: block; margin-bottom: 6px; color: #12477d; }
        .sandbox p { margin: 6px 0; }
        .sandbox a, .back { color: #06499b; }
        .secure-note { color: #56657a; font-size: 13px; }
        .back { display: inline-block; margin-top: 16px; font-weight: 650; }
        @media (max-width: 520px) { main { padding: 24px 20px; } }
    </style>
</head>
<body>
<main>
    <span class="eyebrow">DESEGO · PAGO SEGURO CON NIUBIZ</span>
    <h1>Confirma el anticipo de tu reserva</h1>
    <p class="amount">
        S/ {{ number_format($attempt->amount_cents / 100, 2) }}
        <span class="operation">· Operación {{ $attempt->purchase_number }} · PEN</span>
    </p>

    @if ($sandbox)
        <aside class="sandbox" aria-label="Datos oficiales del entorno de pruebas">
            <strong>Entorno oficial de pruebas Niubiz</strong>
            <p>Comercio de prueba (soles): <code>{{ $merchant }}</code></p>
            <p>Los datos oficiales disponibles no incluyen números de tarjeta ni códigos OTP. Solicita esos datos de prueba a Niubiz; no uses tarjetas reales.</p>
            <a href="https://desarrolladores.niubiz.com.pe/docs/data-para-pruebas" target="_blank" rel="noopener noreferrer">
                Consultar datos oficiales de prueba
            </a>
        </aside>
    @endif

    <form action="{{ $callback }}" method="post">
        @csrf
        <script
            src="{{ $script }}"
            data-sessiontoken="{{ $sessionKey }}"
            data-channel="web"
            data-merchantid="{{ $merchant }}"
            data-purchasenumber="{{ $attempt->purchase_number }}"
            data-amount="{{ number_format($attempt->amount_cents / 100, 2, '.', '') }}"
            data-expirationminutes="10"
            data-timeouturl="{{ route('store.reservations') }}"
            data-merchantname="DESEGO"
            data-buttonsize="LARGE"
            data-buttoncolor="NAVY"
            data-formbuttoncolor="#004797"
            data-showamount="TRUE"
        ></script>
    </form>

    <p class="secure-note">La reserva solo se confirma cuando Niubiz aprueba el pago. No cierres esta ventana durante la operación.</p>
    <a class="back" href="{{ route('store.reservations') }}">Volver a la reserva</a>
</main>
</body>
</html>
