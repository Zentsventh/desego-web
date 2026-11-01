import AdminReservationTables from '../../Components/Restaurant/AdminReservationTables';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import DiningFloor from '../../Components/Restaurant/DiningFloor';
import AdminRoles from '../../Components/Restaurant/AdminRoles';
import AdminChatHistory from '../../Components/Restaurant/AdminChatHistory';
import AdminSettingsPanel from '../../Components/Restaurant/AdminSettingsPanel';
import AdminReviewsPanel from '../../Components/Restaurant/AdminReviewsPanel';
import AdminDeliveryZones from '../../Components/Restaurant/AdminDeliveryZones';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Award,
    BarChart3,
    Bell,
    CalendarDays,
    CircleDollarSign,
    Clock3,
    ClipboardList,
    ArrowUpRight,
    Flame,
    MessageSquare,
    Paperclip,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
    Upload,
    UsersRound,
    X,
} from 'lucide-react';
import { ErrorList, money } from '../../Components/Restaurant/ShopParts';
import AdminNavigation, {
    AdminTools,
} from '../../Components/Restaurant/AdminNavigation';
import { adminModuleTitle, adminText } from '../../lib/adminSpanish';
import '../../../css/restaurant-glass.css';

const COLUMN_LABELS = {
    ticket_number: 'N.° Ticket',
    creator_name: 'Cliente',
    creator_email: 'Correo',
    subject: 'Asunto',
    priority: 'Prioridad',
    attachment_url: 'Adjunto / Evidencia',
    message_count: 'Mensajes',
    order_number: 'Pedido',
    customer_name: 'Cliente',
    order_type: 'Canal',
    order_channel: 'Origen',
    payment_status: 'Estado de pago',
    payment_method: 'Medio de pago',
    payment_preference: 'Preferencia de pago',
    subtotal: 'Subtotal',
    discount: 'Descuento',
    delivery_fee: 'Envío',
    total: 'Total',
    created_at: 'Fecha',
    updated_at: 'Actualizado',
    paid_at: 'Fecha de cobro',
    name: 'Nombre',
    full_name: 'Nombre completo',
    reviewer_name: 'Cliente',
    moderator_name: 'Moderador',
    product_name: 'Producto',
    supplier_name: 'Proveedor',
    branch_name: 'Sucursal',
    employee_name: 'Colaborador',
    user_name: 'Usuario',
    actor_name: 'Usuario',
    price: 'Precio',
    category: 'Categoría',
    stock_quantity: 'Existencia',
    unit: 'Unidad',
    min_stock: 'Stock mínimo',
    cost_per_unit: 'Costo unitario',
    unit_cost: 'Costo unitario',
    status: 'Estado',
    operational_status: 'Estado operativo',
    effective_status: 'Estado actual',
    last_check_status: 'Última verificación',
    phone: 'Celular',
    customer_phone: 'Celular',
    email: 'Correo',
    customer_email: 'Correo',
    seating: 'Distribuci\u00f3n',
    reservation_code: 'Reserva',
    guest_count: 'Personas',
    reservation_at: 'Fecha de reserva',
    duration_minutes: 'Duración (min)',
    table_label: 'Mesa',
    table_number: 'N.° Mesa',
    zone: 'Zona',
    capacity: 'Capacidad',
    advance_amount: 'Anticipo',
    applied_credit_amount: 'Crédito aplicado',
    arrival_deadline: 'Límite tolerancia',
    checked_in_at: 'Llegada cliente',
    seating: 'Distribución mesas',
    customer_code: 'Código cliente',
    document_type: 'Tipo doc.',
    document_number: 'N.° documento',
    orders_count: 'Pedidos',
    total_spent: 'Consumo total',
    role_name: 'Rol',
    code: 'Código',
    description: 'Descripción',
    event_code: 'Evento',
    ip_address: 'Dirección IP',
    file_name: 'Archivo',
    size_bytes: 'Tamaño',
    verification_status: 'Integridad',
    employee_code: 'Código',
    job_title: 'Puesto',
    employment_type: 'Tipo de contrato',
    hire_date: 'Contratación',
    hourly_rate: 'Tarifa / hora',
    subject: 'Asunto',
    priority: 'Prioridad',
    opened_at: 'Apertura',
    closed_at: 'Cierre',
    opening_amount: 'Monto inicial',
    expected_closing: 'Esperado',
    counted_closing: 'Contado',
    difference_amount: 'Diferencia',
    movement_type: 'Movimiento',
    quantity_delta: 'Variación',
    previous_quantity: 'Stock anterior',
    new_quantity: 'Nuevo stock',
    reference: 'Referencia',
    notes: 'Notas',
    discount_type: 'Tipo descuento',
    discount_value: 'Valor descuento',
    promotion_type: 'Tipo promoción',
    per_customer_limit: 'Usos por cliente',
    usage_count: 'Usos realizados',
    usage_limit: 'Límite total',
    max_discount: 'Descuento máximo',
    min_subtotal: 'Compra mínima',
    points_cost: 'Puntos requeridos',
    points_balance: 'Saldo puntos',
    tier: 'Nivel',
    reward_type: 'Tipo beneficio',
    reward_value: 'Valor beneficio',
    contact_name: 'Contacto',
    tax_id: 'RUC',
    id: 'ID',
    recipient_phone: 'Celular destinatario',
    address_line: 'Dirección',
    courier_user_id: 'ID Motorizado',
    delivery_status: 'Estado de envío',
    estimated_delivery_at: 'Entrega estimada',
    picked_up_at: 'Recogido',
    delivered_at: 'Entregado',
    incident_notes: 'Incidencias',
    courier_name: 'Motorizado',
    elapsed_minutes: 'Minutos transcurridos',
    item_summary: 'Detalle de productos',
    customer_id: 'ID Cliente',
    lifetime_earned: 'Puntos acumulados',
    lifetime_redeemed: 'Puntos canjeados',
    paid_orders: 'Pedidos pagados',
    soles_per_point: 'Soles por punto',
    minimum_eligible_amount: 'Compra mínima elegible',
    points_expiry_days: 'Días de vigencia',
    supplier_id: 'ID Proveedor',
    supplier_email: 'Correo proveedor',
    supplier_phone: 'Teléfono proveedor',
    created_by_name: 'Creado por',
    opened_by_name: 'Abierto por',
    order_date: 'Fecha de orden',
    expected_date: 'Fecha esperada',
    received_at: 'Fecha de recepción',
    payment_due_date: 'Vencimiento de pago',
    invoice_reference: 'Ref. Comprobante',
    item_count: 'N.° Ítems',
    received_progress: 'Progreso recepción',
    address: 'Dirección',
    payment_terms_days: 'Plazo pago (días)',
    purchase_orders_count: 'Órdenes de compra',
    total_purchased: 'Total comprado',
    branch_id: 'ID Sucursal',
    user_id: 'ID Usuario',
    emergency_contact_name: 'Contacto emergencia',
    emergency_contact_phone: 'Tel. emergencia',
    linked_user_name: 'Usuario vinculado',
    linked_user_email: 'Correo vinculado',
    backup_kind: 'Tipo de respaldo',
    checksum_sha256: 'Firma SHA-256',
    storage_targets: 'Destino',
    verified_at: 'Verificado el',
    restored_at: 'Restaurado el',
    error_message: 'Mensaje de error',
    assigned_to_user_id: 'ID Asignado',
    closed_by_user_id: 'ID Cerrado por',
    resolution_notes: 'Notas de resolución',
    assigned_to_name: 'Asignado a',
    message_count: 'Mensajes',
    last_message_at: 'Último mensaje',
    sales_total: 'Ventas totales',
    net_sales: 'Ventas netas',
    orders_total: 'Total pedidos',
    average_ticket: 'Ticket promedio',
    active_tables: 'Mesas activas',
    available_tables: 'Mesas disponibles',
    occupied_tables: 'Mesas ocupadas',
    reserved_tables: 'Mesas reservadas',
    low_stock_count: 'Alertas de stock',
    active_promotions: 'Promociones activas',
    active_rewards: 'Beneficios activos',
    open_tickets: 'Tickets abiertos',
    total_backups: 'Copias totales',
    valid_backups: 'Copias válidas',
    costing_method: 'Método de costeo',
    inventory_value: 'Valor de inventario',
    amount: 'Monto',
    net_total: 'Total neto',
    is_featured: 'Destacado',
    is_popular: 'Popular',
    is_new: 'Nuevo',
    included_items: 'Platos incluidos',
    emoji: 'Ícono',
};

const SUMMARY_GROUP_LABELS = {
    summary: 'Resumen general',
    totals: 'Totales del período',
    kpis: 'Indicadores clave',
    cash: 'Estado de caja',
    session: 'Sesión de caja activa',
    loyalty: 'Programa de fidelización',
    retention: 'Política de retención',
    backup_policy: 'Política de respaldos',
    stats: 'Estadísticas',
    valuation: 'Valorización de inventario',
    counts: 'Conteos operativos',
    meta: 'Información adicional',
};

function display(value, key, section = '') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    if (
        [
            'price',
            'total',
            'subtotal',
            'discount',
            'delivery_fee',
            'cost_per_unit',
            'unit_cost',
            'opening_amount',
            'expected_closing',
            'counted_closing',
            'difference_amount',
            'amount',
            'net_total',
            'total_spent',
            'total_purchased',
            'sales_total',
            'net_sales',
            'average_ticket',
            'min_subtotal',
            'minimum_eligible_amount',
            'hourly_rate',
            'inventory_value',
            'advance_amount',
            'applied_credit_amount',
        ].includes(key)
    ) {
        return money(value);
    }
    if (key === 'per_customer_limit') {
        const limit = Number(value);
        return limit === 0
            ? 'Sin límite'
            : limit === 1
              ? 'Una vez por cliente'
              : `${limit} veces por cliente`;
    }
    if (
        [
            'reservation_at',
            'created_at',
            'updated_at',
            'paid_at',
            'completed_at',
            'arrival_deadline',
            'checked_in_at',
        ].includes(key) &&
        typeof value === 'string'
    ) {
        const str = value.trim();
        let d;
        if (str.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(str)) {
            d = new Date(str);
        } else {
            d = new Date(str.replace(' ', 'T') + 'Z');
        }
        if (!isNaN(d.getTime())) {
            return d.toLocaleString('es-PE', {
                timeZone: 'America/Lima',
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
            });
        }
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    return adminText(value, key, section);
}

const GOOD_STATES = [
    'active', 'available', 'Entregado', 'Pagado', 'Confirmada', 'Sentada', 'Completada', 'Listo',
    'completed', 'closed', 'valid', 'received', 'resolved', 'issued', 'approved',
    'Disponible', 'Activo', 'Válido', 'Abierto', 'Cerrado',
    'CONFIRMED', 'CHECKED_IN', 'IN_SERVICE', 'PAID',
];
const WARN_STATES = [
    'Pendiente', 'pending', 'Preparando', 'En reparto', 'draft', 'in_progress',
    'waiting_user', 'scheduled', 'partially_received', 'open',
    'PENDING_PAYMENT', 'PARTIALLY_PAID',
];
const BAD_STATES = [
    'Cancelado', 'Cancelada', 'Reembolsado', 'inactive', 'unavailable', 'invalid', 'failed',
    'cancelled', 'canceled', 'voided', 'deleted', 'rejected', 'No disponible', 'Inactivo',
    'NO_SHOW', 'RETAINED', 'EXPIRED', 'REFUNDED',
];

function StatusBadge({ value, field = 'status', section = '' }) {
    const raw = String(value ?? '');
    const tone = GOOD_STATES.includes(raw)
        ? 'good'
        : BAD_STATES.includes(raw)
          ? 'bad'
          : WARN_STATES.includes(raw)
            ? 'warn'
            : 'info';

    return (
        <span className={`glass-badge glass-badge-${tone}`}>
            {adminText(value, field, section)}
        </span>
    );
}

const BRAND = '#004797';
const DONUT_COLORS = ['#004797', '#1d72d2', '#5b9bdc', '#9dc2ea', '#d3e3f6'];
const WEEKDAYS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
const peruDateTime = (value) => {
    if (!value) return '';
    const str = String(value).trim();
    let d;
    if (str.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(str)) {
        d = new Date(str);
    } else {
        d = new Date(str.replace(' ', 'T') + 'Z');
    }
    if (isNaN(d.getTime())) return '';
    const limaDate = new Date(d.getTime() - 5 * 60 * 60 * 1000);
    return limaDate.toISOString().slice(0, 16);
};

