import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { money } from './ShopParts';

const EMPTY_ZONE = {
    id: null,
    name: '',
    district: 'Ate',
    zone_sector: 'Huaycán',
    center_latitude: -12.01685,
    center_longitude: -76.82112,
    radius_km: 1.5,
    delivery_fee: '0.00',
    fee_confirmed: false,
    min_order_amount: '0.00',
    estimated_minutes_min: 25,
    estimated_minutes_max: 45,
    is_active: true,
};

export default function AdminDeliveryZones({ zones = [], canManage = false }) {
    const [openForm, setOpenForm] = useState(false);
    const [form, setForm] = useState(EMPTY_ZONE);
    const [saving, setSaving] = useState(false);

    const startCreate = () => {
        setForm({ ...EMPTY_ZONE });
        setOpenForm(true);
    };

    const startEdit = (z) => {
        setForm({
            id: z.id,
            name: z.name || '',
            district: z.district || 'Ate',
            zone_sector: z.zone_sector || '',
            center_latitude: z.center_latitude ?? -12.01685,
            center_longitude: z.center_longitude ?? -76.82112,
            radius_km: z.radius_km ?? 1.5,
            delivery_fee: Number(z.delivery_fee || 0).toFixed(2),
            fee_confirmed: Boolean(z.fee_confirmed),
            min_order_amount: Number(z.min_order_amount || 0).toFixed(2),
            estimated_minutes_min: z.estimated_minutes_min ?? 25,
            estimated_minutes_max: z.estimated_minutes_max ?? 45,
            is_active: Boolean(z.is_active),
        });
        setOpenForm(true);
    };

    const submitZone = (e) => {
        e.preventDefault();
        setSaving(true);
        router.post(
            '/admin/acciones/operations',
            {
                action: form.id ? 'zone.update' : 'zone.create',
                ...form,
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpenForm(false),
                onFinish: () => setSaving(false),
            }
        );
    };

    const toggleZone = (z) => {
        router.post(
            '/admin/acciones/operations',
            { action: 'zone.toggle', id: z.id, is_active: !z.is_active },
            { preserveScroll: true }
        );
    };

    const deleteZone = (z) => {
        if (!window.confirm(`¿Eliminar la zona "${z.name}"?`)) {
            return;
        }
        router.post(
            '/admin/acciones/operations',
            { action: 'zone.delete', id: z.id },
            { preserveScroll: true }
        );
    };

    return (
        <section className="glass-panel glass-panel-pad mb-5 space-y-4" aria-label="Zonas de cobertura de delivery">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="glass-section-title">
                        <MapPin size={18} />
                        <span>Zonas de Cobertura Geográfica ({zones.length})</span>
                    </h3>
                    <p className="glass-panel-sub">
                        Define radios en km y coordenadas alrededor de Huaycán / Ate. Activa &ldquo;Tarifa oficial confirmada&rdquo; solo cuando definas el costo comercial de reparto.
                    </p>
                </div>
                {canManage && (
                    <button
                        type="button"
                        onClick={startCreate}
                        className="glass-btn glass-btn-sm"
                        style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                    >
                        <Plus size={14} /> Nueva zona
                    </button>
                )}
            </div>

            {zones.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {zones.map((z) => (
                        <div key={z.id} className="glass-panel p-3.5 space-y-2 text-xs">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <strong className="text-sm block">{z.name}</strong>
                                    <span className="opacity-75">
                                        {z.zone_sector ? `${z.zone_sector} · ` : ''}{z.district}
                                    </span>
                                </div>
                                <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold ${z.is_active ? 'bg-emerald-500/15 text-emerald-600' : 'bg-slate-500/15 text-slate-500'}`}>
                                    {z.is_active ? 'Activa' : 'Pausada'}
                                </span>
                            </div>
                            <div className="space-y-0.5 opacity-85">
                                <div>
                                    <strong>Tarifa:</strong>{' '}
                                    {z.fee_confirmed ? money(z.delivery_fee) : 'Por coordinar con el local (S/ 0.00)'}
                                </div>
                                <div>
                                    <strong>Radio:</strong> {z.radius_km || '—'} km · <strong>Tiempo:</strong> {z.estimated_minutes_min}–{z.estimated_minutes_max} min
                                </div>
                                {Number(z.min_order_amount) > 0 && (
                                    <div>
                                        <strong>Pedido mín.:</strong> {money(z.min_order_amount)}
                                    </div>
                                )}
                            </div>
                            {canManage && (
                                <div className="flex items-center gap-2 pt-2 border-t">
                                    <button type="button" onClick={() => startEdit(z)} className="glass-btn glass-btn-sm">
                                        <Pencil size={12} /> Editar
                                    </button>
                                    <button type="button" onClick={() => toggleZone(z)} className="glass-btn glass-btn-sm">
                                        {z.is_active ? 'Pausar' : 'Activar'}
                                    </button>
                                    <button type="button" onClick={() => deleteZone(z)} className="glass-btn glass-btn-sm text-rose-600 ml-auto" aria-label={`Eliminar ${z.name}`}>
                                        <Trash2 size={12} />
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {openForm && (
                <form onSubmit={submitZone} className="glass-panel p-4 space-y-3 border-t">
                    <strong className="block text-sm">{form.id ? 'Editar zona de cobertura' : 'Registrar nueva zona de cobertura'}</strong>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                        <label className="glass-field">
                            Nombre de zona
                            <input required className="glass-input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Distrito
                            <input required className="glass-input" value={form.district} onChange={(e) => setForm({ ...form, district: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Sector / Referencia
                            <input className="glass-input" value={form.zone_sector} onChange={(e) => setForm({ ...form, zone_sector: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Latitud centro
                            <input type="number" step="0.0000001" required className="glass-input" value={form.center_latitude} onChange={(e) => setForm({ ...form, center_latitude: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Longitud centro
                            <input type="number" step="0.0000001" required className="glass-input" value={form.center_longitude} onChange={(e) => setForm({ ...form, center_longitude: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Radio cobertura (km)
                            <input type="number" step="0.1" min="0.2" max="50" required className="glass-input" value={form.radius_km} onChange={(e) => setForm({ ...form, radius_km: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Tarifa de envío (S/)
                            <input type="number" step="0.50" min="0" required className="glass-input" value={form.delivery_fee} onChange={(e) => setForm({ ...form, delivery_fee: e.target.value })} />
                        </label>
                        <label className="glass-field">
                            Pedido mínimo (S/)
                            <input type="number" step="1" min="0" required className="glass-input" value={form.min_order_amount} onChange={(e) => setForm({ ...form, min_order_amount: e.target.value })} />
                        </label>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="glass-field">
                                Min. (min)
                                <input type="number" min="5" max="180" required className="glass-input" value={form.estimated_minutes_min} onChange={(e) => setForm({ ...form, estimated_minutes_min: e.target.value })} />
                            </label>
                            <label className="glass-field">
                                Máx. (min)
                                <input type="number" min="5" max="240" required className="glass-input" value={form.estimated_minutes_max} onChange={(e) => setForm({ ...form, estimated_minutes_max: e.target.value })} />
                            </label>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-4 text-xs">
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={form.fee_confirmed} onChange={(e) => setForm({ ...form, fee_confirmed: e.target.checked })} />
                            <span>Tarifa oficial confirmada por administración</span>
                        </label>
                        <label className="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
                            <span>Zona activa para pedidos web</span>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setOpenForm(false)} className="glass-btn glass-btn-sm">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="glass-btn glass-btn-sm"
                            style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                        >
                            {saving ? 'Guardando...' : 'Guardar zona'}
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}
