import React, { useState, useMemo, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import CartDrawer, { formatMoney } from '@/Components/CartDrawer';

const CART_STORAGE_KEY = 'desego_store_cart_v1';
const FAVORITES_KEY_NAME = 'desego_store_favorites_key_v1';

function getOrCreateFavoriteKey() {
    if (typeof window === 'undefined') {
        return '';
    }
    try {
        const existing = window.localStorage.getItem(FAVORITES_KEY_NAME);
        if (existing && /^[a-f0-9]{64}$/.test(existing)) {
            return existing;
        }
    } catch (_) {
        // Fallback en memoria si localStorage está restringido
    }
    const bytes = new Uint8Array(32);
    if (window.crypto?.getRandomValues) {
        window.crypto.getRandomValues(bytes);
    } else {
        for (let i = 0; i < bytes.length; i++) {
            bytes[i] = Math.floor(Math.random() * 256);
        }
    }
    const key = Array.from(bytes)
        .map((v) => v.toString(16).padStart(2, '0'))
        .join('');
    try {
        window.localStorage.setItem(FAVORITES_KEY_NAME, key);
    } catch (_) {
        // Ignorar
    }
    return key;
}

const ORDER_STATUS_META = {
    pending: {
        label: 'Pendiente de confirmación',
        step: 1,
        badgeClass: 'bg-[#FFFBEB] text-[#B45309] border-[#FDE68A]',
    },
    preparing: {
        label: 'En preparación en cocina',
        step: 2,
        badgeClass: 'bg-[#EFF6FF] text-[#004797] border-[#BFDBFE]',
    },
    ready: {
        label: 'Listo para despacho',
        step: 3,
        badgeClass: 'bg-[#ECFDF5] text-[#047857] border-[#A7F3D0]',
    },
    delivered: {
        label: 'Pedido entregado',
        step: 4,
        badgeClass: 'bg-[#ECFDF5] text-[#047857] border-[#A7F3D0]',
    },
    cancelled: {
        label: 'Cancelado',
        step: 0,
        badgeClass: 'bg-[#FEF2F2] text-[#B91C1C] border-[#FECACA]',
    },
};

export default function Home({
    categories = [],
    products = [],
    promotions = [],
    tables = [],
    deliveryFee = 5.0,
}) {
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [favoritesOnly, setFavoritesOnly] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [cartItems, setCartItems] = useState(() => {
        if (typeof window === 'undefined') {
            return [];
        }
        try {
            const raw = window.sessionStorage.getItem(CART_STORAGE_KEY);
            if (!raw) {
                return [];
            }
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed;
            }
            if (parsed && typeof parsed === 'object') {
                return Object.values(parsed);
            }
        } catch (_) {
            // Ignorar
        }
        return [];
    });
    const [isCartOpen, setIsCartOpen] = useState(false);
    const [externalCouponCode, setExternalCouponCode] = useState('');
    const [toastMessage, setToastMessage] = useState('');

    // Favoritos y Reseñas Verificadas (/api/reviews.php)
    const [favoriteKey] = useState(() => getOrCreateFavoriteKey());
    const [favorites, setFavorites] = useState([]);
    const [togglingFavoriteId, setTogglingFavoriteId] = useState(null);
    const [reviewSummary, setReviewSummary] = useState({});
    const [selectedReviewProductId, setSelectedReviewProductId] = useState(
        () => (products[0]?.id ? String(products[0].id) : '')
    );
    const [productReviews, setProductReviews] = useState([]);
    const [selectedProductSummary, setSelectedProductSummary] = useState({
        count: 0,
        average: null,
    });
    const [loadingReviews, setLoadingReviews] = useState(false);
    const [reviewFormOpen, setReviewFormOpen] = useState(false);
    const [reviewForm, setReviewForm] = useState({
        order_number: '',
        customer_phone: '',
        rating: '5',
        title: '',
        comment: '',
    });
    const [submittingReview, setSubmittingReview] = useState(false);
    const [reviewFeedback, setReviewFeedback] = useState({ message: '', isError: false });

    // Pedido Web Activo en Sesión (/api/storefront.php?view=order) + Rastreo Manual (/api/mobile.php)
    const [sessionOrder, setSessionOrder] = useState(null);
    const [trackForm, setTrackForm] = useState({ order_number: '', phone: '' });
    const [trackingLoading, setTrackingLoading] = useState(false);
    const [trackedOrder, setTrackedOrder] = useState(null);
    const [trackError, setTrackError] = useState('');

    // Reserva de Mesa en Línea (/api/mobile.php)
    const [resForm, setResForm] = useState({
        customer_name: '',
        phone: '',
        email: '',
        reservation_date: new Date().toISOString().slice(0, 10),
        reservation_time: '13:30',
        party_size: 2,
        zone: 'Salón Principal',
        table_id: '',
        notes: '',
    });
    const [resLoading, setResLoading] = useState(false);
    const [resSuccess, setResSuccess] = useState(null);
    const [resError, setResError] = useState('');

    const showToast = useCallback((msg) => {
        setToastMessage(msg);
    }, []);

    useEffect(() => {
        if (!toastMessage) {
            return;
        }
        const t = setTimeout(() => setToastMessage(''), 3200);
        return () => clearTimeout(t);
    }, [toastMessage]);

    // Sincronizar carrito con sessionStorage y catálogo actual
    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        try {
            window.sessionStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cartItems));
        } catch (_) {
            // Ignorar
        }
    }, [cartItems]);

    const getCsrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Cargar resumen de reseñas, favoritos y pedido activo de la sesión
    const loadStorefrontExtras = useCallback(async () => {
        try {
            const [summaryRes, favRes, orderRes] = await Promise.all([
                fetch('/api/reviews.php?view=summary', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                }),
                fetch(
                    `/api/reviews.php?view=favorites&visitor_key=${encodeURIComponent(
                        favoriteKey
                    )}`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    }
                ),
                fetch('/api/storefront.php?view=order', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                }),
            ]);

            const summaryData = await summaryRes.json().catch(() => ({}));
            if (summaryRes.ok && summaryData.ok) {
                setReviewSummary(summaryData.summary || {});
            }

            const favData = await favRes.json().catch(() => ({}));
            if (favRes.ok && favData.ok && Array.isArray(favData.favorites)) {
                setFavorites(favData.favorites.map(Number));
            }

            const orderData = await orderRes.json().catch(() => ({}));
            if (orderRes.ok && orderData.ok && orderData.order) {
                setSessionOrder(orderData.order);
            }
        } catch (_) {
            // Continúa operando normalmente
        }
    }, [favoriteKey]);

    useEffect(() => {
        loadStorefrontExtras();
    }, [loadStorefrontExtras]);

    // Cargar reseñas aprobadas del plato seleccionado
    const loadProductReviews = useCallback(async (productId) => {
        if (!productId) {
            setProductReviews([]);
            setSelectedProductSummary({ count: 0, average: null });
            return;
        }
        setLoadingReviews(true);
        try {
            const res = await fetch(
                `/api/reviews.php?view=product&product_id=${encodeURIComponent(productId)}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                }
            );
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) {
                const summary = data.summary || { count: 0, average: null };
                setSelectedProductSummary(summary);
                setProductReviews(Array.isArray(data.reviews) ? data.reviews : []);
                setReviewSummary((prev) => ({
                    ...prev,
                    [String(productId)]: summary,
                }));
            }
        } catch (_) {
            setProductReviews([]);
        } finally {
            setLoadingReviews(false);
        }
    }, []);

    useEffect(() => {
        if (selectedReviewProductId) {
            loadProductReviews(selectedReviewProductId);
        }
    }, [selectedReviewProductId, loadProductReviews]);

    const handleToggleFavorite = async (productId) => {
        if (togglingFavoriteId === productId) {
            return;
        }
        setTogglingFavoriteId(productId);
        try {
            const csrfToken = getCsrfToken();
            const res = await fetch('/api/reviews.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    action: 'favorite.toggle',
                    visitor_key: favoriteKey,
                    product_id: Number(productId),
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo actualizar favoritos.');
            }
            if (data.favorite) {
                setFavorites((prev) => [...new Set([...prev, Number(productId)])]);
            } else {
                setFavorites((prev) => prev.filter((id) => Number(id) !== Number(productId)));
            }
            showToast(data.message || 'Favoritos actualizados.');
        } catch (err) {
            showToast(err.message || 'Error al guardar favorito.');
        } finally {
            setTogglingFavoriteId(null);
        }
    };

    const handleOpenProductReviews = (productId) => {
        setSelectedReviewProductId(String(productId));
        const el = document.getElementById('customerReviews');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const handleSubmitReview = async (e) => {
        e.preventDefault();
        setReviewFeedback({ message: '', isError: false });
        if (!selectedReviewProductId) {
            setReviewFeedback({
                message: 'Selecciona primero el plato que deseas calificar.',
                isError: true,
            });
            return;
        }
        setSubmittingReview(true);
        try {
            const csrfToken = getCsrfToken();
            const res = await fetch('/api/reviews.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    action: 'review.submit',
                    order_number: reviewForm.order_number.trim().toUpperCase(),
                    customer_phone: reviewForm.customer_phone.trim(),
                    product_id: Number(selectedReviewProductId),
                    rating: Number(reviewForm.rating),
                    title: reviewForm.title.trim(),
                    comment: reviewForm.comment.trim(),
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo enviar la reseña.');
            }
            setReviewFeedback({
                message:
                    data.message ||
                    '¡Gracias! Tu reseña verificada fue enviada a moderación.',
                isError: false,
            });
            setReviewForm((prev) => ({
                ...prev,
                rating: '5',
                title: '',
                comment: '',
            }));
            showToast('Reseña enviada correctamente.');
        } catch (err) {
            setReviewFeedback({
                message: err.message || 'Error al registrar la reseña.',
                isError: true,
            });
        } finally {
            setSubmittingReview(false);
        }
    };

    const filteredProducts = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();
        return products.filter((product) => {
            const matchesFav = !favoritesOnly || favorites.includes(Number(product.id));
            const matchesCategory =
                selectedCategory === 'all' ||
                Number(product.category_id) === Number(selectedCategory);
            const matchesSearch =
                !q ||
                product.name.toLowerCase().includes(q) ||
                (product.description && product.description.toLowerCase().includes(q)) ||
                (product.category && product.category.toLowerCase().includes(q));
            return matchesFav && matchesCategory && matchesSearch;
        });
    }, [products, favoritesOnly, favorites, selectedCategory, searchQuery]);

    const addToCart = (product) => {
        if (!product.can_order) {
            showToast('Este plato aún no está habilitado para pedidos en línea.');
            return;
        }
        setCartItems((prev) => {
            const existing = prev.find((item) => Number(item.id) === Number(product.id));
            if (existing) {
                if (existing.quantity >= 20) {
                    showToast('Máximo 20 unidades por plato.');
                    return prev;
                }
                return prev.map((item) =>
                    Number(item.id) === Number(product.id)
                        ? { ...item, quantity: item.quantity + 1 }
                        : item
                );
            }
            return [
                ...prev,
                {
                    id: Number(product.id),
                    name: product.name,
                    price: Number(product.price),
                    emoji: product.emoji,
                    image: product.image,
                    quantity: 1,
                },
            ];
        });
        showToast(`${product.name} agregado a tu pedido.`);
    };

    const updateQuantity = (id, quantity) => {
        if (quantity <= 0) {
            setCartItems((prev) => prev.filter((item) => Number(item.id) !== Number(id)));
            return;
        }
        if (quantity > 20) {
            showToast('Máximo 20 unidades por plato.');
            return;
        }
        setCartItems((prev) =>
            prev.map((item) =>
                Number(item.id) === Number(id) ? { ...item, quantity } : item
            )
        );
    };

    const removeFromCart = (id) => {
        setCartItems((prev) => prev.filter((item) => Number(item.id) !== Number(id)));
    };

    const clearCart = () => setCartItems([]);

    const totalItems = cartItems.reduce((acc, item) => acc + Number(item.quantity), 0);
    const totalAmount = cartItems.reduce(
        (acc, item) => acc + Number(item.price) * Number(item.quantity),
        0
    );

    const handleTrackOrder = async (e) => {
        e.preventDefault();
        setTrackingLoading(true);
        setTrackError('');
        setTrackedOrder(null);

        try {
            const res = await fetch('/api/mobile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    action: 'order.track',
                    order_number: trackForm.order_number.trim().toUpperCase(),
                    phone: trackForm.phone.trim(),
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                throw new Error(data.message || 'No se encontró el pedido.');
            }
            setTrackedOrder(data.order);
        } catch (err) {
            setTrackError(err.message);
        } finally {
            setTrackingLoading(false);
        }
    };

    const handleReservationSubmit = async (e) => {
        e.preventDefault();
        setResLoading(true);
        setResError('');
        setResSuccess(null);

        try {
            const res = await fetch('/api/mobile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    action: 'reservation.create',
                    ...resForm,
                    table_id: resForm.table_id ? Number(resForm.table_id) : null,
                    party_size: Number(resForm.party_size),
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                throw new Error(data.message || 'No se pudo registrar la reserva.');
            }
            setResSuccess(data.reservation);
            showToast(`Reserva ${data.reservation.reservation_code} confirmada.`);
        } catch (err) {
            setResError(err.message);
        } finally {
            setResLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-[#F8FAFC] text-[#1E293B] font-sans antialiased selection:bg-[#004797] selection:text-white">
            <Head title="DESEGO CEVICHERÍA · Tienda Oficial y Reservas en Línea" />

            {/* Notificación Toast Discreta */}
            {toastMessage && (
                <div
                    role="status"
                    aria-live="polite"
                    className="fixed bottom-5 right-5 z-50 flex items-center gap-2.5 px-4 py-3 rounded-[10px] bg-[#0F172A] text-white text-xs font-medium shadow-lg border border-[#334155]"
                >
                    <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[#004797] text-white text-[11px] font-bold">
                        ✓
                    </span>
                    <span>{toastMessage}</span>
                </div>
            )}

            {/* Barra Superior Corporativa SaaS */}
            <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-[#E2E8F0]">
                <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                    <a href="#inicio" className="flex items-center gap-3 shrink-0">
                        <img
                            src="/assets/desego-logo.png"
                            alt="DESEGO CEVICHERÍA"
                            className="h-10 w-auto object-contain"
                        />
                        <div className="hidden sm:block">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-bold tracking-tight text-[#0F172A]">
                                    DESEGO CEVICHERÍA
                                </span>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#ECFDF5] border border-[#A7F3D0] text-[#047857] text-[10px] font-semibold">
                                    <span className="w-1.5 h-1.5 rounded-full bg-[#10B981]" />
                                    Abierto
                                </span>
                            </div>
                            <span className="block text-[11px] text-[#64748B] font-medium">
                                Pescados y Mariscos · Tienda Oficial
                            </span>
                        </div>
                    </a>

                    <nav className="hidden md:flex items-center gap-1 text-xs font-semibold text-[#475569]">
                        <a
                            href="#catalogo"
                            className="px-3 py-2 rounded-[8px] hover:bg-[#F1F5F9] hover:text-[#004797] transition-colors"
                        >
                            Carta Digital
                        </a>
                        {promotions.length > 0 && (
                            <a
                                href="#promociones"
                                className="px-3 py-2 rounded-[8px] hover:bg-[#F1F5F9] hover:text-[#004797] transition-colors"
                            >
                                Promociones
                            </a>
                        )}
                        <a
                            href="#customerReviews"
                            className="px-3 py-2 rounded-[8px] hover:bg-[#F1F5F9] hover:text-[#004797] transition-colors"
                        >
                            Opiniones Verificadas
                        </a>
                        <a
                            href="#reservas"
                            className="px-3 py-2 rounded-[8px] hover:bg-[#F1F5F9] hover:text-[#004797] transition-colors"
                        >
                            Reservar Mesa
                        </a>
                        <a
                            href="#seguimiento"
                            className="px-3 py-2 rounded-[8px] hover:bg-[#F1F5F9] hover:text-[#004797] transition-colors"
                        >
                            Rastrear Pedido
                        </a>
                    </nav>

                    <div className="flex items-center gap-2.5">
                        <button
                            type="button"
                            onClick={() => {
                                setFavoritesOnly((prev) => !prev);
                                document
                                    .getElementById('catalogo')
                                    ?.scrollIntoView({ behavior: 'smooth' });
                            }}
                            className={`hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-[8px] text-xs font-semibold border transition-colors ${
                                favoritesOnly
                                    ? 'bg-[#EFF6FF] border-[#004797] text-[#004797]'
                                    : 'bg-white border-[#CBD5E1] text-[#334155] hover:bg-[#F8FAFC]'
                            }`}
                        >
                            <span>{favoritesOnly ? '♥' : '♡'}</span>
                            <span>Favoritos</span>
                            <span className="px-1.5 py-0.2 rounded-full bg-[#F1F5F9] text-[#0F172A] text-[11px] font-bold">
                                {favorites.length}
                            </span>
                        </button>

                        <a
                            href="/admin"
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white hover:bg-[#F8FAFC] text-[#1E293B] text-xs font-semibold transition-colors"
                        >
                            <span>Panel Admin</span>
                            <span className="text-[#64748B]">↗</span>
                        </a>

                        {/* Botón móvil de carrito */}
                        <button
                            type="button"
                            onClick={() => setIsCartOpen(true)}
                            className="lg:hidden inline-flex items-center gap-2 px-3.5 py-2 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white text-xs font-semibold shadow-xs transition-colors"
                        >
                            <span>🛒</span>
                            <span>{ formatMoney(totalAmount) }</span>
                            <span className="px-1.5 py-0.5 rounded-full bg-white text-[#004797] text-[10px] font-bold">
                                {totalItems}
                            </span>
                        </button>
                    </div>
                </div>
            </header>

            {/* Hero Ejecutivo y Métricas Operativas */}
            <section id="inicio" className="bg-white border-b border-[#E2E8F0]">
                <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
                    <div className="grid lg:grid-cols-12 gap-8 items-center">
                        <div className="lg:col-span-7 space-y-4">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#EFF6FF] border border-[#BFDBFE] text-[#004797] text-[11px] font-semibold">
                                <span>GASTRONOMÍA MARINA PERUANA</span>
                                <span>·</span>
                                <span>PEDIDOS Y RESERVAS EN TIEMPO REAL</span>
                            </div>

                            <h1 className="text-2xl sm:text-3xl lg:text-4xl font-bold text-[#0F172A] tracking-tight leading-[1.18]">
                                Frescura marina del día, preparada al momento y entregada con precisión.
                            </h1>

                            <p className="text-sm sm:text-base text-[#475569] max-w-2xl leading-relaxed">
                                Ordena nuestros ceviches, arroces marinos y especialidades directamente a cocina sin comisiones de intermediarios, o reserva tu mesa en salón con confirmación inmediata.
                            </p>

                            <div className="flex flex-wrap items-center gap-3 pt-1">
                                <a
                                    href="#catalogo"
                                    className="px-5 py-2.5 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white text-xs sm:text-sm font-semibold transition-colors shadow-xs"
                                >
                                    Explorar Carta Digital
                                </a>
                                <a
                                    href="#reservas"
                                    className="px-4 py-2.5 rounded-[8px] border border-[#CBD5E1] bg-white hover:bg-[#F8FAFC] text-[#1E293B] text-xs sm:text-sm font-semibold transition-colors"
                                >
                                    Reservar Mesa en Salón
                                </a>
                                <a
                                    href="#seguimiento"
                                    className="px-4 py-2.5 rounded-[8px] border border-[#E2E8F0] bg-[#F8FAFC] hover:bg-[#F1F5F9] text-[#475569] text-xs sm:text-sm font-semibold transition-colors"
                                >
                                    Rastrear Pedido
                                </a>
                            </div>
                        </div>

                        <div className="lg:col-span-5 grid grid-cols-2 gap-3">
                            <div className="p-4 rounded-[12px] bg-[#F8FAFC] border border-[#E2E8F0]">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">
                                    Carta Activa
                                </span>
                                <strong className="block text-xl font-bold text-[#0F172A] mt-1">
                                    {products.length} Platos
                                </strong>
                                <span className="text-xs text-[#475569] mt-0.5 block">
                                    Recetas con control de frescura
                                </span>
                            </div>
                            <div className="p-4 rounded-[12px] bg-[#F8FAFC] border border-[#E2E8F0]">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">
                                    Despacho Directo
                                </span>
                                <strong className="block text-xl font-bold text-[#004797] mt-1">
                                    30 – 45 min
                                </strong>
                                <span className="text-xs text-[#475569] mt-0.5 block">
                                    Envío estándar {formatMoney(deliveryFee)}
                                </span>
                            </div>
                            <div className="p-4 rounded-[12px] bg-[#F8FAFC] border border-[#E2E8F0]">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">
                                    Calidad Garantizada
                                </span>
                                <strong className="block text-xl font-bold text-[#0F172A] mt-1">
                                    100% Fresco
                                </strong>
                                <span className="text-xs text-[#475569] mt-0.5 block">
                                    Trazabilidad de alérgenos e insumos
                                </span>
                            </div>
                            <div className="p-4 rounded-[12px] bg-[#F8FAFC] border border-[#E2E8F0]">
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">
                                    Fidelización
                                </span>
                                <strong className="block text-xl font-bold text-[#059669] mt-1">
                                    1 pto / S/ 10
                                </strong>
                                <span className="text-xs text-[#475569] mt-0.5 block">
                                    Acumulación automática con tu celular
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Pedido Web Activo en Sesión (si existe) */}
            {sessionOrder && (
                <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                    <div className="p-4 rounded-[12px] bg-white border border-[#BFDBFE] shadow-xs flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span className="w-9 h-9 rounded-[8px] bg-[#EFF6FF] text-[#004797] font-bold flex items-center justify-center text-sm">
                                ✓
                            </span>
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-[11px] font-bold uppercase tracking-wider text-[#004797]">
                                        PEDIDO RECIENTE EN CURSO
                                    </span>
                                    <span className="font-mono text-xs font-bold text-[#0F172A]">
                                        {sessionOrder.order_number}
                                    </span>
                                </div>
                                <p className="text-xs text-[#475569] mt-0.5">
                                    Total: <strong>{formatMoney(sessionOrder.total)}</strong> · Estado actual:{' '}
                                    <strong className="text-[#004797]">
                                        {ORDER_STATUS_META[sessionOrder.status]?.label ||
                                            sessionOrder.status}
                                    </strong>
                                </p>
                            </div>
                        </div>
                        <a
                            href="#seguimiento"
                            onClick={() => {
                                setTrackForm((prev) => ({
                                    ...prev,
                                    order_number: sessionOrder.order_number,
                                }));
                            }}
                            className="px-3.5 py-2 rounded-[8px] border border-[#CBD5E1] bg-[#F8FAFC] hover:bg-[#F1F5F9] text-xs font-semibold text-[#1E293B] transition-colors"
                        >
                            Ver detalle de seguimiento →
                        </a>
                    </div>
                </div>
            )}

            {/* WORKSPACE PRINCIPAL DE 2 COLUMNAS: CARTA + CHECKOUT STICKY */}
            <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="grid lg:grid-cols-[minmax(0,1fr)_380px] xl:grid-cols-[minmax(0,1fr)_400px] gap-8 items-start">
                    {/* COLUMNA IZQUIERDA: PROMOCIONES, CARTA, RESEÑAS, RESERVAS, SEGUIMIENTO */}
                    <div className="space-y-10 min-w-0">
                        {/* Promociones Activas */}
                        {promotions.length > 0 && (
                            <section id="promociones" className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#004797]">
                                            BENEFICIOS VIGENTES
                                        </span>
                                        <h2 className="text-lg font-bold text-[#0F172A]">
                                            Promociones y Cupones Disponibles
                                        </h2>
                                    </div>
                                    <span className="text-xs text-[#64748B] hidden sm:inline">
                                        Se aplican automáticamente o ingresando el código en tu pedido
                                    </span>
                                </div>

                                <div className="grid sm:grid-cols-2 gap-3">
                                    {promotions.map((promo) => {
                                        const discountText =
                                            promo.discount_type === 'percentage'
                                                ? `${promo.discount_value}% DSCTO.`
                                                : `S/ ${Number(promo.discount_value).toFixed(2)} DSCTO.`;
                                        return (
                                            <div
                                                key={promo.id}
                                                className="p-4 rounded-[12px] bg-white border border-[#E2E8F0] hover:border-[#CBD5E1] transition-colors flex items-start justify-between gap-3 shadow-2xs"
                                            >
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="px-2 py-0.5 rounded-[6px] bg-[#EFF6FF] text-[#004797] text-[11px] font-bold">
                                                            {discountText}
                                                        </span>
                                                        {promo.min_subtotal > 0 && (
                                                            <span className="text-[11px] text-[#64748B]">
                                                                Desde {formatMoney(promo.min_subtotal)}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <h3 className="text-sm font-bold text-[#0F172A]">
                                                        {promo.name}
                                                    </h3>
                                                    {promo.description && (
                                                        <p className="text-xs text-[#475569]">
                                                            {promo.description}
                                                        </p>
                                                    )}
                                                </div>

                                                {promo.code ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setExternalCouponCode(promo.code);
                                                            showToast(
                                                                `Cupón ${promo.code} enviado a tu pedido.`
                                                            );
                                                        }}
                                                        className="px-3 py-1.5 rounded-[8px] border border-[#BFDBFE] bg-[#EFF6FF] hover:bg-[#DBEAFE] text-[#004797] font-mono text-xs font-bold shrink-0 transition-colors"
                                                    >
                                                        {promo.code}
                                                    </button>
                                                ) : (
                                                    <span className="px-2.5 py-1 rounded-[6px] bg-[#ECFDF5] text-[#047857] text-[11px] font-semibold shrink-0">
                                                        Automática
                                                    </span>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        )}

                        {/* CARTA DIGITAL */}
                        <section id="catalogo" className="space-y-5">
                            <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 pb-4 border-b border-[#E2E8F0]">
                                <div>
                                    <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#004797]">
                                        MENÚ DEL DÍA
                                    </span>
                                    <h2 className="text-xl font-bold text-[#0F172A]">
                                        Nuestra Carta Marina
                                    </h2>
                                    <p className="text-xs text-[#64748B] mt-0.5">
                                        Selecciona tus platos favoritos. Precios incluyen impuestos.
                                    </p>
                                </div>

                                {/* Buscador */}
                                <div className="relative w-full sm:w-72">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[#64748B] text-xs">
                                        ⌕
                                    </span>
                                    <input
                                        type="search"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="Buscar plato o ingrediente…"
                                        aria-label="Buscar en el menú"
                                        className="w-full pl-8 pr-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs text-[#0F172A] placeholder:text-[#94A3B8] focus:outline-none focus:border-[#004797]"
                                    />
                                </div>
                            </div>

                            {/* Filtros de Categoría + Favoritos */}
                            <div className="flex items-center gap-2 overflow-x-auto pb-1">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setFavoritesOnly(false);
                                        setSelectedCategory('all');
                                    }}
                                    className={`px-3.5 py-1.5 rounded-[8px] text-xs font-semibold whitespace-nowrap transition-colors border ${
                                        !favoritesOnly && selectedCategory === 'all'
                                            ? 'bg-[#004797] text-white border-[#004797]'
                                            : 'bg-white text-[#475569] border-[#E2E8F0] hover:border-[#CBD5E1] hover:text-[#0F172A]'
                                    }`}
                                >
                                    Todos ({products.length})
                                </button>
                                {categories.map((cat) => (
                                    <button
                                        key={cat.id}
                                        type="button"
                                        onClick={() => {
                                            setFavoritesOnly(false);
                                            setSelectedCategory(cat.id);
                                        }}
                                        className={`px-3.5 py-1.5 rounded-[8px] text-xs font-semibold whitespace-nowrap transition-colors border ${
                                            !favoritesOnly &&
                                            Number(selectedCategory) === Number(cat.id)
                                                ? 'bg-[#004797] text-white border-[#004797]'
                                                : 'bg-white text-[#475569] border-[#E2E8F0] hover:border-[#CBD5E1] hover:text-[#0F172A]'
                                        }`}
                                    >
                                        {cat.name}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    onClick={() => setFavoritesOnly((prev) => !prev)}
                                    className={`px-3.5 py-1.5 rounded-[8px] text-xs font-semibold whitespace-nowrap transition-colors border flex items-center gap-1.5 ${
                                        favoritesOnly
                                            ? 'bg-[#EFF6FF] text-[#004797] border-[#004797]'
                                            : 'bg-white text-[#475569] border-[#E2E8F0] hover:border-[#CBD5E1] hover:text-[#0F172A]'
                                    }`}
                                >
                                    <span>{favoritesOnly ? '♥' : '♡'}</span>
                                    <span>Mis favoritos ({favorites.length})</span>
                                </button>
                            </div>

                            {/* Grid de Productos */}
                            {filteredProducts.length === 0 ? (
                                <div className="p-10 rounded-[12px] bg-white border border-[#E2E8F0] text-center space-y-2">
                                    <p className="text-sm font-semibold text-[#0F172A]">
                                        {favoritesOnly
                                            ? 'Aún no tienes platos marcados como favoritos en esta búsqueda.'
                                            : 'No encontramos platos para tu búsqueda.'}
                                    </p>
                                    <p className="text-xs text-[#64748B]">
                                        Prueba con otro término o restablece los filtros de categoría.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setFavoritesOnly(false);
                                            setSelectedCategory('all');
                                            setSearchQuery('');
                                        }}
                                        className="mt-2 inline-flex px-3.5 py-1.5 rounded-[8px] border border-[#CBD5E1] bg-white hover:bg-[#F8FAFC] text-xs font-semibold text-[#1E293B]"
                                    >
                                        Mostrar toda la carta
                                    </button>
                                </div>
                            ) : (
                                <div className="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                                    {filteredProducts.map((product) => {
                                        const inCart = cartItems.find(
                                            (i) => Number(i.id) === Number(product.id)
                                        );
                                        const isFav = favorites.includes(Number(product.id));
                                        const rSum = reviewSummary[String(product.id)] || {
                                            count: 0,
                                            average: null,
                                        };
                                        const ratingLabel =
                                            Number(rSum.count) > 0 && rSum.average !== null
                                                ? Number(rSum.average).toFixed(1)
                                                : 'Nuevo';

                                        return (
                                            <article
                                                key={product.id}
                                                className="rounded-[12px] bg-white border border-[#E2E8F0] hover:border-[#CBD5E1] shadow-2xs hover:shadow-md transition-all flex flex-col overflow-hidden"
                                            >
                                                {/* Cabecera Visual del Plato */}
                                                <div className="relative h-44 bg-[#F1F5F9] overflow-hidden border-b border-[#E2E8F0]">
                                                    {product.image ? (
                                                        <img
                                                            src={product.image}
                                                            alt={product.name}
                                                            className="w-full h-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center text-5xl">
                                                            {product.emoji || '🍽️'}
                                                        </div>
                                                    )}

                                                    <span className="absolute top-3 left-3 px-2.5 py-1 rounded-[6px] bg-white/95 backdrop-blur-xs border border-[#E2E8F0] text-[11px] font-semibold text-[#1E293B]">
                                                        {product.category}
                                                    </span>

                                                    <button
                                                        type="button"
                                                        disabled={togglingFavoriteId === product.id}
                                                        onClick={() =>
                                                            handleToggleFavorite(product.id)
                                                        }
                                                        aria-label={
                                                            isFav
                                                                ? 'Quitar de favoritos'
                                                                : 'Añadir a favoritos'
                                                        }
                                                        className={`absolute top-3 right-3 w-8 h-8 rounded-[8px] border flex items-center justify-center text-sm transition-colors ${
                                                            isFav
                                                                ? 'bg-[#FEF2F2] border-[#FECACA] text-[#DC2626]'
                                                                : 'bg-white/95 border-[#E2E8F0] text-[#475569] hover:text-[#0F172A]'
                                                        }`}
                                                    >
                                                        {isFav ? '♥' : '♡'}
                                                    </button>
                                                </div>

                                                {/* Contenido del Plato */}
                                                <div className="p-4 flex-1 flex flex-col justify-between space-y-3">
                                                    <div className="space-y-1.5">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <h3 className="text-sm font-bold text-[#0F172A] leading-snug">
                                                                {product.name}
                                                            </h3>
                                                            <span className="text-sm font-bold text-[#004797] shrink-0">
                                                                {formatMoney(product.price)}
                                                            </span>
                                                        </div>

                                                        <p className="text-xs text-[#475569] line-clamp-2 leading-relaxed">
                                                            {product.description ||
                                                                'Preparado al momento con pesca fresca del día.'}
                                                        </p>
                                                    </div>

                                                    <div className="space-y-2.5 pt-2 border-t border-[#F1F5F9]">
                                                        {/* Rating y Opiniones */}
                                                        <div className="flex items-center justify-between text-[11px]">
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="font-bold text-[#B45309]">
                                                                    ★ {ratingLabel}
                                                                </span>
                                                                <span className="text-[#64748B]">
                                                                    ({rSum.count || 0}{' '}
                                                                    {Number(rSum.count) === 1
                                                                        ? 'opinión'
                                                                        : 'opiniones'}
                                                                    )
                                                                </span>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    handleOpenProductReviews(
                                                                        product.id
                                                                    )
                                                                }
                                                                className="font-semibold text-[#004797] hover:underline"
                                                            >
                                                                Ver opiniones
                                                            </button>
                                                        </div>

                                                        {/* Alérgenos */}
                                                        <div>
                                                            {product.allergens ? (
                                                                <span className="inline-block px-2 py-0.5 rounded-[6px] bg-[#FFFBEB] border border-[#FDE68A] text-[#B45309] text-[10px] font-medium">
                                                                    Alérgenos: {product.allergens}
                                                                </span>
                                                            ) : (
                                                                <span className="inline-block px-2 py-0.5 rounded-[6px] bg-[#F8FAFC] border border-[#E2E8F0] text-[#64748B] text-[10px] font-medium">
                                                                    Consulta alérgenos al equipo
                                                                </span>
                                                            )}
                                                        </div>

                                                        {/* Acción Agregar / Control de Cantidad */}
                                                        {inCart ? (
                                                            <div className="flex items-center justify-between bg-[#EFF6FF] border border-[#BFDBFE] rounded-[8px] p-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        updateQuantity(
                                                                            product.id,
                                                                            inCart.quantity - 1
                                                                        )
                                                                    }
                                                                    className="w-7 h-7 rounded-[6px] bg-white text-[#004797] font-bold text-xs flex items-center justify-center hover:bg-[#DBEAFE] transition-colors"
                                                                >
                                                                    −
                                                                </button>
                                                                <span className="text-xs font-bold text-[#004797]">
                                                                    {inCart.quantity} en pedido
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        updateQuantity(
                                                                            product.id,
                                                                            inCart.quantity + 1
                                                                        )
                                                                    }
                                                                    className="w-7 h-7 rounded-[6px] bg-[#004797] text-white font-bold text-xs flex items-center justify-center hover:bg-[#003878] transition-colors"
                                                                >
                                                                    +
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                disabled={!product.can_order}
                                                                onClick={() => addToCart(product)}
                                                                className="w-full py-2 px-3 rounded-[8px] bg-[#004797] hover:bg-[#003878] disabled:bg-[#E2E8F0] disabled:text-[#94A3B8] text-white font-semibold text-xs transition-colors"
                                                            >
                                                                {product.can_order
                                                                    ? '+ Agregar al pedido'
                                                                    : 'No disponible'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            )}
                        </section>

                        {/* SECCIÓN DE RESEÑAS Y CALIFICACIONES VERIFICADAS (#customerReviews) */}
                        <section
                            id="customerReviews"
                            className="p-6 rounded-[12px] bg-white border border-[#E2E8F0] shadow-2xs space-y-5"
                        >
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#E2E8F0]">
                                <div>
                                    <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#004797]">
                                        CALIDAD VERIFICADA
                                    </span>
                                    <h2 className="text-lg font-bold text-[#0F172A]">
                                        Opiniones de Clientes
                                    </h2>
                                    <p className="text-xs text-[#64748B] mt-0.5">
                                        Reseñas vinculadas a pedidos reales entregados y pagados en DESEGO.
                                    </p>
                                </div>

                                <div className="flex items-center gap-3">
                                    <select
                                        id="reviewProductSelect"
                                        value={selectedReviewProductId}
                                        onChange={(e) =>
                                            setSelectedReviewProductId(e.target.value)
                                        }
                                        className="px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs font-semibold text-[#0F172A] focus:outline-none focus:border-[#004797]"
                                    >
                                        {products.map((p) => (
                                            <option key={p.id} value={String(p.id)}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>

                                    <div className="px-3 py-1.5 rounded-[8px] bg-[#F8FAFC] border border-[#E2E8F0] text-xs">
                                        <strong className="text-[#B45309]">
                                            ★{' '}
                                            {selectedProductSummary.average !== null
                                                ? `${Number(
                                                      selectedProductSummary.average
                                                  ).toFixed(1)} / 5`
                                                : 'Sin calificación'}
                                        </strong>
                                        <span className="text-[#64748B] ml-2">
                                            ({selectedProductSummary.count || 0} aprobadas)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Lista de opiniones aprobadas */}
                            {loadingReviews ? (
                                <p className="text-xs text-[#64748B] py-4">
                                    Cargando opiniones verificadas…
                                </p>
                            ) : productReviews.length === 0 ? (
                                <div className="p-4 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] text-xs text-[#475569]">
                                    Todavía no hay opiniones publicadas para este plato. Si ya recibiste y pagaste tu pedido, puedes ser la primera persona en calificarlo.
                                </div>
                            ) : (
                                <div className="grid sm:grid-cols-2 gap-3">
                                    {productReviews.map((rev) => (
                                        <article
                                            key={rev.id}
                                            className="p-4 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] space-y-1.5"
                                        >
                                            <div className="flex items-center justify-between">
                                                <strong className="text-xs font-bold text-[#0F172A]">
                                                    {rev.reviewer_display || 'Cliente DESEGO'}
                                                </strong>
                                                <span className="text-xs text-[#B45309] font-bold">
                                                    {'★'.repeat(Number(rev.rating || 5))}
                                                    {'☆'.repeat(
                                                        Math.max(0, 5 - Number(rev.rating || 5))
                                                    )}
                                                </span>
                                            </div>
                                            {rev.title && (
                                                <h4 className="text-xs font-semibold text-[#1E293B]">
                                                    {rev.title}
                                                </h4>
                                            )}
                                            <p className="text-xs text-[#475569] leading-relaxed">
                                                {rev.comment}
                                            </p>
                                            <span className="block text-[10px] text-[#059669] font-semibold pt-1">
                                                ✓ Compra verificada
                                            </span>
                                        </article>
                                    ))}
                                </div>
                            )}

                            {/* Formulario para enviar reseña verificada */}
                            <div className="pt-2 border-t border-[#E2E8F0]">
                                <button
                                    type="button"
                                    onClick={() => setReviewFormOpen((prev) => !prev)}
                                    className="inline-flex items-center gap-2 text-xs font-semibold text-[#004797] hover:underline"
                                >
                                    <span>{reviewFormOpen ? '▾' : '▸'}</span>
                                    <span>Escribir una reseña verificada de mi pedido</span>
                                </button>

                                {reviewFormOpen && (
                                    <form
                                        onSubmit={handleSubmitReview}
                                        className="mt-4 p-4 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] space-y-3"
                                    >
                                        {reviewFeedback.message && (
                                            <div
                                                className={`p-3 rounded-[8px] text-xs font-medium border ${
                                                    reviewFeedback.isError
                                                        ? 'bg-[#FEF2F2] border-[#FECACA] text-[#DC2626]'
                                                        : 'bg-[#ECFDF5] border-[#A7F3D0] text-[#047857]'
                                                }`}
                                            >
                                                {reviewFeedback.message}
                                            </div>
                                        )}

                                        <div className="grid sm:grid-cols-3 gap-3">
                                            <div>
                                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                    Código de pedido *
                                                </label>
                                                <input
                                                    type="text"
                                                    required
                                                    maxLength={24}
                                                    value={reviewForm.order_number}
                                                    onChange={(e) =>
                                                        setReviewForm({
                                                            ...reviewForm,
                                                            order_number:
                                                                e.target.value.toUpperCase(),
                                                        })
                                                    }
                                                    placeholder="PED-000123"
                                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs font-mono uppercase focus:outline-none focus:border-[#004797]"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                    Teléfono del pedido *
                                                </label>
                                                <input
                                                    type="tel"
                                                    required
                                                    maxLength={30}
                                                    value={reviewForm.customer_phone}
                                                    onChange={(e) =>
                                                        setReviewForm({
                                                            ...reviewForm,
                                                            customer_phone: e.target.value,
                                                        })
                                                    }
                                                    placeholder="999 888 777"
                                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                    Calificación *
                                                </label>
                                                <select
                                                    value={reviewForm.rating}
                                                    onChange={(e) =>
                                                        setReviewForm({
                                                            ...reviewForm,
                                                            rating: e.target.value,
                                                        })
                                                    }
                                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                                >
                                                    <option value="5">★★★★★ Excelente (5)</option>
                                                    <option value="4">★★★★☆ Muy bueno (4)</option>
                                                    <option value="3">★★★☆☆ Bueno (3)</option>
                                                    <option value="2">★★☆☆☆ Regular (2)</option>
                                                    <option value="1">★☆☆☆☆ Por mejorar (1)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div className="grid sm:grid-cols-3 gap-3">
                                            <div className="sm:col-span-1">
                                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                    Título breve
                                                </label>
                                                <input
                                                    type="text"
                                                    maxLength={120}
                                                    value={reviewForm.title}
                                                    onChange={(e) =>
                                                        setReviewForm({
                                                            ...reviewForm,
                                                            title: e.target.value,
                                                        })
                                                    }
                                                    placeholder="Ej. Frescura impecable"
                                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                    Comentario *
                                                </label>
                                                <input
                                                    type="text"
                                                    required
                                                    maxLength={600}
                                                    value={reviewForm.comment}
                                                    onChange={(e) =>
                                                        setReviewForm({
                                                            ...reviewForm,
                                                            comment: e.target.value,
                                                        })
                                                    }
                                                    placeholder="Cuéntanos qué te pareció el sabor, presentación y entrega…"
                                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex justify-end">
                                            <button
                                                type="submit"
                                                disabled={submittingReview}
                                                className="px-4 py-2 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white text-xs font-semibold transition-colors"
                                            >
                                                {submittingReview
                                                    ? 'Enviando reseña…'
                                                    : 'Enviar reseña verificada ★'}
                                            </button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        </section>

                        {/* RESERVA DE MESA EN SALÓN (#reservas) */}
                        <section
                            id="reservas"
                            className="p-6 rounded-[12px] bg-white border border-[#E2E8F0] shadow-2xs space-y-5"
                        >
                            <div className="pb-4 border-b border-[#E2E8F0]">
                                <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#004797]">
                                    EXPERIENCIA EN SALÓN
                                </span>
                                <h2 className="text-lg font-bold text-[#0F172A]">
                                    Reserva tu Mesa en Línea
                                </h2>
                                <p className="text-xs text-[#64748B] mt-0.5">
                                    Sincronizado en tiempo real con recepción y plano de salón de DESEGO.
                                </p>
                            </div>

                            {resSuccess ? (
                                <div className="p-5 rounded-[10px] bg-[#F8FAFC] border border-[#BFDBFE] space-y-3">
                                    <div className="flex items-center gap-2.5">
                                        <span className="w-7 h-7 rounded-full bg-[#ECFDF5] border border-[#A7F3D0] text-[#059669] flex items-center justify-center font-bold text-xs">
                                            ✓
                                        </span>
                                        <div>
                                            <h3 className="text-sm font-bold text-[#0F172A]">
                                                ¡Reserva Confirmada!
                                            </h3>
                                            <p className="text-xs text-[#475569]">
                                                Código:{' '}
                                                <strong className="font-mono text-[#004797]">
                                                    {resSuccess.reservation_code}
                                                </strong>
                                            </p>
                                        </div>
                                    </div>
                                    <div className="grid sm:grid-cols-4 gap-3 text-xs bg-white p-3 rounded-[8px] border border-[#E2E8F0]">
                                        <div>
                                            <span className="text-[#64748B] block">Cliente</span>
                                            <strong className="text-[#0F172A]">
                                                {resSuccess.customer_name}
                                            </strong>
                                        </div>
                                        <div>
                                            <span className="text-[#64748B] block">Fecha y Hora</span>
                                            <strong className="text-[#0F172A]">
                                                {resSuccess.reservation_date} ·{' '}
                                                {resSuccess.reservation_time}
                                            </strong>
                                        </div>
                                        <div>
                                            <span className="text-[#64748B] block">Personas</span>
                                            <strong className="text-[#0F172A]">
                                                {resSuccess.party_size} comensales
                                            </strong>
                                        </div>
                                        <div>
                                            <span className="text-[#64748B] block">Ubicación</span>
                                            <strong className="text-[#0F172A]">
                                                {resSuccess.zone}
                                                {resSuccess.table_label
                                                    ? ` · ${resSuccess.table_label}`
                                                    : ''}
                                            </strong>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setResSuccess(null)}
                                        className="px-4 py-2 rounded-[8px] border border-[#CBD5E1] bg-white hover:bg-[#F1F5F9] text-xs font-semibold text-[#1E293B]"
                                    >
                                        Realizar otra reserva
                                    </button>
                                </div>
                            ) : (
                                <form onSubmit={handleReservationSubmit} className="space-y-4">
                                    {resError && (
                                        <div className="p-3 rounded-[8px] bg-[#FEF2F2] border border-[#FECACA] text-[#DC2626] text-xs font-medium">
                                            {resError}
                                        </div>
                                    )}

                                    <div className="grid sm:grid-cols-3 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Nombre completo *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={resForm.customer_name}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        customer_name: e.target.value,
                                                    })
                                                }
                                                placeholder="Ej. María Vargas"
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Teléfono / WhatsApp *
                                            </label>
                                            <input
                                                type="tel"
                                                required
                                                value={resForm.phone}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        phone: e.target.value,
                                                    })
                                                }
                                                placeholder="999 888 777"
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Correo electrónico
                                            </label>
                                            <input
                                                type="email"
                                                value={resForm.email}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        email: e.target.value,
                                                    })
                                                }
                                                placeholder="cliente@correo.com"
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid sm:grid-cols-4 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Fecha *
                                            </label>
                                            <input
                                                type="date"
                                                required
                                                value={resForm.reservation_date}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        reservation_date: e.target.value,
                                                    })
                                                }
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Hora *
                                            </label>
                                            <input
                                                type="time"
                                                required
                                                value={resForm.reservation_time}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        reservation_time: e.target.value,
                                                    })
                                                }
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Personas *
                                            </label>
                                            <select
                                                value={resForm.party_size}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        party_size: Number(e.target.value),
                                                    })
                                                }
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            >
                                                {[1, 2, 3, 4, 5, 6, 8, 10, 12].map((n) => (
                                                    <option key={n} value={n}>
                                                        {n} {n === 1 ? 'persona' : 'personas'}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Zona preferida
                                            </label>
                                            <select
                                                value={resForm.zone}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        zone: e.target.value,
                                                    })
                                                }
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            >
                                                <option value="Salón Principal">
                                                    Salón Principal
                                                </option>
                                                <option value="Terraza">Terraza Marina</option>
                                                <option value="Privado">Salón Privado</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div className="grid sm:grid-cols-2 gap-3">
                                        {tables.length > 0 && (
                                            <div>
                                                <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                    Mesa disponible (opcional)
                                                </label>
                                                <select
                                                    value={resForm.table_id}
                                                    onChange={(e) =>
                                                        setResForm({
                                                            ...resForm,
                                                            table_id: e.target.value,
                                                        })
                                                    }
                                                    className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                                >
                                                    <option value="">
                                                        Asignación automática por recepción
                                                    </option>
                                                    {tables.map((t) => (
                                                        <option key={t.id} value={t.id}>
                                                            {t.label} · {t.zone} (Cap. {t.capacity}p)
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                        <div className={tables.length > 0 ? '' : 'sm:col-span-2'}>
                                            <label className="block text-xs font-semibold text-[#334155] mb-1">
                                                Notas o solicitud especial
                                            </label>
                                            <input
                                                type="text"
                                                value={resForm.notes}
                                                onChange={(e) =>
                                                    setResForm({
                                                        ...resForm,
                                                        notes: e.target.value,
                                                    })
                                                }
                                                placeholder="Cumpleaños, silla para bebé, alergias…"
                                                className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={resLoading}
                                            className="px-5 py-2.5 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white text-xs font-semibold transition-colors shadow-xs"
                                        >
                                            {resLoading
                                                ? 'Procesando reserva…'
                                                : 'Confirmar Reserva de Mesa'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </section>

                        {/* SEGUIMIENTO DE PEDIDO EN TIEMPO REAL (#seguimiento) */}
                        <section
                            id="seguimiento"
                            className="p-6 rounded-[12px] bg-white border border-[#E2E8F0] shadow-2xs space-y-5"
                        >
                            <div className="pb-4 border-b border-[#E2E8F0]">
                                <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-[#004797]">
                                    TRAZABILIDAD EN VIVO
                                </span>
                                <h2 className="text-lg font-bold text-[#0F172A]">
                                    Consulta el Estado de tu Pedido
                                </h2>
                                <p className="text-xs text-[#64748B] mt-0.5">
                                    Ingresa tu código de pedido y teléfono para verificar el avance en cocina y despacho.
                                </p>
                            </div>

                            <form
                                onSubmit={handleTrackOrder}
                                className="grid sm:grid-cols-[1fr_1fr_auto] gap-3 items-end"
                            >
                                <div>
                                    <label className="block text-xs font-semibold text-[#334155] mb-1">
                                        Código de pedido *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={trackForm.order_number}
                                        onChange={(e) =>
                                            setTrackForm({
                                                ...trackForm,
                                                order_number: e.target.value.toUpperCase(),
                                            })
                                        }
                                        placeholder="Ej. PED-000125"
                                        className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs font-mono uppercase focus:outline-none focus:border-[#004797]"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-[#334155] mb-1">
                                        Teléfono registrado (opcional)
                                    </label>
                                    <input
                                        type="tel"
                                        value={trackForm.phone}
                                        onChange={(e) =>
                                            setTrackForm({ ...trackForm, phone: e.target.value })
                                        }
                                        placeholder="999 888 777"
                                        className="w-full px-3 py-2 rounded-[8px] border border-[#CBD5E1] bg-white text-xs focus:outline-none focus:border-[#004797]"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={trackingLoading}
                                    className="px-5 py-2 rounded-[8px] bg-[#004797] hover:bg-[#003878] text-white text-xs font-semibold transition-colors h-[34px]"
                                >
                                    {trackingLoading ? 'Consultando…' : 'Buscar Pedido'}
                                </button>
                            </form>

                            {trackError && (
                                <div className="p-3 rounded-[8px] bg-[#FEF2F2] border border-[#FECACA] text-[#DC2626] text-xs font-medium">
                                    {trackError}
                                </div>
                            )}

                            {trackedOrder && (
                                <div className="p-4 rounded-[10px] bg-[#F8FAFC] border border-[#E2E8F0] space-y-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-[#E2E8F0]">
                                        <div>
                                            <span className="font-mono text-sm font-bold text-[#004797]">
                                                {trackedOrder.order_number}
                                            </span>
                                            <p className="text-xs text-[#475569]">
                                                Cliente: {trackedOrder.customer_name} · Total:{' '}
                                                <strong>{formatMoney(trackedOrder.total)}</strong>
                                            </p>
                                        </div>
                                        <span
                                            className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${
                                                ORDER_STATUS_META[trackedOrder.status]
                                                    ?.badgeClass ||
                                                'bg-[#F1F5F9] text-[#475569] border-[#CBD5E1]'
                                            }`}
                                        >
                                            {ORDER_STATUS_META[trackedOrder.status]?.label ||
                                                trackedOrder.status}
                                        </span>
                                    </div>

                                    {trackedOrder.status !== 'cancelled' && (
                                        <div className="grid grid-cols-4 gap-2">
                                            {[
                                                { step: 1, label: 'Recibido' },
                                                { step: 2, label: 'En Cocina' },
                                                { step: 3, label: 'Listo' },
                                                { step: 4, label: 'Entregado' },
                                            ].map((s) => {
                                                const active =
                                                    (ORDER_STATUS_META[trackedOrder.status]
                                                        ?.step || 1) >= s.step;
                                                return (
                                                    <div key={s.step} className="space-y-1">
                                                        <div
                                                            className={`h-1.5 rounded-full ${
                                                                active
                                                                    ? 'bg-[#004797]'
                                                                    : 'bg-[#E2E8F0]'
                                                            }`}
                                                        />
                                                        <span
                                                            className={`block text-[11px] font-semibold ${
                                                                active
                                                                    ? 'text-[#004797]'
                                                                    : 'text-[#94A3B8]'
                                                            }`}
                                                        >
                                                            {s.label}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            )}
                        </section>
                    </div>

                    {/* COLUMNA DERECHA STICKY (ESCRITORIO >= 1024px): TU PEDIDO Y CHECKOUT */}
                    <aside className="hidden lg:block sticky top-22">
                        <CartDrawer
                            mode="sidebar"
                            items={cartItems}
                            deliveryFee={deliveryFee}
                            externalCouponCode={externalCouponCode}
                            onUpdateQty={updateQuantity}
                            onRemove={removeFromCart}
                            onClear={clearCart}
                            onOrderCreated={(data) => {
                                setSessionOrder({
                                    order_number: data.order_number,
                                    status: data.status || 'pending',
                                    total: data.total,
                                });
                                showToast(`Pedido ${data.order_number} registrado con éxito.`);
                            }}
                        />
                    </aside>
                </div>
            </div>

            {/* Footer Corporativo */}
            <footer className="bg-white border-t border-[#E2E8F0] mt-12">
                <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[#64748B]">
                    <div className="flex items-center gap-3">
                        <img
                            src="/assets/desego-logo.png"
                            alt="DESEGO"
                            className="w-7 h-7 rounded-[6px] object-contain border border-[#E2E8F0]"
                        />
                        <span className="font-semibold text-[#1E293B]">
                            DESEGO CEVICHERÍA · Pescados y Mariscos
                        </span>
                    </div>
                    <div className="flex flex-wrap items-center gap-4">
                        <a href="#catalogo" className="hover:text-[#004797]">
                            Carta Digital
                        </a>
                        <a href="#customerReviews" className="hover:text-[#004797]">
                            Opiniones Verificadas
                        </a>
                        <a href="#reservas" className="hover:text-[#004797]">
                            Reservas
                        </a>
                        <a href="/admin" className="font-semibold text-[#004797] hover:underline">
                            Panel Administrativo
                        </a>
                    </div>
                </div>
            </footer>

            {/* Drawer Móvil (< 1024px) */}
            <CartDrawer
                mode="drawer"
                isOpen={isCartOpen}
                onClose={() => setIsCartOpen(false)}
                items={cartItems}
                deliveryFee={deliveryFee}
                externalCouponCode={externalCouponCode}
                onUpdateQty={updateQuantity}
                onRemove={removeFromCart}
                onClear={clearCart}
                onOrderCreated={(data) => {
                    setSessionOrder({
                        order_number: data.order_number,
                        status: data.status || 'pending',
                        total: data.total,
                    });
                    showToast(`Pedido ${data.order_number} registrado con éxito.`);
                }}
            />
        </div>
    );
}
