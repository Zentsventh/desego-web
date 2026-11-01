import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ArrowRight,
    LogOut,
    MapPin,
    ShoppingBag,
    CalendarDays,
    UserRound,
    Trash2,
    RefreshCw,
    Plus,
    Heart,
    MessagesSquare,
    Star,
    FileText,
    ShieldCheck,
    Check,
    Award,
    Sparkles,
    Copy,
    Truck,
    Receipt,
} from '../../Components/Restaurant/Icons';
import { Pencil, Paperclip, Search, Mail, Phone, ChevronRight, ChevronDown, Menu, X, UtensilsCrossed } from 'lucide-react';
import { ErrorList, foodImage, money } from '../../Components/Restaurant/ShopParts';
import InteractiveMap from '../../Components/Restaurant/InteractiveMap';
import '../../../css/restaurant.css';
import '../../../css/restaurant-mobile.css';
import '../../../css/restaurant-account.css';

const ACCOUNT_SECTIONS = [
    ['orders', 'Mis pedidos', ShoppingBag, 'Historial, seguimiento en vivo y constancias'],
    ['loyalty', 'Club DeSego', Award, 'Puntos acumulados, recompensas y cupones'],
    ['reservations', 'Mis reservas', CalendarDays, 'Reserva de mesas en salón Huaycán'],
    ['addresses', 'Mis direcciones', MapPin, 'Ubicaciones guardadas para delivery rápido'],
    ['favorites', 'Favoritos', Heart, 'Tus platos marinos preferidos'],
    ['reviews', 'Mis reseñas', Star, 'Calificaciones y opiniones gastronómicas'],
    ['support', 'Ayuda y soporte', MessagesSquare, 'Atención al cliente, WhatsApp y tickets'],
    ['profile', 'Mi perfil', UserRound, 'Datos personales, RENIEC, seguridad y ajustes'],
];

const MOBILE_PRIMARY_TABS = [
    ['orders', 'Pedidos', ShoppingBag],
    ['loyalty', 'Club', Award],
    ['reservations', 'Reservas', CalendarDays],
];

const ORDER_STEPS_DELIVERY = ['Pendiente', 'Preparando', 'Listo', 'En reparto', 'Entregado'];
const ORDER_STEPS_LOCAL = ['Pendiente', 'Preparando', 'Listo', 'Entregado'];

