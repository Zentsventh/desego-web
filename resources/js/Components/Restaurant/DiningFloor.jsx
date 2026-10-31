import React, { useEffect, useRef, useState } from 'react';
import { diningApi } from '../../lib/dining';
import { money } from './ShopParts';
import TableQr from './TableQr';
import '../../../css/dining.css';

const labels = {
    open: 'Ocupada',
    checkout: 'Cuenta solicitada',
    paid: 'Pagada',
    cleaning: 'Por limpiar',
    available: 'Disponible',
    maintenance: 'Mantenimiento',
};
const kinds = {
    waiter: 'Llamada al mesero',
    bill: 'Solicitud de cuenta',
    help: 'Necesita ayuda',
};

const PRESET_VIEWS = [
    { key: 'iso', label: 'Perspectiva 3D', pitch: 54, yaw: -16, zoom: 1 },
    { key: 'front', label: 'Frontal 3D', pitch: 44, yaw: 0, zoom: 1 },
    { key: 'flat', label: 'Plano 2D', pitch: 0, yaw: 0, zoom: 1 },
];

function playDiningChime(type = 'order') {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const notes = type === 'bill' ? [587.33, 783.99, 1046.5] : [523.25, 659.25];
        notes.forEach((freq, idx) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.14);
            gain.gain.setValueAtTime(0.001, ctx.currentTime + idx * 0.14);
            gain.gain.exponentialRampToValueAtTime(0.22, ctx.currentTime + idx * 0.14 + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.14 + 0.36);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(ctx.currentTime + idx * 0.14);
            osc.stop(ctx.currentTime + idx * 0.14 + 0.38);
        });
    } catch {
        // Ignore audio autoplay restrictions until user interacts
    }
}

