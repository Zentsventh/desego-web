import React, { useEffect, useState } from 'react';
import {
    AlertTriangle,
    Check,
    Clock3,
    PanelsTopLeft,
    Plus,
    RefreshCw,
    Settings2,
    SlidersHorizontal,
    Sparkles,
    UsersRound,
    X,
} from './Icons';
import { money } from './ShopParts';

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
        if (!table.is_active) {
            return { label: 'Deshabilitada', bg: 'rgba(100, 116, 139, 0.15)', text: '#64748b', dot: '#94a3b8' };
        }
        if (table.effective_status === 'occupied') {
            return { label: 'En atención', bg: 'rgba(0, 71, 151, 0.12)', text: '#004797', dot: '#004797' };
        }
        if (table.effective_status === 'reserved') {
            return { label: 'Reservada', bg: 'rgba(217, 119, 6, 0.15)', text: '#b45309', dot: '#f59e0b' };
        }
        if (table.effective_status === 'cleaning') {
            return { label: 'Por limpiar', bg: 'rgba(147, 51, 234, 0.12)', text: '#7e22ce', dot: '#a855f7' };
        }
        return { label: 'Disponible', bg: 'rgba(22, 163, 74, 0.12)', text: '#15803d', dot: '#22c55e' };
    };

    return (
        <section className="glass-panel glass-panel-pad mb-6" style={{ borderRadius: '20px', border: '1px solid rgba(0, 71, 151, 0.14)' }}>
            {/* Header del Centro de Control */}
            <div className="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-blue-900/10">
                <div className="flex items-center gap-3">
                    <div style={{ width: '42px', height: '42px', borderRadius: '12px', background: 'linear-gradient(135deg, #004797 0%, #002b5e 100%)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', boxShadow: '0 4px 14px rgba(0, 71, 151, 0.28)' }}>
                        <PanelsTopLeft size={22} />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 style={{ fontSize: '18px', fontWeight: '800', color: '#0f172a', margin: 0, letterSpacing: '-0.02em' }}>
                                Salón Principal & Políticas de Reserva
                            </h3>
                            <span style={{ fontSize: '11px', fontWeight: '700', padding: '2px 8px', borderRadius: '999px', background: 'rgba(0, 71, 151, 0.1)', color: '#004797' }}>
                                12 Mesas · 40 Pax
                            </span>
                        </div>
                        <p style={{ fontSize: '12.5px', color: '#64748b', margin: '2px 0 0' }}>
                            Supervisión en tiempo real, gestión visual de capacidad y políticas de anticipo de S/ 10.00
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {/* Botones de pestaña */}
                    <div style={{ display: 'inline-flex', background: 'rgba(0, 71, 151, 0.06)', padding: '4px', borderRadius: '12px', border: '1px solid rgba(0, 71, 151, 0.1)' }}>
                        <button
                            type="button"
                            onClick={() => setActiveTab('grid')}
                            style={{
                                padding: '6px 14px',
                                borderRadius: '8px',
                                fontSize: '12.5px',
                                fontWeight: activeTab === 'grid' ? '700' : '600',
                                background: activeTab === 'grid' ? '#004797' : 'transparent',
                                color: activeTab === 'grid' ? '#fff' : '#475569',
                                border: 'none',
                                cursor: 'pointer',
                                transition: 'all 160ms ease',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '6px',
                            }}
                        >
                            <PanelsTopLeft size={14} />
                            <span>Mesas del Salón</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('config')}
                            style={{
                                padding: '6px 14px',
                                borderRadius: '8px',
                                fontSize: '12.5px',
                                fontWeight: activeTab === 'config' ? '700' : '600',
                                background: activeTab === 'config' ? '#004797' : 'transparent',
                                color: activeTab === 'config' ? '#fff' : '#475569',
                                border: 'none',
                                cursor: 'pointer',
                                transition: 'all 160ms ease',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '6px',
                            }}
                        >
                            <SlidersHorizontal size={14} />
                            <span>Políticas & Parámetros</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={fetchTables}
                        disabled={loading}
                        aria-label="Actualizar estado de mesas"
                        style={{
                            width: '36px',
                            height: '36px',
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
                        <RefreshCw size={15} className={loading ? 'animate-spin' : ''} />
                    </button>

                    {canManage && activeTab === 'grid' && (
                        <button
                            type="button"
                            onClick={() => setCreatingTable(true)}
                            style={{
                                padding: '7px 14px',
                                borderRadius: '10px',
                                background: 'linear-gradient(135deg, #004797 0%, #003878 100%)',
                                color: '#fff',
                                fontWeight: '700',
                                fontSize: '12.5px',
                                border: 'none',
                                cursor: 'pointer',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '6px',
                                boxShadow: '0 4px 12px rgba(0, 71, 151, 0.25)',
                            }}
                        >
                            <Plus size={15} />
                            <span>Agregar Mesa</span>
                        </button>
                    )}
                </div>
            </div>

            {/* Notificación Toast */}
            {toast && (
                <div
                    style={{
                        marginTop: '14px',
                        padding: '10px 16px',
                        borderRadius: '10px',
                        background: toast.type === 'error' ? '#fef2f2' : '#f0fdf4',
                        border: `1px solid ${toast.type === 'error' ? '#fecaca' : '#bbf7d0'}`,
                        color: toast.type === 'error' ? '#991b1b' : '#166534',
                        fontSize: '13px',
                        fontWeight: '600',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                    }}
                >
                    {toast.type === 'error' ? <AlertTriangle size={16} /> : <Check size={16} />}
                    <span>{toast.message}</span>
                </div>
            )}

            {/* VISTA 1: CUADRÍCULA DE MESAS (3 columnas x 4 filas) */}
            {activeTab === 'grid' && (
                <div style={{ marginTop: '20px' }}>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {tables.map((table) => {
                            const badge = getStatusBadge(table);
                            return (
                                <div
                                    key={table.id}
                                    style={{
                                        background: table.is_active ? '#ffffff' : 'rgba(248, 250, 252, 0.8)',
                                        border: `1px solid ${table.is_active ? 'rgba(0, 71, 151, 0.12)' : 'rgba(148, 163, 184, 0.2)'}`,
                                        borderRadius: '16px',
                                        padding: '16px',
                                        boxShadow: table.is_active ? '0 4px 16px -3px rgba(0, 71, 151, 0.06)' : 'none',
                                        opacity: table.is_active ? 1 : 0.72,
                                        transition: 'all 200ms ease',
                                    }}
                                >
                                    <div className="flex items-start justify-between gap-2 mb-3">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h4 style={{ fontSize: '16px', fontWeight: '800', color: '#0f172a', margin: 0 }}>
                                                    {table.label}
                                                </h4>
                                                <span
                                                    style={{
                                                        fontSize: '11px',
                                                        fontWeight: '700',
                                                        padding: '2px 8px',
                                                        borderRadius: '999px',
                                                        background: badge.bg,
                                                        color: badge.text,
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: '4px',
                                                    }}
                                                >
                                                    <span style={{ width: '6px', height: '6px', borderRadius: '50%', background: badge.dot }} />
                                                    {badge.label}
                                                </span>
                                            </div>
                                            <span style={{ fontSize: '11.5px', color: '#64748b' }}>
                                                {table.zone || 'Salón principal'}
                                            </span>
                                        </div>

                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: '4px',
                                                background: 'rgba(0, 71, 151, 0.05)',
                                                padding: '4px 8px',
                                                borderRadius: '8px',
                                                color: '#004797',
                                                fontSize: '12px',
                                                fontWeight: '700',
                                            }}
                                        >
                                            <UsersRound size={13} />
                                            <span>{table.capacity} pax</span>
                                        </div>
                                    </div>

                                    {/* Información de estado o reserva vinculada */}
                                    <div style={{ minHeight: '44px', marginBottom: '12px' }}>
                                        {table.next_reservation ? (
                                            <div style={{ background: '#fffbeb', border: '1px solid #fef3c7', borderRadius: '10px', padding: '6px 10px', fontSize: '11.5px', color: '#92400e' }}>
                                                <div className="flex items-center justify-between">
                                                    <strong>{table.next_reservation.customer_name}</strong>
                                                    <span>{table.next_reservation.guest_count} personas</span>
                                                </div>
                                                <div className="flex items-center gap-1 mt-0.5 text-amber-700">
                                                    <Clock3 size={11} />
                                                    <span>Hora: {new Date(table.next_reservation.reservation_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                                </div>
                                            </div>
                                        ) : table.active_session ? (
                                            <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '10px', padding: '6px 10px', fontSize: '11.5px', color: '#166534' }}>
                                                <div className="flex items-center justify-between">
                                                    <strong>Mesa en salón activa</strong>
                                                    <span className="font-mono text-xs">Visita #{table.active_session.id.substring(0, 6)}</span>
                                                </div>
                                            </div>
                                        ) : (
                                            <div style={{ fontSize: '11.5px', color: '#94a3b8', fontStyle: 'italic', paddingTop: '4px' }}>
                                                {table.is_active ? 'Lista para nuevas reservas o comensales' : 'Mesa inhabilitada temporalmente'}
                                            </div>
                                        )}
                                    </div>

                                    {/* Controles de mesa para Administrador */}
                                    {canManage && (
                                        <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    title={table.is_active ? 'Deshabilitar mesa' : 'Habilitar mesa'}
                                                    onClick={() => handleUpdateTable(table.id, { is_active: !table.is_active })}
                                                    style={{
                                                        fontSize: '11px',
                                                        fontWeight: '700',
                                                        padding: '4px 8px',
                                                        borderRadius: '6px',
                                                        border: '1px solid',
                                                        borderColor: table.is_active ? '#cbd5e1' : '#bbf7d0',
                                                        background: table.is_active ? '#f8fafc' : '#dcfce7',
                                                        color: table.is_active ? '#475569' : '#15803d',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    {table.is_active ? 'Pausar' : 'Activar'}
                                                </button>

                                                <button
                                                    type="button"
                                                    title={table.is_online_reservable ? 'Ocultar de reservas web' : 'Habilitar en reservas web'}
                                                    onClick={() => handleUpdateTable(table.id, { is_online_reservable: !table.is_online_reservable })}
                                                    style={{
                                                        fontSize: '11px',
                                                        fontWeight: '700',
                                                        padding: '4px 8px',
                                                        borderRadius: '6px',
                                                        border: '1px solid',
                                                        borderColor: table.is_online_reservable ? '#e2e8f0' : '#fed7aa',
                                                        background: table.is_online_reservable ? '#fff' : '#ffedd5',
                                                        color: table.is_online_reservable ? '#004797' : '#c2410c',
                                                        cursor: 'pointer',
                                                    }}
                                                >
                                                    {table.is_online_reservable ? 'Web Activa' : 'Solo Local'}
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => setEditingTable({ ...table })}
                                                style={{
                                                    fontSize: '11.5px',
                                                    fontWeight: '700',
                                                    padding: '4px 10px',
                                                    borderRadius: '6px',
                                                    background: 'rgba(0, 71, 151, 0.08)',
                                                    color: '#004797',
                                                    border: 'none',
                                                    cursor: 'pointer',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '4px',
                                                }}
                                            >
                                                <Settings2 size={12} />
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
                <div style={{ marginTop: '20px' }}>
                    {configLoading ? (
                        <div className="py-12 text-center text-slate-500">
                            <RefreshCw size={24} className="animate-spin mx-auto mb-2 text-blue-600" />
                            <span>Cargando parámetros de configuración...</span>
                        </div>
                    ) : config ? (
                        <form onSubmit={handleSaveConfig} style={{ background: '#fff', borderRadius: '16px', padding: '24px', border: '1px solid rgba(0, 71, 151, 0.12)' }}>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                                {/* Anticipo de Reserva */}
                                <div>
                                    <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '6px' }}>
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
                                            padding: '10px 14px',
                                            borderRadius: '10px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '14px',
                                            fontWeight: '700',
                                            color: '#004797',
                                        }}
                                    />
                                    <small style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px', display: 'block' }}>
                                        Monto fijo por reserva (aplicable como crédito al consumo final).
                                    </small>
                                </div>

                                {/* Tolerancia de Inasistencia */}
                                <div>
                                    <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '6px' }}>
                                        Tolerancia Máxima (minutos)
                                    </label>
                                    <input
                                        type="number"
                                        min="5"
                                        max="60"
                                        required
                                        value={config.tolerance_minutes}
                                        onChange={(e) => setConfig({ ...config, tolerance_minutes: Number(e.target.value) })}
                                        style={{
                                            width: '100%',
                                            padding: '10px 14px',
                                            borderRadius: '10px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '14px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px', display: 'block' }}>
                                        Superado este tiempo, la reserva pasa a NO_SHOW y retiene el anticipo.
                                    </small>
                                </div>

                                {/* Retención del Bloqueo Temporal */}
                                <div>
                                    <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '6px' }}>
                                        Tiempo de Bloqueo de Pago (minutos)
                                    </label>
                                    <input
                                        type="number"
                                        min="3"
                                        max="30"
                                        required
                                        value={config.hold_duration_minutes}
                                        onChange={(e) => setConfig({ ...config, hold_duration_minutes: Number(e.target.value) })}
                                        style={{
                                            width: '100%',
                                            padding: '10px 14px',
                                            borderRadius: '10px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '14px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px', display: 'block' }}>
                                        Minutos para que el cliente pague su anticipo antes de liberar la mesa.
                                    </small>
                                </div>

                                {/* Tiempo de Limpieza entre Reservas */}
                                <div>
                                    <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '6px' }}>
                                        Margen de Limpieza (minutos)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="60"
                                        required
                                        value={config.cleaning_minutes}
                                        onChange={(e) => setConfig({ ...config, cleaning_minutes: Number(e.target.value) })}
                                        style={{
                                            width: '100%',
                                            padding: '10px 14px',
                                            borderRadius: '10px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '14px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px', display: 'block' }}>
                                        Buffer de desinfección entre el fin de una reserva y la siguiente.
                                    </small>
                                </div>

                                {/* Cancelación con Devolución */}
                                <div>
                                    <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '6px' }}>
                                        Límite Cancelación c/ Reembolso (horas)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="48"
                                        required
                                        value={config.cancellation_deadline_hours}
                                        onChange={(e) => setConfig({ ...config, cancellation_deadline_hours: Number(e.target.value) })}
                                        style={{
                                            width: '100%',
                                            padding: '10px 14px',
                                            borderRadius: '10px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '14px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px', display: 'block' }}>
                                        Horas de antelación requeridas para reembolso automático del anticipo.
                                    </small>
                                </div>

                                {/* Antelación Máxima para Reservar */}
                                <div>
                                    <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '6px' }}>
                                        Antelación Máxima (días)
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="90"
                                        required
                                        value={config.max_advance_days}
                                        onChange={(e) => setConfig({ ...config, max_advance_days: Number(e.target.value) })}
                                        style={{
                                            width: '100%',
                                            padding: '10px 14px',
                                            borderRadius: '10px',
                                            border: '1px solid #cbd5e1',
                                            fontSize: '14px',
                                            fontWeight: '700',
                                        }}
                                    />
                                    <small style={{ fontSize: '11.5px', color: '#64748b', marginTop: '4px', display: 'block' }}>
                                        Ventana de días hacia adelante disponibles en el calendario web.
                                    </small>
                                </div>
                            </div>

                            {/* Switch de Reservas Habilitadas */}
                            <div className="flex items-center justify-between p-4 bg-slate-50 rounded-xl mb-6 border border-slate-200">
                                <div>
                                    <strong style={{ fontSize: '14px', color: '#0f172a' }}>Habilitar Sistema de Reservas Online</strong>
                                    <p style={{ fontSize: '12px', color: '#64748b', margin: 0 }}>
                                        Al desactivar, los clientes verán un mensaje avisando que las reservas online están en mantenimiento.
                                    </p>
                                </div>
                                <label style={{ position: 'relative', display: 'inline-block', width: '48px', height: '26px' }}>
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
                                                height: '20px',
                                                width: '20px',
                                                left: config.reservations_enabled ? '24px' : '3px',
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
                                        padding: '10px 24px',
                                        borderRadius: '10px',
                                        background: 'linear-gradient(135deg, #004797 0%, #003878 100%)',
                                        color: '#fff',
                                        fontWeight: '700',
                                        fontSize: '13.5px',
                                        border: 'none',
                                        cursor: 'pointer',
                                        boxShadow: '0 4px 14px rgba(0, 71, 151, 0.28)',
                                    }}
                                >
                                    {configSaving ? 'Guardando...' : 'Guardar Políticas de Reserva'}
                                </button>
                            </div>
                        </form>
                    ) : null}
                </div>
            )}

            {/* MODAL: EDITAR MESA */}
            {editingTable && (
                <div style={{ position: 'fixed', inset: 0, background: 'rgba(15, 23, 42, 0.5)', backdropFilter: 'blur(4px)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: '20px' }}>
                    <div style={{ background: '#fff', borderRadius: '18px', maxWidth: '440px', width: '100%', padding: '24px', boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 style={{ fontSize: '17px', fontWeight: '800', color: '#0f172a', margin: 0 }}>
                                Configurar {editingTable.label}
                            </h3>
                            <button type="button" onClick={() => setEditingTable(null)} style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#94a3b8' }}>
                                <X size={20} />
                            </button>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Capacidad de Comensales
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="20"
                                    value={editingTable.capacity}
                                    onChange={(e) => setEditingTable({ ...editingTable, capacity: Number(e.target.value) })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
                                />
                            </div>

                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Grupo de Unión de Mesas (Contiguas)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ej. GRUPO-A (dejar vacío si es independiente)"
                                    value={editingTable.join_group || ''}
                                    onChange={(e) => setEditingTable({ ...editingTable, join_group: e.target.value })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
                                />
                                <small style={{ fontSize: '11px', color: '#64748b' }}>
                                    Mesas con el mismo identificador de grupo podrán unirse físicamente para grupos grandes.
                                </small>
                            </div>

                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Estado Operativo Físico
                                </label>
                                <select
                                    value={editingTable.operational_status}
                                    onChange={(e) => setEditingTable({ ...editingTable, operational_status: e.target.value })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
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
                                        onChange={(e) => setEditingTable({ ...editingTable, is_active: e.target.checked })}
                                    />
                                    <span style={{ fontSize: '13px', fontWeight: '600', color: '#1e293b' }}>Habilitada en salón</span>
                                </label>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={editingTable.is_online_reservable}
                                        onChange={(e) => setEditingTable({ ...editingTable, is_online_reservable: e.target.checked })}
                                    />
                                    <span style={{ fontSize: '13px', fontWeight: '600', color: '#1e293b' }}>Reservable online</span>
                                </label>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-5 border-t border-slate-100 mt-5">
                            <button
                                type="button"
                                onClick={() => setEditingTable(null)}
                                style={{ padding: '8px 16px', borderRadius: '8px', border: '1px solid #cbd5e1', background: '#fff', fontSize: '13px', cursor: 'pointer' }}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                disabled={tableSaving}
                                onClick={() => handleUpdateTable(editingTable.id, {
                                    capacity: editingTable.capacity,
                                    join_group: editingTable.join_group,
                                    operational_status: editingTable.operational_status,
                                    is_active: editingTable.is_active,
                                    is_online_reservable: editingTable.is_online_reservable,
                                })}
                                style={{ padding: '8px 18px', borderRadius: '8px', background: '#004797', color: '#fff', fontWeight: '700', fontSize: '13px', border: 'none', cursor: 'pointer' }}
                            >
                                {tableSaving ? 'Guardando...' : 'Guardar Cambios'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL: NUEVA MESA */}
            {creatingTable && (
                <div style={{ position: 'fixed', inset: 0, background: 'rgba(15, 23, 42, 0.5)', backdropFilter: 'blur(4px)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: '20px' }}>
                    <form onSubmit={handleCreateTable} style={{ background: '#fff', borderRadius: '18px', maxWidth: '420px', width: '100%', padding: '24px', boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 style={{ fontSize: '17px', fontWeight: '800', color: '#0f172a', margin: 0 }}>
                                Registrar Nueva Mesa
                            </h3>
                            <button type="button" onClick={() => setCreatingTable(false)} style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#94a3b8' }}>
                                <X size={20} />
                            </button>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Etiqueta / Nombre
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ej. Mesa 13 o Mesa Terraza"
                                    value={newTable.label}
                                    onChange={(e) => setNewTable({ ...newTable, label: e.target.value })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
                                />
                            </div>

                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Capacidad (asientos)
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="20"
                                    required
                                    value={newTable.capacity}
                                    onChange={(e) => setNewTable({ ...newTable, capacity: Number(e.target.value) })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
                                />
                            </div>

                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Zona
                                </label>
                                <input
                                    type="text"
                                    value={newTable.zone}
                                    onChange={(e) => setNewTable({ ...newTable, zone: e.target.value })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
                                />
                            </div>

                            <div>
                                <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#1e293b', marginBottom: '4px' }}>
                                    Grupo de Unión (Opcional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="Ej. GRUPO-B"
                                    value={newTable.join_group}
                                    onChange={(e) => setNewTable({ ...newTable, join_group: e.target.value })}
                                    style={{ width: '100%', padding: '9px 12px', borderRadius: '8px', border: '1px solid #cbd5e1' }}
                                />
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-5 border-t border-slate-100 mt-5">
                            <button
                                type="button"
                                onClick={() => setCreatingTable(false)}
                                style={{ padding: '8px 16px', borderRadius: '8px', border: '1px solid #cbd5e1', background: '#fff', fontSize: '13px', cursor: 'pointer' }}
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={tableSaving}
                                style={{ padding: '8px 18px', borderRadius: '8px', background: '#004797', color: '#fff', fontWeight: '700', fontSize: '13px', border: 'none', cursor: 'pointer' }}
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
