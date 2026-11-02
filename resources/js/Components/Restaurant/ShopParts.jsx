import React, { useEffect, useRef, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import Carousel from 'bootstrap/js/dist/carousel';
import {
    ArrowUpRight,
    ArrowRight,
    ArrowLeft,
    ShoppingBag,
    Plus,
    Minus,
    X,
    Heart,
    Fish,
    Waves,
    Leaf,
    Truck,
    Check,
    Pause,
    Play,
    MapPin,
    BrandWhatsapp,
    UtensilsCrossed,
    TicketPercent,
    ShieldCheck,
    Trash2,
} from './Icons';
import { navigateToStoreSection, STORE_SECTION_PATHS } from '../../lib/storeNavigation';
import InteractiveMap from './InteractiveMap';

export const money = (value) =>
    new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(value || 0));

const DISH_FALLBACKS = [
    { test: /conchas|ceviche\s+mixto|ceviche\s+desego/i, src: '/assets/images/dish_ceviche_mixto.jpg' },
    { test: /ceviche/i, src: '/assets/images/dish_ceviche_clasico.jpg' },
    { test: /leche/i, src: '/assets/images/dish_leche_tigre.jpg' },
    { test: /causa/i, src: '/assets/images/dish_causa_marina.jpg' },
    { test: /parihuela|sudado|chilcano/i, src: '/assets/images/dish_chupe_langostinos.jpg' },
    { test: /jalea|chicharr[oó]n|ronda/i, src: '/assets/images/dish_jalea_mixta.jpg' },
    { test: /chaufa|tallar[ií]n|spaghetti/i, src: '/assets/images/food_arroz.jpg' },
    { test: /arroz/i, src: '/assets/images/dish_arroz_mariscos.jpg' },
    { test: /tr[ií]o|d[uú]o/i, src: '/assets/images/dish_ceviche_mixto.jpg' },
    { test: /pescado/i, src: '/assets/images/food_pescado.jpg' },
    { test: /chicha|bebida/i, src: '/assets/images/drink_chicha_morada.jpg' },
];

export function foodImage(product) {
    if (!product) {
        return '/assets/images/dish_ceviche_clasico.jpg';
    }
    if (product.image_url) {
        return product.image_url;
    }
    if (product.image) {
        return product.image;
    }
    const haystack = `${product.name || ''} ${product.category || ''}`;
    const matched = DISH_FALLBACKS.find((rule) => rule.test.test(haystack));
    return matched ? matched.src : '/assets/images/dish_ceviche_clasico.jpg';
}

export function Brand({ light = false }) {
    return (
        <Link
            href="/"
            className={`dg-brand ${light ? 'dg-brand-light' : ''}`}
            aria-label="DESEGO, inicio"
        >
            <Fish strokeWidth={1.2} size={39} />
            <span>
                DESEGO
                <small>CEVICHERÍA & COCINA MARINA</small>
            </span>
        </Link>
    );
}

export function ErrorList({ errors }) {
    return Object.keys(errors || {}).length ? (
        <div className="dg-error" role="alert">
            {Object.entries(errors).map(([key, value]) => (
                <p key={key}>{value}</p>
            ))}
        </div>
    ) : null;
}

export function useAtmosphere() {
    useEffect(() => {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let frame = 0;
        const move = () => {
            if (frame || reduced.matches) {
                return;
            }
            frame = requestAnimationFrame(() => {
                document.querySelectorAll('[data-parallax]').forEach((element) => {
                    const rect = element.parentElement.getBoundingClientRect();
                    if (rect.bottom > 0 && rect.top < window.innerHeight) {
                        element.style.setProperty(
                            '--drift',
                            `${Math.max(-130, Math.min(130, -rect.top * Number(element.dataset.parallax)))}px`
                        );
                    }
                });
                frame = 0;
            });
        };
        const observer = new IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('dg-visible');
                        observer.unobserve(entry.target);
                    }
                }),
            { threshold: 0.08 }
        );
        document.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
        window.addEventListener('scroll', move, { passive: true });
        move();
        return () => {
            observer.disconnect();
            window.removeEventListener('scroll', move);
            cancelAnimationFrame(frame);
        };
    }, []);
}