export default function DiningFloor() {
    const [data, setData] = useState(null);
    const [selected, setSelected] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [qr, setQr] = useState(false);
    const [method, setMethod] = useState('Efectivo');
    const [updated, setUpdated] = useState(null);
    const [confirm, setConfirm] = useState(null);
    const [soundEnabled, setSoundEnabled] = useState(true);
    const [lastAlert, setLastAlert] = useState('');
    const [printMode, setPrintMode] = useState('qr');

    const [camera, setCamera] = useState({ pitch: 54, yaw: -16, zoom: 1 });
    const [preset, setPreset] = useState('iso');
    const dragRef = useRef(null);
    const seenRef = useRef({ initialized: false, orders: new Set(), requests: new Set() });

    async function refresh() {
        try {
            const next = await diningApi('floor');
            const currentOrders = new Set();
            const currentRequests = new Set();
            let alertMessage = '';
            let alertType = 'order';

            (next?.tables || []).forEach((t) => {
                (t.visit?.orders || []).forEach((o) => {
                    const key = `${o.id}:${o.status}:${o.payment_status}`;
                    currentOrders.add(key);
                    if (seenRef.current.initialized && !seenRef.current.orders.has(key)) {
                        alertMessage =
                            o.payment_status === 'Pagado'
                                ? `Pago confirmado en ${t.label} (${o.order_number})`
                                : `Movimiento de pedido en ${t.label} (${o.order_number} · ${o.status})`;
                    }
                });
                (t.visit?.requests || []).forEach((r) => {
                    if (r.status === 'pending') {
                        const key = `${r.id}:${r.notes || ''}`;
                        currentRequests.add(key);
                        if (seenRef.current.initialized && !seenRef.current.requests.has(key)) {
                            alertType = r.kind === 'bill' ? 'bill' : 'order';
                            alertMessage = `${t.label}: ${kinds[r.kind] || 'Aviso'}${
                                r.notes ? ` (${r.notes})` : ''
                            }`;
                        }
                    }
                });
            });

            seenRef.current = {
                initialized: true,
                orders: currentOrders,
                requests: currentRequests,
            };

            if (alertMessage) {
                setLastAlert(alertMessage);
                if (soundEnabled) {
                    playDiningChime(alertType);
                }
            }

            setData(next);
            setError('');
            setUpdated(new Date());
        } catch (e) {
            setError(e.message);
        }
    }

    useEffect(() => {
        refresh();
        const timer = setInterval(() => {
            if (!document.hidden) refresh();
        }, 5000);
        return () => clearInterval(timer);
    }, []);

    const table = data?.tables.find((item) => item.id === selected);
    const visit = table?.visit;
    const can = (permissions) =>
        permissions.some((permission) => data?.permissions.includes(permission));

    useEffect(() => {
        if (!visit?.requests) return;
        const billReq = visit.requests.find(
            (r) => r.kind === 'bill' && r.status !== 'resolved' && r.notes
        );
        if (!billReq) return;
        const note = billReq.notes.toLowerCase();
        if (note.includes('yape') || note.includes('plin')) {
            setMethod('Yape/Plin');
        } else if (note.includes('pos') || note.includes('tarjeta')) {
            setMethod('Tarjeta');
        } else if (note.includes('transferencia')) {
            setMethod('Transferencia');
        } else if (note.includes('efectivo')) {
            setMethod('Efectivo');
        }
    }, [selected, visit?.requests?.length]);

    function printThermalTicket() {
        setPrintMode('ticket');
        setTimeout(() => {
            window.print();
            setTimeout(() => setPrintMode('qr'), 400);
        }, 80);
    }

    async function act(action, extra = {}) {
        setBusy(true);
        try {
            await diningApi('actions', {
                method: 'POST',
                body: { action, session_id: visit.visit.id, ...extra },
            });
            setConfirm(null);
            await refresh();
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    }

    function applyPreset(item) {
        setPreset(item.key);
        setCamera({ pitch: item.pitch, yaw: item.yaw, zoom: item.zoom });
    }

    function onPointerDown(e) {
        if (e.target.closest('button.dining-table')) return;
        dragRef.current = {
            startX: e.clientX,
            startY: e.clientY,
            pitch: camera.pitch,
            yaw: camera.yaw,
        };
    }

    function onPointerMove(e) {
        if (!dragRef.current) return;
        const dx = e.clientX - dragRef.current.startX;
        const dy = e.clientY - dragRef.current.startY;
        const nextYaw = Math.max(-42, Math.min(42, dragRef.current.yaw + dx * 0.22));
        const nextPitch = Math.max(0, Math.min(68, dragRef.current.pitch - dy * 0.22));
        setPreset('custom');
        setCamera((prev) => ({ ...prev, pitch: nextPitch, yaw: nextYaw }));
    }

    function stopDrag() {
        dragRef.current = null;
    }

    const isFlat = camera.pitch < 6 && Math.abs(camera.yaw) < 4;

    return (
        <section className="dining-floor-module" data-print-mode={printMode}>
            <header className="dining-floor-heading">
                <div>
                    <span className="dining-eyebrow">ATENCIÓN CONECTADA · MAQUETA 3D</span>
                    <h2>El salón, en tus manos.</h2>
                    <p>
                        {updated
                            ? `Actualizado a las ${updated.toLocaleTimeString('es-PE')}. Arrastra el salón para girar la cámara o haz clic en una mesa.`
                            : 'Conectando con el salón…'}
                    </p>
                </div>
                <div className="dining-floor-controls">
                    <div className="dining-camera-pills" role="group" aria-label="Ángulo de cámara">
                        {PRESET_VIEWS.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                className={preset === item.key ? 'is-active' : ''}
                                onClick={() => applyPreset(item)}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        className={`dining-sound-toggle ${soundEnabled ? 'is-active' : ''}`}
                        onClick={() => {
                            const next = !soundEnabled;
                            setSoundEnabled(next);
                            if (next) playDiningChime('order');
                        }}
                    >
                        {soundEnabled ? 'Sonido activo' : 'Silenciado'}
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setPrintMode('qr');
                            setQr(!qr);
                        }}
                    >
                        {qr ? 'Ver salón 3D' : 'Imprimir códigos QR'}
                    </button>
                </div>
            </header>

            {lastAlert && (
                <div className="dining-live-alert-banner" role="status">
                    <span>{lastAlert}</span>
                    <button type="button" onClick={() => setLastAlert('')}>
                        Entendido
                    </button>
                </div>
            )}

            {error && (
                <p role="alert" className="dining-error">
                    {error}
                </p>
            )}

            {qr ? (
                <div className="dining-qr-sheet">
                    <div className="dining-qr-print-action">
                        <p>
                            Imprime y coloca un código en cada mesa. El enlace debe usar una
                            dirección accesible desde los celulares del local.
                        </p>
                        <button onClick={() => window.print()}>Imprimir las 12 mesas</button>
                    </div>
                    {data?.tables.map((item) => (
                        <article className="dining-qr-card" key={item.id}>
                            <h3>DESEGO</h3>
                            <strong>{item.label}</strong>
                            {item.qr_url ? (
                                <TableQr value={item.qr_url} label={item.label} />
                            ) : (
                                <p>Ejecuta dining:prepare para generar el código.</p>
                            )}
                            <p>Escanea. Elige. Disfruta.</p>
                            <a href={item.qr_url} target="_blank" rel="noreferrer">
                                Abrir experiencia de mesa
                            </a>
                        </article>
                    ))}
                </div>
            ) : (
                <div className="dining-floor-layout">
                    <div>
                        <div className="dining-legend-bar">
                            <div className="dining-legend">
                                {['available', 'open', 'checkout', 'paid', 'cleaning'].map(
                                    (status) => (
                                        <span key={status}>
                                            <i data-status={status} />
                                            {labels[status]}
                                        </span>
                                    )
                                )}
                            </div>
                            <span className="dining-camera-hint">
                                Giro: {Math.round(camera.yaw)}° · Inclinación: {Math.round(camera.pitch)}°
                            </span>
                        </div>

                        <div
                            className={`dining-room ${isFlat ? 'is-flat' : ''}`}
                            onPointerDown={onPointerDown}
                            onPointerMove={onPointerMove}
                            onPointerUp={stopDrag}
                            onPointerLeave={stopDrag}
                        >
                            <div
                                className="dining-room-scene"
                                style={{
                                    transform: `rotateX(${camera.pitch}deg) rotateZ(${camera.yaw}deg) scale(${camera.zoom})`,
                                }}
                            >
                                {/* Losas / espesor del piso 3D */}
                                <div className="dining-floor-slab" aria-hidden="true">
                                    <span className="slab-edge slab-front" />
                                    <span className="slab-edge slab-left" />
                                    <span className="slab-edge slab-right" />
                                </div>

                                {/* Pared trasera arquitectónica y barra */}
                                <div className="dining-wall-back" aria-hidden="true">
                                    <div className="dining-wall-brand">
                                        <span>CEVICHERÍA PERUANA</span>
                                        <strong>DESEGO · SALÓN PRINCIPAL</strong>
                                    </div>
                                    <div className="dining-kitchen-bar">
                                        <span className="bar-counter-top" />
                                        <span className="bar-counter-front">BARRA MARINA & PASE DE COCINA</span>
                                    </div>
                                </div>

                                {/* Pared lateral izquierda con ventanales */}
                                <div className="dining-wall-left" aria-hidden="true">
                                    <span className="wall-window" />
                                    <span className="wall-window" />
                                    <span className="wall-window" />
                                </div>

                                {/* Alfombra / pasillo central y entrada */}
                                <div className="dining-aisle-runner" aria-hidden="true" />
                                <div className="dining-entrance-mat" aria-hidden="true">
                                    <span>INGRESO PRINCIPAL</span>
                                </div>

                                {/* Jardineras decorativas en esquinas */}
                                <div className="dining-planter p-top-left" aria-hidden="true" />
                                <div className="dining-planter p-top-right" aria-hidden="true" />
                                <div className="dining-planter p-bottom-left" aria-hidden="true" />
                                <div className="dining-planter p-bottom-right" aria-hidden="true" />

                                <div className="dining-room-grid">
                                    {data?.tables.map((item) => {
                                        const state =
                                            item.visit?.visit.status || item.operational_status;
                                        const requests =
                                            item.visit?.requests.filter(
                                                (request) => request.status === 'pending'
                                            ) || [];
                                        const hasOrders = (item.visit?.orders?.length || 0) > 0;
                                        const isOccupied = ['open', 'checkout', 'paid'].includes(state);

                                        return (
                                            <button
                                                key={item.id}
                                                type="button"
                                                className={`dining-table ${
                                                    item.id === selected ? 'is-selected' : ''
                                                }`}
                                                data-status={state}
                                                aria-pressed={item.id === selected}
                                                aria-label={`${item.label}, ${
                                                    labels[state] || state
                                                }${
                                                    requests.length ? ', requiere atención' : ''
                                                }`}
                                                onClick={() => setSelected(item.id)}
                                            >
                                                {/* Sombra proyectada en el piso */}
                                                <span className="dining-table-shadow" aria-hidden="true" />

                                                {/* Alfombra bajo cada juego de comedor */}
                                                <span className="dining-table-rug" aria-hidden="true" />

                                                {/* 4 Sillas 3D con asiento y respaldo */}
                                                <span className="dining-chair c1" aria-hidden="true">
                                                    <i className="chair-seat" />
                                                    <i className="chair-back" />
                                                </span>
                                                <span className="dining-chair c2" aria-hidden="true">
                                                    <i className="chair-seat" />
                                                    <i className="chair-back" />
                                                </span>
                                                <span className="dining-chair c3" aria-hidden="true">
                                                    <i className="chair-seat" />
                                                    <i className="chair-back" />
                                                </span>
                                                <span className="dining-chair c4" aria-hidden="true">
                                                    <i className="chair-seat" />
                                                    <i className="chair-back" />
                                                </span>

                                                {/* Pedestal central y base metálica */}
                                                <span className="dining-table-base" aria-hidden="true" />
                                                <span className="dining-table-pedestal" aria-hidden="true" />

                                                {/* Canto/espesor 3D del tablero */}
                                                <span className="dining-table-rim" aria-hidden="true" />

                                                {/* Tablero principal de la mesa */}
                                                <span className="dining-table-top">
                                                    {/* Menaje 3D sobre la mesa (platos, QR, estado) */}
                                                    <span className="table-place-setting s1" aria-hidden="true" />
                                                    <span className="table-place-setting s2" aria-hidden="true" />
                                                    <span className="table-place-setting s3" aria-hidden="true" />
                                                    <span className="table-place-setting s4" aria-hidden="true" />

                                                    {/* Stand acrílico del QR en esquina de la mesa */}
                                                    <span className="table-qr-stand" aria-hidden="true" />

                                                    {/* Indicador luminoso de estado */}
                                                    <span className="table-status-beacon" aria-hidden="true" />

                                                    <span className="table-top-content">
                                                        <strong>
                                                            {String(item.table_number).padStart(2, '0')}
                                                        </strong>
                                                        <span className="table-status-pill">
                                                            {labels[state] || state}
                                                        </span>
                                                        {isOccupied && hasOrders && (
                                                            <small className="table-consumption-tag">
                                                                {money(item.visit?.balance || 0)}
                                                            </small>
                                                        )}
                                                        {requests.length > 0 && (
                                                            <b className="table-alert-badge">
                                                                {requests.length} aviso
                                                                {requests.length === 1 ? '' : 's'}
                                                            </b>
                                                        )}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside className="dining-table-detail">
                        {table ? (
                            <>
                                <span className="dining-eyebrow">{table.zone}</span>
                                <h3>{table.label}</h3>
                                <p>{labels[visit?.visit.status || table.operational_status]}</p>
                                {visit ? (
                                    <>
                                        <div className="dining-balance">
                                            <span>Cuenta pendiente</span>
                                            <strong>{money(visit.balance)}</strong>
                                        </div>
                                        <p className="dining-join-code">
                                            Código para compartir mesa:{' '}
                                            <strong>
                                                {visit.visit.join_code || 'Visita cerrándose'}
                                            </strong>
                                        </p>
                                        {visit.requests
                                            .filter((request) => request.status !== 'resolved')
                                            .map((request) => (
                                                <div
                                                    className="dining-service-request"
                                                    key={request.id}
                                                >
                                                    <strong>{kinds[request.kind]}</strong>
                                                    {request.notes && (
                                                        <span className="dining-request-note">
                                                            {request.notes}
                                                        </span>
                                                    )}
                                                    <p>
                                                        {request.status === 'acknowledged'
                                                            ? 'El personal va en camino'
                                                            : 'Pendiente de atención'}
                                                    </p>
                                                    {can([
                                                        'tables.manage',
                                                        'reservations.manage',
                                                        'orders.update',
                                                    ]) && (
                                                        <button
                                                            disabled={busy}
                                                            onClick={() =>
                                                                act(
                                                                    request.status === 'pending'
                                                                        ? 'acknowledge'
                                                                        : 'resolve',
                                                                    { request_id: request.id }
                                                                )
                                                            }
                                                        >
                                                            {request.status === 'pending'
                                                                ? 'Voy en camino'
                                                                : 'Marcar atendido'}
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        {visit.orders.map((order) => (
                                            <article className="dining-order" key={order.id}>
                                                <strong>
                                                    {order.order_number} · {order.status}
                                                </strong>
                                                {order.items.map((item, index) => (
                                                    <p key={index}>
                                                        {item.quantity} × {item.product_name}
                                                    </p>
                                                ))}
                                                <small>
                                                    {order.payment_status} · {money(order.total)}
                                                </small>
                                                {can(['orders.update', 'kitchen.manage']) &&
                                                    ['Pendiente', 'Preparando', 'Listo'].includes(
                                                        order.status
                                                    ) && (
                                                        <button
                                                            disabled={busy}
                                                            onClick={() =>
                                                                act('status', {
                                                                    order_id: order.id,
                                                                    status: {
                                                                        Pendiente: 'Preparando',
                                                                        Preparando: 'Listo',
                                                                        Listo: 'Entregado',
                                                                    }[order.status],
                                                                })
                                                            }
                                                        >
                                                            Marcar{' '}
                                                            {
                                                                {
                                                                    Pendiente: 'Preparando',
                                                                    Preparando: 'Listo',
                                                                    Listo: 'Entregado',
                                                                }[order.status]
                                                            }
                                                        </button>
                                                    )}
                                            </article>
                                        ))}
                                        {can(['orders.pay', 'cash.manage']) &&
                                            visit.balance > 0 && (
                                                <div className="dining-payment-actions">
                                                    <span className="dining-eyebrow">
                                                        COBRO EN MESA · SIN COMISIÓN
                                                    </span>
                                                    <div className="dining-method-chips">
                                                        {[
                                                            'Yape/Plin',
                                                            'Efectivo',
                                                            'Tarjeta',
                                                            'Transferencia',
                                                        ].map((value) => (
                                                            <button
                                                                key={value}
                                                                type="button"
                                                                className={
                                                                    method === value
                                                                        ? 'is-active'
                                                                        : ''
                                                                }
                                                                onClick={() => setMethod(value)}
                                                            >
                                                                {value === 'Tarjeta'
                                                                    ? 'POS Físico / Tarjeta'
                                                                    : value}
                                                            </button>
                                                        ))}
                                                    </div>
                                                    <label>
                                                        Medio verificado
                                                        <select
                                                            value={method}
                                                            onChange={(e) =>
                                                                setMethod(e.target.value)
                                                            }
                                                        >
                                                            {[
                                                                'Efectivo',
                                                                'Yape/Plin',
                                                                'Tarjeta',
                                                                'Transferencia',
                                                            ].map((value) => (
                                                                <option key={value}>{value}</option>
                                                            ))}
                                                        </select>
                                                    </label>
                                                    <button
                                                        disabled={busy}
                                                        onClick={() => setConfirm('pay')}
                                                    >
                                                        Registrar cobro ({method})
                                                    </button>
                                                </div>
                                            )}
                                        {visit.orders.length > 0 && (
                                            <div className="dining-ticket-action">
                                                <button
                                                    type="button"
                                                    onClick={printThermalTicket}
                                                >
                                                    Imprimir Pre-cuenta / Ticket 80mm
                                                </button>
                                            </div>
                                        )}
                                        {can([
                                            'tables.manage',
                                            'reservations.manage',
                                            'orders.update',
                                        ]) && (
                                            <div className="dining-actions">
                                                {visit.visit.status === 'checkout' && (
                                                    <button
                                                        disabled={busy}
                                                        onClick={() => act('reopen')}
                                                    >
                                                        Reabrir cuenta
                                                    </button>
                                                )}
                                                <button
                                                    disabled={busy || visit.balance > 0}
                                                    onClick={() => setConfirm('clean')}
                                                >
                                                    {visit.visit.status === 'cleaning'
                                                        ? 'Confirmar limpieza y liberar'
                                                        : 'Cerrar visita · Por limpiar'}
                                                </button>
                                            </div>
                                        )}
                                        {confirm && (
                                            <div className="dining-confirm" role="alert">
                                                <p>
                                                    {confirm === 'pay'
                                                        ? `Confirma que recibiste ${money(
                                                              visit.balance
                                                          )} por ${method}. Esto registra el cobro en caja.`
                                                        : visit.visit.status === 'cleaning'
                                                          ? 'Confirma que los clientes se retiraron y la mesa está limpia.'
                                                          : 'Confirma que la visita terminó. La mesa quedará por limpiar.'}
                                                </p>
                                                <button
                                                    disabled={busy}
                                                    onClick={() => act(confirm, { method })}
                                                >
                                                    Confirmar
                                                </button>
                                                <button
                                                    disabled={busy}
                                                    onClick={() => setConfirm(null)}
                                                >
                                                    Volver
                                                </button>
                                            </div>
                                        )}

                                        {/* Formato de impresión térmica de 80mm para esta mesa */}
                                        <div className="dining-thermal-ticket" aria-hidden="true">
                                            <h4>CEVICHERÍA DESEGO</h4>
                                            <p className="ticket-subtitle">
                                                {table.label} · {table.zone}
                                            </p>
                                            <p className="ticket-meta">
                                                Fecha: {new Date().toLocaleString('es-PE')}
                                            </p>
                                            <hr />
                                            {visit.orders.map((order) => (
                                                <div key={order.id} className="ticket-order-block">
                                                    <strong>
                                                        {order.order_number} ({order.payment_status})
                                                    </strong>
                                                    {order.items.map((item, idx) => (
                                                        <div key={idx} className="ticket-line">
                                                            <span>
                                                                {item.quantity}x {item.product_name}
                                                            </span>
                                                            <span>{money(item.subtotal)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ))}
                                            <hr />
                                            <div className="ticket-totals">
                                                <div>
                                                    <span>Consumido:</span>
                                                    <strong>{money(visit.total)}</strong>
                                                </div>
                                                <div>
                                                    <span>Pagado:</span>
                                                    <strong>{money(visit.paid)}</strong>
                                                </div>
                                                <div className="ticket-grand">
                                                    <span>Por pagar:</span>
                                                    <strong>{money(visit.balance)}</strong>
                                                </div>
                                            </div>
                                            <hr />
                                            <p className="ticket-footer">
                                                Yape / Plin sin comisión · Gracias por su visita
                                            </p>
                                        </div>
                                    </>
                                ) : (
                                    <p>No hay una visita activa en esta mesa.</p>
                                )}
                            </>
                        ) : (
                            <div className="dining-detail-empty">
                                <h3>Selecciona una mesa</h3>
                                <p>
                                    Consulta su consumo, los pedidos y las solicitudes de atención.
                                </p>
                            </div>
                        )}
                    </aside>
                </div>
            )}
        </section>
    );
}
