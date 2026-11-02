import React, { useEffect, useMemo, useState } from 'react';
import {
    AlertTriangle,
    Check,
    Clock3,
    PanelsTopLeft,
    Plus,
    RefreshCw,
    Settings2,
    SlidersHorizontal,
    UsersRound,
    X,
} from './Icons';

const TIME_ZONE = 'America/Lima';

function formatReservationTime(value) {
    if (!value) return '';
    const raw = String(value);
    const date = new Date(raw.includes('T') ? raw : `${raw.replace(' ', 'T')}Z`);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat('es-PE', {
        timeZone: TIME_ZONE,
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(date);
}

export default function AdminReservationTables({ tables: initialTables = [], canManage = false }) {
    const [tables, setTables] = useState(initialTables);
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('grid'); // 'grid' | 'config'
    const [config, setConfig] = useState(null);
    const [configLoading, setConfigLoading] = useState(false);
    const [configSaving, setConfigSaving] = useState(false);
    const [toast, setToast] = useState(null);

    // Modal de edición de mesa
    const [editingTable, setEditingTable] = useState(null);
    const [tableSaving, setTableSaving] = useState(false);

    // Modal de nueva mesa
    const [creatingTable, setCreatingTable] = useState(false);
    const [newTable, setNewTable] = useState({
        label: '',
        capacity: 4,
        zone: 'Salón principal',
        join_group: '',
    });

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const showToast = (message, type = 'success') => {
        setToast({ message, type });
        setTimeout(() => setToast(null), 4000);
    };

    const fetchTables = async () => {
        setLoading(true);
        try {
            const res = await fetch('/api/admin/reservas/mesas', {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (data.ok && Array.isArray(data.tables)) {
                setTables(data.tables);
            }
        } catch (err) {
            console.error('Error fetching tables', err);
        } finally {
            setLoading(false);
        }
    };

    const fetchConfig = async () => {
        setConfigLoading(true);
        try {
            const res = await fetch('/api/admin/reservas/configuracion', {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (data.ok && data.configuration) {
                setConfig(data.configuration);
            }
        } catch (err) {
            console.error('Error fetching config', err);
        } finally {
            setConfigLoading(false);
        }
    };

    useEffect(() => {
        fetchTables();
        fetchConfig();
    }, []);

    // Métricas dinámicas calculadas en tiempo real
    const metrics = useMemo(() => {
        const totalTables = tables.length;
        const totalPax = tables.reduce((sum, t) => sum + (Number(t.capacity) || 0), 0);
        const availableTables = tables.filter(
            (t) => t.is_active && t.effective_status === 'available' && !t.next_reservation
        ).length;
        const occupiedTables = tables.filter((t) => t.effective_status === 'occupied').length;
        const reservedTables = tables.filter(
            (t) => t.effective_status === 'reserved' || (t.is_active && Boolean(t.next_reservation))
        ).length;
        const cleaningTables = tables.filter((t) => t.effective_status === 'cleaning').length;

        return {
            totalTables,
            totalPax,
            availableTables,
            occupiedTables,
            reservedTables,
            cleaningTables,
        };
    }, [tables]);

    const handleSaveConfig = async (e) => {
        e.preventDefault();
        if (!config) return;
        setConfigSaving(true);
        try {
            const res = await fetch('/api/admin/reservas/configuracion', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(config),
            });
            const data = await res.json();
            if (data.ok) {
                showToast('Parámetros de reserva guardados correctamente.');
            } else {
                showToast(data.message || 'Error al guardar configuración', 'error');
            }
        } catch (err) {
            showToast('Error de red al actualizar parámetros', 'error');
        } finally {
            setConfigSaving(false);
        }
    };

    const handleUpdateTable = async (tableId, changes) => {
        setTableSaving(true);
        try {
            const res = await fetch(`/api/admin/reservas/mesas/${tableId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(changes),
            });
            const data = await res.json();
            if (data.ok) {
                showToast('Mesa actualizada correctamente.');
                setEditingTable(null);
                await fetchTables();
            } else {
                showToast(data.message || 'Error al actualizar mesa', 'error');
            }
        } catch (err) {
            showToast('Error de red al actualizar mesa', 'error');
        } finally {
            setTableSaving(false);
        }
    };

    const handleCreateTable = async (e) => {
        e.preventDefault();
        setTableSaving(true);
        try {
            const res = await fetch('/api/admin/reservas/mesas', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(newTable),
            });
            const data = await res.json();
            if (data.ok) {
                showToast(data.message || 'Mesa creada correctamente.');
                setCreatingTable(false);
                setNewTable({ label: '', capacity: 4, zone: 'Salón principal', join_group: '' });
                await fetchTables();
            } else {
                showToast(data.message || 'Error al crear mesa', 'error');
            }
        } catch (err) {
            showToast('Error al procesar la creación de mesa', 'error');
        } finally {
            setTableSaving(false);
        }
    };

    const getStatusBadge = (table) => {
        if (!table.is_active || table.effective_status === 'disabled') {
            return { label: 'Inactiva', bg: 'rgba(100, 116, 139, 0.12)', text: '#64748b', dot: '#94a3b8' };
        }
        if (table.effective_status === 'maintenance') {
            return { label: 'Mantenimiento', bg: 'rgba(239, 68, 68, 0.12)', text: '#b91c1c', dot: '#ef4444' };
        }
        if (table.effective_status === 'occupied') {
            return { label: 'En atención', bg: 'rgba(0, 71, 151, 0.12)', text: '#004797', dot: '#004797' };
        }
        if (table.effective_status === 'cleaning') {
            return { label: 'Por limpiar', bg: 'rgba(147, 51, 234, 0.12)', text: '#7e22ce', dot: '#a855f7' };
        }
        if (table.effective_status === 'reserved') {
            return { label: 'Reservada', bg: 'rgba(217, 119, 6, 0.15)', text: '#b45309', dot: '#f59e0b' };
        }
        if (table.next_reservation) {
            return { label: 'Próx. reserva', bg: 'rgba(14, 116, 144, 0.12)', text: '#0e7490', dot: '#06b6d4' };
        }
        return { label: 'Libre', bg: 'rgba(22, 163, 74, 0.12)', text: '#15803d', dot: '#22c55e' };
    };

    return (
        <section
            className="glass-panel glass-panel-pad mb-6"
            style={{ borderRadius: '18px', border: '1px solid rgba(0, 71, 151, 0.12)' }}
        >
            {/* Header del Salón */}
            <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-blue-900/10">
                <div className="flex items-center gap-3">
                    <div
                        style={{
                            width: '40px',
                            height: '40px',
                            borderRadius: '11px',
                            background: 'linear-gradient(135deg, #004797 0%, #002b5e 100%)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            color: '#fff',
                            boxShadow: '0 4px 12px rgba(0, 71, 151, 0.25)',
                        }}
                    >
                        <PanelsTopLeft size={20} />
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h3
                                style={{
                                    fontSize: '17px',
                                    fontWeight: '800',
                                    color: '#0f172a',
                                    margin: 0,
                                    letterSpacing: '-0.02em',
                                }}
                            >
                                Salón Principal
                            </h3>
                            <div className="flex items-center gap-1.5 flex-wrap">
                                <span
                                    style={{
                                        fontSize: '11px',
                                        fontWeight: '700',
                                        padding: '2px 8px',
                                        borderRadius: '999px',
                                        background: 'rgba(0, 71, 151, 0.08)',
                                        color: '#004797',
                                    }}
                                >
                                    {metrics.totalTables} Mesas · {metrics.totalPax} Pax
                                </span>
                                <span
                                    style={{
                                        fontSize: '11px',
                                        fontWeight: '600',
                                        padding: '2px 8px',
                                        borderRadius: '999px',
                                        background: 'rgba(22, 163, 74, 0.1)',
                                        color: '#15803d',
                                    }}
                                >
                                    ● {metrics.availableTables} libres
                                </span>
                                {metrics.reservedTables > 0 && (
                                    <span
                                        style={{
                                            fontSize: '11px',
                                            fontWeight: '600',
                                            padding: '2px 8px',
                                            borderRadius: '999px',
                                            background: 'rgba(217, 119, 6, 0.1)',
                                            color: '#b45309',
                                        }}
                                    >
                                        ● {metrics.reservedTables} con reserva
                                    </span>
                                )}
                                {metrics.occupiedTables > 0 && (
                                    <span
                                        style={{
                                            fontSize: '11px',
                                            fontWeight: '600',
                                            padding: '2px 8px',
                                            borderRadius: '999px',
                                            background: 'rgba(0, 71, 151, 0.1)',
                                            color: '#004797',
                                        }}
                                    >
                                        ● {metrics.occupiedTables} en salón
                                    </span>
                                )}
                                {metrics.cleaningTables > 0 && (
                                    <span
                                        style={{
                                            fontSize: '11px',
                                            fontWeight: '600',
                                            padding: '2px 8px',
                                            borderRadius: '999px',
                                            background: 'rgba(147, 51, 234, 0.1)',
                                            color: '#7e22ce',
                                        }}
                                    >
                                        ● {metrics.cleaningTables} por limpiar
                                    </span>
                                )}
                            </div>
                        </div>
                        <p style={{ fontSize: '12px', color: '#64748b', margin: '3px 0 0' }}>
                            Gestión de capacidad en salón, reservas asignadas y políticas de atención.
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {/* Botones de pestaña */}
                    <div
                        style={{
                            display: 'inline-flex',
                            background: 'rgba(0, 71, 151, 0.06)',
                            padding: '3px',
                            borderRadius: '10px',
                            border: '1px solid rgba(0, 71, 151, 0.1)',
                        }}
                    >
                        <button
                            type="button"
                            onClick={() => setActiveTab('grid')}
                            style={{
                                padding: '6px 13px',
                                borderRadius: '8px',
                                fontSize: '12px',
                                fontWeight: activeTab === 'grid' ? '700' : '600',
                                background: activeTab === 'grid' ? '#004797' : 'transparent',
                                color: activeTab === 'grid' ? '#fff' : '#475569',
                                border: 'none',
                                cursor: 'pointer',
                                transition: 'all 160ms ease',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '5px',
                            }}
                        >
                            <PanelsTopLeft size={14} />
                            <span>Mesas</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('config')}
                            style={{
                                padding: '6px 13px',
                                borderRadius: '8px',
                                fontSize: '12px',
                                fontWeight: activeTab === 'config' ? '700' : '600',
                                background: activeTab === 'config' ? '#004797' : 'transparent',
                                color: activeTab === 'config' ? '#fff' : '#475569',
                                border: 'none',
                                cursor: 'pointer',
                                transition: 'all 160ms ease',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '5px',
                            }}
                        >
                            <SlidersHorizontal size={14} />
                            <span>Políticas</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={fetchTables}
                        disabled={loading}
                        aria-label="Actualizar estado de mesas"
                        title="Actualizar mesas"
                        style={{
                            width: '34px',
                            height: '34px',
                            borderRadius: '10px',
                            border: '1px solid rgba(0, 71, 151, 0.18)',
                            background: '#fff',
                            color: '#004797',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            cursor: 'pointer',
                        }}
                    >
                        <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
                    </button>

                    {canManage && activeTab === 'grid' && (
                        <button
                            type="button"
                            onClick={() => setCreatingTable(true)}
                            style={{
                                padding: '7px 13px',
                                borderRadius: '10px',
                                background: 'linear-gradient(135deg, #004797 0%, #003878 100%)',
                                color: '#fff',
                                fontWeight: '700',
                                fontSize: '12px',
                                border: 'none',
                                cursor: 'pointer',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '5px',
                                boxShadow: '0 4px 12px rgba(0, 71, 151, 0.22)',
                            }}
                        >
                            <Plus size={14} />
                            <span>Nueva Mesa</span>
                        </button>
                    )}
                </div>
            </div>

            {/* Notificación Toast */}
            {toast && (
                <div
                    style={{
                        marginTop: '12px',
                        padding: '9px 14px',
                        borderRadius: '10px',
                        background: toast.type === 'error' ? '#fef2f2' : '#f0fdf4',
                        border: `1px solid ${toast.type === 'error' ? '#fecaca' : '#bbf7d0'}`,
                        color: toast.type === 'error' ? '#991b1b' : '#166534',
                        fontSize: '12.5px',
                        fontWeight: '600',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                    }}
                >
                    {toast.type === 'error' ? <AlertTriangle size={15} /> : <Check size={15} />}
                    <span>{toast.message}</span>
                </div>
            )}

            {/* VISTA 1: CUADRÍCULA DE MESAS */}
            {activeTab === 'grid' && (
                <div style={{ marginTop: '16px' }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5">
                        {tables.map((table) => {
                            const badge = getStatusBadge(table);
                            return (
                                <div
                                    key={table.id}
                                    style={{
                                        background: table.is_active ? '#ffffff' : 'rgba(248, 250, 252, 0.85)',
                                        border: `1px solid ${table.is_active ? 'rgba(0, 71, 151, 0.12)' : 'rgba(148, 163, 184, 0.22)'}`,
                                        borderRadius: '14px',
                                        padding: '14px 15px',
                                        boxShadow: table.is_active ? '0 2px 10px -2px rgba(0, 71, 151, 0.05)' : 'none',
                                        opacity: table.is_active ? 1 : 0.75,
                                        transition: 'all 180ms ease',
                                        display: 'flex',
                                        flexDirection: 'column',
                                        justifyContent: 'space-between',
                                    }}
                                >
                                    {/* Cabecera de la mesa */}
                                    <div>
                                        <div className="flex items-center justify-between gap-2 mb-2.5">
                                            <div className="flex items-center gap-2 min-w-0">
                                                <h4
                                                    style={{
                                                        fontSize: '15px',
                                                        fontWeight: '800',
                                                        color: '#0f172a',
                                                        margin: 0,
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    {table.label}
                                                </h4>
                                                <span
                                                    style={{
                                                        fontSize: '10.5px',
                                                        fontWeight: '700',
                                                        padding: '2px 7px',
                                                        borderRadius: '999px',
                                                        background: badge.bg,
                                                        color: badge.text,
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: '4px',
                                                        whiteSpace: 'nowrap',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            width: '5px',
                                                            height: '5px',
                                                            borderRadius: '50%',
                                                            background: badge.dot,
                                                        }}
                                                    />
                                                    {badge.label}
                                                </span>
                                            </div>

                                            <div
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: '3px',
                                                    background: 'rgba(0, 71, 151, 0.05)',
                                                    padding: '3px 7px',
                                                    borderRadius: '6px',
                                                    color: '#004797',
                                                    fontSize: '11px',
                                                    fontWeight: '700',
                                                    whiteSpace: 'nowrap',
                                                }}
                                            >
                                                <UsersRound size={12} />
                                                <span>{table.capacity} pax</span>
                                            </div>
                                        </div>

                                        {/* Estado de reserva o salón */}
                                        <div style={{ minHeight: '38px', marginBottom: '10px' }}>
                                            {table.next_reservation ? (
                                                <div
                                                    style={{
                                                        background: 'rgba(245, 158, 11, 0.08)',
                                                        border: '1px solid rgba(245, 158, 11, 0.22)',
                                                        borderRadius: '8px',
                                                        padding: '7px 9px',
                                                        fontSize: '11.5px',
                                                        color: '#92400e',
                                                    }}
                                                >
                                                    <div className="flex items-center justify-between gap-1">
                                                        <strong className="truncate">
                                                            {table.next_reservation.customer_name}
                                                        </strong>
                                                        <span className="text-amber-800 font-semibold shrink-0">
                                                            {table.next_reservation.guest_count} pax
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between gap-1 mt-1 text-amber-700 text-xs">
                                                        <span className="flex items-center gap-1 font-semibold">
                                                            <Clock3 size={11} />
                                                            {formatReservationTime(table.next_reservation.reservation_at)}
                                                        </span>
                                                        {table.next_reservation.reservation_code && (
                                                            <span className="font-mono text-[10px] text-amber-600 bg-amber-100/60 px-1.5 py-0.5 rounded">
                                                                {table.next_reservation.reservation_code}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            ) : table.active_session ? (
                                                <div
                                                    style={{
                                                        background: 'rgba(0, 71, 151, 0.06)',
                                                        border: '1px solid rgba(0, 71, 151, 0.15)',
                                                        borderRadius: '8px',
                                                        padding: '7px 9px',
                                                        fontSize: '11.5px',
                                                        color: '#004797',
                                                    }}
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <strong>Mesa en atención</strong>
                                                        <span className="font-mono text-[10px] text-blue-700 bg-blue-100/60 px-1.5 py-0.5 rounded">
                                                            #{String(table.active_session.id).slice(0, 6)}
                                                        </span>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div
                                                    style={{
                                                        fontSize: '11.5px',
                                                        color: '#94a3b8',
                                                        padding: '6px 2px',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: '6px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            width: '4px',
                                                            height: '4px',
                                                            borderRadius: '50%',
                                                            background: '#cbd5e1',
                                                        }}
                                                    />
                                                    <span>
                                                        {table.is_active
                                                            ? 'Disponible para asignación'
                                                            : 'Mesa inactiva'}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Acciones para Administrador */}
                                    {canManage && (
                                        <div className="pt-2.5 border-t border-slate-100 flex items-center justify-between gap-1.5">
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    title={
                                                        table.is_online_reservable
                                                            ? 'Mesa habilitada para reservas web'
                                                            : 'Solo atención presencial'
                                                    }
                                                    onClick={() =>
                                                        handleUpdateTable(table.id, {
                                                            is_online_reservable: !table.is_online_reservable,
                                                        })
                                                    }
                                                    style={{
                                                        fontSize: '10.5px',
                                                        fontWeight: '600',
                                                        padding: '3px 7px',
                                                        borderRadius: '5px',
                                                        border: '1px solid',
                                                        borderColor: table.is_online_reservable
                                                            ? 'rgba(0, 71, 151, 0.18)'
                                                            : '#fed7aa',
                                                        background: table.is_online_reservable
                                                            ? 'rgba(0, 71, 151, 0.04)'
                                                            : '#fff7ed',
                                                        color: table.is_online_reservable ? '#004797' : '#c2410c',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    {table.is_online_reservable ? 'Web' : 'Solo Local'}
                                                </button>

                                                <button
                                                    type="button"
                                                    title={table.is_active ? 'Pausar mesa' : 'Activar mesa'}
                                                    onClick={() =>
                                                        handleUpdateTable(table.id, { is_active: !table.is_active })
                                                    }
                                                    style={{
                                                        fontSize: '10.5px',
                                                        fontWeight: '600',
                                                        padding: '3px 7px',
                                                        borderRadius: '5px',
                                                        border: '1px solid',
                                                        borderColor: table.is_active ? '#e2e8f0' : '#bbf7d0',
                                                        background: table.is_active ? '#f8fafc' : '#f0fdf4',
                                                        color: table.is_active ? '#64748b' : '#15803d',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    {table.is_active ? 'Pausar' : 'Activar'}
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => setEditingTable({ ...table })}
                                                title="Configurar capacidad y parámetros"
                                                style={{
                                                    fontSize: '11px',
                                                    fontWeight: '600',
                                                    padding: '3px 8px',
                                                    borderRadius: '5px',
                                                    background: 'rgba(0, 71, 151, 0.06)',
                                                    color: '#004797',
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '3px',
                                                }}
                                            >
                                                <Settings2 size={11} />
                                                <span>Editar</span>
                                            </button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* VISTA 2: CONFIGURACIÓN Y POLÍTICAS DEL SISTEMA */}
            {activeTab === 'config' && (
                <div style={{ marginTop: '16px' }}>
                    {configLoading ? (
                        <div className="py-12 text-center text-slate-500">
                            <RefreshCw size={24} className="animate-spin mx-auto mb-2 text-blue-600" />
                            <span>Cargando parámetros de configuración...</span>
                        </div>
                    ) : config ? (
                        <form
                            onSubmit={handleSaveConfig}
                            style={{
                                background: '#fff',
                                borderRadius: '14px',
                                padding: '22px',
                                border: '1px solid rgba(0, 71, 151, 0.12)',
                            }}
                        >
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-5">
                                {/* Anticipo de Reserva */}
                                <div>
                                    <label
                                        style={{
                                            display: 'block',
                                            fontSize: '12px',
                                            fontWeight: '700',
                                            color: '#1e293b',
                                            marginBottom: '5px',
                                        }}
                                    >
                                        Anticipo Obligatorio (S/)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.50"
                                        min="0"
                                        max="500"
                                        required
                                        value={config.advance_amount}
                                        onChange={(e) => setConfig({ ...config, advance_amount: e.target.value })}
                                        style={{
                                            width: '100%',
                                            padding: '9px 12px',
                                            borderRadius: '9px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '13.5px',
                                            fontWeight: '700',
                                            color: '#004797',
                                        }}
                                    />
                                    <small
                                        style={{
                                            fontSize: '11px',
                                            color: '#64748b',
                                            marginTop: '3px',
                                            display: 'block',
                                        }}
                                    >
                                        Monto fijo por reserva (crédito al consumo final).
                                    </small>
                                </div>

                                {/* Tolerancia de Inasistencia */}
                                <div>
                                    <label
                                        style={{
                                            display: 'block',
                                            fontSize: '12px',
                                            fontWeight: '700',
                                            color: '#1e293b',
                                            marginBottom: '5px',
                                        }}
                                    >
                                        Tolerancia Máxima (minutos)
                                    </label>
                                    <input
                                        type="number"
                                        min="5"
                                        max="60"
                                        required
                                        value={config.tolerance_minutes}
                                        onChange={(e) =>
                                            setConfig({ ...config, tolerance_minutes: Number(e.target.value) })
                                        }
                                        style={{
                                            width: '100%',
                                            padding: '9px 12px',
                                            borderRadius: '9px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '13.5px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small
                                        style={{
                                            fontSize: '11px',
                                            color: '#64748b',
                                            marginTop: '3px',
                                            display: 'block',
                                        }}
                                    >
                                        Superado este tiempo, la reserva pasa a inasistencia (NO_SHOW).
                                    </small>
                                </div>

                                {/* Retención del Bloqueo Temporal */}
                                <div>
                                    <label
                                        style={{
                                            display: 'block',
                                            fontSize: '12px',
                                            fontWeight: '700',
                                            color: '#1e293b',
                                            marginBottom: '5px',
                                        }}
                                    >
                                        Tiempo de Bloqueo de Pago (minutos)
                                    </label>
                                    <input
                                        type="number"
                                        min="3"
                                        max="30"
                                        required
                                        value={config.hold_duration_minutes}
                                        onChange={(e) =>
                                            setConfig({ ...config, hold_duration_minutes: Number(e.target.value) })
                                        }
                                        style={{
                                            width: '100%',
                                            padding: '9px 12px',
                                            borderRadius: '9px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '13.5px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small
                                        style={{
                                            fontSize: '11px',
                                            color: '#64748b',
                                            marginTop: '3px',
                                            display: 'block',
                                        }}
                                    >
                                        Minutos para abonar el anticipo antes de liberar la mesa.
                                    </small>
                                </div>

                                {/* Margen de Limpieza entre Reservas */}
                                <div>
                                    <label
                                        style={{
                                            display: 'block',
                                            fontSize: '12px',
                                            fontWeight: '700',
                                            color: '#1e293b',
                                            marginBottom: '5px',
                                        }}
                                    >
                                        Margen de Limpieza (minutos)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="60"
                                        required
                                        value={config.cleaning_minutes}
                                        onChange={(e) =>
                                            setConfig({ ...config, cleaning_minutes: Number(e.target.value) })
                                        }
                                        style={{
                                            width: '100%',
                                            padding: '9px 12px',
                                            borderRadius: '9px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '13.5px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small
                                        style={{
                                            fontSize: '11px',
                                            color: '#64748b',
                                            marginTop: '3px',
                                            display: 'block',
                                        }}
                                    >
                                        Buffer de desinfección entre el fin de una reserva y la siguiente.
                                    </small>
                                </div>

                                {/* Cancelación con Devolución */}
                                <div>
                                    <label
                                        style={{
                                            display: 'block',
                                            fontSize: '12px',
                                            fontWeight: '700',
                                            color: '#1e293b',
                                            marginBottom: '5px',
                                        }}
                                    >
                                        Límite Cancelación c/ Reembolso (horas)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="48"
                                        required
                                        value={config.cancellation_deadline_hours}
                                        onChange={(e) =>
                                            setConfig({
                                                ...config,
                                                cancellation_deadline_hours: Number(e.target.value),
                                            })
                                        }
                                        style={{
                                            width: '100%',
                                            padding: '9px 12px',
                                            borderRadius: '9px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '13.5px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small
                                        style={{
                                            fontSize: '11px',
                                            color: '#64748b',
                                            marginTop: '3px',
                                            display: 'block',
                                        }}
                                    >
                                        Horas previas requeridas para devolución del anticipo.
                                    </small>
                                </div>

                                {/* Antelación Máxima para Reservar */}
                                <div>
                                    <label
                                        style={{
                                            display: 'block',
                                            fontSize: '12px',
                                            fontWeight: '700',
                                            color: '#1e293b',
                                            marginBottom: '5px',
                                        }}
                                    >
                                        Antelación Máxima (días)
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="90"
                                        required
                                        value={config.max_advance_days}
                                        onChange={(e) =>
                                            setConfig({ ...config, max_advance_days: Number(e.target.value) })
                                        }
                                        style={{
                                            width: '100%',
                                            padding: '9px 12px',
                                            borderRadius: '9px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '13.5px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small
                                        style={{
                                            fontSize: '11px',
                                            color: '#64748b',
                                            marginTop: '3px',
                                            display: 'block',
                                        }}
                                    >
                                        Ventana de días hacia adelante disponibles en el calendario web.
                                    </small>
                                </div>
                            </div>

                            {/* Switch de Reservas Habilitadas */}
                            <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl mb-5 border border-slate-200">
                                <div>
                                    <strong style={{ fontSize: '13.5px', color: '#0f172a' }}>
                                        Habilitar Sistema de Reservas Online
                                    </strong>
                                    <p style={{ fontSize: '11.5px', color: '#64748b', margin: 0 }}>
                                        Al desactivar, los clientes verán que las reservas están temporalmente cerradas.
                                    </p>
                                </div>
                                <label style={{ position: 'relative', display: 'inline-block', width: '46px', height: '24px' }}>
                                    <input
                                        type="checkbox"
                                        checked={Boolean(config.reservations_enabled)}
                                        onChange={(e) => setConfig({ ...config, reservations_enabled: e.target.checked })}
                                        style={{ opacity: 0, width: 0, height: 0 }}
                                    />
                                    <span
                                        style={{
                                            position: 'absolute',
                                            cursor: 'pointer',
                                            inset: 0,
                                            background: config.reservations_enabled ? '#004797' : '#cbd5e1',
                                            borderRadius: '34px',
                                            transition: 'all 200ms ease',
                                        }}
                                    >
                                        <span
                                            style={{
                                                position: 'absolute',
                                                height: '18px',
                                                width: '18px',
                                                left: config.reservations_enabled ? '25px' : '3px',
                                                bottom: '3px',
                                                background: '#fff',
                                                borderRadius: '50%',
                                                transition: 'all 200ms ease',
                                            }}
                                        />
                                    </span>
                                </label>
                            </div>

                            <div className="flex justify-end gap-3">
                                <button
                                    type="submit"
                                    disabled={configSaving}
                                    style={{
                                        padding: '9px 20px',
                                        borderRadius: '9px',
                                        background: 'linear-gradient(135deg, #004797 0%, #003878 100%)',
                                        color: '#fff',
                                        fontWeight: '700',
                                        fontSize: '13px',
                                        border: 'none',
                                        cursor: 'pointer',
                                        boxShadow: '0 4px 12px rgba(0, 71, 151, 0.25)',
                                    }}
                                >
                                    {configSaving ? 'Guardando...' : 'Guardar Políticas'}
                                </button>
                            </div>
                        </form>
                    ) : null}
                </div>
            )}

            {/* MODAL: EDITAR MESA */}
            {editingTable && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15, 23, 42, 0.5)',
                        backdropFilter: 'blur(4px)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 9999,
                        padding: '20px',
                    }}
                >
                    <div
                        style={{
                            background: '#fff',
                            borderRadius: '16px',
                            maxWidth: '420px',
                            width: '100%',
                            padding: '22px',
                            boxShadow: '0 20px 40px rgba(0,0,0,0.2)',
                        }}
                    >
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 style={{ fontSize: '16px', fontWeight: '800', color: '#0f172a', margin: 0 }}>
                                Configurar {editingTable.label}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setEditingTable(null)}
                                style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#94a3b8' }}
                            >
                                <X size={18} />
                            </button>
                        </div>

                        <div className="space-y-3.5">
                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Capacidad de Comensales
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="20"
                                    value={editingTable.capacity}
                                    onChange={(e) =>
                                        setEditingTable({ ...editingTable, capacity: Number(e.target.value) })
                                    }
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                />
                            </div>

                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Grupo de Unión (Opcional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ej. GRUPO-A (dejar vacío si es individual)"
                                    value={editingTable.join_group || ''}
                                    onChange={(e) => setEditingTable({ ...editingTable, join_group: e.target.value })}
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                />
                            </div>

                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Estado Operativo Físico
                                </label>
                                <select
                                    value={editingTable.operational_status}
                                    onChange={(e) =>
                                        setEditingTable({ ...editingTable, operational_status: e.target.value })
                                    }
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                >
                                    <option value="available">Disponible</option>
                                    <option value="occupied">Ocupada</option>
                                    <option value="cleaning">Por limpiar</option>
                                    <option value="maintenance">En mantenimiento</option>
                                </select>
                            </div>

                            <div className="flex items-center justify-between pt-2">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={editingTable.is_active}
                                        onChange={(e) =>
                                            setEditingTable({ ...editingTable, is_active: e.target.checked })
                                        }
                                    />
                                    <span style={{ fontSize: '12.5px', fontWeight: '600', color: '#1e293b' }}>
                                        Habilitada en salón
                                    </span>
                                </label>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={editingTable.is_online_reservable}
                                        onChange={(e) =>
                                            setEditingTable({
                                                ...editingTable,
                                                is_online_reservable: e.target.checked,
                                            })
                                        }
                                    />
                                    <span style={{ fontSize: '12.5px', fontWeight: '600', color: '#1e293b' }}>
                                        Reservable online
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-4 border-t border-slate-100 mt-4">
                            <button
                                type="button"
                                onClick={() => setEditingTable(null)}
                                style={{
                                    padding: '7px 14px',
                                    borderRadius: '7px',
                                    border: '1px solid #cbd5e1',
                                    background: '#fff',
                                    fontSize: '12.5px',
                                    cursor: 'pointer',
                                }}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                disabled={tableSaving}
                                onClick={() =>
                                    handleUpdateTable(editingTable.id, {
                                        capacity: editingTable.capacity,
                                        join_group: editingTable.join_group,
                                        operational_status: editingTable.operational_status,
                                        is_active: editingTable.is_active,
                                        is_online_reservable: editingTable.is_online_reservable,
                                    })
                                }
                                style={{
                                    padding: '7px 16px',
                                    borderRadius: '7px',
                                    background: '#004797',
                                    color: '#fff',
                                    fontWeight: '700',
                                    fontSize: '12.5px',
                                    border: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                {tableSaving ? 'Guardando...' : 'Guardar Cambios'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL: NUEVA MESA */}
            {creatingTable && (
                <div
                    style={{
                        position: 'fixed',
                        inset: 0,
                        background: 'rgba(15, 23, 42, 0.5)',
                        backdropFilter: 'blur(4px)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        zIndex: 9999,
                        padding: '20px',
                    }}
                >
                    <form
                        onSubmit={handleCreateTable}
                        style={{
                            background: '#fff',
                            borderRadius: '16px',
                            maxWidth: '400px',
                            width: '100%',
                            padding: '22px',
                            boxShadow: '0 20px 40px rgba(0,0,0,0.2)',
                        }}
                    >
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 style={{ fontSize: '16px', fontWeight: '800', color: '#0f172a', margin: 0 }}>
                                Registrar Nueva Mesa
                            </h3>
                            <button
                                type="button"
                                onClick={() => setCreatingTable(false)}
                                style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#94a3b8' }}
                            >
                                <X size={18} />
                            </button>
                        </div>

                        <div className="space-y-3.5">
                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Etiqueta / Nombre
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ej. Mesa 13 o Mesa Terraza"
                                    required
                                    value={newTable.label}
                                    onChange={(e) => setNewTable({ ...newTable, label: e.target.value })}
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                />
                            </div>

                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Capacidad (asientos)
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="20"
                                    required
                                    value={newTable.capacity}
                                    onChange={(e) => setNewTable({ ...newTable, capacity: Number(e.target.value) })}
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                />
                            </div>

                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Zona
                                </label>
                                <input
                                    type="text"
                                    value={newTable.zone}
                                    onChange={(e) => setNewTable({ ...newTable, zone: e.target.value })}
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                />
                            </div>

                            <div>
                                <label
                                    style={{
                                        display: 'block',
                                        fontSize: '11.5px',
                                        fontWeight: '700',
                                        color: '#1e293b',
                                        marginBottom: '4px',
                                    }}
                                >
                                    Grupo de Unión (Opcional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ej. GRUPO-B"
                                    value={newTable.join_group}
                                    onChange={(e) => setNewTable({ ...newTable, join_group: e.target.value })}
                                    style={{
                                        width: '100%',
                                        padding: '8px 11px',
                                        borderRadius: '8px',
                                        border: '1px solid #cbd5e1',
                                        fontSize: '13px',
                                    }}
                                />
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-4 border-t border-slate-100 mt-4">
                            <button
                                type="button"
                                onClick={() => setCreatingTable(false)}
                                style={{
                                    padding: '7px 14px',
                                    borderRadius: '7px',
                                    border: '1px solid #cbd5e1',
                                    background: '#fff',
                                    fontSize: '12.5px',
                                    cursor: 'pointer',
                                }}
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={tableSaving}
                                style={{
                                    padding: '7px 16px',
                                    borderRadius: '7px',
                                    background: '#004797',
                                    color: '#fff',
                                    fontWeight: '700',
                                    fontSize: '12.5px',
                                    border: 'none',
                                    cursor: 'pointer',
                                }}
                            >
                                {tableSaving ? 'Creando...' : 'Crear Mesa'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </section>
    );
}