export function Hero({ products }) {
    const element = useRef(null),
        carousel = useRef(null);
    const [active, setActive] = useState(0),
        [paused, setPaused] = useState(false);
    const slides = [
        {
            image: 'ceviche',
            label: 'El sabor que nos encuentra',
            title: (
                <>
                    El mar, servido
                    <br />
                    con <em>alma.</em>
                </>
            ),
            description:
                'Fresco, intenso, nuestro. Descubre una cocina que celebra el sabor de la costa peruana.',
            dish: products.find((p) => /ceviche/i.test(p.name)),
        },
        {
            image: 'arroz',
            label: 'Un encuentro con nuestra costa',
            title: (
                <>
                    La buena mesa
                    <br />
                    se <em>comparte.</em>
                </>
            ),
            description:
                'Texturas, aromas y ese toque de casa. Hay sabores que merecen disfrutarse sin prisa.',
            dish: products.find((p) => /arroz/i.test(p.name)),
        },
        {
            image: 'pescado',
            label: 'Del primer bocado al último',
            title: (
                <>
                    Simplemente
                    <br />
                    <em>irresistible.</em>
                </>
            ),
            description:
                'El mar inspira. Nuestra cocina lo transforma en ese plato al que siempre quieres volver.',
            dish: products.find((p) => /frito/i.test(p.name)),
        },
    ];
    useEffect(() => {
        const node = element.current;
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        carousel.current = new Carousel(node, {
            interval: reduced ? false : 7000,
            ride: reduced ? false : 'carousel',
            pause: 'hover',
            touch: true,
        });
        const slide = (event) => setActive(event.to);
        node.addEventListener('slide.bs.carousel', slide);
        return () => {
            node.removeEventListener('slide.bs.carousel', slide);
            carousel.current?.dispose();
        };
    }, []);
    return (
        <section
            className="dg-hero"
            aria-roledescription="carrusel"
            aria-label="Sabores de DESEGO"
        >
            <div className="carousel slide carousel-fade dg-hero-carousel" ref={element}>
                <div className="carousel-inner">
                    {slides.map((slide, index) => (
                        <div
                            key={slide.image}
                            className={`carousel-item ${index === 0 ? 'active' : ''}`}
                            aria-hidden={active !== index}
                        >
                            <img
                                className="dg-hero-image"
                                data-parallax="0.16"
                                src={`/images/menu/food_${slide.image}.jpg`}
                                alt={slide.dish?.name || 'Cocina marina peruana'}
                                fetchPriority={index === 0 ? 'high' : 'auto'}
                            />
                            <div className="dg-hero-shade" />
                            <div className="dg-container dg-hero-copy">
                                <span className="dg-eyebrow dg-eyebrow-light">
                                    <span /> {slide.label}
                                </span>
                                <h1>{slide.title}</h1>
                                <p>{slide.description}</p>
                                <a href={STORE_SECTION_PATHS.carta} onClick={(event) => navigateToStoreSection('carta', event)} className="dg-button dg-button-ochre">
                                    Explorar la carta <ArrowUpRight size={19} />
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            <div className="dg-hero-stamp">
                <Waves size={33} strokeWidth={1} />
                <span>ESENCIA PERUANA</span>
                <small>Hecho al momento</small>
            </div>
            <div className="dg-container dg-hero-bottom">
                <span className="dg-hero-caption">
                    <span className="dg-hairline" />{' '}
                    {slides[active].dish?.name || 'Nuestra cocina marina'}
                </span>
                <div className="dg-slider-controls">
                    <button aria-label="Anterior" onClick={() => carousel.current?.prev()}>
                        <ArrowLeft size={18} />
                    </button>
                    <span>
                        0{active + 1} <i>/ 03</i>
                    </span>
                    <button aria-label="Siguiente" onClick={() => carousel.current?.next()}>
                        <ArrowRight size={18} />
                    </button>
                    <button
                        aria-label={paused ? 'Reproducir carrusel' : 'Pausar carrusel'}
                        onClick={() => {
                            paused ? carousel.current?.cycle() : carousel.current?.pause();
                            setPaused(!paused);
                        }}
                    >
                        {paused ? <Play size={14} /> : <Pause size={14} />}
                    </button>
                </div>
                <a href={STORE_SECTION_PATHS.carta} onClick={(event) => navigateToStoreSection('carta', event)} className="dg-scroll-hint">
                    DESLIZA PARA DESCUBRIR <ArrowRight size={15} />
                </a>
            </div>
        </section>
    );
}

export function Product({ product, favorite, onFavorite, onAdd, onOpen }) {
    return (
        <article className="dg-product" data-reveal>
            <button
                className="dg-product-photo"
                onClick={() => onOpen(product)}
                aria-label={`Ver ${product.name}`}
            >
                <img src={foodImage(product)} alt={product.name} loading="lazy" />
                <span className="dg-product-category">{product.category}</span>
            </button>
            <button
                className={`dg-heart ${favorite ? 'is-favorite' : ''}`}
                onClick={() => onFavorite(product.id)}
                aria-label={
                    favorite ? `Quitar ${product.name} de favoritos` : `Guardar ${product.name}`
                }
                aria-pressed={favorite}
            >
                <Heart size={18} fill={favorite ? 'currentColor' : 'none'} />
            </button>
            <div className="dg-product-description">
                <div className="dg-product-title">
                    <h3>
                        <button onClick={() => onOpen(product)}>{product.name}</button>
                    </h3>
                    <span>{money(product.price)}</span>
                </div>
                <p>{product.description || 'Preparado al momento con el sabor de nuestra cocina.'}</p>
                <div className="dg-product-bottom">
                    <span>
                        {product.allergens ? (
                            <>
                                <Leaf size={13} /> Ver ingredientes
                            </>
                        ) : (
                            'Sabor de nuestra costa'
                        )}
                    </span>
                    <button
                        onClick={() => onAdd(product.id)}
                        disabled={!product.can_order}
                        aria-label={`Agregar ${product.name}`}
                        className="dg-add"
                    >
                        {product.can_order ? <Plus size={20} /> : <span>No disponible</span>}
                    </button>
                </div>
            </div>
        </article>
    );
}

export function Checkout({
    open,
    close,
    cart,
    mode,
    setMode,
    fee,
    accountUser,
    lastOrder,
    deliveryZones = [],
    social,
    branch,
    customerProfile = null,
    customerAddresses = [],
    paymentMethods = ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'],
    services = {},
}) {
    const dialog = useRef(null);
    const { flash } = usePage().props;
    const [stage, setStage] = useState('cart');
    const [quote, setQuote] = useState(null);
    const [selectedZoneId, setSelectedZoneId] = useState(String(deliveryZones[0]?.id || ''));
    const selectedZone =
        deliveryZones.find((z) => Number(z.id) === Number(selectedZoneId)) || null;
    const delivery =
        mode === 'Delivery' ? (selectedZone ? Number(selectedZone.delivery_fee) : fee) : 0;
    const deliveryFeeConfirmed = selectedZone ? Boolean(selectedZone.fee_confirmed) : false;
    const total = quote?.total ?? Math.max(0, cart.subtotal + delivery - (quote?.discount || 0));

    const defaultAddr = customerAddresses.find((a) => a.is_default) || customerAddresses[0] || null;

    const form = useForm({
        customer_name: customerProfile?.name || accountUser?.name || '',
        email: customerProfile?.email || accountUser?.email || '',
        phone: customerProfile?.phone || defaultAddr?.phone || '',
        saved_address_id: defaultAddr?.id || '',
        address_line: defaultAddr?.address_line || '',
        district: defaultAddr?.district || 'Ate',
        province: 'Lima',
        department: 'Lima',
        reference: defaultAddr?.reference || '',
        latitude: defaultAddr?.latitude ?? '',
        longitude: defaultAddr?.longitude ?? '',
        notes: '',
        table_label: '',
        delivery_zone_id: deliveryZones[0]?.id || 1,
        consent: false,
        payment_preference: paymentMethods[0] || 'Efectivo',
        coupon_code: '',
        checkout_key: crypto.randomUUID(),
        order_type: mode,
        items: [],
    });

    useEffect(() => {
        try {
            const pendingCoupon = localStorage.getItem('desego.pending_coupon');
            if (pendingCoupon) {
                form.setData('coupon_code', pendingCoupon.toUpperCase());
                localStorage.removeItem('desego.pending_coupon');
            }
        } catch {}
    }, []);

    const signature = JSON.stringify(
        cart.items.map((item) => ({
            product_id: item.product.id,
            quantity: item.quantity,
            notes: item.notes || '',
            selected_options: (item.selected_options || []).map((o) => o.id),
        }))
    );

    useEffect(() => {
        form.setData((data) => ({
            ...data,
            items: JSON.parse(signature),
            order_type: mode,
            delivery_zone_id: selectedZoneId,
        }));
        setQuote(null);
    }, [signature, mode, selectedZoneId, form.data.coupon_code]);

    useEffect(() => {
        if (open && !dialog.current.open) {
            dialog.current.showModal();
        } else if (!open && dialog.current.open) {
            dialog.current.close();
        }
    }, [open]);

    useEffect(() => {
        if (flash?.result?.type === 'order') {
            setStage('success');
            cart.clear();
            form.setData('checkout_key', crypto.randomUUID());
        }
        if (flash?.result?.type === 'quote') {
            setQuote(flash.result.quote);
        }
    }, [flash?.result]);

    const deliveryDisplay = () => {
        if (mode !== 'Delivery') {
            return 'Sin recargo';
        }
        if (delivery > 0) {
            return money(delivery);
        }
        return deliveryFeeConfirmed ? 'Gratis' : 'Por coordinar';
    };

    function buildWhatsAppLink(orderNum = null) {
        const orderCode = orderNum || lastOrder?.order_number || flash?.result?.order_number || '';
        const orderTypeText =
            mode === 'Delivery'
                ? '🛵 Delivery a domicilio'
                : mode === 'Consumo en local'
                  ? '🍽️ Consumo en local / Mesa'
                  : '🛍️ Recojo en restaurante';
        const waNumber =
            (social?.whatsapp_phone || '+51922119238').replace(/\D+/g, '') || '51922119238';
        const msgParts = [
            '🌊 *NUEVO PEDIDO - DESEGO CEVICHERÍA*',
            '━━━━━━━━━━━━━━━━━━━━',
            `📌 *Modalidad:* ${orderTypeText}`,
            `👤 *Cliente:* ${form.data.customer_name || 'Cliente'}`,
            form.data.phone ? `📱 *Teléfono:* ${form.data.phone}` : null,
            mode === 'Delivery'
                ? `📍 *Entrega:* ${form.data.address_line || 'Por coordinar'}${form.data.district ? `, ${form.data.district}` : ''}${form.data.reference ? ` (${form.data.reference})` : ''}`
                : null,
            mode === 'Delivery' && form.data.latitude && form.data.longitude
                ? `🗺️ *Ubicación GPS:* https://maps.google.com/?q=${form.data.latitude},${form.data.longitude}`
                : null,
            mode === 'Delivery' && selectedZone ? `🏘️ *Sector:* ${selectedZone.name}` : null,
            mode === 'Consumo en local' && form.data.table_label
                ? `🪑 *Mesa:* ${form.data.table_label}`
                : null,
            mode === 'Para llevar'
                ? `🏪 *Punto de recojo:* ${social?.address || 'UCV 19, Lote 13, Zona A, Huaycán, Ate'}`
                : null,
            `💳 *Forma de pago:* ${form.data.payment_preference}`,
            orderCode ? `🔖 *Código de orden:* ${orderCode}` : null,
            '',
            '🛒 *Platos seleccionados:*',
            ...cart.items.map((item) => {
                const opts = item.selected_options?.length
                    ? ` [${item.selected_options.map((o) => o.name).join(', ')}]`
                    : '';
                const note = item.notes ? ` (Nota: ${item.notes})` : '';
                return `• ${item.quantity}x ${item.product.name}${opts}${note} (${money(item.lineTotal ?? item.product.price * item.quantity)})`;
            }),
            '',
            '━━━━━━━━━━━━━━━━━━━━',
            `💵 *Subtotal:* ${money(cart.subtotal)}`,
            mode === 'Delivery' ? `🛵 *Envío:* ${deliveryDisplay()}` : null,
            quote?.discount > 0 ? `🎟️ *Descuento:* -${money(quote.discount)}` : null,
            `💰 *TOTAL GENERAL:* ${money(total)}`,
            form.data.notes ? `📝 *Indicaciones:* ${form.data.notes}` : null,
            '',
            `_DeSego Cevichería · ${social?.address || 'UCV 19, Lote 13, Zona A, Huaycán, Ate'}_`,
        ].filter(Boolean);
        return `https://wa.me/${waNumber}?text=${encodeURIComponent(msgParts.join('\n'))}`;
    }

    const paymentDesc = (pm) => {
        if (pm === 'Niubiz') {
            return 'Paga en línea con tarjeta en el formulario seguro de Niubiz.';
        }
        if (/yape|plin/i.test(pm)) {
            return 'Pago rápido desde tu celular al confirmar o recibir.';
        }
        if (/tarjeta/i.test(pm)) {
            return 'POS inalámbrico disponible contra entrega o en salón.';
        }
        return 'Pago en soles al momento de recibir tu pedido.';
    };

    return (
        <dialog
            ref={dialog}
            className="dg-cart"
            onCancel={close}
            onClose={close}
            onClick={(event) => {
                if (event.target === dialog.current) {
                    close();
                }
            }}
        >
            <div className="dg-cart-inside">
                <header className="dg-cart-header">
                    <div>
                        <div className="dg-eyebrow-pill">
                            <span className="dg-eyebrow-dot" />
                            <span className="dg-eyebrow">DESEGO CEVICHERÍA</span>
                            {cart.count > 0 && stage !== 'success' && (
                                <span className="dg-eyebrow-badge">
                                    {cart.count} {cart.count === 1 ? 'plato' : 'platos'}
                                </span>
                            )}
                        </div>
                        <h2>
                            {stage === 'success'
                                ? '¡Buen provecho!'
                                : stage === 'checkout'
                                  ? 'Completa tu pedido'
                                  : 'Tu selección marina'}
                        </h2>
                    </div>
                    <button
                        type="button"
                        className="dg-cart-close"
                        onClick={close}
                        aria-label="Cerrar carrito"
                    >
                        <X size={18} />
                    </button>
                </header>

                {stage === 'success' ? (
                    <div className="dg-order-success">
                        <div className="dg-success-icon-wrap">
                            <Check size={38} />
                        </div>
                        <h3>¡Pedido recibido en cocina!</h3>
                        <p className="dg-success-lead">
                            Hemos registrado tu orden y nuestro equipo comenzará a prepararla al momento.
                        </p>
                        <div className="dg-order-code-badge">
                            <span>Código de seguimiento</span>
                            <strong>
                                {lastOrder?.order_number ||
                                    flash?.result?.order_number ||
                                    'REGISTRADO'}
                            </strong>
                        </div>
                        <div className="dg-success-status-pill">
                            <span className="dg-status-dot" />
                            <span>Estado actual: {lastOrder?.status || 'Pendiente'}</span>
                        </div>

                        <div className="dg-success-actions">
                            {accountUser ? (
                                <Link href="/cuenta" className="dg-cta-button">
                                    <span>Seguir estado en Mi Cuenta</span>
                                    <span className="dg-cta-arrow">
                                        <ArrowRight size={16} />
                                    </span>
                                </Link>
                            ) : (
                                <button
                                    type="button"
                                    className="dg-cta-button"
                                    onClick={() =>
                                        router.reload({
                                            only: ['lastOrder'],
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <span>Actualizar estado</span>
                                    <span className="dg-cta-arrow">
                                        <ArrowRight size={16} />
                                    </span>
                                </button>
                            )}
                            <a
                                href={buildWhatsAppLink(
                                    lastOrder?.order_number || flash?.result?.order_number
                                )}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="dg-wa-button"
                            >
                                <BrandWhatsapp size={18} />
                                <span>Enviar constancia por WhatsApp</span>
                            </a>
                            <button
                                type="button"
                                className="dg-text-button"
                                onClick={() => {
                                    setStage('cart');
                                    close();
                                }}
                            >
                                Seguir explorando la carta
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        {stage === 'cart' ? (
                            <>
                                <div className="dg-cart-lines">
                                    {cart.items.length ? (
                                        cart.items.map((item) => {
                                            const {
                                                key,
                                                product,
                                                quantity,
                                                notes,
                                                selected_options,
                                                unitPrice,
                                                lineTotal,
                                            } = item;
                                            const itemKey = key || product.id;
                                            return (
                                                <div className="dg-cart-line" key={itemKey}>
                                                    <div className="dg-cart-img-wrap">
                                                        <img
                                                            src={foodImage(product)}
                                                            alt={product.name}
                                                            loading="lazy"
                                                        />
                                                        <span className="dg-item-qty-tag">
                                                            ×{quantity}
                                                        </span>
                                                    </div>
                                                    <div className="dg-cart-info">
                                                        <div className="dg-cart-title-row">
                                                            <div>
                                                                <h3>{product.name}</h3>
                                                                <span className="dg-unit-price">
                                                                    {money(unitPrice ?? product.price)} c/u
                                                                </span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                className="dg-remove-item"
                                                                aria-label={`Eliminar ${product.name}`}
                                                                onClick={() =>
                                                                    cart.setQuantity(itemKey, 0)
                                                                }
                                                            >
                                                                <Trash2 size={15} />
                                                            </button>
                                                        </div>

                                                        {selected_options?.length > 0 && (
                                                            <div className="dg-cart-opts-summary">
                                                                +{' '}
                                                                {selected_options
                                                                    .map(
                                                                        (o) =>
                                                                            `${o.name}${o.price_delta > 0 ? ` (${money(o.price_delta)})` : ''}`
                                                                    )
                                                                    .join(' · ')}
                                                            </div>
                                                        )}

                                                        <div className="dg-cart-actions-row">
                                                            <div className="dg-quantity">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        cart.setQuantity(
                                                                            itemKey,
                                                                            quantity - 1
                                                                        )
                                                                    }
                                                                    aria-label={`Quitar una unidad de ${product.name}`}
                                                                >
                                                                    <Minus size={13} />
                                                                </button>
                                                                <span>{quantity}</span>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        cart.setQuantity(
                                                                            itemKey,
                                                                            quantity + 1
                                                                        )
                                                                    }
                                                                    aria-label={`Sumar una unidad de ${product.name}`}
                                                                    disabled={quantity >= 20}
                                                                >
                                                                    <Plus size={13} />
                                                                </button>
                                                            </div>
                                                            <strong className="dg-line-total">
                                                                {money(
                                                                    lineTotal ??
                                                                        product.price * quantity
                                                                )}
                                                            </strong>
                                                        </div>

                                                        {cart.updateNotes && (
                                                            <input
                                                                type="text"
                                                                maxLength={160}
                                                                className="dg-cart-note-input focus:outline-none focus:ring-0"
                                                                placeholder="Nota p/ cocina (ej. poco ají, sin cebolla)"
                                                                value={notes || ''}
                                                                onChange={(e) =>
                                                                    cart.updateNotes(
                                                                        itemKey,
                                                                        e.target.value
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="dg-empty">
                                            <div className="dg-empty-icon">
                                                <ShoppingBag size={34} strokeWidth={1.6} />
                                            </div>
                                            <h3>Tu pedido aún está vacío</h3>
                                            <p>
                                                Explora nuestra carta marina y agrega ceviches, arroces, jaleas o rondas preparadas al momento.
                                            </p>
                                            <button
                                                type="button"
                                                className="dg-cta-button"
                                                style={{ maxWidth: '250px', marginTop: '6px' }}
                                                onClick={close}
                                            >
                                                <span>Descubrir la carta</span>
                                                <span className="dg-cta-arrow">
                                                    <ArrowUpRight size={16} />
                                                </span>
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {cart.count > 0 && (
                                    <>
                                        <div
                                            className="dg-mode-segmented"
                                            role="group"
                                            aria-label="Modalidad del pedido"
                                        >
                                            {services.delivery_enabled !== false && (
                                                <button
                                                    type="button"
                                                    className={`dg-mode-btn ${mode === 'Delivery' ? 'is-active' : ''}`}
                                                    onClick={() => setMode('Delivery')}
                                                >
                                                    <span className="dg-mode-icon-wrap">
                                                        <Truck size={15} />
                                                    </span>
                                                    <span className="dg-mode-text">
                                                        <strong>Delivery</strong>
                                                        <small>A domicilio</small>
                                                    </span>
                                                </button>
                                            )}
                                            {services.pickup_enabled !== false && (
                                                <button
                                                    type="button"
                                                    className={`dg-mode-btn ${mode === 'Para llevar' ? 'is-active' : ''}`}
                                                    onClick={() => setMode('Para llevar')}
                                                >
                                                    <span className="dg-mode-icon-wrap">
                                                        <ShoppingBag size={15} />
                                                    </span>
                                                    <span className="dg-mode-text">
                                                        <strong>Recoger</strong>
                                                        <small>En local</small>
                                                    </span>
                                                </button>
                                            )}
                                            {services.dine_in_enabled !== false && (
                                                <button
                                                    type="button"
                                                    className={`dg-mode-btn ${mode === 'Consumo en local' ? 'is-active' : ''}`}
                                                    onClick={() => setMode('Consumo en local')}
                                                >
                                                    <span className="dg-mode-icon-wrap">
                                                        <UtensilsCrossed size={15} />
                                                    </span>
                                                    <span className="dg-mode-text">
                                                        <strong>En salón</strong>
                                                        <small>Para mesa</small>
                                                    </span>
                                                </button>
                                            )}
                                        </div>

                                        <div className="dg-coupon-section">
                                            <div className="dg-coupon-box">
                                                <div className="dg-coupon-input-wrap">
                                                    <TicketPercent
                                                        size={16}
                                                        className="dg-coupon-icon"
                                                    />
                                                    <input
                                                        aria-label="Código de cupón"
                                                        placeholder="Cupón o código Club DeSego"
                                                        value={form.data.coupon_code}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'coupon_code',
                                                                e.target.value.toUpperCase()
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    className="dg-coupon-submit"
                                                    disabled={
                                                        form.processing ||
                                                        !form.data.coupon_code.trim()
                                                    }
                                                    onClick={() =>
                                                        form.post('/carrito/cotizar', {
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                >
                                                    Aplicar
                                                </button>
                                            </div>
                                            {quote?.promotion?.name && (
                                                <div className="dg-coupon-applied">
                                                    <span className="dg-coupon-applied-pill">
                                                        <Check size={13} />
                                                        {quote.promotion.name} (-
                                                        {money(quote.discount)})
                                                    </span>
                                                    <button
                                                        type="button"
                                                        className="dg-coupon-remove"
                                                        onClick={() => {
                                                            form.setData('coupon_code', '');
                                                            setQuote(null);
                                                        }}
                                                        aria-label="Quitar cupón"
                                                    >
                                                        <X size={13} />
                                                    </button>
                                                </div>
                                            )}
                                            <ErrorList errors={form.errors} />
                                        </div>
                                    </>
                                )}
                            </>
                        ) : (
                            <form
                                id="dg-checkout-form"
                                className="dg-form"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post('/pedido', { preserveScroll: true });
                                }}
                            >
                                <button
                                    type="button"
                                    className="dg-back-button"
                                    onClick={() => setStage('cart')}
                                >
                                    <ArrowLeft size={15} /> Volver a mi selección
                                </button>
                                <ErrorList errors={form.errors} />

                                <div className="dg-form-section">
                                    <span className="dg-section-legend">
                                        01 · Datos de contacto
                                    </span>
                                    <div className="dg-field-group">
                                        <label>
                                            <span>Nombre completo *</span>
                                            <input
                                                autoComplete="name"
                                                required
                                                placeholder="Ej. Carlos Mendoza"
                                                value={form.data.customer_name}
                                                onChange={(e) =>
                                                    form.setData('customer_name', e.target.value)
                                                }
                                            />
                                        </label>
                                    </div>
                                    <div className="dg-form-grid">
                                        <label>
                                            <span>
                                                Celular / WhatsApp{' '}
                                                {mode !== 'Consumo en local' ? '*' : ''}
                                            </span>
                                            <input
                                                type="tel"
                                                autoComplete="tel"
                                                required={mode !== 'Consumo en local'}
                                                placeholder="999 000 000"
                                                value={form.data.phone}
                                                onChange={(e) =>
                                                    form.setData('phone', e.target.value)
                                                }
                                            />
                                        </label>
                                        <label>
                                            <span>Correo electrónico</span>
                                            <input
                                                type="email"
                                                autoComplete="email"
                                                placeholder="hola@ejemplo.com"
                                                value={form.data.email}
                                                onChange={(e) =>
                                                    form.setData('email', e.target.value)
                                                }
                                            />
                                        </label>
                                    </div>
                                </div>

                                <div className="dg-form-section">
                                    <span className="dg-section-legend">
                                        02 ·{' '}
                                        {mode === 'Delivery'
                                            ? 'Dirección y cobertura de entrega'
                                            : mode === 'Consumo en local'
                                              ? 'Atención en salón'
                                              : 'Recojo en el restaurante'}
                                    </span>

                                    {mode === 'Delivery' && (
                                        <>
                                            {customerAddresses.length > 0 && (
                                                <div className="dg-field-group">
                                                    <label>
                                                        <span>Mis direcciones guardadas</span>
                                                        <select
                                                            value={form.data.saved_address_id || ''}
                                                            onChange={(e) => {
                                                                const val = e.target.value;
                                                                if (!val) {
                                                                    form.setData((d) => ({
                                                                        ...d,
                                                                        saved_address_id: '',
                                                                        address_line: '',
                                                                        reference: '',
                                                                    }));
                                                                    return;
                                                                }
                                                                const found =
                                                                    customerAddresses.find(
                                                                        (a) =>
                                                                            String(a.id) ===
                                                                            String(val)
                                                                    );
                                                                if (found) {
                                                                    form.setData((d) => ({
                                                                        ...d,
                                                                        saved_address_id: found.id,
                                                                        address_line:
                                                                            found.address_line ||
                                                                            '',
                                                                        district:
                                                                            found.district || 'Ate',
                                                                        reference:
                                                                            found.reference || '',
                                                                        phone:
                                                                            found.phone || d.phone,
                                                                        latitude:
                                                                            found.latitude ??
                                                                            d.latitude,
                                                                        longitude:
                                                                            found.longitude ??
                                                                            d.longitude,
                                                                    }));
                                                                }
                                                            }}
                                                        >
                                                            <option value="">
                                                                + Ingresar una nueva dirección
                                                            </option>
                                                            {customerAddresses.map((addr) => (
                                                                <option
                                                                    key={addr.id}
                                                                    value={addr.id}
                                                                >
                                                                    {addr.label}: {addr.address_line}{' '}
                                                                    ({addr.district})
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </label>
                                                </div>
                                            )}

                                            {deliveryZones.length > 0 && (
                                                <div className="dg-field-group">
                                                    <label>
                                                        <span>Zona de reparto (Huaycán / Ate)</span>
                                                        <select
                                                            value={selectedZoneId}
                                                            onChange={(e) => {
                                                                setSelectedZoneId(e.target.value);
                                                                form.setData(
                                                                    'delivery_zone_id',
                                                                    e.target.value
                                                                );
                                                            }}
                                                        >
                                                            <option value="">Selecciona una zona de reparto</option>
                                                            {deliveryZones.map((z) => (
                                                                <option key={z.id} value={z.id}>
                                                                    {z.name} —{' '}
                                                                    {z.fee_confirmed
                                                                        ? `Envío: ${money(z.delivery_fee)}`
                                                                        : 'Tarifa por coordinar'}{' '}
                                                                    ({z.estimated_minutes_min}-
                                                                    {z.estimated_minutes_max} min)
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </label>
                                                </div>
                                            )}

                                            <div className="dg-field-group">
                                                <label>
                                                    <span>Dirección exacta *</span>
                                                    <input
                                                        autoComplete="street-address"
                                                        required
                                                        minLength={5}
                                                        placeholder="UCV, lote, zona, avenida o calle"
                                                        value={form.data.address_line}
                                                        onChange={(e) =>
                                                            form.setData((d) => ({
                                                                ...d,
                                                                saved_address_id: '',
                                                                address_line: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                </label>
                                            </div>

                                            <div className="dg-form-grid">
                                                <label>
                                                    <span>Distrito / Zona *</span>
                                                    <input
                                                        required
                                                        placeholder="Ate / Huaycán"
                                                        value={form.data.district}
                                                        onChange={(e) =>
                                                            form.setData((d) => ({
                                                                ...d,
                                                                saved_address_id: '',
                                                                district: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                </label>
                                            </div>

                                            <div style={{ marginTop: '4px' }}>
                                                <InteractiveMap
                                                    mode="delivery"
                                                    height={185}
                                                    restaurantLat={social?.latitude ?? -12.009536180504373}
                                                    restaurantLng={social?.longitude ?? -76.82422937768825}
                                                    restaurantName={
                                                        social?.name || 'DeSego Cevichería'
                                                    }
                                                    restaurantAddress={
                                                        social?.address ||
                                                        'UCV 19, Lote 13, Zona A, Huaycán, Ate'
                                                    }
                                                    latitude={form.data.latitude || null}
                                                    longitude={form.data.longitude || null}
                                                    deliveryZones={deliveryZones}
                                                    onLocationChange={(lat, lng) => {
                                                        form.setData((d) => ({
                                                            ...d,
                                                            latitude: lat,
                                                            longitude: lng,
                                                        }));
                                                    }}
                                                    onZoneDetected={(zone) => {
                                                        if (zone?.id) {
                                                            setSelectedZoneId(String(zone.id));
                                                            form.setData(
                                                                'delivery_zone_id',
                                                                zone.id
                                                            );
                                                        } else {
                                                            setSelectedZoneId('');
                                                            form.setData('delivery_zone_id', '');
                                                        }
                                                    }}
                                                />
                                            </div>

                                            <div className="dg-field-group">
                                                <label>
                                                    <span>Referencia para encontrar tu domicilio</span>
                                                    <textarea
                                                        rows={3}
                                                        maxLength={255}
                                                        placeholder="Portón, color de fachada, parque cercano, piso o indicación para llegar"
                                                        value={form.data.reference}
                                                        onChange={(e) =>
                                                            form.setData((d) => ({
                                                                ...d,
                                                                saved_address_id: '',
                                                                reference: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                    <small>
                                                        Ayuda al repartidor a ubicar la puerta exacta. Máximo 255 caracteres.
                                                    </small>
                                                </label>
                                            </div>
                                        </>
                                    )}

                                    {mode === 'Para llevar' && (
                                        <div className="dg-pickup-box">
                                            <div className="dg-pickup-head">
                                                <MapPin size={16} />
                                                <span>
                                                    {social?.name || 'DeSego Cevichería'}
                                                </span>
                                            </div>
                                            <p>
                                                {social?.address ||
                                                    'UCV 19, Lote 13, Zona A, Huaycán, Ate'}
                                            </p>
                                            <small>
                                                Horario:{' '}
                                                {social?.schedule ||
                                                    'Lunes a Domingo: 9:00 a. m. – 5:00 p. m.'}{' '}
                                                · Listo en 15–25 min aprox.
                                            </small>
                                        </div>
                                    )}

                                    {mode === 'Consumo en local' && (
                                        <div className="dg-field-group">
                                            <label>
                                                <span>Mesa o ubicación en salón (opcional)</span>
                                                <input
                                                    placeholder="Ej. Mesa 4 · Salón principal"
                                                    value={form.data.table_label || ''}
                                                    onChange={(e) =>
                                                        form.setData('table_label', e.target.value)
                                                    }
                                                />
                                            </label>
                                            <small className="dg-small">
                                                Prepararemos tus platos al instante para servirlos directamente en tu mesa.
                                            </small>
                                        </div>
                                    )}
                                </div>

                                <div className="dg-form-section">
                                    <span className="dg-section-legend">
                                        03 · Preferencia de pago y notas
                                    </span>
                                    <div className="dg-pay-cards-grid">
                                        {(paymentMethods.length
                                            ? paymentMethods
                                            : ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir']
                                        ).map((pm) => {
                                            const active = form.data.payment_preference === pm;
                                            return (
                                                <button
                                                    key={pm}
                                                    type="button"
                                                    className={`dg-pay-card ${active ? 'is-selected' : ''}`}
                                                    onClick={() =>
                                                        form.setData('payment_preference', pm)
                                                    }
                                                >
                                                    <span className="dg-pay-card-radio">
                                                        <span className="dg-pay-card-dot" />
                                                    </span>
                                                    <span className="dg-pay-card-info">
                                                        <strong>{pm}</strong>
                                                        <small>{paymentDesc(pm)}</small>
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>

                                    <div className="dg-field-group" style={{ marginTop: '4px' }}>
                                        <label>
                                            <span>Observaciones generales (opcional)</span>
                                            <textarea
                                                maxLength={300}
                                                rows={2}
                                                placeholder="Ej. Incluir cubiertos, ají aparte, vuelto de S/ 50..."
                                                value={form.data.notes}
                                                onChange={(e) =>
                                                    form.setData('notes', e.target.value)
                                                }
                                            />
                                        </label>
                                    </div>

                                    <label className="dg-check" style={{ display: 'inline-flex', flexDirection: 'row', alignItems: 'flex-start', gap: '8px', cursor: 'pointer' }}>
                                        <input
                                            type="checkbox"
                                            required
                                            checked={form.data.consent}
                                            onChange={(e) =>
                                                form.setData('consent', e.target.checked)
                                            }
                                            className="dg-checkbox"
                                            style={{
                                                width: '18px',
                                                height: '18px',
                                                minHeight: '18px',
                                                maxHeight: '18px',
                                                padding: 0,
                                                margin: '2px 0 0',
                                                accentColor: '#004797',
                                                cursor: 'pointer',
                                                flexShrink: 0,
                                                borderRadius: '4px',
                                                boxShadow: 'none',
                                            }}
                                        />
                                        <span>
                                            Autorizo el uso de mis datos para coordinar y atender este pedido.
                                        </span>
                                    </label>
                                </div>
                            </form>
                        )}

                        {cart.count > 0 && (
                            <footer className="dg-cart-total">
                                <div className="dg-summary-card" style={{ display: 'flex', flexDirection: 'column', gap: '8px', width: '100%' }}>
                                    <div className="dg-summary-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                                        <span>Subtotal ({cart.count} {cart.count === 1 ? 'plato' : 'platos'})</span>
                                        <strong>{money(cart.subtotal)}</strong>
                                    </div>
                                    <div className="dg-summary-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                                        <span>
                                            {mode === 'Delivery'
                                                ? 'Entrega a domicilio'
                                                : mode === 'Consumo en local'
                                                  ? 'Atención en salón'
                                                  : 'Recojo en local'}
                                        </span>
                                        {deliveryDisplay() === 'Gratis' ||
                                        deliveryDisplay() === 'Sin recargo' ? (
                                            <i className="dg-free-badge">{deliveryDisplay()}</i>
                                        ) : (
                                            <strong>{deliveryDisplay()}</strong>
                                        )}
                                    </div>
                                    {quote?.discount > 0 && (
                                        <div className="dg-summary-row dg-discount-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                                            <span>Descuento aplicado</span>
                                            <strong>- {money(quote.discount)}</strong>
                                        </div>
                                    )}
                                    <div className="dg-grand-total" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }}>
                                        <div className="dg-grand-title">
                                            <span>Total estimado</span>
                                            <small>Soles peruanos (PEN)</small>
                                        </div>
                                        <strong className="dg-grand-price">{money(total)}</strong>
                                    </div>
                                </div>

                                {stage === 'cart' ? (
                                    <button
                                        type="button"
                                        className="dg-cta-button"
                                        onClick={() => setStage('checkout')}
                                    >
                                        <span>Continuar con mi pedido</span>
                                        <span className="dg-cta-arrow">
                                            <ArrowRight size={16} />
                                        </span>
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        form="dg-checkout-form"
                                        className="dg-cta-button"
                                        disabled={form.processing}
                                    >
                                        <span>
                                            {form.processing
                                                ? 'Registrando pedido…'
                                                : 'Confirmar pedido ahora'}
                                        </span>
                                        <span className="dg-cta-arrow">
                                            <ArrowRight size={16} />
                                        </span>
                                    </button>
                                )}

                                <a
                                    href={buildWhatsAppLink()}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="dg-wa-button"
                                >
                                    <BrandWhatsapp size={16} />
                                    <span>Coordinar o enviar por WhatsApp</span>
                                </a>

                                <div className="dg-trust-footer">
                                    <ShieldCheck size={14} />
                                    <span>Atención directa de DeSego Cevichería · Sin cobros automáticos</span>
                                </div>
                            </footer>
                        )}
                    </>
                )}
            </div>
        </dialog>
    );
}
