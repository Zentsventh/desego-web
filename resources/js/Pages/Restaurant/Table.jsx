import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import { diningApi } from '../../lib/dining';
import { searchProducts } from '../../lib/productSearch';
import { money } from '../../Components/Restaurant/ShopParts';
import DishModel from '../../Components/Restaurant/DishModel';
import { Plus, Minus, ArrowUpRight, Fish, Check, Search } from '../../Components/Restaurant/Icons';
import '../../../css/dining.css';

const visitLabels = {
    open: 'Tu mesa está lista',
    checkout: 'Cuenta solicitada',
    paid: 'Cuenta pagada',
    cleaning: 'Gracias por tu visita',
    closed: 'Visita terminada',
};
const requestLabels = {
    waiter: 'Llamar al mesero',
    bill: 'Pedir la cuenta',
    help: 'Necesito ayuda',
};

export default function Table({ tablePreview, qrToken, catalog }) {
    const storageKey = `desego.dining.${qrToken}`;
    const [token, setToken] = useState(() => {
        try {
            return localStorage.getItem(storageKey) || '';
        } catch {
            return '';
        }
    });
    const [state, setState] = useState(null);
    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [confirmed, setConfirmed] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [cart, setCart] = useState({});
    const [notes, setNotes] = useState('');
    const [detail, setDetail] = useState(null);
    const [rotation, setRotation] = useState({ x: 0, y: 0 });
    const [notice, setNotice] = useState('');
    const [productQuery, setProductQuery] = useState('');
    const [productCategory, setProductCategory] = useState('all');

    const checkoutKey = useRef(crypto.randomUUID());
    const products = catalog?.products || [];
    const categories = useMemo(
        () => [...new Set(products.map((product) => product.category).filter(Boolean))],
        [products]
    );
    const visibleProducts = useMemo(() => {
        const categoryProducts =
            productCategory === 'all'
                ? products
                : products.filter((product) => product.category === productCategory);
        return searchProducts(categoryProducts, productQuery);
    }, [products, productCategory, productQuery]);
    const total = products.reduce((sum, product) => sum + product.price * (cart[product.id] || 0), 0);
    const count = Object.values(cart).reduce((sum, quantity) => sum + quantity, 0);

    useEffect(() => {
        if (!token) return;
        let active = true;
        async function refresh() {
            try {
                const result = await diningApi('session', { token });
                if (active) {
                    setState(result);
                    setError('');
                }
            } catch (e) {
                if (active) setError(e.message);
            }
        }
        refresh();
        const timer = setInterval(() => {
            if (!document.hidden) refresh();
        }, 5000);
        return () => {
            active = false;
            clearInterval(timer);
        };
    }, [token]);

    async function run(task) {
        setBusy(true);
        setError('');
        try {
            await task();
        } catch (e) {
            setError(e.message);
        } finally {
            setBusy(false);
        }
    }

    async function join(event) {
        event.preventDefault();
        await run(async () => {
            const next = await diningApi(`tables/${qrToken}/join`, {
                method: 'POST',
                body: {
                    name,
                    join_code: code || undefined,
                    confirm: confirmed,
                    member_token: token || undefined,
                },
            });
            setToken(next.member_token);
            try {
                localStorage.setItem(storageKey, next.member_token);
            } catch {}
            setState(next);
        });
    }

    function quantity(id, value) {
        if (busy) return;
        setCart((current) => {
            const next = { ...current };
            if (value <= 0) delete next[id];
            else next[id] = Math.min(value, 20);
            return next;
        });
    }

    async function order() {
        await run(async () => {
            const result = await diningApi('orders', {
                token,
                method: 'POST',
                body: {
                    checkout_key: checkoutKey.current,
                    items: Object.entries(cart).map(([id, qty]) => ({
                        product_id: Number(id),
                        quantity: qty,
                    })),
                    notes,
                },
            });
            setState(result);
            setCart({});
            setNotes('');
            checkoutKey.current = crypto.randomUUID();
            setNotice(
                result.inventory_warning ||
                    'Pedido recibido. Tu cocina ya tiene el antojo.'
            );
        });
    }

    async function attention(kind, noteText = '') {
        await run(async () => {
            const body = noteText ? { kind, notes: noteText } : { kind };
            setState(await diningApi('requests', { token, method: 'POST', body }));
            setNotice(
                noteText
                    ? `Avisamos al personal: ${noteText}. Se reflejará en el Salón 3D.`
                    : kind === 'bill'
                      ? 'La cuenta está solicitada. Puedes pagar en mesa o en línea.'
                      : 'Tu solicitud fue enviada al personal.'
            );
        });
    }

    function forget() {
        try {
            localStorage.removeItem(storageKey);
        } catch {}
        setToken('');
        setState(null);
    }

    const directPayment = state?.direct_payment;
    const showInlineOrderError =
        Boolean(error) && count > 0 && state?.visit?.status === 'open';

    return (
        <main className="dining-client">
            <Head title={`${tablePreview.table.label} · Tu experiencia`} />
            <header className="dining-client-header">
                <a href="/">
                    DESEGO<span>COCINA MARINA</span>
                </a>
                <strong>{tablePreview.table.label}</strong>
            </header>

            {error && !showInlineOrderError && (
                <p className="dining-error" role="alert">
                    {error}
                </p>
            )}
            {notice && (
                <p className="dining-notice" role="status">
                    <Check size={19} />
                    {notice}
                    <button onClick={() => setNotice('')}>Cerrar</button>
                </p>
            )}

            {!state ? (
                <section className="dining-welcome">
                    <span className="dining-eyebrow">EL GUSTO DE ESTAR AQUÍ</span>
                    <h1>
                        Tu mesa.
                        <br />
                        Tu momento.
                    </h1>
                    <p>Explora la carta, pide a cocina y llama al mesero desde tu celular.</p>
                    <form onSubmit={join}>
                        <label>
                            ¿Cómo te llamas?
                            <input
                                autoComplete="given-name"
                                required
                                minLength={2}
                                maxLength={100}
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Tu nombre"
                            />
                        </label>
                        <label>
                            Código para unirte (si alguien abrió la mesa)
                            <input
                                inputMode="numeric"
                                maxLength={6}
                                pattern="[0-9]{6}"
                                value={code}
                                onChange={(e) => setCode(e.target.value)}
                                placeholder="Pídeselo a tu acompañante"
                            />
                        </label>
                        <label className="dining-checkbox">
                            <input
                                type="checkbox"
                                required
                                checked={confirmed}
                                onChange={(e) => setConfirmed(e.target.checked)}
                            />
                            Estoy en esta mesa y quiero iniciar o unirme a la visita.
                        </label>
                        <button disabled={busy || tablePreview.maintenance}>
                            {busy ? 'Preparando tu mesa…' : 'Entrar a mi mesa'}
                            <ArrowUpRight size={19} />
                        </button>
                    </form>
                    <a className="dining-native-link" href={`desego://mesa/${qrToken}`}>
                        Abrir en la app Desego
                    </a>
                </section>
            ) : (
                <>
                    <section className="dining-visit-heading">
                        <div>
                            <span className="dining-eyebrow">{visitLabels[state.visit.status]}</span>
                            <h1>
                                Que empiece
                                <br />
                                el buen antojo.
                            </h1>
                        </div>
                        <div className="dining-visit-code">
                            <span>Comparte la mesa</span>
                            <strong>{state.visit.join_code || 'Visita cerrándose'}</strong>
                            <small>Tus acompañantes escanean el QR e ingresan este código.</small>
                        </div>
                    </section>

                    {['closed', 'cleaning'].includes(state.visit.status) ? (
                        <section className="dining-welcome">
                            <Fish size={45} />
                            <h2>Gracias por compartir tu mesa.</h2>
                            <button onClick={forget}>Finalizar en este dispositivo</button>
                        </section>
                    ) : (
                        <>
                            <div className="dining-quick-actions">
                                {Object.entries(requestLabels).map(([kind, label]) => {
                                    const pending = state.requests.find(
                                        (request) =>
                                            request.kind === kind &&
                                            ['pending', 'acknowledged'].includes(request.status)
                                    );
                                    return (
                                        <button
                                            key={kind}
                                            disabled={busy || !!pending || state.visit.status === 'paid'}
                                            onClick={() => attention(kind)}
                                        >
                                            {label}
                                            <small>
                                                {pending?.status === 'acknowledged'
                                                    ? 'El personal va en camino'
                                                    : pending
                                                      ? pending.notes || 'Solicitud enviada'
                                                      : 'Estamos para ayudarte'}
                                            </small>
                                        </button>
                                    );
                                })}
                            </div>

                            <section className="dining-client-catalog">
                                <div className="dining-section-title">
                                    <div>
                                        <span className="dining-eyebrow">CARTA DE LA CASA</span>
                                        <h2>Elige tu próximo antojo.</h2>
                                    </div>
                                    <span className="dining-catalog-count">
                                        {visibleProducts.length}{' '}
                                        {visibleProducts.length === 1 ? 'plato' : 'platos'}
                                    </span>
                                </div>
                                <label className="dining-catalog-search">
                                    <Search size={17} aria-hidden="true" />
                                    <input
                                        type="search"
                                        aria-label="Buscar platos de la carta"
                                        placeholder="Busca un plato, ingrediente o categoría..."
                                        value={productQuery}
                                        onChange={(event) => setProductQuery(event.target.value)}
                                    />
                                </label>
                                {categories.length > 0 && (
                                    <div
                                        className="dining-catalog-categories"
                                        aria-label="Filtrar por categoría"
                                    >
                                        {['all', ...categories].map((category) => (
                                            <button
                                                key={category}
                                                type="button"
                                                aria-pressed={productCategory === category}
                                                onClick={() => setProductCategory(category)}
                                            >
                                                {category === 'all' ? 'Toda la carta' : category}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <div className="dining-client-products">
                                    {visibleProducts.map((product) => (
                                        <article key={product.id}>
                                            <button
                                                type="button"
                                                className="dining-dish-photo"
                                                onClick={() => {
                                                    setDetail(product);
                                                    setRotation({ x: 0, y: 0 });
                                                }}
                                            >
                                                <img src={product.image} alt={product.name} />
                                                <span>Explorar plato ↗</span>
                                            </button>
                                            <div>
                                                <span className="dining-eyebrow">
                                                    {product.category}
                                                </span>
                                                {product.can_order === false && (
                                                    <span className="dining-product-unavailable">
                                                        Agotado por ahora
                                                    </span>
                                                )}
                                                <h3>{product.name}</h3>
                                                <p>{product.description}</p>
                                                <footer>
                                                    <strong>{money(product.price)}</strong>
                                                    <div className="dining-quantity">
                                                        <button
                                                            disabled={busy || !cart[product.id]}
                                                            onClick={() =>
                                                                quantity(
                                                                    product.id,
                                                                    (cart[product.id] || 0) - 1
                                                                )
                                                            }
                                                            aria-label={`Quitar ${product.name}`}
                                                        >
                                                            <Minus size={17} />
                                                        </button>
                                                        <span>{cart[product.id] || 0}</span>
                                                        <button
                                                            disabled={
                                                                busy ||
                                                                product.can_order === false ||
                                                                state.visit.status !== 'open' ||
                                                                cart[product.id] >= 20
                                                            }
                                                            onClick={() =>
                                                                quantity(
                                                                    product.id,
                                                                    (cart[product.id] || 0) + 1
                                                                )
                                                            }
                                                            aria-label={`Agregar ${product.name}`}
                                                        >
                                                            <Plus size={17} />
                                                        </button>
                                                    </div>
                                                </footer>
                                            </div>
                                        </article>
                                    ))}
                                    {visibleProducts.length === 0 && (
                                        <p className="dining-catalog-empty" role="status">
                                            No encontramos platos con esa búsqueda. Prueba otra
                                            palabra o categoría.
                                        </p>
                                    )}
                                </div>
                            </section>

                            <section className="dining-bill">
                                <h2>Tu mesa, al día.</h2>
                                {state.orders.length === 0 ? (
                                    <p>Aún no hay pedidos. Elige tus favoritos de la carta.</p>
                                ) : (
                                    state.orders.map((item) => (
                                        <article key={item.id}>
                                            <div>
                                                <strong>
                                                    {item.order_number}
                                                    {item.is_mine ? ' · Tu pedido' : ''}
                                                </strong>
                                                <span>
                                                    {item.status} · {item.payment_status}
                                                </span>
                                            </div>
                                            <p>
                                                {item.items
                                                    .map(
                                                        (line) =>
                                                            `${line.quantity} × ${line.product_name}`
                                                    )
                                                    .join(', ')}
                                            </p>
                                            <strong>{money(item.total)}</strong>
                                        </article>
                                    ))
                                )}
                                <div className="dining-account-totals">
                                    <p>
                                        Tu consumo pendiente <strong>{money(state.my_balance)}</strong>
                                    </p>
                                    <p>
                                        Total pendiente de la mesa{' '}
                                        <strong>{money(state.balance)}</strong>
                                    </p>
                                </div>

                                {state.balance > 0 && (
                                    <div className="dining-payment-hub">
                                        {directPayment?.enabled && (
                                            <div className="dining-direct-pay-box">
                                                <span className="dining-eyebrow">
                                                    COBRO EN MESA · SIN COMISIÓN
                                                </span>
                                                <h3>Paga con Yape / Plin, Efectivo o POS</h3>
                                                <p>
                                                    <strong>Yape / Plin:</strong>{' '}
                                                    {directPayment.yape_number} ({directPayment.holder})
                                                </p>
                                                {directPayment.bank_account && (
                                                    <p>
                                                        <strong>Transferencia:</strong>{' '}
                                                        {directPayment.bank_account}
                                                    </p>
                                                )}
                                                <div className="dining-direct-pay-buttons">
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() =>
                                                            attention(
                                                                'bill',
                                                                'Pago con Yape/Plin en mesa'
                                                            )
                                                        }
                                                    >
                                                        Ya yapeé / Pagar con Yape o Plin
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() =>
                                                            attention(
                                                                'bill',
                                                                'Solicito POS físico (Tarjeta en mesa)'
                                                            )
                                                        }
                                                    >
                                                        Pedir POS físico (Tarjeta)
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() =>
                                                            attention(
                                                                'bill',
                                                                'Pago en Efectivo en mesa'
                                                            )
                                                        }
                                                    >
                                                        Pagar en Efectivo al mesero
                                                    </button>
                                                </div>
                                            </div>
                                        )}

                                        <a
                                            className="dining-primary-link"
                                            href={`/mesa-pago#${token}`}
                                        >
                                            Pagar con Niubiz{' '}
                                            {state.niubiz_sandbox ? '(Sandbox Demo)' : ''}{' '}
                                            <ArrowUpRight size={19} />
                                        </a>
                                        <small>
                                            El personal verificará el cobro en el Salón 3D o se
                                            confirmará automáticamente con Niubiz.
                                        </small>
                                    </div>
                                )}
                            </section>
                        </>
                    )}

                    {count > 0 && state.visit.status === 'open' && (
                        <div className="dining-order-dock">
                            {showInlineOrderError && (
                                <p className="dining-error" role="alert">
                                    {error}
                                </p>
                            )}
                            <label>
                                ¿Alguna indicación?
                                <input
                                    maxLength={500}
                                    value={notes}
                                    disabled={busy}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder="Picante, preferencias o alergias"
                                />
                            </label>
                            <button type="button" disabled={busy} onClick={order}>
                                {busy
                                    ? 'Enviando…'
                                    : `Pedir ${count} ${count === 1 ? 'plato' : 'platos'}`}
                                <strong>{money(total)}</strong>
                            </button>
                        </div>
                    )}
                </>
            )}

            {detail && (
                <div className="dining-detail-backdrop" onClick={() => setDetail(null)}>
                    <section
                        className="dining-dish-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-label={detail.name}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <button className="dining-detail-close" onClick={() => setDetail(null)}>
                            Cerrar
                        </button>
                        <div
                            className="dining-dish-perspective"
                            onPointerMove={(event) => {
                                if (event.pointerType !== 'mouse') return;
                                const rect = event.currentTarget.getBoundingClientRect();
                                setRotation({
                                    x: (event.clientY - rect.top - rect.height / 2) / 20,
                                    y: (event.clientX - rect.left - rect.width / 2) / 20,
                                });
                            }}
                        >
                            {detail.model_url ? (
                                <DishModel
                                    src={detail.model_url}
                                    poster={detail.image}
                                    name={detail.name}
                                />
                            ) : (
                                <img
                                    src={detail.image}
                                    alt={detail.name}
                                    style={{
                                        transform: `rotateX(${-rotation.x}deg) rotateY(${rotation.y}deg)`,
                                    }}
                                />
                            )}
                        </div>
                        <span className="dining-eyebrow">
                            VISTA INTERACTIVA ·{' '}
                            {detail.model_url ? 'MODELO 3D' : 'FOTOGRAFÍA DEL PLATO'}
                        </span>
                        <h2>{detail.name}</h2>
                        <p>{detail.description}</p>
                        {detail.allergens && <p>Alérgenos: {detail.allergens}</p>}
                        <strong>{money(detail.price)}</strong>
                    </section>
                </div>
            )}
        </main>
    );
}
