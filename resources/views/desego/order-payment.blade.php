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
        body { margin: 0; padding: 28px 16px; background: #f5f7fa; color: #20344f; font: 15px/1.6 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 500px; margin: 2vh auto; padding: 28px 24px; border: 1px solid #e3e9f1; border-radius: 22px; background: #fff; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04); }
        .logo { display: block; width: 160px; height: 60px; object-fit: contain; margin: 0 auto 22px; }
        .eyebrow { color: #74859a; font-size: 11px; letter-spacing: 1.5px; font-weight: 700; text-transform: uppercase; }
        h1 { font-size: 26px; line-height: 1.25; margin: 8px 0 14px; color: #0f2441; }
        .amount-row { display: flex; align-items: baseline; justify-content: space-between; margin: 10px 0 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
        .amount { font-size: 34px; font-weight: 800; color: #004797; margin: 0; }
        .operation-num { color: #64748b; font-size: 13px; font-weight: 500; }
        a { color: #004797; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .back { display: block; text-align: center; margin-top: 24px; font-size: 13px; font-weight: 600; }
        small { color: #7d8a9c; font-size: 12px; }

        /* Panel Sandbox con opción única de prueba */
        .sandbox-panel {
            background: linear-gradient(180deg, #f8fbff 0%, #f1f6fd 100%);
            border: 1.5px dashed #004797;
            border-radius: 18px;
            padding: 16px;
            margin: 18px 0 22px;
            box-shadow: 0 4px 16px rgba(0, 71, 151, 0.06);
        }
        .sandbox-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }
        .sandbox-title-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #00336e;
            font-size: 13px;
            font-weight: 700;
        }
        .sandbox-icon {
            width: 18px;
            height: 18px;
            color: #004797;
            flex-shrink: 0;
        }
        .sandbox-badge {
            background: #004797;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.8px;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .sandbox-desc {
            font-size: 12px;
            color: #475569;
            margin: 4px 0 12px;
            line-height: 1.45;
        }
        .sandbox-card-body {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            padding: 14px;
        }
        .sandbox-field {
            margin-bottom: 10px;
        }
        .sandbox-field:last-child {
            margin-bottom: 0;
        }
        .sandbox-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }
        .sandbox-copy-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px 6px 12px;
            transition: border-color 0.2s;
        }
        .sandbox-copy-row:hover {
            border-color: #93c5fd;
        }
        .sandbox-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.5px;
            user-select: all;
        }
        .sandbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .copy-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            background: #004797;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            min-height: 32px;
        }
        .copy-btn:hover {
            background: #00336e;
            transform: translateY(-1px);
        }
        .copy-btn:active {
            transform: translateY(0);
        }
        .copy-btn.is-copied {
            background: #16a34a !important;
            color: #ffffff !important;
        }
        .copy-btn svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }
        .sandbox-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
            flex-wrap: wrap;
            gap: 6px;
        }
        .copy-all-btn {
            background: transparent;
            border: none;
            color: #004797;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .copy-all-btn:hover {
            background: #eff6ff;
            text-decoration: underline;
        }
        .copy-all-btn.is-copied {
            color: #16a34a;
        }
        .autofill-hint {
            font-size: 11px;
            color: #64748b;
        }
        @media (max-width: 480px) {
            main { padding: 22px 16px; margin: 12px auto; }
            .sandbox-grid { grid-template-columns: 1fr; }
            .amount { font-size: 28px; }
        }
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

    <div class="amount-row">
        <p class="amount">S/ {{ number_format($attempt->amount_cents / 100, 2) }}</p>
        <span class="operation-num">Operación #{{ $attempt->purchase_number }} · PEN</span>
    </div>

    @if ($sandbox)
        <!-- Tarjeta de prueba Niubiz Sandbox (Opción oficial única) -->
        <section class="sandbox-panel" aria-label="Tarjeta de prueba Niubiz">
            <div class="sandbox-top">
                <div class="sandbox-title-wrap">
                    <svg class="sandbox-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                        <line x1="2" y1="10" x2="22" y2="10"></line>
                    </svg>
                    <span>Tarjeta de prueba Niubiz</span>
                </div>
                <span class="sandbox-badge">MODO PRUEBA</span>
            </div>
            <p class="sandbox-desc">
                Copia los datos de la tarjeta de prueba con el botón a su costado para ingresarlos en Niubiz:
            </p>

            <div class="sandbox-card-body">
                <div class="sandbox-field">
                    <label class="sandbox-label" for="card-num-text">Número de tarjeta (Visa de prueba)</label>
                    <div class="sandbox-copy-row">
                        <span class="sandbox-val" id="card-num-text">4557 8800 0000 0000</span>
                        <button type="button" class="copy-btn" id="btn-copy-num" data-copy="4557880000000000" title="Copiar número de tarjeta">
                            <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span class="btn-text">Copiar</span>
                        </button>
                    </div>
                </div>

                <div class="sandbox-grid">
                    <div class="sandbox-field">
                        <label class="sandbox-label" for="card-exp-text">Vencimiento</label>
                        <div class="sandbox-copy-row">
                            <span class="sandbox-val" id="card-exp-text">12/28</span>
                            <button type="button" class="copy-btn" id="btn-copy-exp" data-copy="12/28" title="Copiar vencimiento">
                                <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                <span class="btn-text">Copiar</span>
                            </button>
                        </div>
                    </div>

                    <div class="sandbox-field">
                        <label class="sandbox-label" for="card-cvv-text">CVV / CVC</label>
                        <div class="sandbox-copy-row">
                            <span class="sandbox-val" id="card-cvv-text">123</span>
                            <button type="button" class="copy-btn" id="btn-copy-cvv" data-copy="123" title="Copiar código de seguridad">
                                <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                <span class="btn-text">Copiar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sandbox-actions">
                    <button type="button" class="copy-all-btn" id="btn-copy-all">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        </svg>
                        <span>Copiar tarjeta completa</span>
                    </button>
                    <span class="autofill-hint" id="autofill-status">✓ Tarjeta oficial de prueba</span>
                </div>
            </div>
        </section>
    @endif

    <form action="{{ $callback }}" method="post">
        <script src="{{ $script }}" data-sessiontoken="{{ $sessionKey }}" data-channel="web"
            data-merchantid="{{ $merchant }}" data-purchasenumber="{{ $attempt->purchase_number }}"
            data-amount="{{ number_format($attempt->amount_cents / 100, 2, '.', '') }}" data-expirationminutes="10"
            data-timeouturl="{{ url('/cuenta') }}" data-merchantname="DESEGO" data-buttonsize="LARGE"
            data-buttoncolor="NAVY" data-formbuttoncolor="#004797" data-showamount="TRUE"></script>
    </form>
    <p><small>Al completar el pago, Niubiz confirma la operación automáticamente en línea y tu pedido queda registrado como pagado.</small></p>
    <a class="back" href="{{ url('/cuenta') }}">Volver a mi cuenta</a>
</main>

@if ($sandbox)
<script>
(function() {
    var card = {
        name: 'Visa de prueba',
        cleanNum: '4557880000000000',
        exp: '12/28',
        cvv: '123'
    };

    var btnCopyAll = document.getElementById('btn-copy-all');
    var copyIconSvg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    var checkIconSvg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';

    function copyText(text, btn, successLabel) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showSuccess(btn, successLabel);
            }).catch(function() {
                fallbackCopy(text, btn, successLabel);
            });
        } else {
            fallbackCopy(text, btn, successLabel);
        }
    }

    function fallbackCopy(text, btn, successLabel) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand('copy');
            showSuccess(btn, successLabel);
        } catch (_) {}
        document.body.removeChild(ta);
    }

    function showSuccess(btn, successLabel) {
        if (!btn) return;
        var origHtml = btn.innerHTML;
        btn.innerHTML = checkIconSvg + ' <span>' + (successLabel || '¡Copiado!') + '</span>';
        btn.classList.add('is-copied');
        setTimeout(function() {
            btn.innerHTML = origHtml;
            btn.classList.remove('is-copied');
        }, 1800);
    }

    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var val = btn.getAttribute('data-copy');
            copyText(val, btn, '¡Copiado!');
        });
    });

    if (btnCopyAll) {
        btnCopyAll.addEventListener('click', function() {
            var allText = 'Tarjeta: ' + card.cleanNum + ' | Vence: ' + card.exp + ' | CVV: ' + card.cvv;
            copyText(allText, btnCopyAll, '¡Datos copiados!');
        });
    }

    function tryAutoFill() {
        try {
            function fillInDoc(doc) {
                if (!doc) return;
                var numInputs = doc.querySelectorAll('input[name*="card" i], input[name*="number" i], input[id*="card" i], input[autocomplete*="cc-number" i]');
                numInputs.forEach(function(input) {
                    if (input.type === 'hidden' || input.disabled) return;
                    input.value = card.cleanNum;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                var expInputs = doc.querySelectorAll('input[name*="exp" i], input[id*="exp" i], input[autocomplete*="cc-exp" i]');
                expInputs.forEach(function(input) {
                    if (input.type === 'hidden' || input.disabled) return;
                    input.value = card.exp;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                var cvvInputs = doc.querySelectorAll('input[name*="cvv" i], input[name*="cvc" i], input[id*="cvv" i], input[autocomplete*="cc-csc" i]');
                cvvInputs.forEach(function(input) {
                    if (input.type === 'hidden' || input.disabled) return;
                    input.value = card.cvv;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            fillInDoc(document);
            document.querySelectorAll('iframe').forEach(function(iframe) {
                try {
                    if (iframe.contentDocument) fillInDoc(iframe.contentDocument);
                } catch (_) {}
            });
        } catch (_) {}
    }

    var observer = new MutationObserver(function() {
        tryAutoFill();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    setTimeout(tryAutoFill, 1000);
})();
</script>
@endif
</body>
</html>