export default function Account({ account }) {
    const { flash, auth } = usePage().props;
    const [tab, setTab] = useState('orders');
    const [navigationOpen, setNavigationOpen] = useState(false);
    const mobileMenuRef = useRef(null);
    const pillRefs = useRef({});
    const appNavScrollRef = useRef(null);
    const [copiedCode, setCopiedCode] = useState(false);
    const [copiedCoupon, setCopiedCoupon] = useState('');
    const [accountConfirmation, setAccountConfirmation] = useState(null);
    const accountConfirmationDialogRef = useRef(null);
    const currentSection = ACCOUNT_SECTIONS.find(([key]) => key === tab);

    const selectSection = (key) => {
        setTab(key);
        setNavigationOpen(false);
        if (window.matchMedia('(max-width: 768px)').matches) {
            requestAnimationFrame(() => {
                document.getElementById('account-content')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    };

    useEffect(() => {
        const el = pillRefs.current[tab];
        if (el) {
            el.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center',
            });
        }
    }, [tab]);

    useEffect(() => {
        const dialog = mobileMenuRef.current;
        if (!navigationOpen) {
            dialog?.close();
            return;
        }

        dialog?.showModal();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const desktopMedia = window.matchMedia('(min-width: 769px)');
        const closeOnDesktop = () => {
            if (desktopMedia.matches) {
                setNavigationOpen(false);
            }
        };
        desktopMedia.addEventListener('change', closeOnDesktop);

        return () => {
            document.body.style.overflow = previousOverflow;
            desktopMedia.removeEventListener('change', closeOnDesktop);
        };
    }, [navigationOpen]);

    useEffect(() => {
        const dialog = accountConfirmationDialogRef.current;
        if (!dialog) {
            return;
        }

        if (accountConfirmation && !dialog.open) {
            dialog.showModal();
        } else if (!accountConfirmation && dialog.open) {
            dialog.close();
        }
    }, [accountConfirmation]);

    const cancelOrderForm = useForm({});

    const requestAccountConfirmation = (confirmation) => {
        setAccountConfirmation({ type: 'action', tone: 'danger', ...confirmation });
    };

    const closeAccountConfirmation = () => {
        if (!cancelOrderForm.processing) {
            setAccountConfirmation(null);
        }
    };

    const confirmAccountAction = () => {
        if (!accountConfirmation || cancelOrderForm.processing) {
            return;
        }

        if (accountConfirmation.type === 'cancel-order') {
            cancelOrderForm.post(`/cuenta/pedidos/${accountConfirmation.orderId}/cancelar`, {
                preserveScroll: true,
                onSuccess: () => setAccountConfirmation(null),
            });
            return;
        }

        const { onConfirm } = accountConfirmation;
        setAccountConfirmation(null);
        onConfirm();
    };

    // Order Filters
    const [orderStatusFilter, setOrderStatusFilter] = useState('all');
    const [orderTypeFilter, setOrderTypeFilter] = useState('all');
    const [orderSearch, setOrderSearch] = useState('');

    // Modify Pending Order Modal
    const [modifyOrderModal, setModifyOrderModal] = useState(null);
    const modifyOrderForm = useForm({
        notes: '',
        address_line: '',
        district: '',
        reference: '',
        recipient_phone: '',
    });

    // Reschedule Reservation Modal
    const [rescheduleModal, setRescheduleModal] = useState(null);
    const rescheduleForm = useForm({
        date: '',
        time: '13:00',
        people: 2,
        notes: '',
    });

    // Review Modal State
    const [reviewModal, setReviewModal] = useState(null);
    const reviewForm = useForm({
        order_id: '',
        product_id: '',
        rating: 5,
        title: '',
        comment: '',
        photo: null,
    });

    // Support Reply State
    const [replyingTicketId, setReplyingTicketId] = useState(null);
    const replyForm = useForm({
        message: '',
        attachment: null,
    });

    // Profile Form
    const profile = useForm({
        name: account.customer.full_name || '',
        phone: account.customer.phone || '',
        document_type: account.customer.document_type && account.customer.document_type !== 'SIN_DOCUMENTO' ? account.customer.document_type : 'DNI',
        document_number: account.customer.document_number || '',
        birth_date: account.customer.birth_date ? account.customer.birth_date.substring(0, 10) : '',
    });

    const documentWasEdited = useRef(false);
    const profileNameRef = useRef(profile.data.name);
    profileNameRef.current = profile.data.name;
    const [documentLookupRetry, setDocumentLookupRetry] = useState(0);
    const [documentLookup, setDocumentLookup] = useState({
        status: 'idle',
        message: profile.data.document_type === 'RUC'
            ? 'Ingresa los 11 dígitos del RUC para consultar tu razón social.'
            : 'Ingresa los 8 dígitos de tu DNI para autocompletar tus nombres con RENIEC.'
    });
    const [paymentStarting, setPaymentStarting] = useState(null);
    const [paymentError, setPaymentError] = useState(null);

    useEffect(() => {
        if (!documentWasEdited.current && documentLookupRetry === 0) {
            return;
        }
        const docType = profile.data.document_type;
        const number = (profile.data.document_number || '').trim();
        const isDni = docType === 'DNI' && /^\d{8}$/.test(number);
        const isRuc = docType === 'RUC' && /^\d{11}$/.test(number);

        if (!isDni && !isRuc) {
            setDocumentLookup({
                status: 'idle',
                message: docType === 'DNI'
                    ? 'Ingresa los 8 dígitos de tu DNI para consultar tus nombres.'
                    : docType === 'RUC'
                    ? 'Ingresa los 11 dígitos del RUC para consultar tu razón social.'
                    : 'Completa tus datos tal como aparecen en tu documento oficial.'
            });
            return;
        }

        const controller = new AbortController();
        let cancelled = false;
        const nameBeforeLookup = profileNameRef.current;
        setDocumentLookup({
            status: 'loading',
            message: isDni ? 'Consultando datos en RENIEC…' : 'Consultando razón social en SUNAT…'
        });

        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`/consulta-documento?type=${encodeURIComponent(docType)}&number=${encodeURIComponent(number)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal
                });
                const result = await response.json();
                if (cancelled) {
                    return;
                }
                if (!response.ok || !result.success || !result.data) {
                    throw new Error(result.message || 'No pudimos verificar el documento. Ingresa los datos manualmente.');
                }

                const fullName = isDni
                    ? ([result.data.nombres, result.data.apellido_paterno, result.data.apellido_materno].filter(Boolean).join(' ').trim() || result.data.nombre_completo || result.data.nombre || '')
                    : (result.data.nombre_o_razon_social || result.data.nombre || result.data.razon_social || '');

                if (!fullName) {
                    throw new Error('No se encontraron registros para este documento. Puedes ingresar los datos manualmente.');
                }

                const nameWasEdited = profileNameRef.current !== nameBeforeLookup;
                const isGenericName = !profile.data.name || ['Cliente DESEGO', 'Administrador General', 'Administrador DESEGO'].includes(profile.data.name.trim());
                if (!nameWasEdited || isGenericName) {
                    profile.setData('name', fullName);
                }

                setDocumentLookup({
                    status: 'success',
                    message: isDni
                        ? (nameWasEdited && !isGenericName ? 'DNI verificado con RENIEC. Conservamos tu edición manual.' : 'DNI verificado con RENIEC. Nombres autocompletados correctamente.')
                        : (nameWasEdited && !isGenericName ? 'RUC verificado con SUNAT. Conservamos tu edición manual.' : 'RUC verificado con SUNAT. Razón social autocompletada.')
                });
            } catch (error) {
                if (!cancelled && error.name !== 'AbortError') {
                    setDocumentLookup({
                        status: 'error',
                        message: error.message || 'No se pudo verificar el documento. Ingresa los datos manualmente.'
                    });
                }
            }
        }, 350);

        return () => {
            cancelled = true;
            clearTimeout(timer);
            controller.abort();
        };
    }, [profile.data.document_type, profile.data.document_number, documentLookupRetry]);

    const startOnlinePayment = async (orderId) => {
        if (paymentStarting !== null) {
            return;
        }
        setPaymentStarting(orderId);
        setPaymentError(null);
        try {
            const response = await fetch(`/cuenta/pedidos/${orderId}/pagar`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const result = await response.json();
            if (!response.ok || !result.checkout_url) {
                throw new Error(result.message || 'No pudimos iniciar el pago. Inténtalo nuevamente.');
            }
            window.location.assign(result.checkout_url);
        } catch (error) {
            setPaymentError({ orderId, message: error.message });
        } finally {
            setPaymentStarting(null);
        }
    };

    // OTP Verification State
    const [verifyingChannel, setVerifyingChannel] = useState(null);
    const [verificationRequestError, setVerificationRequestError] = useState('');
    const verifyConfirmForm = useForm({
        channel: 'email',
        code: '',
    });

    // Preferences Form
    const preferencesForm = useForm({
        desktop_notifications: account.preferences?.desktop_notifications ?? true,
        sound_notifications: account.preferences?.sound_notifications ?? false,
        order_emails: account.preferences?.order_emails ?? true,
        promo_emails: account.preferences?.promo_emails ?? true,
        preferred_payment: account.preferences?.preferred_payment || 'Efectivo',
    });

    // Password Form
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    // Address Form
    const address = useForm({
        label: 'Casa',
        address_line: '',
        district: 'Ate',
        reference: '',
        phone: account.customer.phone || '',
        latitude: '',
        longitude: '',
        is_default: false,
    });

    // Support Ticket Form
    const ticketForm = useForm({
        subject: '',
        category: 'orders',
        priority: 'normal',
        message: '',
        attachment: null,
    });

    // Deactivate & Delete Account Forms
    const [showDeactivate, setShowDeactivate] = useState(false);
    const deactivateForm = useForm({
        password: '',
    });
    const [showDeleteAccount, setShowDeleteAccount] = useState(false);
    const deleteAccountForm = useForm({
        password: '',
        confirmation: '',
    });

    const isStaff = Boolean(auth?.user && auth.user.role_code && auth.user.role_code !== 'customer');

    const activeOrdersCount = useMemo(
        () => (account.orders || []).filter((o) => ['Pendiente', 'Preparando', 'Listo', 'En reparto', 'En camino'].includes(o.status)).length,
        [account.orders]
    );

    const activeReservationsCount = useMemo(
        () => Number(account.stats?.active_reservations ?? 0),
        [account.stats?.active_reservations]
    );

    const openTicketsCount = useMemo(
        () => (account.tickets || []).filter((t) => ['open', 'in_progress', 'pending'].includes(t.status)).length,
        [account.tickets]
    );

    const getSectionBadge = (key) => {
        switch (key) {
            case 'orders':
                return activeOrdersCount > 0 ? `${activeOrdersCount} en curso` : account.orders?.length ? `${account.orders.length}` : null;
            case 'loyalty':
                return `${account.loyalty?.points_balance ?? 0} pts`;
            case 'reservations':
                return activeReservationsCount > 0 ? `${activeReservationsCount} activa${activeReservationsCount > 1 ? 's' : ''}` : account.reservations?.length ? `${account.reservations.length}` : null;
            case 'addresses':
                return account.addresses?.length ? `${account.addresses.length}` : null;
            case 'favorites':
                return account.favorites?.length ? `${account.favorites.length}` : null;
            case 'reviews':
                return account.reviews?.length ? `${account.reviews.length}` : null;
            case 'support':
                return openTicketsCount > 0 ? `${openTicketsCount} abierto` : null;
            case 'profile':
                return account.customer.email_verified_at && account.customer.phone_verified_at ? '✓ Verificado' : null;
            default:
                return null;
        }
    };

    const hasActiveOrders = useMemo(
        () =>
            (account.orders || []).some((o) =>
                ['Pendiente', 'Preparando', 'Listo', 'En reparto'].includes(o.status)
            ),
        [account.orders]
    );

    useEffect(() => {
        if (!hasActiveOrders) {
            return;
        }
        const timer = setInterval(() => {
            router.reload({ only: ['account'], preserveScroll: true, preserveState: true });
        }, 15000);
        return () => clearInterval(timer);
    }, [hasActiveOrders]);

    const filteredOrders = useMemo(() => {
        const q = orderSearch.trim().toLowerCase();
        return (account.orders || []).filter((o) => {
            if (orderStatusFilter === 'active' && !['Pendiente', 'Preparando', 'Listo', 'En reparto'].includes(o.status)) {
                return false;
            }
            if (orderStatusFilter === 'delivered' && o.status !== 'Entregado') {
                return false;
            }
            if (orderStatusFilter === 'cancelled' && o.status !== 'Cancelado') {
                return false;
            }
            if (orderTypeFilter !== 'all' && o.order_type !== orderTypeFilter) {
                return false;
            }
            if (!q) {
                return true;
            }
            const itemsText = (o.items || []).map((i) => i.product_name).join(' ');
            return `${o.order_number} ${itemsText} ${o.status}`.toLowerCase().includes(q);
        });
    }, [account.orders, orderStatusFilter, orderTypeFilter, orderSearch]);

    const copyCustomerCode = () => {
        if (!account.customer.customer_code) {
            return;
        }
        navigator.clipboard?.writeText(account.customer.customer_code);
        setCopiedCode(true);
        setTimeout(() => setCopiedCode(false), 2200);
    };

    const reorder = (order) => {
        localStorage.setItem(
            'desego.cart.v1',
            JSON.stringify(
                order.items.map((item) => ({
                    id: item.product_id,
                    quantity: Math.min(20, item.quantity),
                    notes: item.notes || '',
                }))
            )
        );
        localStorage.setItem('desego.open_cart', '1');
        router.visit('/#carta');
    };

    const quickOrderProduct = (productId) => {
        localStorage.setItem('desego.reorder_product', String(productId));
        router.visit('/#carta');
    };

    const openModifyOrder = (order) => {
        const rawAddr = order.delivery?.address_line || '';
        const parts = rawAddr.split(' · ');
        modifyOrderForm.setData({
            notes: order.notes || '',
            address_line: parts[0] || '',
            district: (order.delivery?.zone || 'Ate').split(',')[0].trim(),
            reference: parts[1] || '',
            recipient_phone: order.delivery?.recipient_phone || account.customer.phone || '',
        });
        setModifyOrderModal(order);
    };

    const submitModifyOrder = (e) => {
        e.preventDefault();
        if (!modifyOrderModal) {
            return;
        }
        modifyOrderForm.post(`/cuenta/pedidos/${modifyOrderModal.id}/modificar`, {
            preserveScroll: true,
            onSuccess: () => setModifyOrderModal(null),
        });
    };

    const openReschedule = (resItem) => {
        const dt = new Date(resItem.reservation_at.replace(' ', 'T') + (resItem.reservation_at.endsWith('Z') ? '' : 'Z'));
        const dateStr = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'America/Lima',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(dt);
        const timeStr = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'America/Lima',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(dt);

        rescheduleForm.setData({
            date: dateStr,
            time: timeStr,
            people: resItem.guest_count || 2,
            notes: resItem.notes || '',
        });
        setRescheduleModal(resItem);
    };

    const submitReschedule = (e) => {
        e.preventDefault();
        if (!rescheduleModal) {
            return;
        }
        rescheduleForm.post(`/cuenta/reservas/${rescheduleModal.id}/reprogramar`, {
            preserveScroll: true,
            onSuccess: () => setRescheduleModal(null),
        });
    };

    const openReview = (orderId, productId, productName) => {
        reviewForm.setData({
            order_id: orderId,
            product_id: productId,
            rating: 5,
            title: `Excelente ${productName}`,
            comment: '',
            photo: null,
        });
        setReviewModal({ orderId, productId, productName });
    };

    const submitReview = (e) => {
        e.preventDefault();
        reviewForm.post('/cuenta/resenas', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setReviewModal(null),
        });
    };

    const toggleFavorite = (productId) => {
        router.post(`/cuenta/favoritos/${productId}/toggle`, {}, { preserveScroll: true });
    };

    const requestOtp = (channel) => {
        setVerifyingChannel(channel);
        setVerificationRequestError('');
        verifyConfirmForm.setData({ channel, code: '' });
        router.post(
            '/cuenta/verificar/enviar',
            { channel },
            {
                preserveScroll: true,
                onError: (errors) => setVerificationRequestError(errors.channel || 'No se pudo enviar el código. Inténtalo nuevamente.'),
            }
        );
    };

    const confirmOtp = (e) => {
        e.preventDefault();
        verifyConfirmForm.post('/cuenta/verificar/confirmar', {
            preserveScroll: true,
            onSuccess: () => {
                setVerifyingChannel(null);
                verifyConfirmForm.reset('code');
            },
        });
    };

    return (
        <main className="dg-account">
            <Head title="Mi Cuenta · DeSego Cevichería" />

            {/* TOP NAVIGATION HEADER */}
            <header className="dg-account-header">
                <div className="dg-container dg-account-header-inner">
                    <div className="dg-account-header-brand">
                        <Link href="/" className="dg-account-logo" aria-label="DeSego, inicio">
                            <img src="/images/logodesego-1.png" alt="DeSego Cevichería" width={800} height={300} />
                        </Link>
                    </div>
                    <div className="dg-account-header-actions">
                        {isStaff && (
                            <Link href="/admin" className="dg-btn-outline dg-account-admin-link" aria-label="Panel de control">
                                <span>Panel de control</span> <ArrowUpRight size={14} />
                            </Link>
                        )}
                        <Link href="/#carta" className="dg-button">
                            <UtensilsCrossed size={15} /> Ver la carta
                        </Link>
                    </div>
                </div>
            </header>

            <div className="dg-container">
                {/* STAFF ACCESS NOTICE */}
                {isStaff && (
                    <aside className="dg-staff-banner">
                        <span><ShieldCheck size={15} /> Sesión de gestión · <strong>{auth.user.role_name || auth.user.role_code}</strong></span>
                        <Link href="/admin">
                            Ir a administración <ArrowUpRight size={14} />
                        </Link>
                    </aside>
                )}

                <div className="dg-account-breadcrumb">
                    <Link href="/">Inicio</Link>
                    <ChevronRight size={12} />
                    <span>Mi cuenta</span>
                </div>

                <div className="dg-account-layout">
                    <aside className="dg-account-sidebar">
                        <div className="dg-account-sidebar-person">
                            <div className="dg-user-avatar-circle">{(account.customer.full_name || 'C').charAt(0).toUpperCase()}</div>
                            <strong>{account.customer.full_name || 'Cliente DeSego'}</strong>
                            <span>{account.customer.email}</span>
                            <span className="dg-account-member">
                                <Award size={12} /> Club DeSego · {account.loyalty?.tier || 'Bronce'}
                            </span>
                        </div>
                        <nav id="account-navigation" className="dg-account-tabs" aria-label="Secciones de mi cuenta">
                            {ACCOUNT_SECTIONS.map(([key, title, Icon]) => {
                                const badge = getSectionBadge(key);
                                return (
                                    <button className={tab === key ? 'active' : ''} key={key} onClick={() => selectSection(key)} type="button" aria-current={tab === key ? 'page' : undefined}>
                                        <Icon size={18} />
                                        <span>{title}</span>
                                        {badge && <span className="dg-sidebar-badge">{badge}</span>}
                                        <ChevronRight size={14} />
                                    </button>
                                );
                            })}
                        </nav>
                        <button type="button" className="dg-account-club-preview" onClick={() => selectSection('loyalty')}>
                            <span className="dg-account-club-icon"><Award size={18} /></span>
                            <div>
                                <small>PUNTOS CLUB</small>
                                <strong>{account.loyalty?.points_balance ?? 0} <span style={{ fontSize: '11px', fontWeight: 500, color: '#64748b' }}>pts disponibles</span></strong>
                            </div>
                        </button>
                        <div className="dg-account-sidebar-bottom">
                            <button type="button" onClick={() => router.post('/salir')}><LogOut size={16} /> Cerrar sesión</button>
                        </div>
                    </aside>

                    <div className={`dg-account-main dg-account-view-${tab}`}>
                        <section className="dg-account-hero">
                            <div className="dg-account-hero-content">
                                <div className="dg-account-hero-left">
                                    <div className="dg-account-hero-avatar-wrap">
                                        <div className="dg-user-avatar-circle">
                                            {(account.customer.full_name || 'C').charAt(0).toUpperCase()}
                                        </div>
                                    </div>
                                    <div className="dg-account-hero-info">
                                        <div className="dg-account-hero-badges">
                                            <button
                                                type="button"
                                                className="dg-code-pill"
                                                onClick={copyCustomerCode}
                                                title="Copiar código de cliente"
                                            >
                                                <Copy size={11} />
                                                <span>{account.customer.customer_code || 'Mi código'}</span>
                                                {copiedCode && <span role="status">¡Copiado!</span>}
                                            </button>
                                            <span className="dg-account-member" style={{ margin: 0 }}>
                                                <Award size={12} /> Nivel {account.loyalty?.tier || 'Bronce'}
                                            </span>
                                            <button
                                                type="button"
                                                className="dg-account-hero-points-badge"
                                                onClick={() => selectSection('loyalty')}
                                                title="Ver Club DeSego y Puntos"
                                            >
                                                <Sparkles size={11} /> {account.loyalty?.points_balance ?? 0} pts
                                            </button>
                                        </div>
                                        <h1 className="dg-account-hero-title">
                                            Hola, {(account.customer.full_name || 'Cliente').split(' ')[0]}
                                        </h1>
                                        <p className="dg-account-hero-subtitle">
                                            {currentSection ? (currentSection[3] || currentSection[1]) : 'Tu espacio gastronómico en DeSego'}
                                        </p>
                                    </div>
                                </div>

                                <div className="dg-hero-actions">
                                    <Link href="/#reservas" className="dg-btn-outline">
                                        <CalendarDays size={15} /> Reservar mesa
                                    </Link>
                                </div>
                            </div>
                        </section>

                        {/* TOP APP HORIZONTAL NAVIGATION (MOBILE & RESPONSIVE - ALL 8 SECTIONS DIRECTLY ACCESSIBLE) */}
                        <nav className="dg-account-app-nav" aria-label="Navegación principal de cuenta">
                            <div className="dg-account-app-nav-scroll" ref={appNavScrollRef}>
                                {ACCOUNT_SECTIONS.map(([key, title, Icon]) => {
                                    const isActive = tab === key;
                                    const badge = getSectionBadge(key);
                                    return (
                                        <button
                                            key={key}
                                            type="button"
                                            ref={(el) => (pillRefs.current[key] = el)}
                                            className={`dg-account-app-pill ${isActive ? 'is-active' : ''}`}
                                            onClick={() => selectSection(key)}
                                            aria-current={isActive ? 'page' : undefined}
                                        >
                                            <Icon size={16} />
                                            <span>{title}</span>
                                            {badge && <span className="dg-app-pill-badge">{badge}</span>}
                                        </button>
                                    );
                                })}
                            </div>
                        </nav>

                        {/* 4 STAT METRIC CARDS */}
                        {tab === 'orders' && (
                            <section className="dg-account-stats">
                                <button type="button" className="dg-stat-card" onClick={() => selectSection('orders')}>
                                    <div className="dg-stat-icon-wrap dg-stat-icon-blue">
                                        <ShoppingBag size={20} />
                                    </div>
                                    <div className="dg-stat-card-body">
                                        <span>Consumo total</span>
                                        <strong>{money(account.stats?.total_spent ?? 0)}</strong>
                                        <small>{account.stats?.orders_count ?? 0} {account.stats?.orders_count === 1 ? 'pedido' : 'pedidos'}</small>
                                    </div>
                                </button>

                                <button type="button" className="dg-stat-card" onClick={() => selectSection('loyalty')}>
                                    <div className="dg-stat-icon-wrap dg-stat-icon-gold">
                                        <Award size={20} />
                                    </div>
                                    <div className="dg-stat-card-body">
                                        <span>Club DeSego</span>
                                        <strong>{account.loyalty?.points_balance ?? 0} pts</strong>
                                        <small>Nivel {account.loyalty?.tier ?? 'Bronce'}</small>
                                    </div>
                                </button>

                                <button type="button" className="dg-stat-card" onClick={() => selectSection('reservations')}>
                                    <div className="dg-stat-icon-wrap dg-stat-icon-green">
                                        <CalendarDays size={20} />
                                    </div>
                                    <div className="dg-stat-card-body">
                                        <span>Reservas</span>
                                        <strong>{account.stats?.active_reservations ?? 0}</strong>
                                        <small>{account.stats?.active_reservations === 1 ? 'reserva activa' : 'reservas activas'}</small>
                                    </div>
                                </button>

                                <button type="button" className="dg-stat-card" onClick={() => selectSection('favorites')}>
                                    <div className="dg-stat-icon-wrap dg-stat-icon-rose">
                                        <Heart size={20} />
                                    </div>
                                    <div className="dg-stat-card-body">
                                        <span>Favoritos</span>
                                        <strong>{account.stats?.favorites_count ?? 0}</strong>
                                        <small>Platos guardados</small>
                                    </div>
                                </button>
                            </section>
                        )}

                        <div id="account-content" />

                        {/* FLASH FEEDBACK MESSAGE */}
                        {flash?.success && (
                            <div className="dg-confirmation" role="status">
                                <Check size={18} />
                                <span>{flash.success}</span>
                            </div>
                        )}

                        {/* ============================================================ */}
                        {/* TAB 1: MIS PEDIDOS, PLATOS FRECUENTES & CONSTANCIAS          */}
                        {/* ============================================================ */}
                        {tab === 'orders' && (
                            <section className="dg-account-section">
                                {/* PLATOS FRECUENTES */}
                                {account.frequent_products?.length > 0 && (
                                    <div className="dg-frequent-box">
                                        <div className="dg-frequent-head">
                                            <h3>Tus platos frecuentes</h3>
                                            <Link href="/#carta">Ver carta completa →</Link>
                                        </div>
                                        <div className="dg-frequent-grid">
                                            {account.frequent_products.map((fp) => (
                                                <div key={fp.id} className="dg-frequent-item">
                                                    <img src={foodImage(fp)} alt={fp.name} />
                                                    <div className="dg-frequent-info">
                                                        <span className="dg-frequent-title">{fp.name}</span>
                                                        <span className="dg-frequent-meta">
                                                            {money(fp.price)} · {fp.total_quantity} {fp.total_quantity === 1 ? 'pedido' : 'pedidos'}
                                                        </span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        disabled={!fp.can_order}
                                                        onClick={() => quickOrderProduct(fp.id)}
                                                        className="dg-button"
                                                    >
                                                        Pedir
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="dg-account-section-head">
                                    <h2>Mis pedidos</h2>
                                    <button className="dg-btn-outline" onClick={() => router.reload({ only: ['account'] })}>
                                        <RefreshCw size={14} /> Actualizar
                                    </button>
                                </div>

                                {/* FILTROS DE PEDIDOS */}
                                {account.orders.length > 0 && (
                                    <div className="dg-orders-filter-bar">
                                        <div className="dg-account-order-search">
                                            <Search size={15} />
                                            <input
                                                type="search"
                                                aria-label="Buscar pedidos por código o plato"
                                                placeholder="Buscar por código de orden o plato…"
                                                value={orderSearch}
                                                onChange={(e) => setOrderSearch(e.target.value)}
                                            />
                                        </div>
                                        <select
                                            aria-label="Filtrar pedidos por estado"
                                            value={orderStatusFilter}
                                            onChange={(e) => setOrderStatusFilter(e.target.value)}
                                        >
                                            <option value="all">Todos los estados</option>
                                            <option value="active">En curso (Activos)</option>
                                            <option value="delivered">Entregados</option>
                                            <option value="cancelled">Cancelados</option>
                                        </select>
                                        <select
                                            aria-label="Filtrar pedidos por modalidad"
                                            value={orderTypeFilter}
                                            onChange={(e) => setOrderTypeFilter(e.target.value)}
                                        >
                                            <option value="all">Todas las modalidades</option>
                                            <option value="Delivery">Delivery</option>
                                            <option value="Para llevar">Para llevar</option>
                                            <option value="Mesa">Salón / Mesa</option>
                                        </select>
                                    </div>
                                )}

                                {filteredOrders.length ? (
                                    filteredOrders.map((order) => {
                                        const statusClass =
                                            order.status === 'Entregado'
                                                ? 'status-entregado'
                                                : order.status === 'Cancelado'
                                                  ? 'status-cancelado'
                                                  : ['En reparto', 'En camino', 'Listo'].includes(order.status)
                                                    ? 'status-camino'
                                                    : 'status-pendiente';

                                        const steps = order.order_type === 'Delivery' ? ORDER_STEPS_DELIVERY : ORDER_STEPS_LOCAL;
                                        const currentStepIdx = steps.indexOf(order.status === 'En camino' ? 'En reparto' : order.status);

                                        return (
                                            <article className="dg-order-card" key={order.id}>
                                                <header>
                                                    <div>
                                                        <div className="dg-order-number-title">
                                                            ORDEN #{order.order_number}
                                                        </div>
                                                        <div className="dg-order-date">
                                                            {new Date(order.created_at.replace(' ', 'T') + 'Z').toLocaleString('es-PE', {
                                                                timeZone: 'America/Lima',
                                                                dateStyle: 'medium',
                                                                timeStyle: 'short',
                                                            })}
                                                        </div>
                                                    </div>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '14px', flexWrap: 'wrap' }}>
                                                        <span className={`dg-status-pill ${statusClass}`}>
                                                            {order.status}
                                                        </span>
                                                        <strong style={{ fontSize: '19px', fontWeight: 700, color: '#004797' }}>
                                                            {money(order.total)}
                                                        </strong>
                                                    </div>
                                                </header>

                                                {/* BARRA DE PROGRESO EN VIVO */}
                                                {order.status !== 'Cancelado' && currentStepIdx >= 0 && (
                                                    <div className="dg-order-progress-box">
                                                        <div className="dg-order-progress" style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}>
                                                            {steps.map((st, idx) => {
                                                                const done = idx <= currentStepIdx;
                                                                return (
                                                                    <div key={st} className={`dg-order-progress-step ${done ? 'is-done' : ''}`}>
                                                                        <div className="dg-order-progress-bar" />
                                                                        <span className="dg-order-progress-label">
                                                                            {st}
                                                                        </span>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                )}

                                        {/* ITEMS LIST */}
                                        <div className="dg-order-items-table">
                                            {order.items.map((item) => {
                                                const isReviewed = order.reviewed_product_ids?.includes(item.product_id);
                                                return (
                                                    <div className="dg-order-item-row" key={item.id}>
                                                        <div>
                                                            <div className="dg-order-item-name">
                                                                {item.quantity} × {item.product_name}
                                                            </div>
                                                            {item.selected_options?.length > 0 && (
                                                                <div style={{ fontSize: '11px', color: '#004797', marginTop: '2px' }}>
                                                                    + {item.selected_options.map((o) => o.name).join(' · ')}
                                                                </div>
                                                            )}
                                                            {item.notes && (
                                                                <div className="dg-order-item-note">
                                                                    Instrucción especial: {item.notes}
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
                                                            <span style={{ fontWeight: 600, color: '#1e293b' }}>
                                                                {money(item.line_total)}
                                                            </span>
                                                            {['Entregado', 'Listo'].includes(order.status) &&
                                                                (isReviewed ? (
                                                                    <span style={{ color: '#15803d', display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', fontWeight: 600 }}>
                                                                        <Check size={13} /> Calificado
                                                                    </span>
                                                                ) : (
                                                                    <button
                                                                        type="button"
                                                                        className="dg-btn-outline"
                                                                        style={{ fontSize: '11px', padding: '5px 10px', borderRadius: '8px' }}
                                                                        onClick={() => openReview(order.id, item.product_id, item.product_name)}
                                                                    >
                                                                        <Star size={13} /> Calificar plato
                                                                    </button>
                                                                ))}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {/* INVOICE RECEIPT WHEN ISSUED */}
                                        {order.invoice && (
                                            <div className="dg-invoice-box">
                                                <header>
                                                    <strong>
                                                        <Receipt size={16} />
                                                        Comprobante: {order.invoice.document_type?.toUpperCase()} {order.invoice.document_number}
                                                    </strong>
                                                    <span style={{ color: '#004797', fontWeight: 700, fontSize: '11px' }}>
                                                        <Check size={13} style={{ display: 'inline', marginRight: '3px' }} /> REGISTRADO
                                                    </span>
                                                </header>
                                                <div className="dg-invoice-meta">
                                                    <div>
                                                        <strong>Adquirente:</strong> {order.invoice.buyer_name} ({order.invoice.buyer_document_type}: {order.invoice.buyer_document})
                                                    </div>
                                                    <div>
                                                        <strong>Total facturado:</strong> {money(order.invoice.total_amount ?? order.total)}
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {/* DELIVERY DETAILS */}
                                        {order.delivery && (
                                            <div style={{ fontSize: '12px', color: '#64748b', background: '#f8fafc', padding: '10px 16px', borderRadius: '10px', display: 'flex', alignItems: 'center', gap: '8px', margin: '8px 0' }}>
                                                <Truck size={16} style={{ color: '#004797' }} />
                                                <span>
                                                    Entrega en: <strong>{order.delivery.address_line}</strong>
                                                    {order.delivery.zone ? ` (${order.delivery.zone})` : ''}
                                                    {order.delivery.delivery_status ? ` · Estado reparto: ${order.delivery.delivery_status}` : ''}
                                                </span>
                                            </div>
                                        )}

                                        <footer>
                                            <small style={{ color: '#64748b', fontSize: '12px' }}>
                                                Modalidad: <strong>{order.order_type}</strong> · Pago: <strong>{order.payment_status}</strong> ({order.payment_preference || order.payment_method || 'Efectivo'})
                                            </small>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                                                {account.niubiz_available && !order.dining_session_id && order.payment_status === 'Pendiente' && order.status !== 'Cancelado' && (
                                                    <button type="button" className="dg-button" disabled={paymentStarting !== null} onClick={() => startOnlinePayment(order.id)}>
                                                        <ShieldCheck size={15} /> {paymentStarting === order.id ? 'Conectando…' : order.online_payment_pending ? 'Continuar pago Niubiz' : 'Pagar con Niubiz'}
                                                    </button>
                                                )}
                                                <a
                                                    href={`/cuenta/pedidos/${order.id}/comprobante`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="dg-btn-outline"
                                                    style={{ fontSize: '12px', display: 'inline-flex', alignItems: 'center', gap: '5px', textDecoration: 'none' }}
                                                >
                                                    <FileText size={14} /> Constancia / PDF
                                                </a>

                                                {order.status === 'Pendiente' && (
                                                    <button
                                                        type="button"
                                                        className="dg-btn-outline"
                                                        onClick={() => openModifyOrder(order)}
                                                    >
                                                        <Pencil size={13} /> Modificar datos
                                                    </button>
                                                )}

                                                <button className="dg-button" style={{ background: '#004797' }} onClick={() => reorder(order)}>
                                                    Volver a Pedir <ArrowRight size={14} />
                                                </button>

                                                {order.status === 'Pendiente' && order.payment_status === 'Pendiente' && (
                                                    <button
                                                        type="button"
                                                        className="dg-btn-outline"
                                                        style={{ color: '#b91c1c', borderColor: '#fca5a5' }}
                                                        onClick={() => {
                                                            cancelOrderForm.clearErrors();
                                                            setAccountConfirmation({
                                                                type: 'cancel-order',
                                                                title: 'Cancelar pedido',
                                                                message: `¿Confirmas la cancelación del pedido #${order.order_number}? Esta acción no se puede deshacer.`,
                                                                confirmLabel: 'Sí, cancelar pedido',
                                                                orderId: order.id,
                                                            });
                                                        }}
                                                    >
                                                        Cancelar pedido
                                                    </button>
                                                )}
                                            </div>
                                        </footer>
                                        {paymentError?.orderId === order.id && <div className="dg-error" role="alert">{paymentError.message}</div>}
                                    </article>
                                );
                            })
                        ) : (
                            <div className="dg-empty">
                                <ShoppingBag size={48} strokeWidth={1.5} style={{ color: '#004797' }} />
                                <h3>{account.orders.length ? 'No hay pedidos que coincidan con este filtro.' : 'Tu primer antojo marino te espera.'}</h3>
                                <p style={{ fontSize: '13px', maxWidth: '400px', margin: '8px auto 20px' }}>
                                    Explora nuestra carta oficial de pescados, mariscos, arroces y rondas marinas preparadas al momento.
                                </p>
                                <Link href="/#carta" className="dg-button" style={{ background: '#004797' }}>
                                    Descubrir la Carta <ArrowUpRight size={16} />
                                </Link>
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 2: CLUB DESEGO & PUNTOS                                  */}
                {/* ============================================================ */}
                {tab === 'loyalty' && (
                    <section className="dg-account-section">
                        <div className="dg-tier-card">
                            <div className="dg-tier-card-head">
                                <div>
                                    <span className="dg-eyebrow" style={{ color: '#93c5fd' }}>
                                        CLUB DESEGO
                                    </span>
                                    <h3 style={{ fontSize: '26px', margin: '4px 0 0', color: '#ffffff', fontWeight: 700 }}>
                                        Programa de Beneficios
                                    </h3>
                                </div>
                                <span className={`dg-tier-badge tier-${(account.loyalty?.tier || 'bronce').toLowerCase()}`}>
                                    <Sparkles size={14} /> Nivel {account.loyalty?.tier || 'Bronce'}
                                </span>
                            </div>

                            <div className="dg-tier-card-stats">
                                <div className="dg-tier-card-points">
                                    {account.loyalty?.points_balance ?? 0}
                                    <span>puntos disponibles</span>
                                </div>
                                <div style={{ color: '#cbd5e1', fontSize: '13px' }}>
                                    · Total acumulado: <strong>{account.loyalty?.lifetime_earned ?? 0} pts</strong>
                                </div>
                            </div>

                            {account.loyalty?.next_tier && (
                                <>
                                    <div className="dg-tier-progress-track">
                                        <div
                                            className="dg-tier-progress-fill"
                                            style={{
                                                width: `${Math.min(
                                                    100,
                                                    Math.max(
                                                        6,
                                                        Math.round(
                                                            (((account.loyalty.lifetime_earned || 0) %
                                                                (account.loyalty.next_tier === 'Oro' ? 300 : 100)) /
                                                                (account.loyalty.next_tier === 'Oro' ? 300 : 100)) *
                                                                100
                                                        )
                                                    )
                                                )}%`,
                                            }}
                                        />
                                    </div>
                                    <small style={{ color: '#cbd5e1', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', marginTop: '10px' }}>
                                        <Sparkles size={13} style={{ color: '#fde047' }} />
                                        Te faltan <strong>{account.loyalty.points_to_next_tier} puntos</strong> para alcanzar la categoría <strong>Nivel {account.loyalty.next_tier}</strong>.
                                    </small>
                                </>
                            )}
                        </div>

                        {/* REWARDS CATALOG */}
                        <div>
                            <div className="dg-account-section-head">
                                <h2>Beneficios disponibles</h2>
                            </div>

                            {account.loyalty?.rewards?.length ? (
                                <div className="dg-rewards-grid">
                                    {account.loyalty.rewards.map((reward) => {
                                        const canRedeem = (account.loyalty?.points_balance ?? 0) >= reward.points_cost;
                                        return (
                                            <article className="dg-reward-card" key={reward.id}>
                                                <div>
                                                    <span className="dg-eyebrow" style={{ color: '#004797' }}>
                                                        {reward.reward_type === 'discount' ? 'Descuento en Cuenta' : 'Plato de Cortesía'}
                                                    </span>
                                                    <h4>{reward.name}</h4>
                                                    <p>{reward.description || 'Válido para pedidos en la tienda web o consumo en salón.'}</p>
                                                </div>
                                                <div>
                                                    <div className="dg-reward-card-cost">
                                                        <Award size={16} /> Costo: {reward.points_cost} puntos
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="dg-button"
                                                        disabled={!canRedeem}
                                                        style={{ width: '100%', fontSize: '12px', background: '#004797' }}
                                                        onClick={() => {
                                                            requestAccountConfirmation({
                                                                title: 'Canjear beneficio',
                                                                message: `¿Confirmas el canje de "${reward.name}" por ${reward.points_cost} puntos?`,
                                                                confirmLabel: 'Sí, canjear',
                                                                tone: 'primary',
                                                                onConfirm: () => router.post('/cuenta/fidelizacion/canjear', {
                                                                    reward_id: reward.id,
                                                                }, { preserveScroll: true }),
                                                            });
                                                        }}
                                                    >
                                                        {canRedeem ? 'Canjear beneficio' : `Faltan ${reward.points_cost - (account.loyalty?.points_balance ?? 0)} pts`}
                                                    </button>
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p style={{ fontSize: '13px', color: '#64748b' }}>Pronto agregaremos nuevas recompensas canjeables.</p>
                            )}
                        </div>

                        {/* ACTIVE REDEEMED VOUCHERS */}
                        {account.loyalty?.redemptions?.length > 0 && (
                            <div style={{ marginTop: '36px' }}>
                                <div className="dg-account-section-head">
                                    <h2>Tus cupones activos</h2>
                                </div>

                                <div>
                                    {account.loyalty.redemptions.map((item) => (
                                        <div className="dg-redemption-card" key={item.id}>
                                            <div>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                                                    <span className="dg-redemption-code">{item.redemption_code}</span>
                                                    <button
                                                        type="button"
                                                        className="dg-btn-outline"
                                                        style={{ padding: '4px 10px', fontSize: '11px' }}
                                                        onClick={() => {
                                                            navigator.clipboard?.writeText(item.redemption_code);
                                                            setCopiedCoupon(item.redemption_code);
                                                            setTimeout(() => setCopiedCoupon(''), 2000);
                                                        }}
                                                    >
                                                        <Copy size={12} /> {copiedCoupon === item.redemption_code ? '¡Copiado!' : 'Copiar'}
                                                    </button>
                                                </div>
                                                <div style={{ margin: '8px 0 2px', fontSize: '14px', fontWeight: 700, color: '#0f172a' }}>
                                                    {item.reward_name}
                                                </div>
                                                <small style={{ color: '#64748b' }}>
                                                    Emitido: {new Date(item.issued_at.replace(' ', 'T') + 'Z').toLocaleString('es-PE', {
                                                        timeZone: 'America/Lima',
                                                        dateStyle: 'medium',
                                                    })}
                                                </small>
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                                                {item.status === 'issued' && (
                                                    <button
                                                        type="button"
                                                        className="dg-button"
                                                        style={{ background: '#004797', fontSize: '12px', padding: '8px 14px' }}
                                                        onClick={() => {
                                                            localStorage.setItem('desego.pending_coupon', item.redemption_code);
                                                            localStorage.setItem('desego.open_cart', '1');
                                                            router.visit('/#carta');
                                                        }}
                                                    >
                                                        Aplicar en Tienda <ArrowRight size={13} />
                                                    </button>
                                                )}
                                                <span className={`dg-status-pill ${item.status === 'issued' ? 'status-entregado' : 'status-pendiente'}`}>
                                                    {item.status === 'issued' ? 'Disponible para Uso' : 'Utilizado'}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* TRANSACTIONS LEDGER */}
                        {account.loyalty?.transactions?.length > 0 && (
                            <div style={{ marginTop: '36px' }}>
                                <div className="dg-account-section-head">
                                    <h2>Historial de puntos</h2>
                                </div>

                                <div className="dg-table-wrap">
                                    <table className="dg-table">
                                        <thead>
                                            <tr>
                                                <th>Fecha y Hora</th>
                                                <th>Detalle de la Operación</th>
                                                <th>Variación</th>
                                                <th>Saldo Resultante</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {account.loyalty.transactions.map((tx) => (
                                                <tr key={tx.id}>
                                                    <td>
                                                        {new Date(tx.created_at.replace(' ', 'T') + 'Z').toLocaleString('es-PE', {
                                                            timeZone: 'America/Lima',
                                                            dateStyle: 'short',
                                                            timeStyle: 'short',
                                                        })}
                                                    </td>
                                                    <td style={{ fontWeight: 600, color: '#1e293b' }}>{tx.description}</td>
                                                    <td style={{ fontWeight: 700, color: tx.points_delta > 0 ? '#004797' : '#b91c1c' }}>
                                                        {tx.points_delta > 0 ? `+${tx.points_delta}` : tx.points_delta} pts
                                                    </td>
                                                    <td style={{ fontWeight: 600 }}>{tx.balance_after} pts</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 3: RESERVAS DE MESA                                      */}
                {/* ============================================================ */}
                {tab === 'reservations' && (
                    <section className="dg-account-section">
                        <div className="dg-account-section-head">
                            <h2>Mis reservas</h2>
                            <Link href="/#reservas" className="dg-button" style={{ background: '#004797' }}>
                                <Plus size={15} /> Nueva reserva
                            </Link>
                        </div>

                        {account.reservations.length ? (
                            account.reservations.map((item) => {
                                const canManageRes = ['Pendiente', 'Confirmada', 'Solicitada', 'CONFIRMED', 'PENDING_PAYMENT'].includes(item.status);
                                const canReschedule = ['Pendiente', 'Confirmada', 'Solicitada', 'CONFIRMED'].includes(item.status);
                                const statusLabel =
                                    item.status === 'CONFIRMED'
                                        ? 'Confirmada'
                                        : item.status === 'PENDING_PAYMENT'
                                          ? 'Pendiente de pago'
                                          : item.status === 'CANCELLED'
                                            ? 'Cancelada'
                                            : item.status;

                                return (
                                    <article className="dg-reservation-card" key={item.id}>
                                        <div className="dg-reservation-date" aria-hidden="true">
                                            <span>{new Date(item.reservation_at.replace(' ', 'T') + (item.reservation_at.endsWith('Z') ? '' : 'Z')).toLocaleDateString('es-PE', { timeZone: 'America/Lima', month: 'short' }).replace('.', '')}</span>
                                            <strong>{new Date(item.reservation_at.replace(' ', 'T') + (item.reservation_at.endsWith('Z') ? '' : 'Z')).toLocaleDateString('es-PE', { timeZone: 'America/Lima', day: '2-digit' })}</strong>
                                            <CalendarDays size={16} />
                                        </div>
                                        <div className="dg-reservation-body">
                                            <div className="dg-reservation-card-top">
                                                <span className="dg-reservation-code">{item.reservation_code}</span>
                                                <span className={`dg-status-pill ${item.status === 'Cancelada' || item.status === 'CANCELLED' ? 'status-cancelado' : 'status-camino'}`}>
                                                    {statusLabel}
                                                </span>
                                            </div>
                                            <h3 className="dg-reservation-title">Tu mesa en DeSego</h3>
                                            <p className="dg-reservation-time">
                                                {new Date(item.reservation_at.replace(' ', 'T') + (item.reservation_at.endsWith('Z') ? '' : 'Z')).toLocaleString('es-PE', {
                                                    timeZone: 'America/Lima',
                                                    dateStyle: 'full',
                                                    timeStyle: 'short',
                                                })}
                                            </p>
                                            <div className="dg-reservation-details">
                                                <span><UserRound size={15} /> {item.guest_count} personas</span>
                                                <span><MapPin size={15} /> {item.tables?.map((table) => `${table.label}${table.zone ? ` · ${table.zone}` : ''}`).join(' + ') || 'Mesa por asignar'}</span>
                                            </div>
                                            {item.notes && (
                                                <p className="dg-reservation-note"><MessagesSquare size={14} /> {item.notes}</p>
                                            )}
                                        </div>
                                        {canManageRes && (
                                            <div className="dg-reservation-actions">
                                                {canReschedule && (
                                                    <button
                                                        type="button"
                                                        className="dg-btn-outline"
                                                        onClick={() => openReschedule(item)}
                                                    >
                                                        <Pencil size={13} /> Reprogramar
                                                    </button>
                                                )}
                                                <button
                                                    type="button"
                                                    className="dg-reservation-cancel"
                                                    onClick={() => requestAccountConfirmation({
                                                        title: 'Cancelar reserva',
                                                        message: `¿Deseas cancelar la reserva ${item.reservation_code}?`,
                                                        confirmLabel: 'Sí, cancelar reserva',
                                                        onConfirm: () => router.post(`/cuenta/reservas/${item.id}/cancelar`, {}, { preserveScroll: true }),
                                                    })}
                                                >
                                                    Cancelar reserva
                                                </button>
                                            </div>
                                        )}
                                    </article>
                                );
                            })
                        ) : (
                            <div className="dg-empty">
                                <CalendarDays size={44} strokeWidth={1.5} style={{ color: '#004797' }} />
                                <h3>Planifica tu próxima visita a DeSego</h3>
                                <p style={{ fontSize: '13px', maxWidth: '380px', margin: '6px auto 18px' }}>
                                    Asegura tu mesa en nuestro salón de Huaycán con confirmación inmediata.
                                </p>
                                <Link href="/#reservas" className="dg-button" style={{ background: '#004797' }}>
                                    Reservar mesa <ArrowUpRight size={15} />
                                </Link>
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 4: PLATOS FAVORITOS                                      */}
                {/* ============================================================ */}
                {tab === 'favorites' && (
                    <section className="dg-account-section">
                        <div className="dg-account-section-head">
                            <h2>Mis favoritos</h2>
                            <Link href="/#carta" className="dg-btn-outline">
                                Explorar carta <ArrowRight size={14} />
                            </Link>
                        </div>

                        {account.favorites?.length ? (
                            <div className="dg-favorites-grid">
                                {account.favorites.map((product) => (
                                    <article className="dg-favorite-card" key={product.id}>
                                        <div style={{ height: '170px', background: '#f0f6ff', display: 'flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' }}>
                                            <img src={foodImage(product)} alt={product.name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                        </div>
                                        <div className="dg-favorite-card-body">
                                            <h4 style={{ margin: '0 0 6px', fontFamily: 'inherit', fontSize: '18px' }}>
                                                {product.name}
                                            </h4>
                                            <p style={{ flex: 1, margin: '0 0 14px', fontSize: '12px', color: '#64748b', lineHeight: 1.5 }}>
                                                {product.description || 'Preparado al momento con la sazón tradicional de DeSego.'}
                                            </p>
                                            <strong style={{ fontSize: '18px', color: '#004797', fontFamily: 'inherit' }}>
                                                {money(product.price)}
                                            </strong>
                                            <div className="dg-favorite-card-actions">
                                                <button
                                                    type="button"
                                                    className="dg-button"
                                                    style={{ flex: 1, fontSize: '12px', padding: '9px 14px', background: '#004797' }}
                                                    onClick={() => quickOrderProduct(product.id)}
                                                >
                                                    <ShoppingBag size={14} /> Pedir ahora
                                                </button>
                                                <button
                                                    type="button"
                                                    className="dg-btn-outline"
                                                    style={{ padding: '8px 11px', color: '#e11d48', borderColor: '#fecdd3' }}
                                                    title="Quitar de favoritos"
                                                    onClick={() => toggleFavorite(product.id)}
                                                >
                                                    <Trash2 size={15} />
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <div className="dg-empty">
                                <Heart size={44} strokeWidth={1.5} style={{ color: '#e11d48' }} />
                                <h3>Aún no has guardado platos favoritos</h3>
                                <p style={{ fontSize: '13px', maxWidth: '380px', margin: '6px auto 18px' }}>
                                    Haz clic en el corazón de cualquier plato de nuestra carta para tener acceso rápido en tus próximos pedidos.
                                </p>
                                <Link href="/#carta" className="dg-button" style={{ background: '#004797' }}>
                                    Explorar la carta <ArrowRight size={14} />
                                </Link>
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 5: MIS RESEÑAS ENVIADAS                                  */}
                {/* ============================================================ */}
                {tab === 'reviews' && (
                    <section className="dg-account-section">
                        <div className="dg-account-section-head">
                            <h2>Mis reseñas</h2>
                        </div>

                        {account.reviews?.length ? (
                            <div style={{ display: 'grid', gap: '16px' }}>
                                {account.reviews.map((rv) => (
                                    <article className="dg-ticket-card" key={rv.id}>
                                        <div className="dg-ticket-card-header">
                                            <div>
                                                <span className="dg-eyebrow" style={{ color: '#004797' }}>
                                                    {rv.product_name}
                                                </span>
                                                <h4 style={{ margin: '4px 0', fontSize: '16px' }}>
                                                    {rv.title || 'Valoración de plato'} ·{' '}
                                                    <span style={{ color: '#d97706' }}>{'★'.repeat(Number(rv.rating || 5))}</span>
                                                </h4>
                                            </div>
                                            <span className={`dg-status-pill ${rv.status === 'approved' ? 'status-entregado' : rv.status === 'rejected' ? 'status-cancelado' : 'status-pendiente'}`}>
                                                {rv.status === 'approved' ? 'Publicada' : rv.status === 'rejected' ? 'Observada' : 'En revisión'}
                                            </span>
                                        </div>
                                        <p style={{ margin: '10px 0', fontSize: '14px', color: '#334155' }}>{rv.comment}</p>
                                        {rv.photo_url && (
                                            <img
                                                src={rv.photo_url}
                                                alt="Foto adjunta"
                                                style={{ width: '110px', height: '110px', objectFit: 'cover', borderRadius: '12px', marginBottom: '8px' }}
                                            />
                                        )}
                                        {rv.admin_reply && (
                                            <div style={{ padding: '12px 14px', borderRadius: '12px', background: '#eff6ff', borderLeft: '4px solid #004797', fontSize: '13px', color: '#1e3a8a', marginTop: '8px' }}>
                                                <strong>Respuesta de DeSego Cevichería:</strong>
                                                <p style={{ margin: '4px 0 0' }}>{rv.admin_reply}</p>
                                            </div>
                                        )}
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <div className="dg-empty">
                                <Star size={44} style={{ color: '#004797' }} />
                                <h3>Aún no has calificado tus platos.</h3>
                                <p style={{ fontSize: '13px', maxWidth: '420px', margin: '8px auto 20px' }}>
                                    Cuando un pedido esté listo o entregado, podrás calificar cada plato desde la pestaña Mis Pedidos.
                                </p>
                            </div>
                        )}
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 6: DIRECCIONES DE ENTREGA                                */}
                {/* ============================================================ */}
                {tab === 'addresses' && (
                    <section className="dg-account-section dg-account-columns">
                        <div>
                            <div className="dg-account-section-head" style={{ marginBottom: '16px' }}>
                                <h2>Mis direcciones</h2>
                            </div>

                            {account.addresses.map((item) => (
                                <article className="dg-address-card" key={item.id}>
                                    <div style={{ background: '#f0f6ff', color: '#004797', padding: '10px', borderRadius: '10px' }}>
                                        <MapPin size={20} />
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                                            <strong>{item.label}</strong>
                                            {item.is_default ? (
                                                <span className="dg-badge-default">Predeterminada</span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="dg-text-button"
                                                    style={{ fontSize: '11px', padding: '0 4px' }}
                                                    onClick={() => router.post(`/cuenta/direcciones/${item.id}/predeterminada`)}
                                                >
                                                    Hacer predeterminada
                                                </button>
                                            )}
                                        </div>
                                        <p style={{ margin: '4px 0 2px' }}>{item.address_line}, {item.district}</p>
                                        <small>{item.reference || 'Sin referencia registrada'}</small>
                                        {item.phone && <small style={{ display: 'block', color: '#004797' }}>Teléfono: {item.phone}</small>}
                                    </div>
                                    <button
                                        type="button"
                                        className="dg-btn-outline"
                                        style={{ padding: '7px 11px', color: '#b91c1c', borderColor: '#fca5a5' }}
                                        aria-label={`Eliminar ${item.label}`}
                                        onClick={() => requestAccountConfirmation({
                                            title: 'Eliminar dirección',
                                            message: `¿Deseas eliminar la dirección "${item.label}"? Esta acción no se puede deshacer.`,
                                            confirmLabel: 'Sí, eliminar dirección',
                                            onConfirm: () => router.delete(`/cuenta/direcciones/${item.id}`, { preserveScroll: true }),
                                        })}
                                    >
                                        <Trash2 size={14} />
                                    </button>
                                </article>
                            ))}

                            {!account.addresses.length && (
                                <div className="dg-empty" style={{ padding: '24px 16px' }}>
                                    <MapPin size={34} strokeWidth={1.5} style={{ color: '#004797' }} />
                                    <p style={{ margin: '6px 0 0', fontSize: '13px' }}>Aún no tienes direcciones registradas.</p>
                                </div>
                            )}
                        </div>

                        {/* FORM: AGREGAR DIRECCIÓN CON MAPA */}
                        <form
                            className="dg-form dg-account-form"
                            onSubmit={(e) => {
                                e.preventDefault();
                                address.post('/cuenta/direcciones', {
                                    onSuccess: () => address.reset('address_line', 'reference', 'latitude', 'longitude'),
                                });
                            }}
                        >
                            <h3>Nueva dirección</h3>
                            <ErrorList errors={address.errors} />

                            <label>
                                Etiqueta de la dirección
                                <input
                                    required
                                    placeholder="Ej. Casa, Trabajo, Huaycán Zona A"
                                    value={address.data.label}
                                    onChange={(e) => address.setData('label', e.target.value)}
                                />
                            </label>

                            <label>
                                Dirección completa (Av., Jr., UCV, Lote, Calle)
                                <input
                                    required
                                    placeholder="Ej. UCV 24, Lote 8, Zona A, Huaycán"
                                    value={address.data.address_line}
                                    onChange={(e) => address.setData('address_line', e.target.value)}
                                />
                            </label>

                            <label>
                                Distrito / Zona
                                <input
                                    required
                                    placeholder="Ej. Ate / Huaycán"
                                    value={address.data.district}
                                    onChange={(e) => address.setData('district', e.target.value)}
                                />
                            </label>

                            <label>
                                Teléfono de contacto para delivery
                                <input
                                    type="tel"
                                    required
                                    placeholder="Ej. 987654321"
                                    value={address.data.phone}
                                    onChange={(e) => address.setData('phone', e.target.value)}
                                />
                            </label>

                            <div style={{ margin: '10px 0' }}>
                                <InteractiveMap
                                    mode="delivery"
                                    height={180}
                                    latitude={address.data.latitude || null}
                                    longitude={address.data.longitude || null}
                                    onLocationChange={(lat, lng) => {
                                        address.setData((d) => ({ ...d, latitude: lat, longitude: lng }));
                                    }}
                                />
                            </div>

                            <label>
                                Referencia para encontrar tu domicilio
                                <textarea
                                    rows={3}
                                    maxLength={255}
                                    placeholder="Ej. Portón blanco frente al parque, casa de dos pisos junto a la bodega..."
                                    value={address.data.reference}
                                    onChange={(e) => address.setData('reference', e.target.value)}
                                />
                                <small style={{ color: '#64748b' }}>
                                    Agrega un punto de referencia, color del portón, piso o indicación útil para el repartidor.
                                </small>
                            </label>

                            <label className="dg-checkbox-row" style={{ display: 'inline-flex', flexDirection: 'row', alignItems: 'center', gap: '10px', cursor: 'pointer', marginTop: '8px' }}>
                                <input
                                    type="checkbox"
                                    checked={address.data.is_default}
                                    onChange={(e) => address.setData('is_default', e.target.checked)}
                                    className="dg-checkbox"
                                    style={{
                                        width: '18px',
                                        height: '18px',
                                        minHeight: '18px',
                                        maxHeight: '18px',
                                        padding: 0,
                                        margin: 0,
                                        accentColor: '#004797',
                                        cursor: 'pointer',
                                        flexShrink: 0,
                                        borderRadius: '4px',
                                        boxShadow: 'none',
                                    }}
                                />
                                <span style={{ fontSize: '13px', color: '#1e293b', fontWeight: 500 }}>Marcar como dirección predeterminada</span>
                            </label>

                            <button className="dg-button" disabled={address.processing} style={{ width: '100%', marginTop: '14px', background: '#004797' }}>
                                <Plus size={16} /> Guardar dirección
                            </button>
                        </form>
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 7: ATENCIÓN Y SOPORTE AL CLIENTE                         */}
                {/* ============================================================ */}
                {tab === 'support' && (
                    <section className="dg-account-section dg-account-columns">
                        <div>
                            <div className="dg-account-section-head" style={{ marginBottom: '16px' }}>
                                <h2>Ayuda y soporte</h2>
                            </div>

                            {account.tickets?.length ? (
                                account.tickets.map((t) => (
                                    <article className="dg-ticket-card" key={t.id}>
                                        <div className="dg-ticket-card-header">
                                            <div>
                                                <span className="dg-eyebrow" style={{ color: '#004797' }}>
                                                    TICKET #{t.ticket_number}
                                                </span>
                                                <h4 style={{ margin: '4px 0 2px', fontSize: '16px', fontFamily: 'inherit' }}>
                                                    {t.subject}
                                                </h4>
                                                <small style={{ color: '#64748b' }}>
                                                    Categoría: {t.category} · Prioridad: {t.priority}
                                                </small>
                                                {t.attachment_url && (
                                                    <div style={{ marginTop: '4px' }}>
                                                        <a
                                                            href={t.attachment_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            style={{ fontSize: '12px', color: '#004797', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                                                        >
                                                            <Paperclip size={13} /> Ver archivo adjunto
                                                        </a>
                                                    </div>
                                                )}
                                            </div>
                                            <span className={t.status === 'resolved' ? 'dg-ticket-badge-resolved' : 'dg-ticket-badge-open'}>
                                                {t.status === 'open' ? 'Abierto' : t.status === 'resolved' ? 'Resuelto' : 'En proceso'}
                                            </span>
                                        </div>

                                        <div className="dg-ticket-messages">
                                            {t.messages?.map((m) => (
                                                <div
                                                    key={m.id}
                                                    className={`dg-ticket-msg-bubble ${m.author_id === account.customer.user_id ? 'from-client' : ''}`}
                                                >
                                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                                                        <strong style={{ fontSize: '12px', color: '#004797' }}>{m.author_name}</strong>
                                                        <small style={{ color: '#94a3b8' }}>{m.created_at}</small>
                                                    </div>
                                                    <p style={{ margin: 0, color: '#1e293b' }}>{m.message}</p>
                                                    {m.attachment_url && (
                                                        <a
                                                            href={m.attachment_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            style={{ fontSize: '11px', color: '#004797', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: '4px', marginTop: '6px' }}
                                                        >
                                                            <Paperclip size={12} /> Adjunto
                                                        </a>
                                                    )}
                                                </div>
                                            ))}
                                        </div>

                                        {replyingTicketId === t.id ? (
                                            <form
                                                onSubmit={(e) => {
                                                    e.preventDefault();
                                                    replyForm.post(`/cuenta/soporte/${t.id}/mensaje`, {
                                                        forceFormData: true,
                                                        onSuccess: () => {
                                                            replyForm.reset();
                                                            setReplyingTicketId(null);
                                                        },
                                                    });
                                                }}
                                                style={{ marginTop: '12px', paddingTop: '12px', borderTop: '1px solid #f1f5f9' }}
                                            >
                                                <textarea
                                                    required
                                                    rows={3}
                                                    placeholder="Escribe tu mensaje o respuesta…"
                                                    value={replyForm.data.message}
                                                    onChange={(e) => replyForm.setData('message', e.target.value)}
                                                    style={{ width: '100%', marginBottom: '8px' }}
                                                />
                                                <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '10px' }}>
                                                    Adjuntar captura o comprobante (opcional):
                                                    <input
                                                        type="file"
                                                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                                                        onChange={(e) => replyForm.setData('attachment', e.target.files?.[0] || null)}
                                                        style={{ display: 'block', marginTop: '4px', fontSize: '12px' }}
                                                    />
                                                </label>
                                                <div style={{ display: 'flex', gap: '8px' }}>
                                                    <button className="dg-button" disabled={replyForm.processing} style={{ fontSize: '12px', background: '#004797' }}>
                                                        Enviar respuesta
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="dg-btn-outline"
                                                        onClick={() => setReplyingTicketId(null)}
                                                        style={{ fontSize: '12px' }}
                                                    >
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        ) : (
                                            <button
                                                type="button"
                                                className="dg-btn-outline"
                                                style={{ fontSize: '12px', marginTop: '8px' }}
                                                onClick={() => setReplyingTicketId(t.id)}
                                            >
                                                Responder ticket <ArrowRight size={13} />
                                            </button>
                                        )}
                                    </article>
                                ))
                            ) : (
                                <div className="dg-empty" style={{ padding: '24px 16px' }}>
                                    <MessagesSquare size={34} strokeWidth={1.5} style={{ color: '#004797' }} />
                                    <p style={{ margin: '6px 0 0', fontSize: '13px' }}>No tienes consultas pendientes.</p>
                                </div>
                            )}
                        </div>

                        {/* FORM: NUEVO TICKET CON ADJUNTO */}
                        <form
                            className="dg-form dg-account-form"
                            onSubmit={(e) => {
                                e.preventDefault();
                                ticketForm.post('/cuenta/soporte', {
                                    forceFormData: true,
                                    onSuccess: () => ticketForm.reset(),
                                });
                            }}
                        >
                            <h3>Nueva consulta</h3>
                            <ErrorList errors={ticketForm.errors} />

                            <label>
                                Asunto principal
                                <input
                                    required
                                    placeholder="Ej. Consulta sobre entrega o reserva"
                                    value={ticketForm.data.subject}
                                    onChange={(e) => ticketForm.setData('subject', e.target.value)}
                                />
                            </label>

                            <label>
                                Categoría de la consulta
                                <select
                                    value={ticketForm.data.category}
                                    onChange={(e) => ticketForm.setData('category', e.target.value)}
                                >
                                    <option value="orders">Pedidos y Delivery</option>
                                    <option value="billing">Comprobantes y Pagos</option>
                                    <option value="reservations">Reservas de Mesa</option>
                                    <option value="loyalty">Club DeSego y Puntos</option>
                                    <option value="other">Otras consultas</option>
                                </select>
                            </label>

                            <label>
                                Nivel de prioridad
                                <select
                                    value={ticketForm.data.priority}
                                    onChange={(e) => ticketForm.setData('priority', e.target.value)}
                                >
                                    <option value="low">Baja</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta (Pedido en curso)</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </label>

                            <label>
                                Mensaje detallado
                                <textarea
                                    required
                                    rows={4}
                                    placeholder="Describe tu consulta…"
                                    value={ticketForm.data.message}
                                    onChange={(e) => ticketForm.setData('message', e.target.value)}
                                />
                            </label>

                            <label>
                                Archivo adjunto opcional (JPG, PNG o PDF · máx. 5 MB)
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    onChange={(e) => ticketForm.setData('attachment', e.target.files?.[0] || null)}
                                />
                            </label>

                            <button className="dg-button" disabled={ticketForm.processing} style={{ width: '100%', marginTop: '14px', background: '#004797' }}>
                                <MessagesSquare size={16} /> Enviar consulta
                            </button>
                        </form>
                    </section>
                )}

                {/* ============================================================ */}
                {/* TAB 8: MI PERFIL, VERIFICACIÓN, SEGURIDAD Y AJUSTES          */}
                {/* ============================================================ */}
                {tab === 'profile' && (
                    <section className="dg-account-section">
                        <div className="dg-account-columns">
                            {/* COLUMNA 1: DATOS PERSONALES Y PREFERENCIAS */}
                            <div>
                                <form
                                    className="dg-form dg-account-form dg-profile-details"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        profile.post('/cuenta/perfil', {
                                            forceFormData: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <h2>Datos personales</h2>
                                    <ErrorList errors={profile.errors} />

                                    <div className="dg-profile-document-fields">
                                        <label>
                                            {profile.data.document_type === 'DNI' ? 'DNI' : profile.data.document_type === 'RUC' ? 'RUC' : 'Número de documento'}
                                            <input
                                                inputMode={['DNI', 'RUC'].includes(profile.data.document_type) ? 'numeric' : 'text'}
                                                maxLength={profile.data.document_type === 'DNI' ? 8 : profile.data.document_type === 'RUC' ? 11 : 30}
                                                placeholder={profile.data.document_type === 'DNI' ? 'Ingresa los 8 dígitos' : profile.data.document_type === 'RUC' ? 'Ingresa los 11 dígitos' : 'Número de documento'}
                                                value={profile.data.document_number}
                                                onChange={(e) => {
                                                    documentWasEdited.current = true;
                                                    profile.setData('document_number', ['DNI', 'RUC'].includes(profile.data.document_type) ? e.target.value.replace(/\D/g, '') : e.target.value);
                                                }}
                                            />
                                        </label>
                                        <label>
                                            Tipo de documento
                                            <select value={profile.data.document_type} onChange={(e) => {
                                                documentWasEdited.current = true;
                                                profile.setData({ ...profile.data, document_type: e.target.value, document_number: '' });
                                            }}>
                                                <option value="DNI">DNI</option>
                                                <option value="RUC">RUC</option>
                                                <option value="CE">Carné de extranjería</option>
                                                <option value="PASAPORTE">Pasaporte</option>
                                                <option value="OTRO">Otro</option>
                                                <option value="SIN_DOCUMENTO">Sin documento</option>
                                            </select>
                                        </label>
                                    </div>
                                    <div className={`dg-profile-document-status is-${documentLookup.status}`} role="status" aria-live="polite">
                                        {documentLookup.status === 'loading' ? (
                                            <RefreshCw size={15} className="dg-spin" />
                                        ) : documentLookup.status === 'success' ? (
                                            <Check size={15} />
                                        ) : (
                                            <ShieldCheck size={15} />
                                        )}
                                        <span>{documentLookup.message}</span>
                                        {documentLookup.status === 'error' && (
                                            <button type="button" onClick={() => setDocumentLookupRetry((value) => value + 1)}>
                                                Reintentar
                                            </button>
                                        )}
                                    </div>
                                    <label>
                                        {profile.data.document_type === 'RUC' ? 'Razón Social' : 'Nombres y apellidos'}
                                        <input required autoComplete="name" value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} />
                                    </label>

                                    <label>
                                        Fecha de nacimiento
                                        <input
                                            type="date"
                                            value={profile.data.birth_date}
                                            onChange={(e) => profile.setData('birth_date', e.target.value)}
                                        />
                                    </label>

                                    <label>
                                        Teléfono / Celular
                                        <input
                                            type="tel"
                                            required
                                            value={profile.data.phone}
                                            onChange={(e) => profile.setData('phone', e.target.value)}
                                        />
                                    </label>

                                    <label>
                                        Correo electrónico (cuenta principal)
                                        <input disabled value={account.customer.email || ''} />
                                    </label>

                                    <button className="dg-button" disabled={profile.processing} style={{ width: '100%', marginTop: '16px', background: '#004797' }}>
                                        Guardar cambios <ArrowRight size={16} />
                                    </button>
                                </form>

                                {/* PREFERENCIAS DEL SERVICIO */}
                                <form
                                    className="dg-form dg-account-form dg-profile-preferences"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        preferencesForm.post('/cuenta/preferencias');
                                    }}
                                >
                                    <h2>Preferencias</h2>

                                    <div className="dg-switch-row">
                                        <div>
                                            <strong>Correos de confirmación de pedido</strong>
                                            <small style={{ display: 'block', color: '#64748b' }}>
                                                Recibir constancias y actualizaciones de estado.
                                            </small>
                                        </div>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            aria-label="Correos de confirmación de pedido"
                                            checked={preferencesForm.data.order_emails}
                                            onChange={(e) => preferencesForm.setData('order_emails', e.target.checked)}
                                        />
                                    </div>

                                    <div className="dg-switch-row">
                                        <div>
                                            <strong>Promociones y novedades Club DeSego</strong>
                                            <small style={{ display: 'block', color: '#64748b' }}>
                                                Beneficios exclusivos y platos de temporada.
                                            </small>
                                        </div>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            aria-label="Promociones y novedades Club DeSego"
                                            checked={preferencesForm.data.promo_emails}
                                            onChange={(e) => preferencesForm.setData('promo_emails', e.target.checked)}
                                        />
                                    </div>

                                    <div className="dg-switch-row">
                                        <div>
                                            <strong>Notificaciones en navegador web</strong>
                                            <small style={{ display: 'block', color: '#64748b' }}>
                                                Alertas cuando tu pedido esté listo o en reparto.
                                            </small>
                                        </div>
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            aria-label="Notificaciones en navegador web"
                                            checked={preferencesForm.data.desktop_notifications}
                                            onChange={(e) => preferencesForm.setData('desktop_notifications', e.target.checked)}
                                        />
                                    </div>

                                    <label style={{ marginTop: '16px' }}>
                                        Método de pago habitual preferido
                                        <select
                                            value={preferencesForm.data.preferred_payment}
                                            onChange={(e) => preferencesForm.setData('preferred_payment', e.target.value)}
                                        >
                                            <option value="Efectivo">Efectivo</option>
                                            <option value="Yape/Plin">Yape / Plin</option>
                                            <option value="Tarjeta al recibir">Tarjeta al recibir</option>
                                            {account.niubiz_available && <option value="Niubiz">Tarjeta en línea · Niubiz</option>}
                                        </select>
                                    </label>

                                    <button className="dg-button" disabled={preferencesForm.processing} style={{ width: '100%', marginTop: '16px', background: '#004797' }}>
                                        Guardar preferencias <Check size={16} />
                                    </button>
                                </form>
                            </div>

                            {/* COLUMNA 2: VERIFICACIÓN OTP, CONTRASEÑA Y GESTIÓN DE CUENTA */}
                            <div>
                                {/* VERIFICACIÓN DE CORREO Y TELÉFONO */}
                                <div className="dg-form dg-account-form dg-profile-verification">
                                    <h2>Verifica tus datos</h2>

                                    <ErrorList errors={verifyConfirmForm.errors} />
                                    {verificationRequestError && <p className="dg-error" role="alert">{verificationRequestError}</p>}

                                    <div style={{ display: 'grid', gap: '12px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 14px', borderRadius: '12px', background: '#f8fafc', border: '1px solid #e2e8f0', gap: '10px', flexWrap: 'wrap' }}>
                                            <div>
                                                <strong style={{ fontSize: '13px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    <Mail size={14} color="#004797" /> Correo electrónico
                                                </strong>
                                                <small style={{ color: '#64748b' }}>{account.customer.email || 'Sin registrar'}</small>
                                            </div>
                                            {account.customer.email_verified_at ? (
                                                <span className="dg-status-pill status-entregado">
                                                    <Check size={12} /> Verificado
                                                </span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="dg-btn-outline"
                                                    style={{ fontSize: '12px' }}
                                                    onClick={() => requestOtp('email')}
                                                >
                                                    Enviar código
                                                </button>
                                            )}
                                        </div>

                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 14px', borderRadius: '12px', background: '#f8fafc', border: '1px solid #e2e8f0', gap: '10px', flexWrap: 'wrap' }}>
                                            <div>
                                                <strong style={{ fontSize: '13px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    <Phone size={14} color="#004797" /> Teléfono móvil
                                                </strong>
                                                <small style={{ color: '#64748b' }}>{account.customer.phone || 'Registra tu celular en Datos personales'}</small>
                                            </div>
                                            {account.customer.phone_verified_at ? (
                                                <span className="dg-status-pill status-entregado">
                                                    <Check size={12} /> Verificado
                                                </span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="dg-btn-outline"
                                                    style={{ fontSize: '12px' }}
                                                    disabled={!account.customer.phone}
                                                    onClick={() => requestOtp('phone')}
                                                >
                                                    Enviar código
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {verifyingChannel && (
                                        <form onSubmit={confirmOtp} style={{ marginTop: '14px', padding: '14px', borderRadius: '12px', background: '#eff6ff', border: '1px solid #bfdbfe' }}>
                                            <label style={{ fontSize: '12px', fontWeight: 700, color: '#1e3a8a' }}>
                                                Ingresa el código de 6 dígitos enviado a tu {verifyingChannel === 'email' ? 'correo' : 'celular'}:
                                                <input
                                                    required
                                                    maxLength={6}
                                                    placeholder="000000"
                                                    value={verifyConfirmForm.data.code}
                                                    onChange={(e) => verifyConfirmForm.setData('code', e.target.value.replace(/\D+/g, ''))}
                                                    style={{ marginTop: '6px', letterSpacing: '0.25em', fontWeight: 700, textAlign: 'center' }}
                                                />
                                            </label>
                                            <div style={{ display: 'flex', gap: '8px', marginTop: '10px' }}>
                                                <button type="submit" className="dg-button" disabled={verifyConfirmForm.processing} style={{ background: '#004797', fontSize: '12px' }}>
                                                    Confirmar Código
                                                </button>
                                                <button type="button" className="dg-btn-outline" style={{ fontSize: '12px' }} onClick={() => setVerifyingChannel(null)}>
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>

                                {/* CAMBIAR CONTRASEÑA */}
                                <form
                                    className="dg-form dg-account-form dg-profile-security"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        passwordForm.post('/cuenta/password', {
                                            onSuccess: () => passwordForm.reset(),
                                        });
                                    }}
                                >
                                    <h2>Seguridad y contraseña</h2>
                                    <ErrorList errors={passwordForm.errors} />

                                    <label>
                                        Contraseña actual
                                        <input
                                            type="password"
                                            required
                                            placeholder="Ingresa tu clave actual"
                                            value={passwordForm.data.current_password}
                                            onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                        />
                                    </label>

                                    <label>
                                        Nueva contraseña (mínimo 10 caracteres)
                                        <input
                                            type="password"
                                            required
                                            minLength={10}
                                            placeholder="Ingresa tu nueva contraseña"
                                            value={passwordForm.data.password}
                                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        />
                                    </label>

                                    <label>
                                        Confirmar nueva contraseña
                                        <input
                                            type="password"
                                            required
                                            minLength={10}
                                            placeholder="Repite la nueva contraseña"
                                            value={passwordForm.data.password_confirmation}
                                            onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                        />
                                    </label>

                                    <button className="dg-button" disabled={passwordForm.processing} style={{ width: '100%', marginTop: '16px', background: '#004797' }}>
                                        Actualizar contraseña <ShieldCheck size={16} />
                                    </button>
                                </form>

                                {/* ZONA DE PELIGRO: DESACTIVAR O ELIMINAR CUENTA */}
                                <div className="dg-danger-zone">
                                    <h4>Privacidad de la cuenta</h4>
                                    <p style={{ fontSize: '12px', color: '#7f1d1d', lineHeight: 1.5, marginBottom: '14px' }}>
                                        Pausa temporalmente tu cuenta o solicita la eliminación definitiva de tus datos personales.
                                    </p>

                                    {showDeactivate ? (
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                requestAccountConfirmation({
                                                    title: 'Desactivar cuenta',
                                                    message: '¿Deseas desactivar temporalmente tu cuenta? Se cerrará tu sesión.',
                                                    confirmLabel: 'Sí, desactivar cuenta',
                                                    onConfirm: () => deactivateForm.post('/cuenta/desactivar'),
                                                });
                                            }}
                                            style={{ marginBottom: '14px' }}
                                        >
                                            <ErrorList errors={deactivateForm.errors} />
                                            <label style={{ color: '#991b1b' }}>
                                                Ingresa tu contraseña para desactivar:
                                                <input
                                                    type="password"
                                                    required
                                                    value={deactivateForm.data.password}
                                                    onChange={(e) => deactivateForm.setData('password', e.target.value)}
                                                />
                                            </label>
                                            <div style={{ display: 'flex', gap: '10px', marginTop: '12px' }}>
                                                <button type="submit" className="dg-btn-danger" disabled={deactivateForm.processing}>
                                                    Confirmar Desactivación
                                                </button>
                                                <button type="button" className="dg-btn-outline" onClick={() => setShowDeactivate(false)}>
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    ) : showDeleteAccount ? (
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                requestAccountConfirmation({
                                                    title: 'Eliminar cuenta definitivamente',
                                                    message: 'Esta acción anonimizará tus datos personales de forma irreversible. ¿Deseas continuar?',
                                                    confirmLabel: 'Sí, eliminar definitivamente',
                                                    onConfirm: () => deleteAccountForm.post('/cuenta/eliminar'),
                                                });
                                            }}
                                        >
                                            <ErrorList errors={deleteAccountForm.errors} />
                                            <label style={{ color: '#991b1b', display: 'block', marginBottom: '10px' }}>
                                                Contraseña actual:
                                                <input
                                                    type="password"
                                                    required
                                                    value={deleteAccountForm.data.password}
                                                    onChange={(e) => deleteAccountForm.setData('password', e.target.value)}
                                                />
                                            </label>
                                            <label style={{ color: '#991b1b', display: 'block' }}>
                                                Escribe <strong>ELIMINAR</strong> en mayúsculas para confirmar:
                                                <input
                                                    required
                                                    placeholder="ELIMINAR"
                                                    value={deleteAccountForm.data.confirmation}
                                                    onChange={(e) => deleteAccountForm.setData('confirmation', e.target.value)}
                                                />
                                            </label>
                                            <div style={{ display: 'flex', gap: '10px', marginTop: '12px' }}>
                                                <button type="submit" className="dg-btn-danger" disabled={deleteAccountForm.processing}>
                                                    Eliminar Cuenta Definitivamente
                                                </button>
                                                <button type="button" className="dg-btn-outline" onClick={() => setShowDeleteAccount(false)}>
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    ) : (
                                        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                                            <button
                                                type="button"
                                                className="dg-btn-outline"
                                                style={{ color: '#b91c1c', borderColor: '#fca5a5' }}
                                                onClick={() => setShowDeactivate(true)}
                                            >
                                                Desactivar Temporalmente
                                            </button>
                                            <button
                                                type="button"
                                                className="dg-btn-danger"
                                                onClick={() => setShowDeleteAccount(true)}
                                            >
                                                Eliminar Cuenta Definitivamente
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>
                )}
                    <footer className="dg-account-footer"><span>DeSego · Cevichería & cocina peruana</span><button type="button" onClick={() => selectSection('support')}>¿Necesitas una mano? <ArrowUpRight size={13} /></button></footer>
                    </div>
                </div>
            </div>

            {/* MOBILE BOTTOM NAVIGATION BAR (4 TABS: 3 PRIMARY + MENÚ) */}
            <nav className="dg-account-bottom-nav" aria-label="Navegación de cuenta móvil">
                {MOBILE_PRIMARY_TABS.map(([key, title, Icon]) => {
                    const isActive = tab === key;
                    const badge = key === 'orders' && activeOrdersCount > 0 ? activeOrdersCount : null;
                    return (
                        <button
                            type="button"
                            key={key}
                            className={isActive ? 'is-active' : ''}
                            aria-current={isActive ? 'page' : undefined}
                            onClick={() => selectSection(key)}
                        >
                            <span className="dg-account-bottom-icon">
                                <Icon size={21} />
                                {badge && <span className="dg-bottom-nav-dot" aria-label={`${badge} en curso`} />}
                            </span>
                            <span>{title}</span>
                        </button>
                    );
                })}

                {/* 4ta OPCIÓN: MENÚ PARA LAS DEMÁS */}
                <button
                    type="button"
                    className={navigationOpen || !MOBILE_PRIMARY_TABS.some(([key]) => key === tab) ? 'is-active' : ''}
                    aria-haspopup="dialog"
                    aria-expanded={navigationOpen}
                    aria-controls="account-mobile-menu"
                    onClick={() => setNavigationOpen((prev) => !prev)}
                >
                    <span className="dg-account-bottom-icon">
                        <Menu size={21} />
                    </span>
                    <span>Menú</span>
                </button>
            </nav>

            <dialog
                ref={accountConfirmationDialogRef}
                className="dg-account-confirm-dialog"
                aria-labelledby="account-confirm-title"
                aria-describedby="account-confirm-description"
                onCancel={(event) => {
                    if (cancelOrderForm.processing) {
                        event.preventDefault();
                        return;
                    }
                    setAccountConfirmation(null);
                }}
                onClose={() => setAccountConfirmation(null)}
                onClick={(event) => {
                    if (event.target === event.currentTarget && !cancelOrderForm.processing) {
                        setAccountConfirmation(null);
                    }
                }}
            >
                {accountConfirmation && (
                    <div className="dg-account-confirm-dialog-content">
                        <span className={`dg-account-confirm-dialog-icon is-${accountConfirmation.tone || 'danger'}`} aria-hidden="true">
                            {accountConfirmation.type === 'cancel-order' ? (
                                <ShoppingBag size={22} />
                            ) : accountConfirmation.tone === 'primary' ? (
                                <Award size={22} />
                            ) : (
                                <ShieldCheck size={22} />
                            )}
                        </span>
                        <h2 id="account-confirm-title">{accountConfirmation.title}</h2>
                        <p id="account-confirm-description">{accountConfirmation.message}</p>
                        {accountConfirmation.type === 'cancel-order' && <ErrorList errors={cancelOrderForm.errors} />}
                        <div className="dg-account-confirm-dialog-actions">
                            <button
                                type="button"
                                className="dg-btn-outline"
                                onClick={closeAccountConfirmation}
                                disabled={cancelOrderForm.processing}
                                autoFocus
                            >
                                Volver
                            </button>
                            <button
                                type="button"
                                className={`dg-button ${accountConfirmation.tone === 'primary' ? 'is-primary' : 'is-danger'}`}
                                disabled={cancelOrderForm.processing}
                                onClick={confirmAccountAction}
                            >
                                {cancelOrderForm.processing ? 'Procesando…' : accountConfirmation.confirmLabel}
                            </button>
                        </div>
                    </div>
                )}
            </dialog>

            <dialog
                ref={mobileMenuRef}
                id="account-mobile-menu"
                className="dg-account-mobile-sheet"
                aria-labelledby="account-mobile-menu-title"
                onCancel={() => setNavigationOpen(false)}
                onClose={() => setNavigationOpen(false)}
                onClick={(event) => {
                    if (event.target === event.currentTarget) {
                        setNavigationOpen(false);
                    }
                }}
            >
                <div className="dg-account-mobile-sheet-inner">
                    <div className="dg-account-sheet-handle" aria-hidden="true" />

                    {/* CUSTOMER PROFILE CARD INSIDE SHEET (LIKE DESEGO-APP) */}
                    <div className="dg-account-sheet-profile">
                        <div className="dg-account-sheet-avatar">
                            {(account.customer.full_name || 'C').charAt(0).toUpperCase()}
                        </div>
                        <div className="dg-account-sheet-user-info">
                            <div className="dg-sheet-user-name">
                                <strong>{account.customer.full_name || 'Cliente DeSego'}</strong>
                                <span className="dg-sheet-tier-badge">
                                    <Award size={11} /> {account.loyalty?.tier || 'Bronce'}
                                </span>
                            </div>
                            <span className="dg-sheet-user-email">{account.customer.email}</span>
                            <div className="dg-sheet-user-meta">
                                <span>Código: <strong>{account.customer.customer_code || '---'}</strong></span>
                                <span className="dg-sheet-points-pill">
                                    <Sparkles size={11} /> {account.loyalty?.points_balance ?? 0} pts
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            className="dg-account-sheet-close"
                            aria-label="Cerrar menú de cuenta"
                            onClick={() => setNavigationOpen(false)}
                        >
                            <X size={18} />
                        </button>
                    </div>

                    <div className="dg-account-sheet-title-row">
                        <span className="dg-eyebrow" style={{ color: '#004797' }}>TU ESPACIO GASTRONÓMICO</span>
                        <h2 id="account-mobile-menu-title">Todas las opciones de tu cuenta</h2>
                    </div>

                    <nav className="dg-account-sheet-list" aria-label="Todas las opciones de cuenta">
                        {ACCOUNT_SECTIONS.map(([key, title, Icon, desc]) => {
                            const badge = getSectionBadge(key);
                            const isActive = tab === key;
                            return (
                                <button
                                    type="button"
                                    key={key}
                                    className={`dg-account-sheet-item ${isActive ? 'is-active' : ''}`}
                                    aria-current={isActive ? 'page' : undefined}
                                    onClick={() => selectSection(key)}
                                >
                                    <div className="dg-sheet-item-icon">
                                        <Icon size={20} />
                                    </div>
                                    <div className="dg-sheet-item-text">
                                        <div className="dg-sheet-item-title-row">
                                            <strong>{title}</strong>
                                            {badge && <span className="dg-sheet-item-badge">{badge}</span>}
                                        </div>
                                        <small>{desc}</small>
                                    </div>
                                    <ChevronRight size={16} className="dg-sheet-item-chevron" />
                                </button>
                            );
                        })}
                    </nav>

                    <div className="dg-account-sheet-quick-actions">
                        <Link href="/#carta" className="dg-btn-outline" onClick={() => setNavigationOpen(false)}>
                            <UtensilsCrossed size={14} /> Ver carta digital
                        </Link>
                        <Link href="/#reservas" className="dg-btn-outline" onClick={() => setNavigationOpen(false)}>
                            <CalendarDays size={14} /> Reservar mesa
                        </Link>
                    </div>

                    <div className="dg-account-sheet-footer">
                        <span><ShieldCheck size={14} /> Tu sesión está protegida</span>
                        <button type="button" onClick={() => router.post('/salir')}>
                            <LogOut size={16} /> Cerrar sesión
                        </button>
                    </div>
                </div>
            </dialog>

            {/* ============================================================ */}
            {/* MODAL: MODIFICAR DATOS DE PEDIDO PENDIENTE                   */}
            {/* ============================================================ */}
            {modifyOrderModal && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(2, 26, 56, 0.65)',
                        backdropFilter: 'blur(8px)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 9999,
                        padding: '20px',
                    }}
                >
                    <form
                        className="dg-form dg-account-form"
                        style={{ maxWidth: '480px', width: '100%', borderRadius: '24px' }}
                        onSubmit={submitModifyOrder}
                    >
                        <h3>Modificar Pedido #{modifyOrderModal.order_number}</h3>
                        <p style={{ fontSize: '13px', color: '#64748b', marginBottom: '14px' }}>
                            Puedes actualizar las notas o datos de entrega mientras el pedido aún no ingresa a preparación.
                        </p>
                        <ErrorList errors={modifyOrderForm.errors} />

                        {modifyOrderModal.order_type === 'Delivery' && (
                            <>
                                <label>
                                    Dirección de entrega
                                    <input
                                        required
                                        minLength={5}
                                        value={modifyOrderForm.data.address_line}
                                        onChange={(e) => modifyOrderForm.setData('address_line', e.target.value)}
                                    />
                                </label>
                                <div className="dg-form-grid">
                                    <label>
                                        Distrito / Zona
                                        <input
                                            value={modifyOrderForm.data.district}
                                            onChange={(e) => modifyOrderForm.setData('district', e.target.value)}
                                        />
                                    </label>
                                    <label>
                                        Celular de recepción
                                        <input
                                            value={modifyOrderForm.data.recipient_phone}
                                            onChange={(e) => modifyOrderForm.setData('recipient_phone', e.target.value)}
                                        />
                                    </label>
                                </div>
                                <label>
                                    Referencia
                                    <input
                                        value={modifyOrderForm.data.reference}
                                        onChange={(e) => modifyOrderForm.setData('reference', e.target.value)}
                                    />
                                </label>
                            </>
                        )}

                        <label>
                            Indicaciones generales para cocina
                            <textarea
                                rows={3}
                                maxLength={300}
                                value={modifyOrderForm.data.notes}
                                onChange={(e) => modifyOrderForm.setData('notes', e.target.value)}
                            />
                        </label>

                        <div style={{ display: 'flex', gap: '12px', marginTop: '18px' }}>
                            <button className="dg-button" disabled={modifyOrderForm.processing} style={{ flex: 1, background: '#004797' }}>
                                Guardar Cambios
                            </button>
                            <button type="button" className="dg-btn-outline" onClick={() => setModifyOrderModal(null)}>
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* ============================================================ */}
            {/* MODAL: REPROGRAMAR RESERVA DE MESA                           */}
            {/* ============================================================ */}
            {rescheduleModal && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(2, 26, 56, 0.65)',
                        backdropFilter: 'blur(8px)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 9999,
                        padding: '20px',
                    }}
                >
                    <form
                        className="dg-form dg-account-form"
                        style={{ maxWidth: '460px', width: '100%', borderRadius: '24px' }}
                        onSubmit={submitReschedule}
                    >
                        <h3>Reprogramar Reserva #{rescheduleModal.reservation_code}</h3>
                        <p style={{ fontSize: '13px', color: '#64748b', marginBottom: '14px' }}>
                            Elige la nueva fecha y horario dentro de nuestra jornada de atención (09:00 – 17:00).
                        </p>
                        <ErrorList errors={rescheduleForm.errors} />

                        <div className="dg-form-grid">
                            <label>
                                Nueva fecha
                                <input
                                    type="date"
                                    required
                                    value={rescheduleForm.data.date}
                                    onChange={(e) => rescheduleForm.setData('date', e.target.value)}
                                />
                            </label>
                            <label>
                                Nueva hora
                                <input
                                    type="time"
                                    required
                                    min="09:00"
                                    max="16:30"
                                    value={rescheduleForm.data.time}
                                    onChange={(e) => rescheduleForm.setData('time', e.target.value)}
                                />
                            </label>
                        </div>

                        <label>
                            Número de comensales
                            <select
                                value={rescheduleForm.data.people}
                                onChange={(e) => rescheduleForm.setData('people', Number(e.target.value))}
                            >
                                {Array.from({ length: 12 }, (_, i) => i + 1).map((n) => (
                                    <option key={n} value={n}>
                                        {n} {n === 1 ? 'persona' : 'personas'}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label>
                            Petición especial (opcional)
                            <textarea
                                rows={2}
                                maxLength={500}
                                value={rescheduleForm.data.notes}
                                onChange={(e) => rescheduleForm.setData('notes', e.target.value)}
                            />
                        </label>

                        <div style={{ display: 'flex', gap: '12px', marginTop: '18px' }}>
                            <button className="dg-button" disabled={rescheduleForm.processing} style={{ flex: 1, background: '#004797' }}>
                                Confirmar Nuevo Horario
                            </button>
                            <button type="button" className="dg-btn-outline" onClick={() => setRescheduleModal(null)}>
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* ============================================================ */}
            {/* MODAL: CALIFICAR PLATO / RESEÑA GASTRONÓMICA CON FOTO        */}
            {/* ============================================================ */}
            {reviewModal && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(2, 26, 56, 0.65)',
                        backdropFilter: 'blur(8px)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 9999,
                        padding: '20px',
                    }}
                >
                    <form
                        className="dg-form dg-account-form"
                        style={{ maxWidth: '480px', width: '100%', borderRadius: '24px' }}
                        onSubmit={submitReview}
                    >
                        <h3>Calificar Experiencia Gastronómica</h3>
                        <p style={{ fontSize: '13px', color: '#64748b', marginBottom: '16px' }}>
                            ¿Qué te pareció <strong>{reviewModal.productName}</strong>? Tu opinión ayuda a otros comensales.
                        </p>
                        <ErrorList errors={reviewForm.errors} />

                        <label>
                            Puntuación
                            <div style={{ display: 'flex', gap: '8px', margin: '8px 0' }}>
                                {[1, 2, 3, 4, 5].map((star) => (
                                    <button
                                        type="button"
                                        key={star}
                                        className={`dg-star-interactive ${reviewForm.data.rating >= star ? 'active' : ''}`}
                                        onClick={() => reviewForm.setData('rating', star)}
                                    >
                                        <Star size={32} filled={reviewForm.data.rating >= star} />
                                    </button>
                                ))}
                            </div>
                        </label>

                        <label>
                            Título de tu opinión
                            <input
                                required
                                value={reviewForm.data.title}
                                onChange={(e) => reviewForm.setData('title', e.target.value)}
                            />
                        </label>

                        <label>
                            Tu comentario sobre sabor y atención
                            <textarea
                                required
                                rows={3}
                                placeholder="Cuéntanos tu experiencia con este plato..."
                                value={reviewForm.data.comment}
                                onChange={(e) => reviewForm.setData('comment', e.target.value)}
                            />
                        </label>

                        <label>
                            Fotografía del plato (opcional)
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(e) => reviewForm.setData('photo', e.target.files?.[0] || null)}
                            />
                        </label>

                        <div style={{ display: 'flex', gap: '12px', marginTop: '20px' }}>
                            <button className="dg-button" disabled={reviewForm.processing} style={{ flex: 1, background: '#004797' }}>
                                <Check size={16} /> Publicar Calificación
                            </button>
                            <button
                                type="button"
                                className="dg-btn-outline"
                                onClick={() => setReviewModal(null)}
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </main>
    );
}
