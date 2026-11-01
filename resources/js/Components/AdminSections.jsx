import { useCallback, useEffect, useState } from 'react';
import { api, formatDateTime, formatMoney } from '../lib/api';

const STATUS_STYLES = {
    Pendiente: 'bg-amber-100 text-amber-800',
    Preparando: 'bg-sky-100 text-sky-800',
    Listo: 'bg-emerald-100 text-emerald-800',
    'En reparto': 'bg-indigo-100 text-indigo-800',
    Entregado: 'bg-slate-200 text-slate-700',
    Cancelado: 'bg-red-100 text-red-700',
    Confirmada: 'bg-sky-100 text-sky-800',
    CONFIRMED: 'bg-sky-100 text-sky-800',
    CHECKED_IN: 'bg-indigo-100 text-indigo-800',
    IN_SERVICE: 'bg-red-100 text-red-800',
    NO_SHOW: 'bg-rose-100 text-rose-800',
    Sentada: 'bg-emerald-100 text-emerald-800',
    Completada: 'bg-slate-200 text-slate-700',
    COMPLETED: 'bg-slate-200 text-slate-700',
    Cancelada: 'bg-red-100 text-red-700',
    CANCELLED: 'bg-red-100 text-red-700',
    available: 'bg-emerald-100 text-emerald-800',
    occupied: 'bg-red-100 text-red-700',
    reserved: 'bg-sky-100 text-sky-800',
    cleaning: 'bg-amber-100 text-amber-800',
    maintenance: 'bg-slate-200 text-slate-700',
    disabled: 'bg-slate-200 text-slate-700',
    unavailable: 'bg-slate-200 text-slate-700',
    active: 'bg-emerald-100 text-emerald-800',
    inactive: 'bg-slate-200 text-slate-700',
};