function buildWeek(dailySales = []) {
    const totals = new Map(
        dailySales.map((item) => [String(item.date).slice(0, 10), Number(item.total) || 0])
    );

    return Array.from({ length: 7 }, (_, index) => {
        const day = new Date();
        day.setDate(day.getDate() - (6 - index));
        const key = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;

        return { key, label: WEEKDAYS[day.getDay()], total: totals.get(key) ?? 0 };
    });
}

function formatTick(value) {
    return value >= 1000 ? `S/ ${(value / 1000).toFixed(1)}k` : `S/ ${Math.round(value)}`;
}

function SalesChart({ days }) {
    const width = 640;
    const height = 240;
    const left = 54;
    const right = 14;
    const top = 14;
    const bottom = 32;
    const innerWidth = width - left - right;
    const innerHeight = height - top - bottom;
    const peak = Math.max(...days.map((day) => day.total), 1);
    const magnitude = 10 ** Math.floor(Math.log10(peak));
    const ceiling = Math.ceil(peak / magnitude) * magnitude;
    const x = (index) => left + (index / (days.length - 1)) * innerWidth;
    const y = (value) => top + innerHeight - (value / ceiling) * innerHeight;
    const points = days.map((day, index) => ({ ...day, cx: x(index), cy: y(day.total) }));
    const line = points.reduce((path, point, index) => {
        if (index === 0) {
            return `M ${point.cx} ${point.cy}`;
        }
        const previous = points[index - 1];
        const middle = (previous.cx + point.cx) / 2;

        return `${path} C ${middle} ${previous.cy}, ${middle} ${point.cy}, ${point.cx} ${point.cy}`;
    }, '');
    const baseline = top + innerHeight;
    const area = `${line} L ${points[points.length - 1].cx} ${baseline} L ${points[0].cx} ${baseline} Z`;

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="glass-chart-svg"
            role="img"
            aria-label="Gráfico de ingresos diarios de los últimos 7 días"
        >
            <defs>
                <linearGradient id="salesArea" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stopColor={BRAND} stopOpacity="0.34" />
                    <stop offset="55%" stopColor="#1d72d2" stopOpacity="0.12" />
                    <stop offset="100%" stopColor={BRAND} stopOpacity="0.0" />
                </linearGradient>
                <linearGradient id="salesBarGrad" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stopColor={BRAND} stopOpacity="0.22" />
                    <stop offset="100%" stopColor={BRAND} stopOpacity="0.05" />
                </linearGradient>
                <filter id="salesLineGlow" x="-10%" y="-20%" width="120%" height="150%">
                    <feDropShadow dx="0" dy="6" stdDeviation="5" floodColor="#004797" floodOpacity="0.28" />
                </filter>
            </defs>

            {[0, 1, 2, 3, 4].map((step) => {
                const value = (ceiling / 4) * step;
                return (
                    <g key={step}>
                        <line className="glass-chart-grid" x1={left} x2={width - right} y1={y(value)} y2={y(value)} />
                        <text x={left - 10} y={y(value) + 4} textAnchor="end">
                            {formatTick(value)}
                        </text>
                    </g>
                );
            })}

            {points.map((point) => (
                <rect
                    key={`bar-${point.key}`}
                    className="glass-chart-bar"
                    x={point.cx - 16}
                    y={point.cy}
                    width="32"
                    height={Math.max(baseline - point.cy, 0)}
                    rx="9"
                    fill="url(#salesBarGrad)"
                >
                    <title>{`${point.label} · ${money(point.total)}`}</title>
                </rect>
            ))}

            <path d={area} fill="url(#salesArea)" />
            <path className="glass-chart-line" d={line} filter="url(#salesLineGlow)" />

            {points.map((point) => (
                <g key={`dot-${point.key}`}>
                    {point.total > 0 && (
                        <g transform={`translate(${point.cx}, ${Math.max(point.cy - 14, 12)})`}>
                            <rect
                                x="-28"
                                y="-11"
                                width="56"
                                height="17"
                                rx="8.5"
                                fill="rgba(0, 71, 151, 0.92)"
                            />
                            <text
                                x="0"
                                y="1"
                                textAnchor="middle"
                                style={{ fill: '#ffffff', fontSize: '9.5px', fontWeight: 700 }}
                            >
                                {formatTick(point.total)}
                            </text>
                        </g>
                    )}
                    <circle className="glass-chart-dot-halo" cx={point.cx} cy={point.cy} r="8" />
                    <circle className="glass-chart-dot" cx={point.cx} cy={point.cy} r="4.5">
                        <title>{`${point.label} · ${money(point.total)}`}</title>
                    </circle>
                    <text x={point.cx} y={height - 8} textAnchor="middle">
                        {point.label}
                    </text>
                </g>
            ))}
        </svg>
    );
}

