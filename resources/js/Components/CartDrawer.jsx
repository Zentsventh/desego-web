import React, { useState, useEffect, useCallback, useRef } from 'react';

function makeCheckoutKey() {
    if (typeof window !== 'undefined' && window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function formatMoney(value) {
    return `S/ ${Number(value || 0).toFixed(2)}`;
}

export default function CartDrawer({
    mode = 'drawer', // 'sidebar' | 'drawer'
    isOpen = false,
    onClose,
    items = [],
    deliveryFee = 5.0,
    externalCouponCode = '',
    onUpdateQty,
    onRemove,
    onClear,
    onOrderCreated,
}) {
    const [step, setStep] = useState('cart'); // 'cart' | 'checkout' | 'success'
    const [couponCode, setCouponCode] = useState('');
    const [appliedCoupon, setAppliedCoupon] = useState('');
    const [quote, setQuote] = useState(null);
    const [couponFeedback, setCouponFeedback] = useState({ message: '', isError: false });
    const [loadingQuote, setLoadingQuote] = useState(false);

    const [profile, setProfile] = useState(null);
    const [savedAddressId, setSavedAddressId] = useState('');

    const [form, setForm] = useState({
        customer_name: '',
        phone: '',
        email: '',
        address_line: '',
        district: 'Ate',
        province: 'Lima',
        department: 'Lima',
        reference: '',
        payment_preference: 'Yape / Plin',
        notes: '',
        consent: true,
        website: '',
    });

    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [orderSuccess, setOrderSuccess] = useState(null);
    const [checkoutKey, setCheckoutKey] = useState(() => makeCheckoutKey());
    const quoteSeqRef = useRef(0);

    const getCsrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const loadSavedProfile = useCallback(async () => {
        try {
            const response = await fetch('/api/storefront.php?view=profile', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.ok && data.profile) {
                setProfile(data.profile);
                const c = data.profile.customer || {};
                setForm((prev) => ({
                    ...prev,
                    customer_name: prev.customer_name || c.name || '',
                    phone: prev.phone || c.phone || '',
                    email: prev.email || c.email || '',
                }));
            }
        } catch (_) {
            // Disponible para clientes nuevos sin sesión previa.
        }
    }, []);

    useEffect(() => {
        loadSavedProfile();
    }, [loadSavedProfile]);

    const refreshQuote = useCallback(
        async (codeToUse, explicit = false) => {
            const seq = ++quoteSeqRef.current;
            if (!items.length) {
                setQuote(null);
                if (!explicit) {
                    setCouponFeedback({ message: '', isError: false });
                }
                return;
            }

            const cleanCode = String(codeToUse ?? appliedCoupon ?? '').trim().toUpperCase();
            setLoadingQuote(true);
            try {
                const csrfToken = getCsrfToken();
                const response = await fetch('/api/storefront.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({
                        _csrf: csrfToken,
                        action: 'quote',
                        coupon_code: cleanCode,
                        customer_email: form.email.trim(),
                        customer_phone: form.phone.trim(),
                        items: items.map((item) => ({
                            product_id: Number(item.id),
                            quantity: Number(item.quantity),
                        })),
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (seq !== quoteSeqRef.current) {
                    return;
                }
                if (!response.ok || data.ok === false) {
                    throw new Error(data.message || 'No se pudo validar la promoción.');
                }

                const q = data.quote || null;
                setQuote(q);
                setAppliedCoupon(cleanCode);

                if (explicit) {
                    if (q?.promotion) {
                        const msg = q.promotion.code
                            ? `Cupón ${q.promotion.code} aplicado. Ahorras ${formatMoney(q.discount)}.`
                            : `Promoción aplicada: ${q.promotion.name}. Ahorras ${formatMoney(q.discount)}.`;
                        setCouponFeedback({ message: msg, isError: false });
                    } else {
                        setCouponFeedback({
                            message: 'No hay una promoción aplicable para este carrito.',
                            isError: false,
                        });
                    }
                }
            } catch (err) {
                if (seq !== quoteSeqRef.current) {
                    return;
                }
                setQuote(null);
                if (explicit || cleanCode) {
                    setCouponFeedback({
                        message: err.message || 'Cupón no válido.',
                        isError: true,
                    });
                }
            } finally {
                if (seq === quoteSeqRef.current) {
                    setLoadingQuote(false);
                }
            }
        },
        [items, appliedCoupon, form.email, form.phone]
    );

    useEffect(() => {
        if (externalCouponCode) {
            const clean = externalCouponCode.trim().toUpperCase();
            setCouponCode(clean);
            setAppliedCoupon(clean);
            refreshQuote(clean, true);
        }
    }, [externalCouponCode, refreshQuote]);

    useEffect(() => {
        refreshQuote(appliedCoupon, false);
    }, [items, appliedCoupon, refreshQuote]);

    const rawSubtotal = items.reduce(
        (acc, item) => acc + Number(item.price) * Number(item.quantity),
        0
    );
    const subtotal = quote ? Number(quote.subtotal) : rawSubtotal;
    const discount = quote ? Number(quote.discount || 0) : 0;
    const fee = items.length > 0 ? (quote ? Number(quote.delivery_fee) : Number(deliveryFee)) : 0;
    const total = items.length > 0 ? (quote ? Number(quote.total) : Math.max(0, subtotal - discount) + fee) : 0;

    const handleApplyCoupon = (e) => {
        e.preventDefault();
        const clean = couponCode.trim().toUpperCase();
        setAppliedCoupon(clean);
        refreshQuote(clean, true);
    };

    const handleSubmitOrder = async (e) => {
        e.preventDefault();
        if (items.length === 0) {
            setError('Agrega al menos un plato al carrito.');
            return;
        }
        if (!form.consent) {
            setError('Confirma que autorizas el uso de tus datos para gestionar la entrega.');
            return;
        }

        setSubmitting(true);
        setError('');

        try {
            const csrfToken = getCsrfToken();
            const usingSavedAddress = savedAddressId !== '';
            const response = await fetch('/api/storefront.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    action: 'checkout',
                    checkout_key: checkoutKey,
                    customer_name: form.customer_name.trim(),
                    phone: form.phone.trim(),
                    email: form.email.trim(),
                    saved_address_id: usingSavedAddress ? Number(savedAddressId) : null,
                    address_line: usingSavedAddress ? '' : form.address_line.trim(),
                    district: usingSavedAddress ? '' : form.district.trim(),
                    province: usingSavedAddress ? '' : form.province.trim(),
                    department: usingSavedAddress ? '' : form.department.trim(),
                    reference: usingSavedAddress ? '' : form.reference.trim(),
                    payment_preference: form.payment_preference,
                    coupon_code: appliedCoupon,
                    notes: form.notes.trim(),
                    consent: Boolean(form.consent),
                    website: form.website,
                    items: items.map((item) => ({
                        product_id: Number(item.id),
                        quantity: Number(item.quantity),
                    })),
                }),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo procesar el pedido.');
            }

            setOrderSuccess(data);
            setStep('success');
            setCouponCode('');
            setAppliedCoupon('');
            setQuote(null);
            setCouponFeedback({ message: '', isError: false });
            setCheckoutKey(makeCheckoutKey());
            onClear?.();
            loadSavedProfile();
            onOrderCreated?.(data);
        } catch (err) {
            setError(err.message || 'Error de conexión con el servidor.');
        } finally {
            setSubmitting(false);
        }
    };

    const savedAddresses = profile?.addresses || [];
    const totalCount = items.reduce((acc, i) => acc + Number(i.quantity), 0);

    const panelContent = (
        <div className="flex flex-col h-full bg-white text-[#1E293B]">
            {/* Header del Panel */}
            <div className="px-5 py-4 border-b border-[#E2E8F0] flex items-center justify-between bg-[#F8FAFC]/60">
                <div className="flex items-center gap-2.5">
                    <span className="inline-flex items-center justify-center w-8 h-8 rounded-[8px] bg-[#EFF6FF] text-[#004797] font-bold text-sm border border-[#BFDBFE]">
                        {totalCount}
                    </span>
                    <div>
                        <span className="block text-[10px] font-bold uppercase tracking-[0.08em] text-[#004797]">
                            PEDIDO EN LÍNEA
                        </span>
                        <h2 className="text-[15px] font-bold text-[#0F172A] leading-tight">
                            {step === 'cart' && 'Tu Pedido'}
                            {step === 'checkout' && 'Datos de Entrega'}
                            {step === 'success' && 'Confirmación de Pedido'}
                        </h2>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {items.length > 0 && step === 'cart' && (
                        <button
                            type="button"
                            onClick={onClear}
                            className="text-xs font-medium text-[#64748B] hover:text-[#DC2626] transition-colors px-2 py-1 rounded-[6px] hover:bg-[#FEF2F2]"
                        >
                            Vaciar
                        </button>
                    )}
                    {mode === 'drawer' && (
                        <button
                            type="button"
                            onClick={onClose}
                            className="w-8 h-8 rounded-[8px] border border-[#E2E8F0] bg-white text-[#64748B] hover:text-[#0F172A] hover:bg-[#F8FAFC] flex items-center justify-center transition-colors text-sm font-semibold"
                            aria-label="Cerrar panel"
                        >
                            ✕
                        </button>
                    )}
                </div>
            </div>

            {/* Cuerpo */}
            <div className="flex-1 overflow-y-auto p-5 space-y-4">
                {step === 'success' && orderSuccess ? (
                    <div className="py-4 text-center space-y-4">
                        <div className="w-12 h-12 rounded-full bg-[#ECFDF5] border border-[#A7F3D0] text-[#059669] flex items-center justify-center text-xl font-bold mx-auto">
                            ✓
                        </div>
                        <div>
                            <span className="inline-block px-2.5 py-0.5 rounded-full bg-[#EFF6FF] text-[#004797] text-[11px] font-bold uppercase tracking-wider">
                                Pedido Registrado
                            </span>
                            <h3 className="text-lg font-bold text-[#0F172A] mt-1.5">
                                ¡Gracias por tu compra!
                            </h3>
                            <p className="text-xs text-[#64748B] mt-1">
                                {orderSuccess.message || 'Tu pedido ya ingresó directamente a cocina y caja.'}
                            </p>
                        </div>

                        <div className="bg-[#F8FAFC] rounded-[10px] p-4 border border-[#E2E8F0] text-left space-y-2 text-xs">
                            <div className="flex justify-between items-center pb-2 border-b border-[#E2E8F0]">
                                <span className="text-[#64748B] font-medium">Código de seguimiento</span>
                                <span className="font-mono font-bold text-sm text-[#004797]">
                                    {orderSuccess.order_number}
                                </span>
                            </div>
                            <div className="flex justify-between text-[#475569]">
                                <span>Subtotal</span>
                                <span>{formatMoney(orderSuccess.subtotal)}</span>
                            </div>
                            {Number(orderSuccess.discount || 0) > 0 && (
                                <div className="flex justify-between text-[#059669] font-medium">
                                    <span>
                                        Descuento
                                        {orderSuccess.promotion?.name
                                            ? ` (${orderSuccess.promotion.name})`
                                            : ''}
                                    </span>
                                    <span>− {formatMoney(orderSuccess.discount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-[#475569]">
                                <span>Costo de envío</span>
                                <span>{formatMoney(orderSuccess.delivery_fee)}</span>
                            </div>
                            <div className="flex justify-between text-sm font-bold text-[#0F172A] pt-2 border-t border-[#E2E8F0]">
                                <span>Total a pagar</span>
                                <span className="text-[#004797]">{formatMoney(orderSuccess.total)}</span>
                            </div>
                            <div className="pt-1 text-[11px] text-[#64748B] flex justify-between">
                                <span>Preferencia de pago:</span>
                                <strong className="text-[#1E293B]">
                                    {orderSuccess.payment_preference || 'Por coordinar'}
                                </strong>
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setStep('cart');
                                setOrderSuccess(null);
                                onClose?.();
                            }}
                            className="w-full py-2.5 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white font-semibold text-xs transition-colors shadow-xs"
                        >
                            Seguir explorando la carta
                        </button>
                    </div>
                ) : items.length === 0 ? (
                    <div className="py-10 text-center space-y-2.5">
                        <div className="w-11 h-11 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] flex items-center justify-center mx-auto text-lg text-[#64748B]">
                            🛒
                        </div>
                        <p className="text-sm font-semibold text-[#1E293B]">Tu carrito está vacío</p>
                        <p className="text-xs text-[#64748B] max-w-[230px] mx-auto leading-relaxed">
                            Selecciona platos frescos de nuestra carta para calcular tu pedido en tiempo real.
                        </p>
                    </div>
                ) : step === 'cart' ? (
                    <>
                        {/* Lista de productos en carrito */}
                        <div className="space-y-2.5">
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className="p-3 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#CBD5E1] transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <h4 className="text-xs font-semibold text-[#0F172A] truncate">
                                                {item.name}
                                            </h4>
                                            <p className="text-[11px] text-[#64748B] mt-0.5">
                                                {formatMoney(item.price)} c/u
                                            </p>
                                        </div>
                                        <span className="text-xs font-bold text-[#0F172A] shrink-0">
                                            {formatMoney(item.price * item.quantity)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between mt-2.5 pt-2 border-t border-[#E2E8F0]/80">
                                        <div className="inline-flex items-center rounded-[6px] bg-white border border-[#CBD5E1] overflow-hidden">
                                            <button
                                                type="button"
                                                onClick={() => onUpdateQty(item.id, item.quantity - 1)}
                                                className="w-6 h-6 flex items-center justify-center text-xs font-bold text-[#475569] hover:bg-[#F1F5F9] transition-colors"
                                                aria-label="Disminuir cantidad"
                                            >
                                                −
                                            </button>
                                            <span className="w-7 text-center text-xs font-semibold text-[#0F172A]">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => onUpdateQty(item.id, item.quantity + 1)}
                                                className="w-6 h-6 flex items-center justify-center text-xs font-bold text-[#475569] hover:bg-[#F1F5F9] transition-colors"
                                                aria-label="Aumentar cantidad"
                                            >
                                                +
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => onRemove(item.id)}
                                            className="text-[11px] font-medium text-[#64748B] hover:text-[#DC2626] transition-colors"
                                        >
                                            Quitar
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Promociones y Cupón */}
                        <div className="p-3.5 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] space-y-2">
                            <label
                                htmlFor={`couponInput-${mode}`}
                                className="block text-[11px] font-semibold uppercase tracking-wider text-[#475569]"
                            >
                                Código promocional
                            </label>
                            <form onSubmit={handleApplyCoupon} className="flex gap-2">
                                <input
                                    id={`couponInput-${mode}`}
                                    type="text"
                                    maxLength={40}
                                    value={couponCode}
                                    onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                                    placeholder="Ej. BIENVENIDA10"
                                    className="flex-1 min-w-0 px-3 py-1.5 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] uppercase placeholder:normal-case placeholder:text-[#94A3B8] focus:outline-none focus:border-[#004797]"
                                />
                                <button
                                    type="submit"
                                    disabled={loadingQuote}
                                    className="px-3 py-1.5 rounded-[8px] border border-[#CBD5E1] bg-white hover:bg-[#F1F5F9] text-[#1E293B] font-semibold text-xs transition-colors shrink-0"
                                >
                                    {loadingQuote ? '...' : 'Aplicar'}
                                </button>
                            </form>
                            {couponFeedback.message && (
                                <p
                                    className={`text-[11px] leading-snug ${
                                        couponFeedback.isError
                                            ? 'text-[#DC2626] font-medium'
                                            : 'text-[#059669] font-medium'
                                    }`}
                                >
                                    {couponFeedback.message}
                                </p>
                            )}
                        </div>
                    </>
                ) : (
                    /* Formulario de Checkout */
                    <form id={`checkoutForm-${mode}`} onSubmit={handleSubmitOrder} className="space-y-3">
                        {error && (
                            <div className="p-3 rounded-[8px] bg-[#FEF2F2] border border-[#FECACA] text-[#DC2626] text-xs font-medium">
                                {error}
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                Nombre completo *
                            </label>
                            <input
                                type="text"
                                required
                                maxLength={120}
                                value={form.customer_name}
                                onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                                placeholder="Ej. Carlos Mendoza"
                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-2.5">
                            <div>
                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                    Teléfono / WhatsApp *
                                </label>
                                <input
                                    type="tel"
                                    required
                                    maxLength={30}
                                    value={form.phone}
                                    onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                    placeholder="999 888 777"
                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                    Correo (opcional)
                                </label>
                                <input
                                    type="email"
                                    maxLength={190}
                                    value={form.email}
                                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                                    placeholder="cliente@correo.com"
                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                />
                            </div>
                        </div>

                        {savedAddresses.length > 0 && (
                            <div>
                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                    Direcciones guardadas
                                </label>
                                <select
                                    value={savedAddressId}
                                    onChange={(e) => setSavedAddressId(e.target.value)}
                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                >
                                    <option value="">Ingresar una dirección nueva</option>
                                    {savedAddresses.map((addr) => {
                                        const town = [addr.district, addr.province]
                                            .filter(Boolean)
                                            .join(', ');
                                        return (
                                            <option key={addr.id} value={String(addr.id)}>
                                                {addr.label || 'Dirección'} · {addr.address_line}
                                                {town ? ` (${town})` : ''}
                                                {addr.is_default ? ' · Predeterminada' : ''}
                                            </option>
                                        );
                                    })}
                                </select>
                            </div>
                        )}

                        {savedAddressId === '' && (
                            <div className="space-y-2.5 pt-1">
                                <div>
                                    <label className="block text-xs font-semibold text-[#334155] mb-1">
                                        Dirección exacta *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        maxLength={220}
                                        value={form.address_line}
                                        onChange={(e) =>
                                            setForm({ ...form, address_line: e.target.value })
                                        }
                                        placeholder="Av., calle, número, interior"
                                        className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                    />
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div>
                                        <label className="block text-[11px] font-semibold text-[#334155] mb-1">
                                            Distrito *
                                        </label>
                                        <input
                                            type="text"
                                            required
                                            maxLength={100}
                                            value={form.district}
                                            onChange={(e) =>
                                                setForm({ ...form, district: e.target.value })
                                            }
                                            className="w-full px-2.5 py-1.5 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-semibold text-[#334155] mb-1">
                                            Provincia
                                        </label>
                                        <input
                                            type="text"
                                            maxLength={100}
                                            value={form.province}
                                            onChange={(e) =>
                                                setForm({ ...form, province: e.target.value })
                                            }
                                            className="w-full px-2.5 py-1.5 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[11px] font-semibold text-[#334155] mb-1">
                                            Dpto.
                                        </label>
                                        <input
                                            type="text"
                                            maxLength={100}
                                            value={form.department}
                                            onChange={(e) =>
                                                setForm({ ...form, department: e.target.value })
                                            }
                                            className="w-full px-2.5 py-1.5 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-[#334155] mb-1">
                                        Referencia
                                    </label>
                                    <input
                                        type="text"
                                        maxLength={180}
                                        value={form.reference}
                                        onChange={(e) =>
                                            setForm({ ...form, reference: e.target.value })
                                        }
                                        placeholder="Frente a parque, portón negro, piso…"
                                        className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                    />
                                </div>
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                Preferencia de pago *
                            </label>
                            <select
                                value={form.payment_preference}
                                onChange={(e) =>
                                    setForm({ ...form, payment_preference: e.target.value })
                                }
                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                            >
                                <option value="Efectivo">Efectivo</option>
                                <option value="Yape / Plin">Yape / Plin</option>
                                <option value="Tarjeta al recibir">Tarjeta al recibir</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                Notas para cocina o entrega
                            </label>
                            <textarea
                                rows={2}
                                maxLength={300}
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                                placeholder="Nivel de picante, sin cebolla, timbre…"
                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] focus:outline-none focus:border-[#004797]"
                            />
                        </div>

                        {/* Honeypot anti-bots */}
                        <div className="hidden" aria-hidden="true">
                            <input
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                                value={form.website}
                                onChange={(e) => setForm({ ...form, website: e.target.value })}
                            />
                        </div>

                        <label className="flex items-start gap-2 pt-1 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.consent}
                                onChange={(e) => setForm({ ...form, consent: e.target.checked })}
                                className="mt-0.5 rounded border-[#CBD5E1] text-[#004797] focus:ring-[#004797]"
                            />
                            <span className="text-[11px] text-[#475569] leading-snug">
                                Autorizo el uso de mis datos para procesar y entregar este pedido.
                            </span>
                        </label>
                    </form>
                )}
            </div>

            {/* Footer con Resumen Financiero y Acción Principal */}
            {items.length > 0 && step !== 'success' && (
                <div className="p-5 border-t border-[#E2E8F0] bg-[#F8FAFC] space-y-3">
                    <div className="space-y-1.5 text-xs">
                        <div className="flex justify-between text-[#475569]">
                            <span>Subtotal</span>
                            <span className="font-medium text-[#0F172A]">{formatMoney(subtotal)}</span>
                        </div>
                        {discount > 0 && (
                            <div className="flex justify-between text-[#059669] font-medium">
                                <span>
                                    Descuento{' '}
                                    {quote?.promotion?.name ? `· ${quote.promotion.name}` : ''}
                                </span>
                                <span>− {formatMoney(discount)}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-[#475569]">
                            <span>Delivery</span>
                            <span className="font-medium text-[#0F172A]">{formatMoney(fee)}</span>
                        </div>
                        <div className="flex justify-between text-sm font-bold text-[#0F172A] pt-2 border-t border-[#E2E8F0]">
                            <span>Total</span>
                            <span className="text-[#004797]">{formatMoney(total)}</span>
                        </div>
                    </div>

                    {step === 'cart' ? (
                        <button
                            type="button"
                            onClick={() => {
                                setError('');
                                setStep('checkout');
                            }}
                            className="w-full py-2.5 px-4 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white font-semibold text-xs flex items-center justify-between transition-colors shadow-xs"
                        >
                            <span>Continuar con el pedido</span>
                            <span>→</span>
                        </button>
                    ) : (
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => setStep('cart')}
                                className="px-3.5 py-2.5 rounded-[8px] border border-[#CBD5E1] bg-white hover:bg-[#F1F5F9] text-[#334155] font-semibold text-xs transition-colors"
                            >
                                ← Volver
                            </button>
                            <button
                                type="submit"
                                form={`checkoutForm-${mode}`}
                                disabled={submitting}
                                className="flex-1 py-2.5 px-4 rounded-[8px] bg-[#004797] hover:bg-[#003878] disabled:opacity-60 text-white font-semibold text-xs transition-colors shadow-xs"
                            >
                                {submitting ? 'Registrando pedido…' : 'Confirmar pedido ✓'}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );

    if (mode === 'sidebar') {
        return (
            <div className="rounded-[12px] border border-[#E2E8F0] bg-white shadow-xs overflow-hidden">
                {panelContent}
            </div>
        );
    }

    if (!isOpen) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 overflow-hidden lg:hidden">
            <div
                onClick={onClose}
                className="fixed inset-0 bg-[#0F172A]/40 backdrop-blur-[2px] transition-opacity"
            />
            <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div className="w-screen max-w-md shadow-2xl border-l border-[#E2E8F0] bg-white flex flex-col">
                    {panelContent}
                </div>
            </div>
        </div>
    );
}
