<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon_desego.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <title>Constancia {{ $order->order_number }} — {{ $restaurant['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 32px 16px; background: #f4f7fb; color: #0f172a; }
        .sheet { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 16px 40px rgba(15, 23, 42, .06); overflow: hidden; }
        .top { background: linear-gradient(135deg, #003572, #004797); color: #fff; padding: 28px 32px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .top h1 { margin: 0 0 6px; font-size: 22px; letter-spacing: -.02em; }
        .top p { margin: 2px 0; font-size: 13px; opacity: .9; }
        .badge-box { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.28); border-radius: 12px; padding: 12px 18px; text-align: right; }
        .badge-box small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }
        .badge-box strong { font-size: 18px; }
        .body { padding: 28px 32px; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .meta-grid small { display: block; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 3px; }
        .meta-grid span { font-size: 14px; font-weight: 600; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px 8px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .sub-note { display: block; font-size: 12px; color: #475569; margin-top: 3px; }
        .totals { margin-left: auto; max-width: 280px; border-top: 2px solid #e2e8f0; padding-top: 12px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 14px; color: #475569; }
        .totals-row.grand { font-size: 18px; font-weight: 800; color: #004797; border-top: 1px solid #e2e8f0; margin-top: 6px; padding-top: 10px; }
        .actions { display: flex; justify-content: space-between; align-items: center; max-width: 720px; margin: 0 auto 16px; gap: 12px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 14px; text-decoration: none; border: none; cursor: pointer; }
        .btn-primary { background: #004797; color: #fff; }
        .btn-outline { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        .legal { margin-top: 24px; padding-top: 16px; border-top: 1px dashed #cbd5e1; font-size: 12px; color: #64748b; line-height: 1.5; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .sheet { box-shadow: none; border: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="/cuenta" class="btn btn-outline">← Volver a Mi Cuenta</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <div class="sheet">
        <div class="top">
            <div>
                <h1>{{ $restaurant['name'] }}</h1>
                <p>{{ $restaurant['address'] }}</p>
                <p>WhatsApp de atención: {{ $restaurant['whatsapp'] }}</p>
            </div>
            <div class="badge-box">
                <small>{{ $invoice ? 'Comprobante registrado' : 'Constancia de pedido' }}</small>
                <strong>{{ $invoice ? $invoice->invoice_number : $order->order_number }}</strong>
                <p style="margin:4px 0 0;font-size:12px;">Pedido {{ $order->order_number }}</p>
            </div>
        </div>

        <div class="body">
            <div class="meta-grid">
                <div>
                    <small>Cliente</small>
                    <span>{{ $order->customer_name ?: $customer->full_name }}</span>
                </div>
                <div>
                    <small>Fecha y hora</small>
                    <span>{{ $order->created_at }}</span>
                </div>
                <div>
                    <small>Modalidad</small>
                    <span>{{ $order->order_type === 'Mesa' ? 'Consumo en local' : $order->order_type }}</span>
                </div>
                <div>
                    <small>Estado / Pago</small>
                    <span>{{ $order->status }} · {{ $order->payment_status }} ({{ $order->payment_method ?: ($order->payment_preference ?: 'Por coordinar') }})</span>
                </div>
                @if ($delivery)
                    <div style="grid-column: 1 / -1;">
                        <small>Dirección de entrega</small>
                        <span>{{ $delivery->address_line }} {{ $delivery->zone ? '('.$delivery->zone.')' : '' }} · Tel: {{ $delivery->recipient_phone }}</span>
                    </div>
                @endif
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Cant.</th>
                        <th>Descripción del plato</th>
                        <th class="num">P. Unit.</th>
                        <th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item->quantity }}x</strong></td>
                            <td>
                                <strong>{{ $item->product_name }}</strong>
                                @if (!empty($item->selected_options))
                                    <span class="sub-note">
                                        Adicionales/Opciones:
                                        {{ collect($item->selected_options)->map(fn ($o) => ($o['name'] ?? '').((float) ($o['price_delta'] ?? 0) > 0 ? ' (+S/ '.number_format((float) $o['price_delta'], 2).')' : ''))->implode(', ') }}
                                    </span>
                                @endif
                                @if (!empty($item->notes))
                                    <span class="sub-note">Observación: {{ $item->notes }}</span>
                                @endif
                            </td>
                            <td class="num">S/ {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="num"><strong>S/ {{ number_format((float) $item->line_total, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <div class="totals-row">
                    <span>Subtotal</span>
                    <span>S/ {{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                @if ((float) $order->discount > 0)
                    <div class="totals-row" style="color:#059669;">
                        <span>Descuento {{ $order->promotion_code ? '('.$order->promotion_code.')' : '' }}</span>
                        <span>- S/ {{ number_format((float) $order->discount, 2) }}</span>
                    </div>
                @endif
                @if ($delivery)
                    <div class="totals-row">
                        <span>Reparto</span>
                        <span>{{ (float) $delivery->delivery_fee > 0 ? 'S/ '.number_format((float) $delivery->delivery_fee, 2) : 'Por coordinar / S/ 0.00' }}</span>
                    </div>
                @endif
                <div class="totals-row grand">
                    <span>Total (PEN)</span>
                    <span>S/ {{ number_format((float) $order->total, 2) }}</span>
                </div>
            </div>

            <div class="legal">
                Documento informativo de control interno generado por la plataforma web de {{ $restaurant['name'] }}. Para solicitar boleta o factura física/electrónica en caja o coordinar cualquier detalle sobre tu pedido, comunícate a nuestro WhatsApp oficial {{ $restaurant['whatsapp'] }} indicando el código <strong>{{ $order->order_number }}</strong>.
            </div>
        </div>
    </div>
</body>
</html>
