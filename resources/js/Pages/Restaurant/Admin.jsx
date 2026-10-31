import React, { useEffect, useMemo, useState } from 'react';
import DiningFloor from '../../Components/Restaurant/DiningFloor';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    Bell,
    CalendarDays,
    CircleDollarSign,
    Clock3,
    ClipboardList,
    ArrowUpRight,
    Pencil,
    Plus,
    RefreshCw,
    Search,
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
    reservation_code: 'Reserva',
    guest_count: 'Personas',
    reservation_at: 'Fecha de reserva',
    duration_minutes: 'Duración (min)',
    table_label: 'Mesa',
    table_number: 'N.° Mesa',
    zone: 'Zona',
    capacity: 'Capacidad',
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
        ].includes(key)
    ) {
        return money(value);
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
];
const WARN_STATES = [
    'Pendiente', 'pending', 'Preparando', 'En reparto', 'draft', 'in_progress',
    'waiting_user', 'scheduled', 'partially_received', 'open',
];
const BAD_STATES = [
    'Cancelado', 'Cancelada', 'Reembolsado', 'inactive', 'unavailable', 'invalid', 'failed',
    'cancelled', 'canceled', 'voided', 'deleted', 'rejected', 'No disponible', 'Inactivo',
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
        const sample = rows[0] || {};
        return Object.keys(sample)
            .filter(
                (key) =>
                    !hiddenColumns.includes(key) &&
                    typeof sample[key] !== 'object'
            )
            .slice(0, 7);
    }, [rows]);

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
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
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
                                            <td className="px-5 py-3.5 text-right whitespace-nowrap">
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
    const defaults = {
        pedidos: {
            action: editing ? 'status' : 'create',
            id: editing?.id || '',
            status: editing?.status || 'Preparando',
            payment_status: editing?.payment_status || 'Pagado',
            payment_method: 'cash',
            customer_name: editing?.customer_name || '',
            order_type: 'Salón',
            table_label: 'Mesa 1',
            notes: '',
            items: [{ product_id: catalog?.products?.[0]?.id || 1, quantity: 1 }],
        },
        carta: {
            entity: 'product',
            id: editing?.id || 0,
            name: editing?.name || '',
            category_id:
                editing?.category_id || catalog?.categories?.[0]?.id || 1,
            price: editing?.price || '25.00',
            emoji: editing?.emoji || '🐟',
            description: editing?.description || '',
            status: editing?.status || 'available',
            ingredients: editing?.ingredients?.length
                ? editing.ingredients.map((item) => ({
                      ingredient_id: item.ingredient_id,
                      quantity: item.quantity,
                  }))
                : [{ ingredient_id: ingredients[0]?.id || 1, quantity: '0.250' }],
        },
        inventario: {
            entity: 'ingredient_adjust',
            id: editing?.id || data.ingredients?.[0]?.id || 1,
            movement_type: 'entry',
            quantity: '1',
            unit_cost: editing?.cost_per_unit || '5.00',
            notes: 'Ajuste desde panel',
        },
        clientes: {
            action: 'save',
            id: editing?.id || 0,
            full_name: editing?.full_name || '',
            document_type: editing?.document_type || 'DNI',
            document_number: editing?.document_number || '',
            phone: editing?.phone || '',
            email: editing?.email || '',
            status: editing?.status || 'active',
        },
        reservas: {
            action: editing ? 'reservation_status' : 'reservation_create',
            id: editing?.id || '',
            status: editing?.status || 'Confirmada',
            table_id: editing?.table_id || data.tables?.[0]?.id || '',
            customer_name: editing?.customer_name || '',
            phone: editing?.phone || '',
            guest_count: editing?.guest_count || 2,
            reservation_at:
                editing?.reservation_at?.slice(0, 16).replace(' ', 'T') || '',
            duration_minutes: 90,
            notes: '',
        },
        caja: {
            action: data.session ? 'close_session' : 'open_session',
            opening_amount: '200.00',
            counted_closing: '200.00',
            notes: 'Operación desde panel',
        },
        cocina: {
            action: 'advance',
            order_id: editing?.id || '',
            status:
                editing?.status === 'Pendiente'
                    ? 'Preparando'
                    : editing?.status === 'Preparando'
                      ? 'Listo'
                      : 'Entregado',
        },
        delivery: {
            action: 'status',
            order_id: editing?.id || '',
            status: editing?.status === 'Listo' ? 'En reparto' : 'Entregado',
            incident_notes: editing?.incident_notes || '',
        },
        compras: {
            action: 'save_supplier',
            id: editing?.id || 0,
            name: editing?.name || '',
            tax_id: editing?.tax_id || '',
            contact_name: editing?.contact_name || '',
            phone: editing?.phone || '',
            email: editing?.email || '',
            status: editing?.status || 'active',
        },
        promociones: {
            action: 'save',
            id: editing?.id || 0,
            code: editing?.code || 'DESEGO15',
            name: editing?.name || '',
            promotion_type: editing?.promotion_type || 'coupon',
            discount_type: editing?.discount_type || 'percentage',
            discount_value: editing?.discount_value || '10',
            min_subtotal: editing?.min_subtotal || '40',
            status: editing?.status || 'active',
        },
        fidelizacion: {
            action: 'save_reward',
            id: editing?.id || 0,
            name: editing?.name || '',
            points_cost: editing?.points_cost || 100,
            reward_type: editing?.reward_type || 'discount_soles',
            reward_value: editing?.reward_value || '15.00',
            status: editing?.status || 'active',
        },
        equipo: {
            action: 'save_employee',
            id: editing?.id || 0,
            full_name: editing?.full_name || '',
            job_title: editing?.job_title || 'Cocina',
            phone: editing?.phone || '',
            email: editing?.email || '',
            employment_type: editing?.employment_type || 'full_time',
            hire_date:
                editing?.hire_date || new Date().toISOString().slice(0, 10),
            status: editing?.status || 'active',
        },
        roles: {
            action: editing ? 'update' : 'create',
            id: editing?.id || 0,
            name: editing?.name || '',
            email: editing?.email || '',
            password: '',
            role_id: editing?.role_id || data.roles?.[0]?.id || 1,
            status: editing?.status || 'active',
        },
        backups: { action: 'create_backup' },
        soporte: {
            action: 'create_ticket',
            subject: '',
            category: 'orders',
            priority: 'normal',
            message: '',
        },
    }[section];

    const modules = {
        pedidos: 'orders',
        carta: 'catalog',
        inventario: 'catalog',
        clientes: 'customers',
        reservas: 'reservations',
        caja: 'cash',
        cocina: 'kitchen',
        delivery: 'delivery',
        compras: 'purchasing',
        promociones: 'promotions',
        fidelizacion: 'loyalty',
        equipo: 'workforce',
        roles: 'users',
        backups: 'admin-tools',
        soporte: 'communications',
    };

    const form = useForm(defaults || {});

    if (!defaults) {
        return null;
    }

    const submit = (e) => {
        e.preventDefault();
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
                                    <option>Salón</option>
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
                        <label className="glass-field">
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
                        <div className="grid grid-cols-2 gap-3">
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
                                    {catalog?.categories?.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="glass-field">
                                Precio (S/)
                                <input
                                    type="number"
                                    step="0.10"
                                    required
                                    className={inputCls}
                                    value={form.data.price}
                                    onChange={(e) =>
                                        form.setData('price', e.target.value)
                                    }
                                />
                            </label>
                        </div>
                        <label className="glass-field">
                            Descripción
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
                        <div className="grid grid-cols-2 gap-3">
                            <label className="glass-field">
                                Insumo principal
                                <select
                                    className={inputCls}
                                    value={
                                        form.data.ingredients[0]?.ingredient_id
                                    }
                                    onChange={(e) =>
                                        form.setData('ingredients', [
                                            {
                                                ...form.data.ingredients[0],
                                                ingredient_id: Number(
                                                    e.target.value
                                                ),
                                            },
                                        ])
                                    }
                                >
                                    {ingredients.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name} ({adminText(i.unit, 'unit')})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="glass-field">
                                Cantidad por plato
                                <input
                                    type="number"
                                    step="0.001"
                                    required
                                    className={inputCls}
                                    value={form.data.ingredients[0]?.quantity}
                                    onChange={(e) =>
                                        form.setData('ingredients', [
                                            {
                                                ...form.data.ingredients[0],
                                                quantity: e.target.value,
                                            },
                                        ])
                                    }
                                />
                            </label>
                        </div>
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
                    </>
                )}

                {section === 'reservas' && editing && (
                    <label className="glass-field">
                        Estado de la reserva
                        <select
                            className={inputCls}
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {[
                                'Pendiente',
                                'Confirmada',
                                'Sentada',
                                'Completada',
                                'Cancelada',
                            ].map((s) => (
                                <option key={s} value={s}>
                                    {s}
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
                                ? ['Preparando', 'Listo', 'Entregado']
                                : ['En reparto', 'Entregado']
                            ).map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

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
                                Código
                                <input
                                    required
                                    className={inputCls}
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                />
                            </label>
                            <label className="glass-field">
                                Descuento (%)
                                <input
                                    type="number"
                                    required
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
                                    value={form.data.reward_value}
                                    onChange={(e) =>
                                        form.setData(
                                            'reward_value',
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

                {section === 'roles' && (
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
                                {data.roles?.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {adminText(r.name, 'role_name')}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </>
                )}

                {section === 'backups' && (
                    <p className="glass-muted-text">
                        Se generará una copia cifrada instantánea de la base de datos MySQL con firma de integridad SHA-256.
                    </p>
                )}

                {section === 'soporte' && (
                    <>
                        <label className="glass-field">
                            Asunto del ticket
                            <input
                                required
                                className={inputCls}
                                value={form.data.subject}
                                onChange={(e) =>
                                    form.setData('subject', e.target.value)
                                }
                            />
                        </label>
                        <label className="glass-field">
                            Detalle
                            <textarea
                                rows={3}
                                required
                                className={inputCls}
                                value={form.data.message}
                                onChange={(e) =>
                                    form.setData('message', e.target.value)
                                }
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

    useEffect(() => {
        const timer = setInterval(
            () => router.reload({ only: ['data'], preserveScroll: true }),
            30000
        );
        return () => clearInterval(timer);
    }, [section]);

    const rows = useMemo(() => {
        const key = Object.keys(data).find((k) => Array.isArray(data[k]));
        return key ? data[key] : [];
    }, [data]);

    const canCreate = {
        pedidos: 'orders.create',
        carta: 'products.manage',
        inventario: 'ingredients.manage',
        clientes: 'customers.manage',
        reservas: 'reservations.manage',
        caja: 'cash.manage',
        compras: 'suppliers.manage',
        promociones: 'promotions.manage',
        fidelizacion: 'loyalty.manage',
        equipo: 'employees.manage',
        roles: 'users.create',
        backups: 'backups.create',
        soporte: 'support.create',
    };

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
                    <main className="screen-content">
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
                                            <span className="glass-metric-detail">Cobros confirmados</span>
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
                                            <span className="glass-metric-detail">Registrados hoy</span>
                                        </div>
                                        <h2>Pedidos del día</h2>
                                        <strong className="glass-metric-value">{data.orders_today ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '68%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <Clock3 size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">En cocina o reparto</span>
                                        </div>
                                        <h2>Pedidos activos</h2>
                                        <strong className="glass-metric-value">{data.pending ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '54%' }} /></div>
                                    </article>

                                    <article className="glass-panel glass-metric">
                                        <div className="glass-metric-top">
                                            <span className="glass-metric-icon">
                                                <UsersRound size={20} strokeWidth={1.7} />
                                            </span>
                                            <span className="glass-metric-detail">Base activa en CRM</span>
                                        </div>
                                        <h2>Clientes registrados</h2>
                                        <strong className="glass-metric-value">{data.customers ?? 0}</strong>
                                        <div className="glass-metric-bar" aria-hidden="true"><span style={{ width: '90%' }} /></div>
                                    </article>
                                </div>

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

                                <RecordTable
                                    rows={rows}
                                    section={section}
                                    onEdit={(row) => {
                                        setEditing(row);
                                        setEditor(true);
                                    }}
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
