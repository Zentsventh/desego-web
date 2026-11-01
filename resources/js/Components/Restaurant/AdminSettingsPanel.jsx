import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Clock3, Store, CalendarDays, Check, RefreshCw } from 'lucide-react';

const DEFAULT_HOURS = [
    { day_of_week: 1, day_name: 'Lunes', open_time: '09:00', close_time: '17:00', is_closed: false },
    { day_of_week: 2, day_name: 'Martes', open_time: '09:00', close_time: '17:00', is_closed: false },
    { day_of_week: 3, day_name: 'Miércoles', open_time: '09:00', close_time: '17:00', is_closed: false },
    { day_of_week: 4, day_name: 'Jueves', open_time: '09:00', close_time: '17:00', is_closed: false },
    { day_of_week: 5, day_name: 'Viernes', open_time: '09:00', close_time: '17:00', is_closed: false },
    { day_of_week: 6, day_name: 'Sábado', open_time: '09:00', close_time: '17:00', is_closed: false },
    { day_of_week: 7, day_name: 'Domingo', open_time: '09:00', close_time: '17:00', is_closed: false },
];

export default function AdminSettingsPanel({ data = {} }) {
    const initialCommercial = data.commercial || {};
    const initialHours = data.business_hours?.length ? data.business_hours : DEFAULT_HOURS;
    const initialRes = data.reservation_config || {
        advance_amount: 10,
        tolerance_minutes: 15,
        cancellation_deadline_hours: 3,
        reservations_enabled: true,
    };

    const [tab, setTab] = useState('horarios');
    const [saving, setSaving] = useState(false);

    const [hours, setHours] = useState(() =>
        DEFAULT_HOURS.map((def) => {
            const found = initialHours.find((h) => Number(h.day_of_week) === def.day_of_week);
            return found
                ? {
                      day_of_week: def.day_of_week,
                      day_name: def.day_name,
                      open_time: String(found.open_time || '09:00').slice(0, 5),
                      close_time: String(found.close_time || '17:00').slice(0, 5),
                      is_closed: Boolean(found.is_closed),
                  }
                : def;
        })
    );

    const [commercial, setCommercial] = useState({
        restaurant_name: initialCommercial.restaurant_name || 'DeSego Cevichería',
        restaurant_specialty: initialCommercial.restaurant_specialty || 'Pescados, mariscos y comida peruana',
        restaurant_address: initialCommercial.restaurant_address || 'UCV 19, Lote 13, Zona A, Huaycán, Ate',
        restaurant_district: initialCommercial.restaurant_district || 'Ate',
        restaurant_postal_code: initialCommercial.restaurant_postal_code || '15479',
        restaurant_latitude: initialCommercial.restaurant_latitude ?? -12.01685,
        restaurant_longitude: initialCommercial.restaurant_longitude ?? -76.82112,
        restaurant_whatsapp: initialCommercial.restaurant_whatsapp || '+51 922 119 238',
        restaurant_whatsapp_url: initialCommercial.restaurant_whatsapp_url || 'https://wa.me/51922119238',
        restaurant_facebook: initialCommercial.restaurant_facebook || 'https://facebook.com/desegocevicheria',
        restaurant_instagram: initialCommercial.restaurant_instagram || 'https://instagram.com/desegocevicheria',
        restaurant_tiktok: initialCommercial.restaurant_tiktok || 'https://www.tiktok.com/@desegocevicheria/',
        restaurant_email: initialCommercial.restaurant_email || '',
        delivery_enabled: initialCommercial.delivery_enabled ?? true,
        pickup_enabled: initialCommercial.pickup_enabled ?? true,
        dine_in_enabled: initialCommercial.dine_in_enabled ?? true,
        reservations_enabled: initialCommercial.reservations_enabled ?? true,
        payment_methods_text: (initialCommercial.payment_methods || ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir']).join(', '),
    });

    const [resConfig, setResConfig] = useState({
        advance_amount: initialRes.advance_amount ?? 10,
        tolerance_minutes: initialRes.tolerance_minutes ?? 15,
        cancellation_deadline_hours: initialRes.cancellation_deadline_hours ?? 3,
        reservations_enabled: initialRes.reservations_enabled ?? true,
    });

    const updateDay = (dow, field, value) => {
        setHours((prev) => prev.map((h) => (h.day_of_week === dow ? { ...h, [field]: value } : h)));
    };

    const applyStandardSchedule = () => {
        setHours(DEFAULT_HOURS.map((h) => ({ ...h })));
    };

    const saveHours = (e) => {
        e.preventDefault();
        setSaving(true);
        router.post(
            '/admin/acciones/admin-tools',
            { action: 'update_business_hours', hours },
            { preserveScroll: true, onFinish: () => setSaving(false) }
        );
    };

    const saveCommercial = (e) => {
        e.preventDefault();
        setSaving(true);
        const payment_methods = commercial.payment_methods_text
            .split(',')
            .map((m) => m.trim())
            .filter(Boolean);
        router.post(
            '/admin/acciones/admin-tools',
            {
                action: 'update_commercial_settings',
                ...commercial,
                payment_methods,
            },
            { preserveScroll: true, onFinish: () => setSaving(false) }
        );
    };

    const saveReservationConfig = (e) => {
        e.preventDefault();
        setSaving(true);
        router.post(
            '/admin/acciones/admin-tools',
            { action: 'update_reservation_settings', ...resConfig },
            { preserveScroll: true, onFinish: () => setSaving(false) }
        );
    };

    return (
        <div className="space-y-5">
            <div className="glass-page-heading">
                <div>
                    <span className="glass-eyebrow">CONFIGURACIÓN CENTRAL</span>
                    <h2>Horarios, Datos Comerciales y Políticas</h2>
                    <p>Todo cambio guardado aquí actualiza en vivo la tienda pública, el mapa de contacto, los métodos de pago y las reservas.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {[
                        ['horarios', 'Horarios de atención', Clock3],
                        ['comercial', 'Datos y redes oficiales', Store],
                        ['reservas', 'Políticas de reservas', CalendarDays],
                    ].map(([id, label, Icon]) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            className={`glass-btn ${tab === id ? 'glass-btn-primary' : ''}`}
                            style={tab === id ? { background: '#004797', color: '#fff', borderColor: '#004797' } : undefined}
                        >
                            <Icon size={15} />
                            <span>{label}</span>
                        </button>
                    ))}
                </div>
            </div>

            {tab === 'horarios' && (
                <form onSubmit={saveHours} className="glass-panel glass-panel-pad space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="glass-section-title">
                                <Clock3 size={18} />
                                <span>Programación semanal de atención</span>
                            </h3>
                            <p className="glass-panel-sub">Horario oficial de referencia: Lunes a Domingo de 09:00 a 17:00 (hora Lima).</p>
                        </div>
                        <button type="button" onClick={applyStandardSchedule} className="glass-btn glass-btn-sm">
                            <RefreshCw size={13} /> Restaurar 09:00 – 17:00
                        </button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="border-b uppercase tracking-wider text-[11px]">
                                    <th className="py-3 px-3">Día</th>
                                    <th className="py-3 px-3">Apertura</th>
                                    <th className="py-3 px-3">Cierre</th>
                                    <th className="py-3 px-3">Estado del día</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {hours.map((row) => (
                                    <tr key={row.day_of_week}>
                                        <td className="py-3 px-3 font-semibold">{row.day_name}</td>
                                        <td className="py-3 px-3">
                                            <input
                                                type="time"
                                                disabled={row.is_closed}
                                                className="glass-input max-w-[140px]"
                                                value={row.open_time}
                                                onChange={(e) => updateDay(row.day_of_week, 'open_time', e.target.value)}
                                            />
                                        </td>
                                        <td className="py-3 px-3">
                                            <input
                                                type="time"
                                                disabled={row.is_closed}
                                                className="glass-input max-w-[140px]"
                                                value={row.close_time}
                                                onChange={(e) => updateDay(row.day_of_week, 'close_time', e.target.value)}
                                            />
                                        </td>
                                        <td className="py-3 px-3">
                                            <label className="inline-flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={!row.is_closed}
                                                    onChange={(e) => updateDay(row.day_of_week, 'is_closed', !e.target.checked)}
                                                />
                                                <span>{row.is_closed ? 'Cerrado' : 'Abierto al público'}</span>
                                            </label>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={saving}
                            className="glass-btn glass-btn-primary"
                            style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                        >
                            <Check size={15} />
                            <span>{saving ? 'Guardando...' : 'Guardar horarios semanales'}</span>
                        </button>
                    </div>
                </form>
            )}

            {tab === 'comercial' && (
                <form onSubmit={saveCommercial} className="glass-panel glass-panel-pad space-y-4">
                    <div>
                        <h3 className="glass-section-title">
                            <Store size={18} />
                            <span>Información comercial confirmada y canales sociales</span>
                        </h3>
                        <p className="glass-panel-sub">Publica únicamente datos oficiales validados por la administración de DeSego Cevichería.</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label className="glass-field">
                            Nombre comercial
                            <input
                                required
                                className="glass-input"
                                value={commercial.restaurant_name}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_name: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Especialidad
                            <input
                                className="glass-input"
                                value={commercial.restaurant_specialty}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_specialty: e.target.value })}
                            />
                        </label>
                        <label className="glass-field md:col-span-2">
                            Dirección del local
                            <input
                                required
                                className="glass-input"
                                value={commercial.restaurant_address}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_address: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Distrito
                            <input
                                required
                                className="glass-input"
                                value={commercial.restaurant_district}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_district: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Código postal
                            <input
                                className="glass-input"
                                value={commercial.restaurant_postal_code}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_postal_code: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Latitud del local (Mapa de contacto)
                            <input
                                type="number"
                                step="0.0000001"
                                required
                                className="glass-input"
                                value={commercial.restaurant_latitude}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_latitude: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Longitud del local (Mapa de contacto)
                            <input
                                type="number"
                                step="0.0000001"
                                required
                                className="glass-input"
                                value={commercial.restaurant_longitude}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_longitude: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            WhatsApp oficial
                            <input
                                required
                                className="glass-input"
                                value={commercial.restaurant_whatsapp}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_whatsapp: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Correo oficial (dejar vacío hasta confirmar)
                            <input
                                type="email"
                                placeholder="Opcional"
                                className="glass-input"
                                value={commercial.restaurant_email}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_email: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Facebook URL
                            <input
                                className="glass-input"
                                value={commercial.restaurant_facebook}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_facebook: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Instagram URL
                            <input
                                className="glass-input"
                                value={commercial.restaurant_instagram}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_instagram: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            TikTok URL
                            <input
                                className="glass-input"
                                value={commercial.restaurant_tiktok}
                                onChange={(e) => setCommercial({ ...commercial, restaurant_tiktok: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Métodos de pago aceptados (separados por coma)
                            <input
                                required
                                className="glass-input"
                                value={commercial.payment_methods_text}
                                onChange={(e) => setCommercial({ ...commercial, payment_methods_text: e.target.value })}
                            />
                        </label>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2">
                        {[
                            ['delivery_enabled', 'Delivery a domicilio'],
                            ['pickup_enabled', 'Recojo en tienda'],
                            ['dine_in_enabled', 'Consumo en local'],
                            ['reservations_enabled', 'Reservas en línea'],
                        ].map(([key, label]) => (
                            <label key={key} className="glass-panel p-3 flex items-center gap-2.5 text-xs font-medium cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(commercial[key])}
                                    onChange={(e) => setCommercial({ ...commercial, [key]: e.target.checked })}
                                />
                                <span>{label}</span>
                            </label>
                        ))}
                    </div>

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={saving}
                            className="glass-btn glass-btn-primary"
                            style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                        >
                            <Check size={15} />
                            <span>{saving ? 'Guardando...' : 'Guardar configuración comercial'}</span>
                        </button>
                    </div>
                </form>
            )}

            {tab === 'reservas' && (
                <form onSubmit={saveReservationConfig} className="glass-panel glass-panel-pad space-y-4">
                    <div>
                        <h3 className="glass-section-title">
                            <CalendarDays size={18} />
                            <span>Reglas operativas de reservas</span>
                        </h3>
                        <p className="glass-panel-sub">Define el anticipo requerido (usa 0.00 para reservas directas sin pago previo), tolerancia de llegada y plazo de cancelación.</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label className="glass-field">
                            Anticipo por reserva (S/)
                            <input
                                type="number"
                                step="0.50"
                                min="0"
                                max="500"
                                required
                                className="glass-input"
                                value={resConfig.advance_amount}
                                onChange={(e) => setResConfig({ ...resConfig, advance_amount: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Tolerancia de llegada (minutos)
                            <input
                                type="number"
                                min="5"
                                max="120"
                                required
                                className="glass-input"
                                value={resConfig.tolerance_minutes}
                                onChange={(e) => setResConfig({ ...resConfig, tolerance_minutes: e.target.value })}
                            />
                        </label>
                        <label className="glass-field">
                            Horas previas para cancelación gratuita
                            <input
                                type="number"
                                min="0"
                                max="72"
                                required
                                className="glass-input"
                                value={resConfig.cancellation_deadline_hours}
                                onChange={(e) => setResConfig({ ...resConfig, cancellation_deadline_hours: e.target.value })}
                            />
                        </label>
                    </div>

                    <label className="inline-flex items-center gap-2 text-xs font-medium cursor-pointer">
                        <input
                            type="checkbox"
                            checked={Boolean(resConfig.reservations_enabled)}
                            onChange={(e) => setResConfig({ ...resConfig, reservations_enabled: e.target.checked })}
                        />
                        <span>Recepción de reservas habilitada en la web</span>
                    </label>

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={saving}
                            className="glass-btn glass-btn-primary"
                            style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                        >
                            <Check size={15} />
                            <span>{saving ? 'Guardando...' : 'Guardar políticas de reservas'}</span>
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