export function Badge({ value, label }) {
    return (
        <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${STATUS_STYLES[value] ?? 'bg-slate-100 text-slate-700'}`}>
            {label ?? value}
        </span>
    );
}

function Card({ title, subtitle, action, children }) {
    return (
        <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sand-200">
            {(title || action) && (
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-sand-100 px-6 py-4">
                    <div>
                        <h2 className="font-display text-lg font-bold text-ocean-900">{title}</h2>
                        {subtitle && <p className="text-xs text-ink/55">{subtitle}</p>}
                    </div>
                    {action}
                </header>
            )}
            {children}
        </section>
    );
}

/**
 * Carga datos de la API conservando los props iniciales entregados por Inertia desde Laravel.
 */
function useResource(path, query, initialData = null) {
    const [state, setState] = useState({ loading: !initialData, error: '', data: initialData });
    const key = JSON.stringify(query ?? {});

    const load = useCallback(() => {
        setState((current) => ({ ...current, loading: true, error: '' }));

        return api(path, { query: JSON.parse(key) })
            .then((data) => setState({ loading: false, error: '', data }))
            .catch((error) => setState((current) => ({ loading: false, error: error.message, data: current.data })));
    }, [path, key]);

    useEffect(() => {
        load();
    }, [load]);

    return { ...state, reload: load };
}

function Feedback({ loading, error, data }) {
    if (loading && !data) {
        return <p className="p-8 text-center text-sm text-ink/50">Cargando datos del restaurante…</p>;
    }

    return error ? <p className="m-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null;
}

function Table({ columns, rows = [], empty = 'Sin registros.' }) {
    if (rows.length === 0) {
        return <p className="p-8 text-center text-sm text-ink/50">{empty}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-sand-50 text-xs uppercase tracking-wider text-ink/55">
                    <tr>
                        {columns.map((column) => (
                            <th key={column.label} className={`px-6 py-3 font-semibold ${column.className ?? ''}`}>
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-sand-100">
                    {rows.map((row) => (
                        <tr key={row.id} className="hover:bg-sand-50/70">
                            {columns.map((column) => (
                                <td key={column.label} className={`px-6 py-3.5 ${column.className ?? ''}`}>
                                    {column.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Stat({ label, value, hint, tone }) {
    return (
        <div className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-sand-200">
            <p className="text-xs font-semibold uppercase tracking-wider text-ink/50">{label}</p>
            <p className={`mt-2 font-display text-3xl font-extrabold ${tone}`}>{value}</p>
            {hint && <p className="mt-1 text-xs text-ink/50">{hint}</p>}
        </div>
    );
}

const NEXT_ORDER_STATUS = {
    Pendiente: 'Preparando',
    Preparando: 'Listo',
};

export function Overview({ can, initialData }) {
    const seed = initialData?.stats ? { stats: initialData.stats, orders: initialData.orders ?? [] } : null;
    const { data, loading, error, reload } = useResource('/api/orders.php', undefined, seed);
    const stats = data?.stats ?? initialData?.stats;
    const extraStats = initialData?.stats ?? {};
    const lowStockItems = (initialData?.inventory ?? []).filter((item) => item.low_stock);

    return (
        <div className="space-y-6">
            <Feedback loading={loading} error={error} data={data} />
            {stats && (
                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Ventas de hoy" value={formatMoney(stats.sales_today)} hint="Ingresos cobrados hoy" tone="text-ocean-800" />
                    <Stat label="Pedidos de hoy" value={stats.orders_today} hint="Salón, llevar y delivery" tone="text-ocean-800" />
                    <Stat label="Pendientes en cocina" value={stats.pending_orders} hint="Por iniciar preparación" tone="text-coral-600" />
                    <Stat label="Ticket promedio" value={formatMoney(stats.average_ticket)} hint="Promedio por orden pagada" tone="text-ocean-800" />
                    {extraStats.available_tables !== undefined && (
                        <Stat label="Mesas disponibles" value={extraStats.available_tables} hint="Salón, terraza y barra" tone="text-emerald-700" />
                    )}
                    {extraStats.active_reservations !== undefined && (
                        <Stat label="Reservas activas" value={extraStats.active_reservations} hint="Pendientes y confirmadas" tone="text-ocean-800" />
                    )}
                    {extraStats.low_stock_count !== undefined && (
                        <Stat
                            label="Alertas de stock"
                            value={extraStats.low_stock_count}
                            hint="Insumos bajo mínimo"
                            tone={extraStats.low_stock_count > 0 ? 'text-coral-600' : 'text-emerald-700'}
                        />
                    )}
                    {extraStats.customers_count !== undefined && (
                        <Stat label="Clientes registrados" value={extraStats.customers_count} hint="Web y App Android" tone="text-ocean-800" />
                    )}
                </div>
            )}

            {lowStockItems.length > 0 && (
                <Card title="⚠️ Insumos con stock crítico" subtitle="Requieren reposición en almacén">
                    <div className="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        {lowStockItems.map((ing) => (
                            <div key={ing.id} className="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
                                <p className="font-bold text-amber-950">{ing.name}</p>
                                <p className="mt-1 text-xs text-amber-800">
                                    Actual: <strong>{ing.stock_quantity} {ing.unit}</strong> · Mín: {ing.min_stock} {ing.unit}
                                </p>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            {data && (
                <Card
                    title="Últimos pedidos en tiempo real"
                    subtitle="Sincronizado con tienda web y app Android"
                    action={
                        <button onClick={reload} className="rounded-full bg-sand-100 px-4 py-1.5 text-xs font-semibold text-ocean-800 hover:bg-sand-200">
                            ↻ Actualizar
                        </button>
                    }
                >
                    <Table
                        rows={(data.orders ?? []).slice(0, 10)}
                        empty="Aún no hay pedidos registrados."
                        columns={[
                            { label: 'Pedido', render: (order) => <span className="font-mono font-bold text-ocean-900">{order.order_number}</span> },
                            { label: 'Cliente', render: (order) => order.customer_name },
                            { label: 'Platos', render: (order) => <span className="text-xs text-ink/65">{order.item_summary || `${order.item_count ?? 1} ítem(s)`}</span> },
                            { label: 'Tipo', render: (order) => order.order_type },
                            { label: 'Estado', render: (order) => <Badge value={order.status} /> },
                            { label: 'Total', className: 'text-right font-semibold', render: (order) => formatMoney(order.total) },
                        ]}
                    />
                </Card>
            )}
            {!can('orders.view') && !loading && !error && (
                <p className="text-sm text-ink/60">Tu rol no tiene acceso a las métricas de pedidos.</p>
            )}
        </div>
    );
}

export function Orders({ can, initialData }) {
    const seed = initialData?.orders ? { orders: initialData.orders, stats: initialData.stats } : null;
    const { data, loading, error, reload } = useResource('/api/orders.php', undefined, seed);
    const [notice, setNotice] = useState('');
    const [filter, setFilter] = useState('Todos');

    const advance = async (order, status) => {
        try {
            const result = await api('/api/orders.php', { method: 'POST', body: { action: 'status', id: order.id, status } });
            setNotice(result.message ?? 'Estado actualizado.');
            reload();
        } catch (problem) {
            setNotice(problem.message);
        }
    };

    const cancelOrder = async (order) => {
        try {
            const result = await api('/api/orders.php', {
                method: 'POST',
                body: { action: 'cancel', id: order.id, reason: 'Cancelado desde el panel de control' },
            });
            setNotice(result.message ?? 'Pedido cancelado.');
            reload();
        } catch (problem) {
            setNotice(problem.message);
        }
    };

    const allOrders = data?.orders ?? [];
    const filteredOrders = filter === 'Todos' ? allOrders : allOrders.filter((o) => o.status === filter);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                    {['Todos', 'Pendiente', 'Preparando', 'Listo', 'Entregado', 'Cancelado'].map((status) => (
                        <button
                            key={status}
                            onClick={() => setFilter(status)}
                            className={`rounded-full px-4 py-1.5 text-xs font-semibold transition ${
                                filter === status
                                    ? 'bg-ocean-900 text-white'
                                    : 'bg-white text-ocean-800 ring-1 ring-sand-200 hover:bg-sand-100'
                            }`}
                        >
                            {status}
                        </button>
                    ))}
                </div>
                <button onClick={reload} className="rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-coral-600 ring-1 ring-sand-200 hover:bg-sand-100">
                    ↻ Actualizar lista
                </button>
            </div>

            <Card title={`Pedidos (${filteredOrders.length})`} subtitle="Flujo de cocina KDS y entregas">
                <Feedback loading={loading} error={error} data={data} />
                {notice && <p className="border-b border-sand-100 bg-sand-50 px-6 py-3 text-sm font-medium text-ocean-800">{notice}</p>}
                {data && (
                    <Table
                        rows={filteredOrders}
                        empty="No hay pedidos en este estado."
                        columns={[
                            { label: 'Pedido', render: (order) => <span className="font-mono font-bold text-ocean-900">{order.order_number}</span> },
                            { label: 'Cliente', render: (order) => order.customer_name },
                            { label: 'Detalle', render: (order) => <span className="text-ink/65">{order.item_summary}</span> },
                            { label: 'Tipo', render: (order) => order.order_type },
                            { label: 'Pago', render: (order) => <Badge value={order.payment_status} /> },
                            { label: 'Estado', render: (order) => <Badge value={order.status} /> },
                            { label: 'Total', className: 'text-right font-semibold', render: (order) => formatMoney(order.total) },
                            { label: 'Fecha', render: (order) => formatDateTime(order.created_at) },
                            {
                                label: 'Acciones',
                                render: (order) => {
                                    const next =
                                        NEXT_ORDER_STATUS[order.status] ??
                                        (order.status === 'Listo' && order.order_type !== 'Delivery' ? 'Entregado' : null);

                                    return (
                                        <div className="flex flex-wrap items-center gap-2">
                                            {next && can('orders.update') && (
                                                <button
                                                    onClick={() => advance(order, next)}
                                                    className="rounded-full bg-ocean-900 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-ocean-800"
                                                >
                                                    Pasar a {next}
                                                </button>
                                            )}
                                            {order.status === 'Pendiente' && order.payment_status !== 'Pagado' && can('orders.cancel') && (
                                                <button
                                                    onClick={() => cancelOrder(order)}
                                                    className="rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                                                >
                                                    Cancelar
                                                </button>
                                            )}
                                            {!next && order.status !== 'Pendiente' && <span className="text-ink/30">—</span>}
                                        </div>
                                    );
                                },
                            },
                        ]}
                    />
                )}
            </Card>
        </div>
    );
}

export function Menu({ can, initialData }) {
    const { data, loading, error, reload } = useResource('/api/catalog.php', { view: 'menu' }, initialData?.menu);
    const [notice, setNotice] = useState('');
    const [newCategory, setNewCategory] = useState({ name: '', description: '' });
    const [showCatForm, setShowCatForm] = useState(false);

    const toggle = async (product) => {
        try {
            const result = await api('/api/catalog.php', {
                method: 'POST',
                body: { action: 'product.status', id: product.id, status: product.status === 'available' ? 'unavailable' : 'available' },
            });
            setNotice(result.message);
            reload();
        } catch (problem) {
            setNotice(problem.message);
        }
    };

    const createCategory = async (event) => {
        event.preventDefault();
        try {
            const result = await api('/api/catalog.php', {
                method: 'POST',
                body: { action: 'category.create', ...newCategory },
            });
            setNotice(result.message);
            setNewCategory({ name: '', description: '' });
            setShowCatForm(false);
            reload();
        } catch (problem) {
            setNotice(problem.message);
        }
    };

    return (
        <div className="space-y-6">
            {data?.categories && (
                <Card
                    title={`Categorías de la carta (${data.categories.length})`}
                    action={
                        can('categories.manage') && (
                            <button
                                onClick={() => setShowCatForm((v) => !v)}
                                className="rounded-full bg-ocean-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-ocean-800"
                            >
                                {showCatForm ? 'Cerrar' : '+ Nueva categoría'}
                            </button>
                        )
                    }
                >
                    {showCatForm && (
                        <form onSubmit={createCategory} className="flex flex-wrap gap-3 border-b border-sand-100 bg-sand-50 p-4">
                            <input
                                required
                                placeholder="Nombre de la categoría"
                                value={newCategory.name}
                                onChange={(e) => setNewCategory({ ...newCategory, name: e.target.value })}
                                className="rounded-xl border border-sand-200 bg-white px-3.5 py-2 text-sm"
                            />
                            <input
                                placeholder="Descripción breve"
                                value={newCategory.description}
                                onChange={(e) => setNewCategory({ ...newCategory, description: e.target.value })}
                                className="flex-1 rounded-xl border border-sand-200 bg-white px-3.5 py-2 text-sm"
                            />
                            <button className="rounded-full bg-coral-500 px-5 py-2 text-xs font-bold text-white hover:bg-coral-600">
                                Guardar
                            </button>
                        </form>
                    )}
                    <div className="flex flex-wrap gap-2.5 p-5">
                        {data.categories.map((cat) => (
                            <div key={cat.id} className="flex items-center gap-2 rounded-full bg-sand-50 px-4 py-2 text-xs font-semibold text-ocean-900 ring-1 ring-sand-200">
                                <span>{cat.name}</span>
                                <Badge value={cat.status} label={cat.status === 'active' ? 'Activa' : 'Inactiva'} />
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <Card
                title="Platos y bebidas del menú"
                subtitle="Control de disponibilidad inmediata en tienda web y app Android"
                action={
                    <button onClick={reload} className="text-xs font-semibold text-coral-600 hover:underline">
                        ↻ Actualizar
                    </button>
                }
            >
                <Feedback loading={loading} error={error} data={data} />
                {notice && <p className="border-b border-sand-100 bg-sand-50 px-6 py-3 text-sm text-ocean-800">{notice}</p>}
                {data && (
                    <Table
                        rows={data.products ?? []}
                        columns={[
                            { label: 'Producto', render: (product) => <span className="font-semibold text-ocean-900">{product.emoji} {product.name}</span> },
                            { label: 'Categoría', render: (product) => product.category },
                            { label: 'Alérgenos / Receta', render: (product) => <span className="text-xs text-ink/65">{product.allergens}</span> },
                            { label: 'Precio', className: 'text-right font-semibold', render: (product) => formatMoney(product.price) },
                            { label: 'Estado', render: (product) => <Badge value={product.status} label={product.status === 'available' ? 'Disponible' : 'Agotado'} /> },
                            {
                                label: 'Acción',
                                render: (product) =>
                                    can('products.manage') ? (
                                        <button
                                            onClick={() => toggle(product)}
                                            className="rounded-full bg-sand-100 px-3.5 py-1.5 text-xs font-semibold text-ocean-900 hover:bg-coral-500 hover:text-white"
                                        >
                                            {product.status === 'available' ? 'Marcar agotado' : 'Habilitar'}
                                        </button>
                                    ) : (
                                        <span className="text-ink/30">—</span>
                                    ),
                            },
                        ]}
                    />
                )}
            </Card>
        </div>
    );
}

const RESERVATION_ACTIONS = {
    Pendiente: ['Confirmada', 'Cancelada'],
    Confirmada: ['REGISTRAR LLEGADA', 'Sentada', 'Cancelada'],
    CONFIRMED: ['REGISTRAR LLEGADA', 'Sentada', 'Cancelada'],
    CHECKED_IN: ['Sentada', 'Cancelada'],
    IN_SERVICE: ['Completada'],
    Sentada: ['Completada'],
    NO_SHOW: ['Excepción'],
};

export function Reservations({ can, initialData }) {
    const { data, loading, error, reload } = useResource('/api/reservations.php', undefined, initialData?.reservations);
    const [notice, setNotice] = useState('');

    const change = async (reservation, status) => {
        try {
            let result;
            if (status === 'REGISTRAR LLEGADA') {
                result = await api(`/api/admin/reservas/${reservation.id}/check-in`, { method: 'POST' });
            } else if (status === 'Sentada') {
                result = await api(`/api/admin/reservas/${reservation.id}/sentar`, { method: 'POST' });
            } else if (status === 'Excepción') {
                result = await api(`/api/admin/reservas/${reservation.id}/check-in`, { method: 'POST' });
            } else {
                result = await api('/api/reservations.php', { method: 'POST', body: { action: 'reservation.status', id: reservation.id, status } });
            }
            setNotice(result.message ?? 'Reserva actualizada.');
            reload();
        } catch (problem) {
            setNotice(problem.message);
        }
    };

    const toggleTableStatus = async (table) => {
        if (!can('tables.manage')) {
            return;
        }
        const current = table.operational_status ?? table.effective_status ?? 'available';
        const next = current === 'available' ? 'occupied' : 'available';
        try {
            const result = await api('/api/reservations.php', {
                method: 'POST',
                body: { action: 'table.status', id: table.id, status: next },
            });
            setNotice(result.message ?? 'Mesa actualizada.');
            reload();
        } catch (problem) {
            setNotice(problem.message);
        }
    };

    return (
        <div className="space-y-6">
            <Feedback loading={loading} error={error} data={data} />
            {notice && <p className="rounded-xl bg-white px-5 py-3 text-sm font-medium text-ocean-800 shadow-sm ring-1 ring-sand-200">{notice}</p>}
            {data && (data.tables ?? []).length > 0 && (
                <Card
                    title={`Plano de mesas (${data.tables.length})`}
                    subtitle="Haz clic en una mesa para alternar entre Libre y Ocupada"
                    action={
                        <button onClick={reload} className="text-xs font-semibold text-coral-600 hover:underline">
                            ↻ Actualizar
                        </button>
                    }
                >
                    <div className="grid gap-3 p-6 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                        {data.tables.map((table) => {
                            const status = table.effective_status ?? table.status ?? table.operational_status;
                            return (
                                <button
                                    type="button"
                                    key={table.id}
                                    onClick={() => toggleTableStatus(table)}
                                    className="rounded-xl border border-sand-200 p-3 text-center transition hover:border-ocean-600 hover:shadow-md"
                                >
                                    <p className="font-display text-lg font-bold text-ocean-900">{table.label}</p>
                                    <p className="text-xs text-ink/55">{table.zone} · {table.capacity} pers.</p>
                                    <div className="mt-2">
                                        <Badge
                                            value={status}
                                            label={{ available: 'Libre', occupied: 'Ocupada', reserved: 'Reservada', maintenance: 'Mantenimiento' }[status] ?? status}
                                        />
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </Card>
            )}
            {data && (
                <Card title="Reservas programadas" subtitle="Reservas creadas desde la tienda web, app Android y recepción">
                    <Table
                        rows={data.reservations ?? []}
                        empty="No hay reservas próximas registradas."
                        columns={[
                            { label: 'Código', render: (reservation) => <span className="font-mono font-bold text-ocean-900">{reservation.reservation_code}</span> },
                            { label: 'Cliente', render: (reservation) => `${reservation.customer_name}${reservation.customer_phone ? ` · ${reservation.customer_phone}` : ''}` },
                            { label: 'Fecha y hora', render: (reservation) => formatDateTime(reservation.reservation_at) },
                            { label: 'Mesa', render: (reservation) => `${reservation.table_label} (${reservation.guest_count} pers.)` },
                            { label: 'Estado', render: (reservation) => <Badge value={reservation.status} /> },
                            {
                                label: 'Acciones',
                                render: (reservation) =>
                                    can('reservations.manage') && RESERVATION_ACTIONS[reservation.status] ? (
                                        <div className="flex flex-wrap gap-2">
                                            {RESERVATION_ACTIONS[reservation.status].map((status) => (
                                                <button
                                                    key={status}
                                                    onClick={() => change(reservation, status)}
                                                    className="rounded-full bg-sand-100 px-3 py-1 text-xs font-semibold text-ocean-900 hover:bg-coral-500 hover:text-white"
                                                >
                                                    {status}
                                                </button>
                                            ))}
                                        </div>
                                    ) : (
                                        <span className="text-ink/30">—</span>
                                    ),
                            },
                        ]}
                    />
                </Card>
            )}
        </div>
    );
}

export function Inventory({ initialData }) {
    const seed = initialData?.inventory ? { ingredients: initialData.inventory } : null;
    const { data, loading, error, reload } = useResource('/api/catalog.php', { view: 'ingredients' }, seed);
    const ingredients = data?.ingredients ?? initialData?.inventory ?? [];

    return (
        <Card
            title={`Inventario de insumos (${ingredients.length})`}
            subtitle="Descuento automático por receta en cada pedido web, salón o app"
            action={
                <button onClick={reload} className="text-xs font-semibold text-coral-600 hover:underline">
                    ↻ Actualizar
                </button>
            }
        >
            <Feedback loading={loading} error={error} data={data} />
            <Table
                rows={ingredients}
                empty="No hay insumos registrados."
                columns={[
                    { label: 'Insumo', render: (row) => <span className="font-semibold text-ocean-900">{row.name}</span> },
                    { label: 'Stock actual', className: 'text-right font-mono font-bold', render: (row) => `${Number(row.stock_quantity).toFixed(2)} ${row.unit}` },
                    { label: 'Stock mínimo', className: 'text-right font-mono text-ink/60', render: (row) => `${Number(row.min_stock).toFixed(2)} ${row.unit}` },
                    { label: 'Costo unitario', className: 'text-right', render: (row) => formatMoney(row.cost_per_unit) },
                    {
                        label: 'Nivel',
                        render: (row) => {
                            const low = Number(row.stock_quantity) <= Number(row.min_stock);
                            return (
                                <Badge
                                    value={low ? 'Pendiente' : 'available'}
                                    label={low ? 'Bajo stock' : 'Óptimo'}
                                />
                            );
                        },
                    },
                ]}
            />
        </Card>
    );
}

export function Customers({ initialData }) {
    const customers = initialData?.customers ?? [];

    return (
        <Card
            title={`Directorio de clientes (${customers.length})`}
            subtitle="Clientes registrados desde pedidos web, salón y app Android"
        >
            <Table
                rows={customers}
                empty="Aún no hay clientes registrados."
                columns={[
                    { label: 'Código', render: (row) => <span className="font-mono font-semibold text-ocean-900">{row.customer_code}</span> },
                    { label: 'Nombre completo', render: (row) => <span className="font-semibold">{row.full_name}</span> },
                    { label: 'Teléfono', render: (row) => row.phone || '—' },
                    { label: 'Correo', render: (row) => row.email || '—' },
                    { label: 'Pedidos', className: 'text-center font-semibold', render: (row) => row.orders_count },
                    { label: 'Total compras', className: 'text-right font-bold text-ocean-900', render: (row) => formatMoney(row.total_spent) },
                    { label: 'Estado', render: (row) => <Badge value={row.status} label={row.status === 'active' ? 'Activo' : 'Inactivo'} /> },
                ]}
            />
        </Card>
    );
}