function StatusDonut({ orders = [] }) {
    const counts = orders.reduce((accumulator, order) => {
        const label = order.status || 'Sin estado';
        accumulator[label] = (accumulator[label] || 0) + 1;
        return accumulator;
    }, {});
    const entries = Object.entries(counts);
    const total = orders.length;

    if (!total) {
        return <p className="glass-empty">Aún no hay pedidos registrados para mostrar su distribución.</p>;
    }

    const radius = 52;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    return (
        <div className="glass-donut-wrap">
            <svg
                viewBox="0 0 140 140"
                className="glass-chart-svg"
                role="img"
                aria-label="Distribución de los últimos pedidos por estado"
            >
                <circle cx="70" cy="70" r={radius} fill="rgba(255,255,255,0.45)" stroke="rgba(0,71,151,0.09)" strokeWidth="16" />
                {entries.map(([label, count], index) => {
                    const length = (count / total) * circumference;
                    const segment = (
                        <circle
                            key={label}
                            cx="70"
                            cy="70"
                            r={radius}
                            fill="none"
                            stroke={DONUT_COLORS[index % DONUT_COLORS.length]}
                            strokeWidth="16"
                            strokeDasharray={`${Math.max(length - 2, 0)} ${circumference - Math.max(length - 2, 0)}`}
                            strokeDashoffset={-offset}
                            strokeLinecap="round"
                            transform="rotate(-90 70 70)"
                        >
                            <title>{`${adminText(label, 'status')} · ${count}`}</title>
                        </circle>
                    );
                    offset += length;
                    return segment;
                })}
                <text className="glass-donut-center" x="70" y="72" textAnchor="middle">
                    {total}
                </text>
                <text className="glass-donut-caption" x="70" y="88" textAnchor="middle">
                    pedidos
                </text>
            </svg>
            <ul className="glass-legend">
                {entries.map(([label, count], index) => {
                    const pct = Math.round((count / total) * 100);
                    const color = DONUT_COLORS[index % DONUT_COLORS.length];
                    return (
                        <li key={label}>
                            <div className="glass-legend-row">
                                <span>
                                    <i style={{ background: color }} />
                                    {adminText(label, 'status')}
                                </span>
                                <strong>{count} <small>({pct}%)</small></strong>
                            </div>
                            <div className="glass-legend-bar">
                                <span style={{ width: `${pct}%`, background: color }} />
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

function RecordTable({ rows = [], section, onEdit }) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);

    useEffect(() => {
        setQuery('');
        setPage(1);
    }, [section]);

    const hiddenColumns = [
        'password_hash',
        'permissions',
        'items',
        'ingredients',
        'allergens',
        'details',
        'customer_user_id',
        'web_checkout_key',
        'created_by',
        'updated_by',
        'cancelled_at',
        'seated_at',
        'completed_at',
        'role_id',
        'category_id',
        'product_id',
        'order_id',
        'table_id',
        'branch_id',
        'supplier_id',
        'user_id',
        'customer_id',
        'courier_user_id',
        'assigned_to_user_id',
        'closed_by_user_id',
    ];

    const columns = useMemo(() => {
        if (section === 'reservas') return ['reservation_code', 'customer_name', 'guest_count', 'reservation_at', 'table_label', 'status', 'payment_status', 'advance_amount'];
        if (section === 'carta') return ['code', 'name', 'category', 'price', 'is_featured', 'is_popular', 'status'];
        if (section === 'soporte') return ['ticket_number', 'creator_name', 'subject', 'category', 'priority', 'status', 'attachment_url', 'created_at'];
        if (section === 'promociones') return ['name', 'promotion_type', 'code', 'discount_type', 'discount_value', 'per_customer_limit', 'usage_count'];
        const sample = rows[0] || {};
        return Object.keys(sample)
            .filter(
                (key) =>
                    !hiddenColumns.includes(key) &&
                    typeof sample[key] !== 'object'
            )
            .slice(0, 7);
    }, [rows, section]);

    const filtered = useMemo(
        () =>
            rows.filter((row) =>
                JSON.stringify(row)
                    .toLowerCase()
                    .includes(query.toLowerCase())
            ),
        [rows, query]
    );

    const pageSize = 10;
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    const current = filtered.slice((page - 1) * pageSize, page * pageSize);

    return (
        <section className="glass-panel glass-table overflow-hidden">
            <div className="px-6 py-4 border-b flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3>
                        {section === 'dashboard'
                            ? 'Últimos pedidos en tiempo real'
                            : 'Registros del módulo'}
                    </h3>
                    <span className="text-xs">
                        {filtered.length} registros sincronizados con la base de datos
                    </span>
                </div>
                <label className="flex items-center gap-2 text-xs">
                    <Search size={14} aria-hidden="true" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setPage(1);
                        }}
                        placeholder="Filtrar en esta tabla..."
                        className="bg-transparent border-0 outline-none text-xs w-48"
                    />
                </label>
            </div>

            {current.length ? (
                <>
                    <div className="admin-records-table-wrap">
                        <table className={`admin-records-table admin-records-table-${section} w-full text-left border-collapse`}>
                            <thead>
                                <tr className="border-b text-[11px] uppercase tracking-wider">
                                    {columns.map((key) => (
                                        <th
                                            key={key}
                                            className="px-5 py-3.5 font-semibold"
                                        >
                                            {COLUMN_LABELS[key] ||
                                                adminText(key, key, section)}
                                        </th>
                                    ))}
                                    {onEdit && (
                                        <th className="px-5 py-3.5 text-right font-semibold">
                                            Acción
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y text-xs">
                                {current.map((row, index) => (
                                    <tr
                                        key={row.id || index}
                                        className="transition-colors"
                                    >
                                        {columns.map((key) => (
                                            <td
                                                key={key}
                                                className="px-5 py-3.5 whitespace-nowrap"
                                                data-label={COLUMN_LABELS[key] || adminText(key, key, section)}
                                            >
                                                {[
                                                    'status',
                                                    'effective_status',
                                                    'operational_status',
                                                    'payment_status',
                                                    'verification_status',
                                                    'delivery_status',
                                                    'last_check_status',
                                                ].includes(key) ? (
                                                    <StatusBadge
                                                        value={row[key]}
                                                        field={key}
                                                        section={section}
                                                    />
                                                ) : key === 'attachment_url' ? (
                                                    row[key] ? (
                                                        <a
                                                            href={row[key]}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-blue-50 text-[#004797] hover:bg-blue-100 hover:text-blue-900 transition-colors border border-blue-200"
                                                            onClick={(e) => e.stopPropagation()}
                                                            title="Ver archivo o evidencia adjunta"
                                                        >
                                                            <Paperclip size={12} /> {String(row[key]).toLowerCase().endsWith('.pdf') ? 'Ver PDF' : 'Ver Imagen'}
                                                        </a>
                                                    ) : (
                                                        <span className="text-slate-400 text-xs">—</span>
                                                    )
                                                ) : (
                                                    display(
                                                        row[key],
                                                        key,
                                                        section
                                                    )
                                                )}
                                            </td>
                                        ))}
                                        {onEdit && (
                                            <td className="px-5 py-3.5 text-right whitespace-nowrap" data-label="Acción" data-action="true">
                                                <button
                                                    type="button"
                                                    onClick={() => onEdit(row)}
                                                    className="glass-row-action"
                                                >
                                                    <Pencil
                                                        size={13}
                                                        aria-hidden="true"
                                                    />
                                                    Gestionar
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {totalPages > 1 && (
                        <footer className="px-6 py-3.5 border-t flex items-center justify-between text-xs">
                            <span>
                                Página {page} de {totalPages}
                            </span>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    disabled={page <= 1}
                                    onClick={() => setPage((p) => p - 1)}
                                    className="glass-btn glass-btn-sm"
                                >
                                    Anterior
                                </button>
                                <button
                                    type="button"
                                    disabled={page >= totalPages}
                                    onClick={() => setPage((p) => p + 1)}
                                    className="glass-btn glass-btn-sm"
                                >
                                    Siguiente
                                </button>
                            </div>
                        </footer>
                    )}
                </>
            ) : (
                <div className="py-12 px-6 text-center">
                    <strong className="block text-sm">
                        Sin registros para mostrar
                    </strong>
                    <p className="text-xs mt-1">
                        La información aparecerá aquí en cuanto se registren operaciones.
                    </p>
                </div>
            )}
        </section>
    );
}

function Editor({ section, data, catalog, ingredients = [], editing, close }) {
    const { auth } = usePage().props;
    const defaults = {
        categorias: {
            action: editing ? 'category.update' : 'category.create', id: editing?.id || null,
            name: editing?.name || '', description: editing?.description || '',
        },
        insumos: {
            action: editing ? 'ingredient.update' : 'ingredient.create', id: editing?.id || null,
            name: editing?.name || '', description: editing?.description || '', unit: editing?.unit || 'kg',
            min_stock: editing?.min_stock || 0, cost_per_unit: editing?.cost_per_unit || 0,
            initial_stock: 0, allergen_ids: editing?.allergen_ids || [],
        },
        alergenos: {
            action: editing ? 'allergen.status' : 'allergen.create', id: editing?.id || null,
            name: editing?.name || '', description: editing?.description || '', status: editing?.status || 'active',
        },
        pedidos: {
            action: editing ? 'status' : 'create',
            id: editing?.id || '',
            status: editing?.status || 'Preparando',
            payment_status: editing?.payment_status || 'Pendiente',
            payment_method: editing?.payment_method || 'Efectivo',
            customer_name: editing?.customer_name || '',
            order_type: 'Mesa',
            table_label: 'Mesa 1',
            notes: '',
            items: [{ product_id: catalog?.products?.[0]?.id || 1, quantity: 1 }],
        },
        carta: {
            action: editing ? 'product.update' : 'product.create',
            entity: 'product',
            id: editing?.id || 0,
            code: editing?.code || '',
            name: editing?.name || '',
            category_id:
                editing?.category_id || catalog?.categories?.[0]?.id || 1,
            price: editing?.price ?? '25.00',
            emoji: editing?.emoji || '🐟',
            description: editing?.description || '',
            included_items: editing?.included_items || '',
            image_url: editing?.image_url || '',
            gallery: editing?.gallery?.length
                ? editing.gallery.map((g) =>
                      typeof g === 'string' ? g : g.image_url || ''
                  )
                : [],
            is_featured: Boolean(editing?.is_featured),
            is_popular: Boolean(editing?.is_popular),
            is_new: Boolean(editing?.is_new),
            status: editing?.status || 'available',
            ingredients: editing
                ? (editing.ingredients || []).map((item) => ({
                      ingredient_id: item.ingredient_id,
                      quantity: item.quantity,
                  }))
                : [],
            option_groups: (editing?.option_groups || []).map((grp) => ({
                name: grp.name || '',
                is_required: Boolean(grp.is_required),
                max_selections: grp.max_selections || 1,
                options: (grp.options || []).map((opt) => ({
                    name: opt.name || '',
                    price_delta: opt.price_delta ?? 0,
                    is_available: opt.is_available !== false,
                })),
            })),
        },
        inventario: {
            action: 'adjust',
            entity: 'ingredient_adjust',
            id: editing?.id || data.ingredients?.[0]?.id || 1,
            movement_type: 'entry',
            quantity: '1',
            unit_cost: editing?.cost_per_unit || '5.00',
            notes: 'Ajuste desde panel',
        },
        clientes: {
            action: editing ? 'customer.update' : 'customer.create',
            id: editing?.id || 0,
            full_name: editing?.full_name || '',
            document_type: editing?.document_type || 'SIN_DOCUMENTO',
            document_number: editing?.document_number || '',
            phone: editing?.phone || '',
            email: editing?.email || '',
            status: editing?.status || 'active',
        },
        reservas: {
            action: editing ? 'reservation.status' : 'reservation.create',
            id: editing?.id || '',
            status: editing?.status || 'Confirmada',
            table_id: editing?.table_id || data.tables?.[0]?.id || '',
            table_ids: editing?.tables?.map(table => table.id) || (data.tables?.[0] ? [data.tables[0].id] : []),
            customer_name: editing?.customer_name || '',
            phone: editing?.customer_phone || editing?.phone || '',
            guest_count: editing?.guest_count || 2,
            reservation_at: peruDateTime(editing?.reservation_at) || '',
            duration_minutes: 120,
            notes: '',
        },
        caja: {
            action: data.session ? 'close' : 'open',
            opening_amount: '200.00',
            counted_closing: '200.00',
            notes: 'Operación desde panel',
        },
        cocina: {
            action: 'kitchen_status',
            id: editing?.id || '',
            status:
                editing?.status === 'Pendiente'
                    ? 'Preparando'
                    : editing?.status === 'Preparando'
                      ? 'Listo'
                      : 'Listo',
        },
        delivery: {
            action: 'delivery_status',
            id: editing?.id || '',
            status: editing?.delivery_status === 'En reparto' ? 'Entregado' : 'En reparto',
            incident_notes: editing?.incident_notes || '',
            recipient_phone: editing?.recipient_phone || '', address_line: editing?.address_line || '',
            zone: editing?.zone || '', courier_user_id: editing?.courier_user_id || '',
            estimated_delivery_at: editing?.estimated_delivery_at?.replace(' ', 'T').slice(0, 16) || '',
        },
        compras: {
            action: 'supplier.save',
            id: editing?.id || 0,
            name: editing?.name || '',
            tax_id: editing?.tax_id || '',
            contact_name: editing?.contact_name || '',
            phone: editing?.phone || '',
            email: editing?.email || '',
            status: editing?.status || 'active',
        },
        promociones: {
            action: editing ? 'update' : 'create',
            starts_at: peruDateTime(editing?.starts_at) || peruDateTime(new Date().toISOString()),
            ends_at: editing?.ends_at ? peruDateTime(editing.ends_at) : '',
            id: editing?.id || 0,
            code: editing?.code || (editing?.promotion_type === 'automatic' ? '' : 'DESEGO15'),
            name: editing?.name || '',
            description: editing?.description || '',
            promotion_type: editing?.promotion_type || 'coupon',
            discount_type: editing?.discount_type || 'percentage',
            discount_value: editing?.discount_value || '10',
            max_discount: editing?.max_discount ?? '',
            min_subtotal: editing?.min_subtotal ?? '40',
            usage_limit: editing?.usage_limit ?? '',
            per_customer_limit: editing?.per_customer_limit ?? 1,
            status: editing?.status || 'active',
        },
        fidelizacion: {
            action: 'reward.save',
            starts_at: peruDateTime(editing?.starts_at),
            ends_at: editing?.ends_at ? peruDateTime(editing.ends_at) : '',
            id: editing?.id || null,
            reward_code: editing?.reward_code || `PREMIO-${Date.now()}`,
            product_id: editing?.product_id || null,
            name: editing?.name || '',
            points_cost: editing?.points_cost || 100,
            reward_type: editing?.reward_type || 'discount',
            discount_amount: editing?.discount_amount || '15.00',
            status: editing?.status || 'active',
        },
        equipo: {
            action: 'employee.save',
            employee_code: editing?.employee_code || `EMP-${Date.now()}`,
            branch_id: editing?.branch_id || null,
            user_id: editing?.user_id || null,
            hourly_rate: editing?.hourly_rate ?? '',
            id: editing?.id || 0,
            full_name: editing?.full_name || '',
            job_title: editing?.job_title || 'Cocina',
            phone: editing?.phone || '',
            email: editing?.email || '',
            employment_type: editing?.employment_type || 'full_time',
            hire_date:
                editing?.hire_date || peruDateTime().slice(0, 10),
            status: editing?.status || 'active',
        },
        usuarios: {
            action: editing ? 'update' : 'create',
            id: editing?.id || 0,
            name: editing?.name || '',
            email: editing?.email || '',
            password: '',
            role_id: editing?.role_id || data.roles?.find((role) => role.code === 'waiter')?.id || data.roles?.find((role) => !['admin', 'customer'].includes(role.code))?.id || '',
            status: editing?.status || 'active',
        },
        backups: { action: 'create_backup' },
        soporte: {
            action: editing ? 'support.update' : 'support.create',
            id: editing?.id || null,
            ticket_id: editing?.id || null,
            subject: editing?.subject || '',
            category: editing?.category || 'orders',
            priority: editing?.priority || 'normal',
            status: editing?.status || 'open',
            assigned_to: editing?.assigned_to || '',
            message: '',
            is_internal: false,
            attachment_url: '',
        },
    }[section];

    const modules = {
        categorias: 'catalog', insumos: 'catalog', alergenos: 'catalog',
        pedidos: 'orders',
        carta: 'catalog',
        inventario: 'inventory',
        clientes: 'customers',
        reservas: 'reservations',
        caja: 'cash',
        cocina: 'operations',
        delivery: 'operations',
        compras: 'purchasing',
        promociones: 'promotions',
        fidelizacion: 'loyalty',
        equipo: 'workforce',
        usuarios: 'users',
        backups: 'admin-tools',
        soporte: 'communications',
    };

    const form = useForm(defaults || {});
    const [uploadingImg, setUploadingImg] = useState(false);

    if (!defaults) {
        return null;
    }

    const uploadAdminFile = async (file, onUploaded) => {
        if (!file) {
            return;
        }
        setUploadingImg(true);
        try {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('folder', 'products');
            const csrf =
                document.querySelector('meta[name="csrf-token"]')?.content ||
                '';
            const res = await fetch('/admin/subir-imagen', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: fd,
            });
            const json = await res.json();
            if (json?.ok && json?.url) {
                onUploaded(json.url);
            }
        } catch {
            // Ignora errores de subida y mantiene el campo editable manualmente
        } finally {
            setUploadingImg(false);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform(values => {
            if (section === 'inventario') { return { ...values, ingredient_id: values.id }; }
            if (['promociones', 'fidelizacion'].includes(section)) {
                return { ...values, starts_at: new Date(`${values.starts_at}-05:00`).toISOString().slice(0, 16), ends_at: values.ends_at ? new Date(`${values.ends_at}-05:00`).toISOString().slice(0, 16) : '' };
            }
            return values;
        });
        form.post('/admin/acciones/' + modules[section], {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    const inputCls = 'glass-input';

    return (
        <div
            className="glass-modal-backdrop"
            onClick={close}
        >
            <form
                className="glass-editor"
                onClick={(e) => e.stopPropagation()}
                onSubmit={submit}
            >
                <header className="glass-editor-head">
                    <div>
                        <span className="glass-eyebrow">
                            GESTIÓN OPERATIVA
                        </span>
                        <h2>
                            {editing ? 'Actualizar registro' : 'Nuevo registro'}
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={close}
                        className="glass-icon-btn"
                        aria-label="Cerrar modal"
                    >
                        <X size={16} aria-hidden="true" />
                    </button>
                </header>

                <ErrorList errors={form.errors} />

                {['categorias', 'insumos', 'alergenos'].includes(section) && <>
                    <label className="glass-field">Nombre<input className={inputCls} required minLength={2} maxLength={section === 'categorias' ? 80 : 100} readOnly={section === 'alergenos' && !!editing} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label>
                    {!(section === 'alergenos' && editing) && <label className="glass-field">Descripción<textarea className={inputCls} maxLength={255} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></label>}
                    {section === 'insumos' && <>
                        <label className="glass-field">Unidad<select className={inputCls} value={form.data.unit} onChange={(event) => form.setData('unit', event.target.value)}>{['g', 'kg', 'ml', 'l', 'unidad', 'porción'].map(unit => <option key={unit}>{unit}</option>)}</select></label>
                        <div className="grid grid-cols-2 gap-3"><label className="glass-field">Stock mínimo<input className={inputCls} type="number" min="0" step="0.001" value={form.data.min_stock} onChange={(event) => form.setData('min_stock', event.target.value)} /></label><label className="glass-field">Costo por unidad (S/)<input className={inputCls} type="number" min="0" step="0.0001" value={form.data.cost_per_unit} onChange={(event) => form.setData('cost_per_unit', event.target.value)} /></label></div>
                        {!editing && auth?.user?.permissions?.includes('inventory.manage') && <label className="glass-field">Stock inicial<input className={inputCls} type="number" min="0" step="0.001" value={form.data.initial_stock} onChange={(event) => form.setData('initial_stock', event.target.value)} /></label>}
                        <fieldset><legend className="glass-field">Alérgenos asociados</legend>{data.allergens?.filter(allergen => allergen.status === 'active').map(allergen => <label className="glass-field" key={allergen.id}><span><input type="checkbox" checked={form.data.allergen_ids.includes(allergen.id)} onChange={() => form.setData('allergen_ids', form.data.allergen_ids.includes(allergen.id) ? form.data.allergen_ids.filter(id => id !== allergen.id) : [...form.data.allergen_ids, allergen.id])} /> {allergen.name}</span></label>)}</fieldset>
                    </>}
                    {section === 'alergenos' && editing && <label className="glass-field">Estado<select className={inputCls} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>}
                    {section === 'categorias' && editing && <button className="glass-btn" type="button" onClick={() => router.post('/admin/acciones/catalog', { action: 'category.status', id: editing.id, status: editing.status === 'active' ? 'inactive' : 'active' }, { preserveScroll: true, onSuccess: close, onError: (errors) => form.setError(errors) })}>{editing.status === 'active' ? 'Desactivar categoría' : 'Activar categoría'}</button>}
                </>}

                {section === 'pedidos' && !editing && (
                    <>
                        <label className="glass-field">
                            Cliente
                            <input
                                required
                                className={inputCls}
                                value={form.data.customer_name}
                                onChange={(e) =>
                                    form.setData('customer_name', e.target.value)
                                }
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Canal
                                <select
                                    className={inputCls}
                                    value={form.data.order_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'order_type',
                                            e.target.value
                                        )
                                    }
                                >
                                    <option value="Mesa">Salón</option>
                                    <option>Para llevar</option>
                                    <option>Delivery</option>
                                </select>
                            </label>
                            <label className="glass-field">
                                Mesa / Referencia
                                <input
                                    className={inputCls}
                                    value={form.data.table_label}
                                    onChange={(e) =>
                                        form.setData(
                                            'table_label',
                                            e.target.value
                                        )
                                    }
                                />
                            </label>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Plato
                                <select
                                    className={inputCls}
                                    value={form.data.items[0].product_id}
                                    onChange={(e) =>
                                        form.setData('items', [
                                            {
                                                ...form.data.items[0],
                                                product_id: Number(
                                                    e.target.value
                                                ),
                                            },
                                        ])
                                    }
                                >
                                    {catalog?.products?.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} ({money(p.price)})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="glass-field">
                                Cantidad
                                <input
                                    type="number"
                                    min={1}
                                    max={20}
                                    className={inputCls}
                                    value={form.data.items[0].quantity}
                                    onChange={(e) =>
                                        form.setData('items', [
                                            {
                                                ...form.data.items[0],
                                                quantity: Number(
                                                    e.target.value
                                                ),
                                            },
                                        ])
                                    }
                                />
                            </label>
                        </div>
                    </>
                )}

                {section === 'pedidos' && editing && (
                    <div className="grid grid-cols-2 gap-3">
                        <label className="glass-field">
                            Estado del pedido
                            <select
                                className={inputCls}
                                value={form.data.status}
                                onChange={(e) =>
                                    form.setData('status', e.target.value)
                                }
                            >
                                {[
                                    'Pendiente',
                                    'Preparando',
                                    'Listo',
                                    'En reparto',
                                    'Entregado',
                                    'Cancelado',
                                ].map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="glass-field">
                            Estado de pago
                            <select
                                className={inputCls}
                                value={form.data.payment_status}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_status',
                                        e.target.value
                                    )
                                }
                            >
                                <option value="Pendiente">Pendiente</option>
                                <option value="Pagado">Pagado</option>
                                <option value="Reembolsado">Reembolsado</option>
                            </select>
                        </label>
                    </div>
                )}

                {section === 'carta' && (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label className="glass-field">
                                Código comercial
                                <input
                                    className={inputCls}
                                    placeholder="Ej. PF001"
                                    maxLength={32}
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field sm:col-span-2">
                                Nombre del plato
                                <input
                                    required
                                    className={inputCls}
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            </label>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label className="glass-field">
                                Categoría
                                <select
                                    className={inputCls}
                                    value={form.data.category_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'category_id',
                                            Number(e.target.value)
                                        )
                                    }
                                >
                                    {(data.categories || catalog?.categories || []).map(
                                        (c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        )
                                    )}
                                </select>
                            </label>
                            <label className="glass-field">
                                Precio (S/)
                                <input
                                    type="number"
                                    step="0.10"
                                    min="0"
                                    required
                                    className={inputCls}
                                    value={form.data.price}
                                    onChange={(e) =>
                                        form.setData('price', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Estado
                                <select
                                    className={inputCls}
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                >
                                    <option value="available">Disponible</option>
                                    <option value="unavailable">
                                        No disponible
                                    </option>
                                </select>
                            </label>
                        </div>

                        <label className="glass-field">
                            Descripción comercial
                            <textarea
                                rows={2}
                                required
                                className={inputCls}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </label>

                        <label className="glass-field">
                            Platos incluidos (para Dúos, Tríos o Rondas marinas)
                            <input
                                className={inputCls}
                                placeholder="Ej. Defina aquí los platos incluidos cuando estén confirmados"
                                value={form.data.included_items}
                                onChange={(e) =>
                                    form.setData(
                                        'included_items',
                                        e.target.value
                                    )
                                }
                            />
                        </label>

                        <div className="glass-field">
                            <span>Imagen principal del plato (URL o archivo real)</span>
                            <div className="flex flex-wrap gap-2 items-center mt-1">
                                <input
                                    className={`${inputCls} flex-1 min-w-[200px]`}
                                    placeholder="/uploads/products/... o https://..."
                                    value={form.data.image_url}
                                    onChange={(e) =>
                                        form.setData('image_url', e.target.value)
                                    }
                                />
                                <label className="glass-btn cursor-pointer inline-flex items-center gap-1.5 text-xs">
                                    <Upload size={14} />
                                    <span>
                                        {uploadingImg
                                            ? 'Subiendo...'
                                            : 'Subir foto'}
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="hidden"
                                        onChange={(e) =>
                                            uploadAdminFile(
                                                e.target.files?.[0],
                                                (url) =>
                                                    form.setData(
                                                        'image_url',
                                                        url
                                                    )
                                            )
                                        }
                                    />
                                </label>
                            </div>
                        </div>

                        <div className="glass-field">
                            <div className="flex items-center justify-between">
                                <span>Galería adicional de imágenes</span>
                                <div className="flex gap-2">
                                    <label className="glass-btn glass-btn-sm cursor-pointer inline-flex items-center gap-1 text-xs">
                                        <Upload size={12} />
                                        <span>Subir a galería</span>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            className="hidden"
                                            onChange={(e) =>
                                                uploadAdminFile(
                                                    e.target.files?.[0],
                                                    (url) =>
                                                        form.setData(
                                                            'gallery',
                                                            [
                                                                ...(form.data
                                                                    .gallery ||
                                                                    []),
                                                                url,
                                                            ]
                                                        )
                                                )
                                            }
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        className="glass-btn glass-btn-sm text-xs"
                                        onClick={() =>
                                            form.setData('gallery', [
                                                ...(form.data.gallery || []),
                                                '',
                                            ])
                                        }
                                    >
                                        + URL
                                    </button>
                                </div>
                            </div>
                            {(form.data.gallery || []).map((gUrl, idx) => (
                                <div
                                    key={idx}
                                    className="flex items-center gap-2 mt-1.5"
                                >
                                    <input
                                        className={`${inputCls} flex-1`}
                                        placeholder="URL de imagen de galería"
                                        value={gUrl}
                                        onChange={(e) => {
                                            const copy = [
                                                ...(form.data.gallery || []),
                                            ];
                                            copy[idx] = e.target.value;
                                            form.setData('gallery', copy);
                                        }}
                                    />
                                    <button
                                        type="button"
                                        className="glass-btn glass-btn-sm"
                                        onClick={() =>
                                            form.setData(
                                                'gallery',
                                                form.data.gallery.filter(
                                                    (_, i) => i !== idx
                                                )
                                            )
                                        }
                                        aria-label="Quitar imagen"
                                    >
                                        <Trash2 size={13} />
                                    </button>
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-wrap gap-4 py-1 text-xs">
                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(form.data.is_featured)}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_featured',
                                            e.target.checked
                                        )
                                    }
                                />
                                <span>Plato destacado</span>
                            </label>
                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(form.data.is_popular)}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_popular',
                                            e.target.checked
                                        )
                                    }
                                />
                                <span>Popular en carta</span>
                            </label>
                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(form.data.is_new)}
                                    onChange={(e) =>
                                        form.setData('is_new', e.target.checked)
                                    }
                                />
                                <span>Nuevo ingreso</span>
                            </label>
                        </div>

                        {/* RECETA MULTI-INSUMO OPCIONAL */}
                        <div className="glass-field border-t pt-3">
                            <div className="flex items-center justify-between mb-1.5">
                                <span>
                                    Receta de insumos (opcional · controla stock y alérgenos)
                                </span>
                                {ingredients.length > 0 && (
                                    <button
                                        type="button"
                                        className="glass-btn glass-btn-sm text-xs"
                                        onClick={() =>
                                            form.setData('ingredients', [
                                                ...(form.data.ingredients ||
                                                    []),
                                                {
                                                    ingredient_id:
                                                        ingredients[0]?.id || 1,
                                                    quantity: '0.200',
                                                },
                                            ])
                                        }
                                    >
                                        + Agregar insumo
                                    </button>
                                )}
                            </div>
                            {(form.data.ingredients || []).length === 0 ? (
                                <p className="text-xs opacity-70">
                                    Sin receta asignada (disponibilidad controlada manualmente).
                                </p>
                            ) : (
                                (form.data.ingredients || []).map(
                                    (ingRow, idx) => (
                                        <div
                                            key={idx}
                                            className="grid grid-cols-12 gap-2 items-center mb-1.5"
                                        >
                                            <select
                                                className={`${inputCls} col-span-7`}
                                                value={ingRow.ingredient_id}
                                                onChange={(e) => {
                                                    const copy = [
                                                        ...form.data.ingredients,
                                                    ];
                                                    copy[idx] = {
                                                        ...copy[idx],
                                                        ingredient_id: Number(
                                                            e.target.value
                                                        ),
                                                    };
                                                    form.setData(
                                                        'ingredients',
                                                        copy
                                                    );
                                                }}
                                            >
                                                {ingredients.map((i) => (
                                                    <option
                                                        key={i.id}
                                                        value={i.id}
                                                    >
                                                        {i.name} (
                                                        {adminText(
                                                            i.unit,
                                                            'unit'
                                                        )}
                                                        )
                                                    </option>
                                                ))}
                                            </select>
                                            <input
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                required
                                                className={`${inputCls} col-span-4`}
                                                value={ingRow.quantity}
                                                onChange={(e) => {
                                                    const copy = [
                                                        ...form.data.ingredients,
                                                    ];
                                                    copy[idx] = {
                                                        ...copy[idx],
                                                        quantity: e.target.value,
                                                    };
                                                    form.setData(
                                                        'ingredients',
                                                        copy
                                                    );
                                                }}
                                            />
                                            <button
                                                type="button"
                                                className="glass-btn glass-btn-sm col-span-1 flex justify-center"
                                                onClick={() =>
                                                    form.setData(
                                                        'ingredients',
                                                        form.data.ingredients.filter(
                                                            (_, i) => i !== idx
                                                        )
                                                    )
                                                }
                                                aria-label="Quitar insumo"
                                            >
                                                <Trash2 size={13} />
                                            </button>
                                        </div>
                                    )
                                )
                            )}
                        </div>

                        {/* OPCIONES Y ADICIONALES */}
                        <div className="glass-field border-t pt-3">
                            <div className="flex items-center justify-between mb-1.5">
                                <span>
                                    Opciones y adicionales del plato (ej. Nivel de ají, Guarnición)
                                </span>
                                <button
                                    type="button"
                                    className="glass-btn glass-btn-sm text-xs"
                                    onClick={() =>
                                        form.setData('option_groups', [
                                            ...(form.data.option_groups || []),
                                            {
                                                name: 'Nivel de picante',
                                                is_required: false,
                                                max_selections: 1,
                                                options: [
                                                    {
                                                        name: 'Sin ají',
                                                        price_delta: 0,
                                                        is_available: true,
                                                    },
                                                ],
                                            },
                                        ])
                                    }
                                >
                                    + Grupo de opciones
                                </button>
                            </div>
                            {(form.data.option_groups || []).map(
                                (grp, gIdx) => (
                                    <div
                                        key={gIdx}
                                        className="p-3 rounded-xl border border-white/10 mb-2 space-y-2"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <input
                                                className={`${inputCls} flex-1 min-w-[150px]`}
                                                placeholder="Nombre del grupo (ej. Punto de ají)"
                                                value={grp.name}
                                                onChange={(e) => {
                                                    const copy = [
                                                        ...form.data
                                                            .option_groups,
                                                    ];
                                                    copy[gIdx] = {
                                                        ...copy[gIdx],
                                                        name: e.target.value,
                                                    };
                                                    form.setData(
                                                        'option_groups',
                                                        copy
                                                    );
                                                }}
                                            />
                                            <label className="text-xs inline-flex items-center gap-1">
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(
                                                        grp.is_required
                                                    )}
                                                    onChange={(e) => {
                                                        const copy = [
                                                            ...form.data
                                                                .option_groups,
                                                        ];
                                                        copy[gIdx] = {
                                                            ...copy[gIdx],
                                                            is_required:
                                                                e.target
                                                                    .checked,
                                                        };
                                                        form.setData(
                                                            'option_groups',
                                                            copy
                                                        );
                                                    }}
                                                />
                                                Obligatorio
                                            </label>
                                            <button
                                                type="button"
                                                className="glass-btn glass-btn-sm text-xs"
                                                onClick={() => {
                                                    const copy = [
                                                        ...form.data
                                                            .option_groups,
                                                    ];
                                                    copy[gIdx] = {
                                                        ...copy[gIdx],
                                                        options: [
                                                            ...(copy[gIdx]
                                                                .options || []),
                                                            {
                                                                name: '',
                                                                price_delta: 0,
                                                                is_available: true,
                                                            },
                                                        ],
                                                    };
                                                    form.setData(
                                                        'option_groups',
                                                        copy
                                                    );
                                                }}
                                            >
                                                + Opción
                                            </button>
                                            <button
                                                type="button"
                                                className="glass-btn glass-btn-sm"
                                                onClick={() =>
                                                    form.setData(
                                                        'option_groups',
                                                        form.data.option_groups.filter(
                                                            (_, i) => i !== gIdx
                                                        )
                                                    )
                                                }
                                            >
                                                <Trash2 size={13} />
                                            </button>
                                        </div>
                                        {(grp.options || []).map(
                                            (opt, oIdx) => (
                                                <div
                                                    key={oIdx}
                                                    className="grid grid-cols-12 gap-2 items-center"
                                                >
                                                    <input
                                                        className={`${inputCls} col-span-7`}
                                                        placeholder="Nombre opción"
                                                        value={opt.name}
                                                        onChange={(e) => {
                                                            const copy = [
                                                                ...form.data
                                                                    .option_groups,
                                                            ];
                                                            const opts = [
                                                                ...copy[gIdx]
                                                                    .options,
                                                            ];
                                                            opts[oIdx] = {
                                                                ...opts[oIdx],
                                                                name: e.target
                                                                    .value,
                                                            };
                                                            copy[gIdx] = {
                                                                ...copy[gIdx],
                                                                options: opts,
                                                            };
                                                            form.setData(
                                                                'option_groups',
                                                                copy
                                                            );
                                                        }}
                                                    />
                                                    <input
                                                        type="number"
                                                        step="0.50"
                                                        min="0"
                                                        className={`${inputCls} col-span-4`}
                                                        placeholder="+ S/ 0.00"
                                                        value={opt.price_delta}
                                                        onChange={(e) => {
                                                            const copy = [
                                                                ...form.data
                                                                    .option_groups,
                                                            ];
                                                            const opts = [
                                                                ...copy[gIdx]
                                                                    .options,
                                                            ];
                                                            opts[oIdx] = {
                                                                ...opts[oIdx],
                                                                price_delta:
                                                                    e.target
                                                                        .value,
                                                            };
                                                            copy[gIdx] = {
                                                                ...copy[gIdx],
                                                                options: opts,
                                                            };
                                                            form.setData(
                                                                'option_groups',
                                                                copy
                                                            );
                                                        }}
                                                    />
                                                    <button
                                                        type="button"
                                                        className="glass-btn glass-btn-sm col-span-1 flex justify-center"
                                                        onClick={() => {
                                                            const copy = [
                                                                ...form.data
                                                                    .option_groups,
                                                            ];
                                                            copy[gIdx] = {
                                                                ...copy[gIdx],
                                                                options: copy[
                                                                    gIdx
                                                                ].options.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        oIdx
                                                                ),
                                                            };
                                                            form.setData(
                                                                'option_groups',
                                                                copy
                                                            );
                                                        }}
                                                    >
                                                        <X size={12} />
                                                    </button>
                                                </div>
                                            )
                                        )}
                                    </div>
                                )
                            )}
                        </div>
                    </>
                )}

                {section === 'inventario' && (
                    <>
                        <label className="glass-field">
                            Insumo
                            <select
                                className={inputCls}
                                value={form.data.id}
                                onChange={(e) =>
                                    form.setData('id', Number(e.target.value))
                                }
                            >
                                {data.ingredients?.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.name} ({i.stock_quantity}{' '}
                                        {adminText(i.unit, 'unit')})
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Movimiento
                                <select
                                    className={inputCls}
                                    value={form.data.movement_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'movement_type',
                                            e.target.value
                                        )
                                    }
                                >
                                    <option value="entry">
                                        Ingreso de mercadería
                                    </option>
                                    <option value="exit">
                                        Salida a cocina
                                    </option>
                                    <option value="waste">Merma</option>
                                </select>
                            </label>
                            <label className="glass-field">
                                Cantidad
                                <input
                                    type="number"
                                    step="0.001"
                                    required
                                    className={inputCls}
                                    value={form.data.quantity}
                                    onChange={(e) =>
                                        form.setData('quantity', e.target.value)
                                    }
                                />
                            </label>
                        </div>
                    </>
                )}

                {section === 'clientes' && (
                    <>
                        <label className="glass-field">
                            Nombre completo
                            <input
                                required
                                className={inputCls}
                                value={form.data.full_name}
                                onChange={(e) =>
                                    form.setData('full_name', e.target.value)
                                }
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Celular
                                <input
                                    required
                                    className={inputCls}
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Correo
                                <input
                                    type="email"
                                    className={inputCls}
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </label>
                        </div>
                    </>
                )}

                {section === 'reservas' && !editing && (
                    <>
                        <label className="glass-field">
                            Cliente
                            <input
                                required
                                className={inputCls}
                                value={form.data.customer_name}
                                onChange={(e) =>
                                    form.setData(
                                        'customer_name',
                                        e.target.value
                                    )
                                }
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Celular
                                <input
                                    required
                                    className={inputCls}
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Personas
                                <input
                                    type="number"
                                    min={1}
                                    max={20}
                                    className={inputCls}
                                    value={form.data.guest_count}
                                    onChange={(e) =>
                                        form.setData(
                                            'guest_count',
                                            Number(e.target.value)
                                        )
                                    }
                                />
                            </label>
                        </div>
                        <label className="glass-field">
                            Fecha y hora
                            <input
                                type="datetime-local"
                                    min={new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date()).replace(' ', 'T')}
                                required
                                className={inputCls}
                                value={form.data.reservation_at}
                                onChange={(e) =>
                                    form.setData(
                                        'reservation_at',
                                        e.target.value
                                    )
                                }
                            />
                        </label>
                        <fieldset className="glass-field"><legend>Mesas asignadas</legend>{data.tables?.filter(table => table.is_active && table.operational_status !== 'maintenance').map(table => <label key={table.id} style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: 8 }}><input type="checkbox" checked={form.data.table_ids.includes(table.id)} onChange={() => form.setData('table_ids', form.data.table_ids.includes(table.id) ? form.data.table_ids.filter(id => id !== table.id) : [...form.data.table_ids, table.id])} />{table.label} · {table.capacity} asientos {table.join_group && `· Unible: ${table.join_group}`}</label>)}</fieldset>
                    </>
                )}

                {section === 'reservas' && editing && (
                    <label className="glass-field">
                        Estado de la reserva
                        <select
                            className={inputCls}
                            value={
                                form.data.status === 'CONFIRMED'
                                    ? 'Confirmada'
                                    : ['CHECKED_IN', 'IN_SERVICE'].includes(form.data.status)
                                      ? 'Sentada'
                                      : form.data.status === 'COMPLETED'
                                        ? 'Completada'
                                        : form.data.status === 'CANCELLED'
                                          ? 'Cancelada'
                                          : form.data.status === 'NO_SHOW'
                                            ? 'No asistió'
                                            : form.data.status
                            }
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {[
                                { value: 'Pendiente', label: 'Pendiente' },
                                { value: 'Confirmada', label: 'Confirmada (Anticipo abonado)' },
                                { value: 'Sentada', label: 'Sentada (En mesa / Servicio)' },
                                { value: 'Completada', label: 'Completada (Consumo pagado)' },
                                { value: 'Cancelada', label: 'Cancelada' },
                                { value: 'No asistió', label: 'No asistió (Retener anticipo S/ 10)' },
                            ].map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {section === 'caja' && (
                    <label className="glass-field">
                        {data.session
                            ? 'Efectivo contado al cierre (S/)'
                            : 'Monto inicial de apertura (S/)'}
                        <input
                            type="number"
                            step="0.10"
                            required
                            className={inputCls}
                            value={
                                data.session
                                    ? form.data.counted_closing
                                    : form.data.opening_amount
                            }
                            onChange={(e) =>
                                form.setData(
                                    data.session
                                        ? 'counted_closing'
                                        : 'opening_amount',
                                    e.target.value
                                )
                            }
                        />
                    </label>
                )}

                {['cocina', 'delivery'].includes(section) && (
                    <label className="glass-field">
                        Nuevo estado operativo
                        <select
                            className={inputCls}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {(section === 'cocina'
                                ? ['Preparando', 'Listo']
                                : ['En reparto', 'Entregado', 'Incidencia']
                            ).map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {section === 'delivery' && <>
                    <label className="glass-field">Notas de incidencia<textarea className={inputCls} required={form.data.status === 'Incidencia'} maxLength={500} value={form.data.incident_notes} onChange={(event) => form.setData('incident_notes', event.target.value)} /></label>
                    {auth?.user?.permissions?.includes('delivery.manage') && <>
                        <label className="glass-field">Repartidor asignado<select className={inputCls} value={form.data.courier_user_id} onChange={(event) => form.setData('courier_user_id', event.target.value ? Number(event.target.value) : '')}><option value="">Sin asignar</option>{data.couriers?.map(courier => <option key={courier.id} value={courier.id}>{courier.name}</option>)}</select></label>
                        <label className="glass-field">Teléfono del destinatario<input className={inputCls} maxLength={30} value={form.data.recipient_phone} onChange={(event) => form.setData('recipient_phone', event.target.value)} /></label>
                        <label className="glass-field">Dirección de entrega<input className={inputCls} maxLength={255} value={form.data.address_line} onChange={(event) => form.setData('address_line', event.target.value)} /></label>
                        <label className="glass-field">Hora estimada de entrega<input type="datetime-local" className={inputCls} value={form.data.estimated_delivery_at} onChange={(event) => form.setData('estimated_delivery_at', event.target.value)} /></label>
                        <button className="glass-btn" type="button" onClick={() => router.post('/admin/acciones/operations', { ...form.data, action: 'save_delivery' }, { preserveScroll: true, onSuccess: close, onError: (errors) => form.setError(errors) })}>Guardar asignación y dirección</button>
                    </>}
                </>}

                {section === 'compras' && (
                    <>
                        <label className="glass-field">
                            Razón social del proveedor
                            <input
                                required
                                className={inputCls}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                RUC
                                <input
                                    className={inputCls}
                                    value={form.data.tax_id}
                                    onChange={(e) =>
                                        form.setData('tax_id', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Teléfono
                                <input
                                    className={inputCls}
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </label>
                        </div>
                    </>
                )}

                {section === 'promociones' && (
                    <>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Tipo de promoción
                                <select
                                    className={inputCls}
                                    value={form.data.promotion_type}
                                    onChange={(e) => {
                                        const promotionType = e.target.value;
                                        form.setData({
                                            ...form.data,
                                            promotion_type: promotionType,
                                            code:
                                                promotionType === 'automatic'
                                                    ? ''
                                                    : form.data.code || 'DESEGO15',
                                        });
                                    }}
                                >
                                    <option value="coupon">Cupón con código</option>
                                    <option value="automatic">Automática</option>
                                </select>
                            </label>
                            {form.data.promotion_type === 'coupon' && (
                                <label className="glass-field">
                                    Código del cupón
                                    <input
                                        required
                                        className={inputCls}
                                        value={form.data.code}
                                        onChange={(e) =>
                                            form.setData('code', e.target.value)
                                        }
                                    />
                                </label>
                            )}
                            <label className="glass-field">
                                Tipo de descuento
                                <select
                                    className={inputCls}
                                    value={form.data.discount_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'discount_type',
                                            e.target.value
                                        )
                                    }
                                >
                                    <option value="percentage">
                                        Porcentaje (%)
                                    </option>
                                    <option value="fixed">
                                        Monto fijo (S/)
                                    </option>
                                </select>
                            </label>
                            <label className="glass-field">
                                {form.data.discount_type === 'percentage'
                                    ? 'Descuento (%)'
                                    : 'Descuento (S/)'}
                                <input
                                    type="number"
                                    required
                                    min="0.01"
                                    max={
                                        form.data.discount_type === 'percentage'
                                            ? 100
                                            : 1000000
                                    }
                                    step="0.01"
                                    className={inputCls}
                                    value={form.data.discount_value}
                                    onChange={(e) =>
                                        form.setData(
                                            'discount_value',
                                            e.target.value
                                        )
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Límite de usos por cliente
                                <select
                                    className={inputCls}
                                    value={form.data.per_customer_limit}
                                    onChange={(e) =>
                                        form.setData(
                                            'per_customer_limit',
                                            Number(e.target.value)
                                        )
                                    }
                                >
                                    <option value={1}>Una vez por cliente</option>
                                    <option value={0}>Sin límite de usos</option>
                                    {Number(form.data.per_customer_limit) > 1 && (
                                        <option value={form.data.per_customer_limit}>
                                            Mantener límite actual: {form.data.per_customer_limit}
                                        </option>
                                    )}
                                </select>
                                <small>
                                    El uso ilimitado por cliente no elimina la fecha de vigencia ni el límite total.
                                </small>
                            </label>
                            <label className="glass-field">
                                Compra mínima (S/)
                                <input
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    step="0.01"
                                    className={inputCls}
                                    value={form.data.min_subtotal}
                                    onChange={(e) =>
                                        form.setData('min_subtotal', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Límite total de usos
                                <input
                                    type="number"
                                    min="1"
                                    max="100000000"
                                    step="1"
                                    placeholder="Sin límite"
                                    className={inputCls}
                                    value={form.data.usage_limit}
                                    onChange={(e) =>
                                        form.setData('usage_limit', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Descuento máximo (S/)
                                <input
                                    type="number"
                                    min="0.01"
                                    max="1000000"
                                    step="0.01"
                                    placeholder="Sin tope"
                                    className={inputCls}
                                    value={form.data.max_discount}
                                    onChange={(e) =>
                                        form.setData('max_discount', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Válida desde
                                <input
                                    type="datetime-local"
                                    required
                                    className={inputCls}
                                    value={form.data.starts_at}
                                    onChange={(e) =>
                                        form.setData('starts_at', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Válida hasta
                                <input
                                    type="datetime-local"
                                    className={inputCls}
                                    value={form.data.ends_at}
                                    onChange={(e) =>
                                        form.setData('ends_at', e.target.value)
                                    }
                                />
                            </label>
                        </div>
                        <label className="glass-field">
                            Nombre de la promoción
                            <input
                                required
                                className={inputCls}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                        <label className="glass-field">
                            Descripción (opcional)
                            <textarea
                                maxLength={255}
                                className={inputCls}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </label>
                    </>
                )}

                {section === 'fidelizacion' && (
                    <>
                        <label className="glass-field">
                            Beneficio
                            <input
                                required
                                className={inputCls}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Puntos requeridos
                                <input
                                    type="number"
                                    required
                                    className={inputCls}
                                    value={form.data.points_cost}
                                    onChange={(e) =>
                                        form.setData(
                                            'points_cost',
                                            Number(e.target.value)
                                        )
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Valor (S/)
                                <input
                                    type="number"
                                    step="0.10"
                                    required
                                    className={inputCls}
                                    value={form.data.discount_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'discount_amount',
                                            e.target.value
                                        )
                                    }
                                />
                            </label>
                        </div>
                    </>
                )}

                {section === 'equipo' && (
                    <>
                        <label className="glass-field">
                            Colaborador
                            <input
                                required
                                className={inputCls}
                                value={form.data.full_name}
                                onChange={(e) =>
                                    form.setData('full_name', e.target.value)
                                }
                            />
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Puesto
                                <input
                                    required
                                    className={inputCls}
                                    value={form.data.job_title}
                                    onChange={(e) =>
                                        form.setData(
                                            'job_title',
                                            e.target.value
                                        )
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Celular
                                <input
                                    className={inputCls}
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </label>
                        </div>
                    </>
                )}

                {section === 'equipo' && <>
                    <label className="glass-field">Cuenta del sistema vinculada<select className={inputCls} value={form.data.user_id || ''} onChange={(event) => form.setData('user_id', event.target.value ? Number(event.target.value) : null)}><option value="">Sin cuenta vinculada</option>{data.users?.map(user => <option key={user.id} value={user.id}>{user.name} · {user.role_name}</option>)}</select></label>
                    {auth?.user?.permissions?.includes('users.view') && <Link href="/admin/seccion/usuarios" className="glass-btn">Gestionar cuentas y asignar roles</Link>}
                    <label className="glass-field">Sucursal<select className={inputCls} value={form.data.branch_id || ''} onChange={(event) => form.setData('branch_id', event.target.value ? Number(event.target.value) : null)}><option value="">Sin sucursal</option>{data.branches?.map(branch => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
                </>}

                {['promociones', 'fidelizacion'].includes(section) && (
                    <div className="grid grid-cols-2 gap-3"><label className="glass-field">Inicio de vigencia (Perú)<input type="datetime-local" className={inputCls} required value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} /></label><label className="glass-field">Fin de vigencia (opcional)<input type="datetime-local" className={inputCls} min={form.data.starts_at} value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} /></label></div>
                )}

                {section === 'usuarios' && (
                    <>
                        <label className="glass-field">
                            Nombre
                            <input
                                required
                                className={inputCls}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                        <label className="glass-field">
                            Correo corporativo
                            <input
                                type="email"
                                required
                                className={inputCls}
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </label>
                        {!editing && (
                            <label className="glass-field">
                                Contraseña inicial
                                <input
                                    type="password"
                                    required
                                    minLength={10}
                                    className={inputCls}
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                />
                            </label>
                        )}
                        <label className="glass-field">
                            Rol asignado
                            <select
                                className={inputCls}
                                value={form.data.role_id}
                                onChange={(e) =>
                                    form.setData(
                                        'role_id',
                                        Number(e.target.value)
                                    )
                                }
                            >
                                {data.roles?.filter((r) => r.code !== 'admin' || auth?.user?.role_code === 'admin').map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {adminText(r.name, 'role_name')}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {editing && !editing.is_self && auth?.user?.permissions?.includes('users.deactivate') && (
                            <button type="button" className="glass-btn" onClick={() => router.post('/admin/acciones/users', { action: 'status', id: editing.id, status: editing.status === 'active' ? 'inactive' : 'active' }, { preserveScroll: true, onSuccess: close })}>{editing.status === 'active' ? 'Desactivar cuenta' : 'Activar cuenta'}</button>
                        )}
                    </>
                )}

                {section === 'backups' && (
                    <p className="glass-muted-text">
                        Se generará una copia cifrada instantánea de la base de datos MySQL con firma de integridad SHA-256.
                    </p>
                )}

                {section === 'soporte' && editing && (
                    <>
                        <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2 text-xs mb-3">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <div className="flex items-center gap-2">
                                    <span className="font-bold text-sm tracking-wide text-[#004797]">
                                        #{editing.ticket_number || `TCK-${String(editing.id).padStart(6, '0')}`}
                                    </span>
                                    <span className="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 font-medium text-[11px]">
                                        {adminText(editing.category, 'category', 'soporte')}
                                    </span>
                                </div>
                                <StatusBadge value={form.data.status || editing.status} field="status" section="soporte" />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                                <div>
                                    <strong className="text-slate-800">Cliente:</strong> {editing.creator_name || 'Cliente registrado'}
                                    {editing.creator_email && <span className="block text-[11px] text-slate-500">{editing.creator_email}</span>}
                                </div>
                                <div>
                                    <strong className="text-slate-800">Registrado:</strong> {display(editing.created_at, 'created_at')}
                                </div>
                            </div>

                            <div className="pt-1">
                                <strong className="text-slate-800">Asunto:</strong>{' '}
                                <span className="text-slate-900 font-semibold">{editing.subject}</span>
                            </div>
                        </div>

                        <label className="glass-field">
                            Estado *
                            <select
                                className={inputCls}
                                value={form.data.status === 'closed' ? 'resolved' : form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)}
                                required
                            >
                                <option value="open">Pendiente</option>
                                <option value="in_progress">En atención</option>
                                <option value="waiting_user">Esperando cliente</option>
                                <option value="resolved">Resuelto</option>
                            </select>
                        </label>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="glass-field">
                                Prioridad
                                <select
                                    className={inputCls}
                                    value={form.data.priority}
                                    onChange={(e) => form.setData('priority', e.target.value)}
                                >
                                    <option value="low">Baja</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </label>

                            <label className="glass-field">
                                Asignado a
                                <select
                                    className={inputCls}
                                    value={form.data.assigned_to || ''}
                                    onChange={(e) => form.setData('assigned_to', e.target.value ? Number(e.target.value) : '')}
                                >
                                    <option value="">Sin asignar (Todo el equipo)</option>
                                    {(data.users || []).map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name} {u.email ? `(${u.email})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        <div className="glass-field">
                            <label className="text-xs font-semibold text-slate-700 flex items-center gap-1.5 mb-1.5">
                                <Paperclip size={14} className="text-[#004797]" />
                                Archivos, imágenes y documentos adjuntos del cliente
                            </label>
                            {(() => {
                                const attachments = [];
                                if (editing.attachment_url) {
                                    attachments.push({
                                        url: editing.attachment_url,
                                        title: 'Archivo adjunto inicial',
                                        author: editing.creator_name || 'Cliente',
                                    });
                                }
                                (editing.messages || []).forEach((m, idx) => {
                                    if (m.attachment_url && !attachments.some((item) => item.url === m.attachment_url)) {
                                        attachments.push({
                                            url: m.attachment_url,
                                            title: `Adjunto en respuesta #${idx + 1}`,
                                            author: m.author_name || 'Cliente',
                                        });
                                    }
                                });

                                if (attachments.length === 0) {
                                    return (
                                        <div className="p-3 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs text-slate-500 text-center">
                                            El cliente no adjuntó archivos ni documentos en este ticket.
                                        </div>
                                    );
                                }

                                return (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        {attachments.map((att, i) => {
                                            const isImage = /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(att.url);
                                            return (
                                                <div key={i} className="flex items-center gap-3 p-2 bg-white rounded-lg border border-slate-200 shadow-xs">
                                                    {isImage ? (
                                                        <a
                                                            href={att.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="block w-14 h-14 rounded overflow-hidden flex-shrink-0 border border-slate-200 relative group"
                                                            title="Clic para ver imagen en tamaño real"
                                                        >
                                                            <img
                                                                src={att.url}
                                                                alt={att.title}
                                                                className="w-full h-full object-cover group-hover:scale-110 transition-transform"
                                                            />
                                                        </a>
                                                    ) : (
                                                        <div className="w-14 h-14 rounded bg-red-50 text-red-600 flex items-center justify-center font-bold text-xs flex-shrink-0 border border-red-200">
                                                            PDF
                                                        </div>
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <span className="block text-xs font-semibold text-slate-800 truncate" title={att.title}>
                                                            {att.title}
                                                        </span>
                                                        <small className="block text-[10px] text-slate-500">
                                                            Por: {att.author}
                                                        </small>
                                                        <a
                                                            href={att.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 mt-1 text-[11px] font-semibold text-[#004797] hover:underline"
                                                        >
                                                            {isImage ? 'Ver imagen completa' : 'Abrir documento PDF'} &rarr;
                                                        </a>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                );
                            })()}
                        </div>

                        {editing.messages && editing.messages.length > 0 && (
                            <div className="glass-field">
                                <label className="text-xs font-semibold text-slate-700 flex items-center gap-1.5 mb-1.5">
                                    <MessageSquare size={14} className="text-[#004797]" />
                                    Historial de mensajes ({editing.messages.length})
                                </label>
                                <div className="max-h-48 overflow-y-auto space-y-2 p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                                    {editing.messages.map((m) => {
                                        const isInternal = Boolean(m.is_internal);
                                        const isStaff = !isInternal && (m.author_name === auth?.user?.name || m.author_id === auth?.user?.id);
                                        return (
                                            <div
                                                key={m.id}
                                                className={`p-2.5 rounded-lg border ${
                                                    isInternal
                                                        ? 'bg-amber-50 border-amber-200 text-amber-950'
                                                        : isStaff
                                                          ? 'bg-blue-50 border-blue-200 text-blue-950'
                                                          : 'bg-white border-slate-200 text-slate-800'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between gap-2 mb-1">
                                                    <strong className="text-xs">
                                                        {m.author_name}
                                                        {isInternal && (
                                                            <span className="ml-1.5 text-[10px] px-1.5 py-0.2 rounded bg-amber-200 text-amber-800 font-normal">
                                                                Nota interna
                                                            </span>
                                                        )}
                                                    </strong>
                                                    <small className="text-[10px] text-slate-500">{display(m.created_at, 'created_at')}</small>
                                                </div>
                                                <p className="whitespace-pre-wrap leading-relaxed m-0 text-xs">{m.message}</p>
                                                {m.attachment_url && (
                                                    <a
                                                        href={m.attachment_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 mt-1.5 text-[11px] font-semibold text-[#004797] hover:underline"
                                                    >
                                                        <Paperclip size={11} /> Ver adjunto del mensaje
                                                    </a>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        <label className="glass-field">
                            Responder al cliente o agregar nota de seguimiento (opcional)
                            <textarea
                                rows={3}
                                className={inputCls}
                                placeholder="Escribe la respuesta para el cliente o notas de la resolución del caso..."
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                            />
                            <label className="flex items-center gap-2 mt-2 text-xs text-slate-700 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={Boolean(form.data.is_internal)}
                                    onChange={(e) => form.setData('is_internal', e.target.checked)}
                                    className="rounded text-[#004797] focus:ring-[#004797]"
                                />
                                <span>Nota interna (solo visible para el equipo, no se notifica al cliente)</span>
                            </label>
                        </label>
                    </>
                )}

                {section === 'soporte' && !editing && (
                    <>
                        <label className="glass-field">
                            Asunto del ticket *
                            <input
                                required
                                className={inputCls}
                                value={form.data.subject}
                                onChange={(e) => form.setData('subject', e.target.value)}
                                placeholder="Ej. Consulta sobre pedido o incidencia..."
                            />
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label className="glass-field">
                                Categoría *
                                <select
                                    className={inputCls}
                                    value={form.data.category}
                                    onChange={(e) => form.setData('category', e.target.value)}
                                >
                                    <option value="orders">Pedidos y ventas</option>
                                    <option value="technical">Problema técnico</option>
                                    <option value="reservations">Reservas</option>
                                    <option value="loyalty">Puntos y Club DeSego</option>
                                    <option value="billing">Pagos y comprobantes</option>
                                    <option value="other">Otro</option>
                                </select>
                            </label>
                            <label className="glass-field">
                                Prioridad *
                                <select
                                    className={inputCls}
                                    value={form.data.priority}
                                    onChange={(e) => form.setData('priority', e.target.value)}
                                >
                                    <option value="low">Baja</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </label>
                        </div>
                        <label className="glass-field">
                            Detalle / Descripción del caso *
                            <textarea
                                rows={4}
                                required
                                className={inputCls}
                                value={form.data.message}
                                onChange={(e) => form.setData('message', e.target.value)}
                                placeholder="Describe el caso o solicitud detalladamente..."
                            />
                        </label>
                    </>
                )}

                <div className="glass-editor-actions">
                    <button
                        type="button"
                        onClick={close}
                        className="glass-btn"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="glass-btn glass-btn-primary"
                    >
                        {form.processing
                            ? 'Guardando...'
                            : editing && section === 'soporte'
                              ? 'Actualizar estado y ticket'
                              : 'Guardar en base de datos'}
                    </button>
                </div>
            </form>
        </div>
    );
}

export default function Admin({
    section,
    title,
    data = {},
    navigation = [],
    catalog,
    ingredients = [],
}) {
    const { auth, flash } = usePage().props;
    const [editor, setEditor] = useState(false);
    const [editing, setEditing] = useState(null);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [moduleQuery, setModuleQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const contentRef = useRef(null);

    useEffect(() => {
        if (contentRef.current) {
            contentRef.current.scrollTop = 0;
        }
    }, [section]);

    useEffect(() => {
        const timer = setInterval(
            () => router.reload({ only: ['data'], preserveScroll: true }),
            30000
        );
        return () => clearInterval(timer);
    }, [section]);

    const rows = useMemo(() => {
        const primaryKeys = { categorias: 'categories', insumos: 'ingredients', alergenos: 'allergens', pedidos: 'orders', carta: 'products', inventario: 'ingredients', clientes: 'customers', reservas: 'reservations', cocina: 'orders', delivery: 'orders', compras: 'suppliers', promociones: 'promotions', fidelizacion: 'rewards', equipo: 'employees', usuarios: 'users', soporte: 'tickets' };
        const key = primaryKeys[section] || Object.keys(data).find((k) => Array.isArray(data[k]));
        return key ? data[key] || [] : [];
    }, [data, section]);

    const canCreate = {
        categorias: 'categories.manage', insumos: 'ingredients.manage', alergenos: 'allergens.manage',
        pedidos: 'orders.create',
        carta: 'products.manage',
        inventario: 'inventory.manage',
        clientes: 'customers.manage',
        reservas: 'reservations.manage',
        caja: 'cash.manage',
        compras: 'suppliers.manage',
        promociones: 'promotions.manage',
        fidelizacion: 'loyalty.manage',
        equipo: 'employees.manage',
        usuarios: 'users.create',
        backups: 'backups.create',
        soporte: 'support.create',
    };
    const canEdit = { ...canCreate, pedidos: 'orders.update', cocina: 'kitchen.manage', delivery: 'delivery.update', usuarios: 'users.update', soporte: 'support.reply', caja: null, backups: null };

    const firstName = useMemo(() => {
        const full = auth?.user?.name || 'Administrador';
        return full.trim().split(/\s+/)[0] || 'Administrador';
    }, [auth?.user?.name]);

    const initials = useMemo(() => {
        const full = auth?.user?.name || 'AD';
        return (
            full
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((p) => p[0]?.toUpperCase() || '')
                .join('') || 'AD'
        );
    }, [auth?.user?.name]);

    const filteredModules = useMemo(() => {
        const q = moduleQuery.trim().toLowerCase();
        if (!q) {
            return navigation.slice(0, 8);
        }
        return navigation.filter(
            (m) =>
                adminModuleTitle(m).toLowerCase().includes(q) ||
                m.key.toLowerCase().includes(q)
        );
    }, [moduleQuery, navigation]);

    const week = useMemo(() => buildWeek(data.daily_sales), [data.daily_sales]);
    const weekTotal = useMemo(() => week.reduce((sum, day) => sum + day.total, 0), [week]);
    const quickLinks = useMemo(() => navigation.filter((item) => item.key !== 'dashboard').slice(0, 8), [navigation]);

    const currentSectionTitle =
        section === 'herramientas'
            ? 'Herramientas'
            : adminModuleTitle({ key: section, title });

    return (
        <div id="app" className="app-shell dg-glass-admin">
            <Head title={`DESEGO · ${currentSectionTitle}`} />

            <div className="glass-ambient-scene" aria-hidden="true">
                <span className="glass-orb glass-orb-1" />
                <span className="glass-orb glass-orb-2" />
                <span className="glass-orb glass-orb-3" />
                <span className="glass-orb glass-orb-4" />
                <span className="glass-ambient-grid" />
            </div>

            <div
                id="dashboardScreen"
                className={`dashboard-screen ${
                    mobileSidebarOpen ? 'is-sidebar-mobile-open' : ''
                }`}
            >
                <div
                    className="sidebar-mobile-backdrop"
                    onClick={() => setMobileSidebarOpen(false)}
                />

                {/* SIDEBAR GLASSMORPHISM */}
                <AdminNavigation
                    navigation={navigation}
                    section={section}
                    user={auth?.user}
                    open={mobileSidebarOpen}
                    onClose={() => setMobileSidebarOpen(false)}
                />

                {/* WORKSPACE PRINCIPAL */}
                <div className="enterprise-workspace">
                    {/* TOPBAR GLASSMORPHISM */}
                    <header className="smart-topbar">
                        <div className="smart-topbar-left">
                            <button
                                id="admin-navigation-toggle"
                                type="button"
                                className="mobile-sidebar-btn"
                                onClick={() => setMobileSidebarOpen(true)}
                                aria-label="Abrir navegación"
                                aria-expanded={mobileSidebarOpen}
                                aria-controls="admin-navigation"
                            >
                                ☰
                            </button>
                            <div>
                                <div className="smart-greeting-row">
                                    <h1 className="smart-greeting-title">
                                        Hola, {firstName}
                                    </h1>
                                    <span className="glass-live-pill">
                                        <i aria-hidden="true" />
                                        En línea
                                    </span>
                                </div>
                                <p className="smart-greeting-meta">
                                    DESEGO Cevichería ·{' '}
                                    <strong>{currentSectionTitle}</strong>
                                </p>
                            </div>
                        </div>

                        <div className="smart-topbar-right">
                            <div className="smart-search-wrapper">
                                <span
                                    className="smart-search-icon"
                                    aria-hidden="true"
                                >
                                    <Search size={14} />
                                </span>
                                <input
                                    type="search"
                                    className="smart-search-input"
                                    placeholder="Buscar módulo..."
                                    value={moduleQuery}
                                    onFocus={() => setSearchOpen(true)}
                                    onBlur={() =>
                                        setTimeout(
                                            () => setSearchOpen(false),
                                            180
                                        )
                                    }
                                    onChange={(e) => {
                                        setModuleQuery(e.target.value);
                                        setSearchOpen(true);
                                    }}
                                />
                                {searchOpen && (
                                    <div className="smart-search-dropdown">
                                        {filteredModules.length ? (
                                            filteredModules.map((mod) => (
                                                <button
                                                    key={mod.key}
                                                    type="button"
                                                    className="smart-search-option"
                                                    onMouseDown={() => {
                                                        setSearchOpen(false);
                                                        setModuleQuery('');
                                                        router.visit(
                                                            mod.key ===
                                                                'dashboard'
                                                                ? '/admin'
                                                                : `/admin/seccion/${mod.key}`
                                                        );
                                                    }}
                                                >
                                                    <span>
                                                        {adminModuleTitle(mod)}
                                                    </span>
                                                    <small>Ir →</small>
                                                </button>
                                            ))
                                        ) : (
                                            <div className="glass-search-empty">
                                                Sin coincidencias
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            <Link
                                href="/"
                                className="smart-store-pill"
                                title="Abrir Tienda Web"
                            >
                                Tienda ↗
                            </Link>

                            <Link
                                href="/admin/seccion/reservas"
                                className="smart-icon-btn flex items-center justify-center"
                                title="Reservas y salón"
                            >
                                <CalendarDays size={16} aria-hidden="true" />
                            </Link>

                            <Link
                                href="/admin/seccion/soporte"
                                className="smart-icon-btn flex items-center justify-center"
                                title="Notificaciones y soporte"
                            >
                                <Bell size={16} aria-hidden="true" />
                            </Link>

                            <div
                                className="smart-avatar-circle"
                                title={`${auth?.user?.name || 'Administrador'} · ${adminText(auth?.user?.role_name, 'role_name')}`}
                            >
                                {initials}
                            </div>
                        </div>
                    </header>

                    {/* CONTENIDO PRINCIPAL */}
                    <main className="screen-content" ref={contentRef}>
                        {flash?.success && (
                            <div
                                className="glass-flash"
                                role="status"
                            >
                                <span>✓ {flash.success}</span>
                            </div>
                        )}

                        {section === 'dashboard' ? (
                            <>
                                <div className="glass-page-heading">
                                    <div>
                                        <span className="glass-eyebrow">
                                            PANEL DE CONTROL
                                        </span>
                                        <h2>Vista general</h2>
                                        <p>
                                            Estado operativo en vivo de ventas,
                                            pedidos, inventario y salón del
                                            restaurante.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="glass-refresh glass-btn-primary"
                                        onClick={() =>
                                            router.reload({ only: ['data'] })
                                        }
                                    >
                                        <RefreshCw
                                            size={15}
                                            aria-hidden="true"
                                        />
                                        <span>Actualizar</span>
                                    </button>
                                </div>

                                {/* INDICADORES CLAVE */}
                                <div className="glass-metrics">
                                    <article className="glass-panel glass-metric glass-metric-featured">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <CircleDollarSign size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">Cobrado hoy</span>
                                        </div>
                                        <h2>Ventas de hoy</h2>
                                        <strong className="glass-metric-value">{money(data.sales || 0)}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '82%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <ClipboardList size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">Hoy</span>
                                        </div>
                                        <h2>Pedidos hoy</h2>
                                        <strong className="glass-metric-value">{data.orders_today ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '68%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <Clock3 size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">En curso</span>
                                        </div>
                                        <h2>Pedidos activos</h2>
                                        <strong className="glass-metric-value">{data.pending ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '54%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <CircleDollarSign size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">Este mes</span>
                                        </div>
                                        <h2>Ventas este mes</h2>
                                        <strong className="glass-metric-value">{money(data.monthly_sales || 0)}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '76%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <CalendarDays size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">Vigentes</span>
                                        </div>
                                        <h2>Reservas activas</h2>
                                        <strong className="glass-metric-value">{data.pending_reservations ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '60%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <UsersRound size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">En CRM</span>
                                        </div>
                                        <h2>Clientes registrados</h2>
                                        <strong className="glass-metric-value">{data.customers ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '90%' }} /></div>
                                    </article>
                                </div>

                                {/* PRODUCTOS MÁS VENDIDOS Y MODALIDAD */}
                                {(data.top_products?.length > 0 || data.orders_by_modality?.length > 0) && (
                                    <div className="glass-grid-secondary mb-5">
                                        <section className="glass-panel glass-panel-pad" aria-label="Platos más vendidos">
                                            <div className="glass-panel-head">
                                                <div>
                                                    <h3 className="glass-section-title">
                                                        <Flame size={18} aria-hidden="true" />
                                                        <span>Platos más vendidos</span>
                                                    </h3>
                                                    <p className="glass-panel-sub">Ranking histórico de unidades e ingresos</p>
                                                </div>
                                                <Link href="/admin/seccion/carta" className="glass-link">
                                                    Ver carta →
                                                </Link>
                                            </div>
                                            {data.top_products?.length ? (
                                                <ul className="glass-stock-list">
                                                    {data.top_products.map((tp, idx) => (
                                                        <li key={tp.product_id || idx}>
                                                            <div>
                                                                <strong>#{idx + 1} · {tp.product_name}</strong>
                                                                <small>{tp.units_sold} porciones vendidas</small>
                                                            </div>
                                                            <span className="glass-stock-qty">{money(tp.revenue || 0)}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="glass-empty">Aún no se registran ventas de platos.</p>
                                            )}
                                        </section>

                                        <section className="glass-panel glass-panel-pad" aria-label="Pedidos por modalidad">
                                            <div className="glass-panel-head">
                                                <div>
                                                    <h3 className="glass-section-title">
                                                        <Award size={18} aria-hidden="true" />
                                                        <span>Pedidos por modalidad</span>
                                                    </h3>
                                                    <p className="glass-panel-sub">Delivery, recojo en local y salón</p>
                                                </div>
                                            </div>
                                            {data.orders_by_modality?.length ? (
                                                <ul className="glass-stock-list">
                                                    {data.orders_by_modality.map((m, idx) => (
                                                        <li key={m.order_type || idx}>
                                                            <div>
                                                                <strong>{m.order_type}</strong>
                                                                <small>{m.total_orders} pedidos registrados</small>
                                                            </div>
                                                            <span className="glass-stock-qty">{money(m.revenue || 0)}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="glass-empty">Sin pedidos acumulados por modalidad.</p>
                                            )}
                                        </section>
                                    </div>
                                )}

                                {/* GRÁFICAS */}
                                <div className="glass-grid-main">
                                    <section className="glass-panel glass-panel-pad" aria-label="Ingresos de los últimos 7 días">
                                        <div className="glass-panel-head">
                                            <div>
                                                <h3 className="glass-section-title">
                                                    <BarChart3 size={18} aria-hidden="true" />
                                                    <span>Ingresos de los últimos 7 días</span>
                                                </h3>
                                                <p className="glass-panel-sub">Evolución diaria de pedidos pagados</p>
                                                <strong className="glass-chart-total">{money(weekTotal)}</strong>
                                            </div>
                                            {navigation.some((n) => n.key === 'reportes') && (
                                                <Link href="/admin/seccion/reportes" className="glass-link">
                                                    Ver reportes →
                                                </Link>
                                            )}
                                        </div>
                                        <SalesChart days={week} />
                                    </section>

                                    <section className="glass-panel glass-panel-pad" aria-label="Estado de los últimos pedidos">
                                        <div className="glass-panel-head">
                                            <div>
                                                <h3 className="glass-section-title">
                                                    <ClipboardList size={18} aria-hidden="true" />
                                                    <span>Estado de pedidos</span>
                                                </h3>
                                                <p className="glass-panel-sub">Distribución de los últimos pedidos</p>
                                            </div>
                                        </div>
                                        <StatusDonut orders={data.recent_orders || []} />
                                    </section>
                                </div>

                                {/* STOCK Y ACCESOS RÁPIDOS */}
                                <div className="glass-grid-secondary">
                                    <section className="glass-panel glass-panel-pad" aria-label="Alertas de stock mínimo">
                                        <div className="glass-panel-head">
                                            <div>
                                                <h3 className="glass-section-title">
                                                    <AlertTriangle size={18} aria-hidden="true" />
                                                    <span>Alertas de stock</span>
                                                </h3>
                                                <p className="glass-panel-sub">Insumos en o por debajo del mínimo</p>
                                            </div>
                                            {navigation.some((n) => n.key === 'inventario') && (
                                                <Link href="/admin/seccion/inventario" className="glass-link">
                                                    Inventario →
                                                </Link>
                                            )}
                                        </div>
                                        {data.low_stock?.length ? (
                                            <ul className="glass-stock-list">
                                                {data.low_stock.slice(0, 5).map((item) => (
                                                    <li key={item.id}>
                                                        <div>
                                                            <strong>{item.name}</strong>
                                                            <small>
                                                                Mínimo: {Number(item.min_stock).toFixed(2)} {adminText(item.unit, 'unit')}
                                                            </small>
                                                        </div>
                                                        <span className="glass-stock-qty">
                                                            {Number(item.stock_quantity).toFixed(2)} {adminText(item.unit, 'unit')}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="glass-empty">Sin alertas: todos los insumos superan el stock mínimo.</p>
                                        )}
                                    </section>

                                    <section className="glass-panel glass-panel-pad" aria-label="Accesos rápidos">
                                        <div className="glass-panel-head">
                                            <div>
                                                <h3 className="glass-section-title">
                                                    <ArrowUpRight size={18} aria-hidden="true" />
                                                    <span>Accesos rápidos</span>
                                                </h3>
                                                <p className="glass-panel-sub">Salta directamente a los módulos de tu rol</p>
                                            </div>
                                            <Link href="/admin/herramientas" className="glass-link">
                                                Todas las herramientas →
                                            </Link>
                                        </div>
                                        <div className="glass-quick-grid">
                                            {quickLinks.map((item) => (
                                                <Link key={item.key} href={`/admin/seccion/${item.key}`} className="glass-quick-link">
                                                    <span>{adminModuleTitle(item)}</span>
                                                    <ArrowUpRight size={15} aria-hidden="true" />
                                                </Link>
                                            ))}
                                        </div>
                                    </section>
                                </div>


                                <RecordTable
                                    rows={data.recent_orders || []}
                                    section="dashboard"
                                />
                            </>
                        ) : section === 'roles' ? (
                            <AdminRoles data={data} />
                        ) : section === 'chatbot' ? (
                            <AdminChatHistory data={data} />
                        ) : section === 'configuracion' ? (
                            <AdminSettingsPanel data={data} />
                        ) : section === 'resenas' ? (
                            <AdminReviewsPanel data={data} />
                        ) : section === 'salon' ? (
                            <DiningFloor />
                        ) : section === 'herramientas' ? (
                            <AdminTools navigation={navigation} />
                        ) : (
                            <>
                                <div className="glass-module-heading flex flex-wrap items-center justify-between gap-4 mb-5">
                                    <div>
                                        <span className="glass-eyebrow">
                                            MÓDULO OPERATIVO
                                        </span>
                                        <h2>
                                            {currentSectionTitle}
                                        </h2>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.reload({
                                                    only: ['data'],
                                                })
                                            }
                                        >
                                            <RefreshCw
                                                size={14}
                                                aria-hidden="true"
                                            />
                                            <span>Actualizar</span>
                                        </button>
                                        {canCreate[section] &&
                                            auth?.user?.permissions?.includes(
                                                canCreate[section]
                                            ) && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setEditing(null);
                                                        setEditor(true);
                                                    }}
                                                >
                                                    <Plus
                                                        size={14}
                                                        aria-hidden="true"
                                                    />
                                                    <span>
                                                        {section === 'caja'
                                                            ? data.session
                                                                ? 'Cerrar caja'
                                                                : 'Abrir caja'
                                                            : 'Nuevo registro'}
                                                    </span>
                                                </button>
                                            )}
                                    </div>
                                </div>

                                {section === 'reservas' && (
                                    <AdminReservationTables
                                        tables={data.tables || []}
                                        canManage={
                                            auth?.user?.permissions?.includes('tables.manage') ||
                                            auth?.user?.role_code === 'admin'
                                        }
                                    />
                                )}
                                {section === 'delivery' && (
                                    <div className="mb-5">
                                        <AdminDeliveryZones
                                            zones={data.delivery_zones || []}
                                            canManage={
                                                auth?.user?.permissions?.includes('delivery.manage') ||
                                                auth?.user?.role_code === 'admin'
                                            }
                                        />
                                    </div>
                                )}

                                <RecordTable
                                    rows={rows}
                                    section={section}
                                    onEdit={canEdit[section] && (auth?.user?.permissions?.includes(canEdit[section]) || (section === 'delivery' && auth?.user?.permissions?.includes('delivery.manage'))) ? (row) => {
                                        setEditing(row);
                                        setEditor(true);
                                    } : undefined}
                                />

                                {section === 'caja' && data.session && (
                                    <div className="glass-panel glass-note mt-5">
                                        Caja abierta · Monto inicial:{' '}
                                        {money(data.session.opening_amount)}
                                    </div>
                                )}

                                {!rows.length &&
                                    Object.keys(data).some(
                                        (key) =>
                                            typeof data[key] === 'object' &&
                                            data[key]!== null &&
                                            !Array.isArray(data[key])
                                    ) && (
                                        <section className="glass-panel glass-summary">
                                            <h3>
                                                Resumen del módulo
                                            </h3>
                                            <div className="glass-summary-grid">
                                                {Object.entries(data)
                                                    .filter(
                                                        ([key, value]) =>
                                                            typeof value ===
                                                                'object' &&
                                                            value !== null &&
                                                            !Array.isArray(
                                                                value
                                                            ) &&
                                                            ![
                                                                'permissions',
                                                                'settings',
                                                            ].includes(key)
                                                    )
                                                    .map(([key, value]) => (
                                                        <div
                                                            key={key}
                                                            className="glass-summary-card"
                                                        >
                                                            <strong>
                                                                {SUMMARY_GROUP_LABELS[
                                                                    key
                                                                ] ||
                                                                    COLUMN_LABELS[
                                                                        key
                                                                    ] ||
                                                                    adminText(
                                                                        key,
                                                                        key,
                                                                        section
                                                                    )}
                                                            </strong>
                                                            {Object.entries(
                                                                value
                                                            )
                                                                .filter(
                                                                    ([, v]) =>
                                                                        typeof v !==
                                                                        'object'
                                                                )
                                                                .map(
                                                                    ([
                                                                        field,
                                                                        v,
                                                                    ]) => (
                                                                        <p
                                                                            key={
                                                                                field
                                                                            }
                                                                            className=""
                                                                        >
                                                                            <span>
                                                                                {COLUMN_LABELS[
                                                                                    field
                                                                                ] ||
                                                                                    adminText(
                                                                                        field,
                                                                                        field,
                                                                                        section
                                                                                    )}
                                                                            </span>
                                                                            <strong>
                                                                                {display(
                                                                                    v,
                                                                                    field,
                                                                                    section
                                                                                )}
                                                                            </strong>
                                                                        </p>
                                                                    )
                                                                )}
                                                        </div>
                                                    ))}
                                            </div>
                                        </section>
                                    )}
                            </>
                        )}

                        <footer className="mt-8 pt-4 border-t flex flex-wrap items-center justify-between gap-4 text-xs">
                            <span>
                                Datos compartidos en tiempo real con la tienda web y la aplicación Android
                            </span>
                            <Link
                                href="/admin/herramientas"
                                className="hover:underline"
                            >
                                Ver todas las herramientas ↗
                            </Link>
                        </footer>
                    </main>
                </div>
            </div>

            {editor && (
                <Editor
                    section={section}
                    data={data}
                    catalog={catalog}
                    ingredients={ingredients}
                    editing={editing}
                    close={() => {
                        setEditor(false);
                        setEditing(null);
                    }}
                />
            )}
        </div>
    );
}
