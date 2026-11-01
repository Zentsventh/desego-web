<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon_desego.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <title>Pago seguro · DESEGO</title>
    <style>
        body {
            font: 16px 'Inter', system-ui, sans-serif;
            background: linear-gradient(180deg, #f4f9ff, #e8f1fc);
            color: #0f172a;
            max-width: 500px;
            margin: 6vh auto;
            padding: 20px;
            line-height: 1.6;
        }
        main {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(0, 71, 151, 0.16);
            border-radius: 26px;
            padding: 28px;
            box-shadow: 0 22px 50px -24px rgba(0, 43, 94, 0.45);
        }
        .eyebrow {
            color: #004797;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }
        h1 {
            margin: 8px 0 12px;
            color: #002b5e;
            font-size: 28px;
            line-height: 1.15;
        }
        .amount {
            margin: 4px 0 18px;
            color: #004797;
            font-size: 38px;
            font-weight: 800;
        }
        .notice {
            background: #eef6ff;
            border: 1px solid rgba(0, 71, 151, 0.2);
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 13px;
            color: #003878;
        }
        .sandbox-card {
            margin-top: 18px;
            padding: 16px;
            border: 1px dashed rgba(0, 71, 151, 0.35);
            border-radius: 18px;
            background: #f8fbff;
        }
        .sandbox-btn {
            width: 100%;
            min-height: 48px;
            margin-top: 10px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #1264be, #004797);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        small {
            color: #52637a;
        }
    </style>
</head>
<body>
<main>
    <span class="eyebrow">DESEGO · NIUBIZ PAGO SEGURO</span>
    <h1>Un buen momento,<br>una cuenta resuelta.</h1>
    @if($sandbox)
        <p class="notice">
            <strong>Modo Demostración (Niubiz Sandbox):</strong> Puedes usar el formulario de Niubiz o autorizar el cobro de prueba con un clic.
        </p>
    @endif
    <p style="margin: 14px 0 0; color: #52637a; font-size: 13px;">Total a pagar (Orden #{{ $attempt->purchase_number }})</p>
    <p class="amount">S/ {{ number_format($attempt->amount_cents / 100, 2) }}</p>

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
            data-timeouturl="{{ route('store.home') }}"
            data-merchantname="DESEGO"
            data-buttonsize="LARGE"
            data-buttoncolor="NAVY"
            data-formbuttoncolor="#004797"
            data-showamount="TRUE"
        ></script>
    </form>

    @if($sandbox)
        <div class="sandbox-card">
            <small><strong>Simulador rápido Niubiz Sandbox</strong> · Tarjeta Visa de prueba <code>4919 •••• •••• 9067</code></small>
            <form action="{{ $callback }}" method="post">
                @csrf
                <input type="hidden" name="transactionToken" value="SANDBOX-DEMO-{{ $attempt->purchase_number }}-{{ substr($attempt->id, 0, 8) }}">
                <button type="submit" class="sandbox-btn">
                    Autorizar pago Sandbox S/ {{ number_format($attempt->amount_cents / 100, 2) }}
                </button>
            </form>
        </div>
    @endif

    <p><small>Los datos de tu tarjeta se procesan de forma segura. El pago se refleja de inmediato en el Salón 3D.</small></p>
</main>
</body>
</html>
