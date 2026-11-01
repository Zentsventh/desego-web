import React, { useEffect, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { ArrowUpRight, Check, X, ArrowRight, Clock3, MapPin, Truck, ShoppingBag } from './Icons';
import { ErrorList, money } from './ShopParts';

function peruDate() {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

export default function ReservationForm() {
    const { auth } = usePage().props;
    const [step, setStep] = useState('select'); // 'select' | 'payment' | 'confirmed'
    const [formData, setFormData] = useState({
        customer_name: auth?.user?.name || '',
        email: auth?.user?.email || '',
        phone: '',
        date: peruDate(),
        time: '',
        people: 2,
        notes: '',
        table_ids: [],
    });

    const [availability, setAvailability] = useState(null);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [revision, setRevision] = useState(0);
    const [today, setToday] = useState(peruDate());

    // Estado del bloqueo temporal y confirmación
    const [hold, setHold] = useState(null);
    const [confirmedReservation, setConfirmedReservation] = useState(null);
    const [acceptedTerms, setAcceptedTerms] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState('tarjeta');

    useEffect(() => {
        const timer = setInterval(() => {
            setToday(peruDate());
            setRevision(v => v + 1);
        }, 30000);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError('');
        const params = new URLSearchParams({
            date: formData.date,
            time: formData.time || '',
            people: String(formData.people),
        });

        fetch(`/api/reservas/disponibilidad?${params}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        })
            .then(async response => {
                const body = await response.json();
                if (!response.ok) {
                    throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'No pudimos consultar las mesas.');
                }
                return body;
            })
            .then(body => {
                if (controller.signal.aborted) return;
                setAvailability(body);
                setToday(body.today);
                setFormData(prev => ({
                    ...prev,
                    time: body.time || '',
                    table_ids: prev.table_ids.filter(id => body.tables.some(table => table.id === id && table.available)),
                }));
            })
            .catch(reason => {
                if (reason.name !== 'AbortError') setError(reason.message);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [formData.date, formData.time, formData.people, revision]);

    const selectedTables = (availability?.tables || []).filter(table => formData.table_ids.includes(table.id));
    const capacity = selectedTables.reduce((sum, table) => sum + table.capacity, 0);
    const together = selectedTables.length > 1 && selectedTables[0].join_group && selectedTables.every(t => t.join_group === selectedTables[0].join_group);
    const groups = (availability?.tables || []).reduce((acc, table) => {
        const zone = table.zone || 'Salón Principal';
        if (!acc[zone]) acc[zone] = [];
        acc[zone].push(table);
        return acc;
    }, {});
    const configuredAdvance = Number(hold?.advance_amount ?? availability?.advance_amount ?? 10);
    const requiresAdvance = configuredAdvance > 0;
    const configuredTolerance = Number(availability?.tolerance_minutes ?? 15);

    const changeSchedule = (field, value) => {
        setAvailability(null);
        setLoading(true);
        setError('');
        setFormData(prev => ({
            ...prev,
            [field]: value,
            ...(field === 'date' ? { time: '' } : {}),
            table_ids: [],
        }));
    };

    const handleCreateHold = async (e) => {
        e.preventDefault();
        setError('');
        if (!formData.customer_name.trim() || !formData.phone.trim() || !formData.date || !formData.time) {
            setError('Por favor completa todos los campos obligatorios.');
            return;
        }

        setSubmitting(true);
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('/api/reservas/crear-bloqueo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    customer_name: formData.customer_name.trim(),
                    phone: formData.phone.trim(),
                    email: formData.email.trim() || null,
                    date: formData.date,
                    time: formData.time,
                    people: formData.people,
                    notes: formData.notes.trim() || null,
                    table_ids: formData.table_ids.length ? formData.table_ids : undefined,
                }),
            });

            const body = await res.json();
            if (!res.ok || !body.ok) {
                throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'No se pudo crear el bloqueo de reserva.');
            }

            setHold(body);
            setStep('payment');
        } catch (err) {
            setError(err.message);
        } finally {
            setSubmitting(false);
        }
    };

    const handleProcessPayment = async () => {
        if (!acceptedTerms) {
            setError('Debes aceptar las condiciones de reserva antes de continuar.');
            return;
        }
        setSubmitting(true);
        setError('');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('/api/reservas/confirmar-pago', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    hold_token: hold.hold_token,
                    provider: paymentMethod === 'tarjeta' ? 'niubiz' : 'direct',
                    external_reference: 'PAG-' + Math.random().toString(36).substring(2, 10).toUpperCase(),
                }),
            });

            const body = await res.json();
            if (!res.ok || !body.ok) {
                throw new Error(body.message || 'No se pudo verificar el pago del anticipo.');
            }

            setConfirmedReservation(body);
            setStep('confirmed');
        } catch (err) {
            setError(err.message);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <section className="im-reservation-container">
            {step === 'select' && (
                <form className="im-reservation-form" onSubmit={handleCreateHold}>
                    <span className="im-eyebrow">TU MESA EMPIEZA AQUÍ.</span>
                    {error && <div role="alert" className="reservation-alert">{error}</div>}

                    <div className="im-form-grid">
                        <label className="im-full">
                            01 / Tu nombre
                            <input
                                required
                                minLength={3}
                                maxLength={100}
                                autoComplete="name"
                                placeholder="¿A nombre de quién?"
                                value={formData.customer_name}
                                onChange={e => setFormData(p => ({ ...p, customer_name: e.target.value }))}
                            />
                        </label>
                        <label>
                            02 / Fecha
                            <input
                                required
                                type="date"
                                min={today}
                                value={formData.date}
                                onChange={e => changeSchedule('date', e.target.value)}
                            />
                        </label>
                        <label>
                            03 / Hora
                            <select
                                required
                                value={formData.time}
                                onChange={e => changeSchedule('time', e.target.value)}
                            >
                                <option value="">{loading ? 'Consultando…' : 'Elige un horario'}</option>
                                {availability?.slots?.map(time => (
                                    <option key={time} value={time}>{time}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            04 / Personas
                            <select
                                value={formData.people}
                                onChange={e => setFormData(p => ({ ...p, people: Number(e.target.value) }))}
                            >
                                {Array.from({ length: 12 }, (_, i) => i + 1).map(num => (
                                    <option key={num} value={num}>
                                        {num} {num === 1 ? 'persona' : 'personas'}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            05 / Celular
                            <input
                                required
                                type="tel"
                                maxLength={30}
                                autoComplete="tel"
                                placeholder="Tu número para confirmación"
                                value={formData.phone}
                                onChange={e => setFormData(p => ({ ...p, phone: e.target.value }))}
                            />
                        </label>
                    </div>

                    <fieldset className="reservation-picker" aria-busy={loading}>
                        <legend>06 / Mapa de mesas en salón (12 mesas: 4 de 2p · 8 de 4p)</legend>
                        <p>
                            Selecciona tus mesas preferidas o deja la selección libre para asignación óptima.{' '}
                            {requiresAdvance
                                ? `Incluye anticipo de ${money(configuredAdvance)} descontable al 100% de tu consumo.`
                                : 'Reserva directa sin costo de anticipo.'}
                        </p>

                        <div className="reservation-legend">
                            <span><i />Disponible</span>
                            <span><i className="is-selected" />Tu selección</span>
                            <span><i className="is-unavailable" />Ocupada</span>
                        </div>

                        {loading ? (
                            <div className="reservation-loading" role="status">
                                Consultando disponibilidad de mesas en tiempo real…
                            </div>
                        ) : !error && !availability?.slots?.length ? (
                            <div className="reservation-alert">Ya no quedan horarios disponibles para esta fecha. Prueba eligiendo otro día.</div>
                        ) : !error && !formData.time ? (
                            <p>Elige un horario arriba para ver las mesas libres del salón.</p>
                        ) : !error && Object.entries(groups).map(([zone, tables]) => (
                            <section className="reservation-zone" key={zone} aria-label={zone}>
                                <h3>{zone}</h3>
                                <div className="reservation-table-grid">
                                    {tables.map(table => {
                                        const active = formData.table_ids.includes(table.id);
                                        return (
                                            <button
                                                type="button"
                                                className={`reservation-table ${active ? 'is-selected' : ''}`}
                                                key={table.id}
                                                disabled={!table.available || submitting}
                                                aria-pressed={active}
                                                onClick={() => {
                                                    setFormData(p => ({
                                                        ...p,
                                                        table_ids: active
                                                            ? p.table_ids.filter(id => id !== table.id)
                                                            : [...p.table_ids, table.id],
                                                    }));
                                                }}
                                            >
                                                <span className="reservation-table-shape" aria-hidden="true">
                                                    {active ? <Check size={18} /> : table.label.replace(/^Mesa\s*/i, 'M')}
                                                </span>
                                                <strong>{table.label}</strong>
                                                <small>{table.capacity} personas</small>
                                                {table.join_group && <small className="reservation-join-tag">Unible</small>}
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}

                        <div className={`reservation-selection ${formData.table_ids.length > 0 && capacity < formData.people ? 'has-warning' : ''}`} role="status" aria-live="polite">
                            <strong>
                                {selectedTables.length
                                    ? selectedTables.map(t => t.label).join(' + ')
                                    : 'Asignación inteligente de salón'}
                            </strong>
                            <span>
                                {capacity > 0
                                    ? `${capacity} asientos seleccionados para ${formData.people} ${formData.people === 1 ? 'comensal' : 'comensales'}`
                                    : `Seleccionaremos automáticamente la mejor ubicación para ${formData.people} ${formData.people === 1 ? 'persona' : 'personas'}.`}
                            </span>
                            {formData.table_ids.length > 0 && capacity < formData.people && (
                                <span className="reservation-capacity-error">
                                    Faltan {formData.people - capacity} asiento(s): suma otra mesa disponible o desmarca para asignación automática.
                                </span>
                            )}
                            {selectedTables.length > 0 && capacity >= formData.people && (
                                <span>
                                    {selectedTables.length === 1
                                        ? 'Mesa lista para tu grupo.'
                                        : together
                                          ? 'Mesas continuas que se integran juntas.'
                                          : 'Mesas cercanas dentro del salón.'}
                                </span>
                            )}
                        </div>

                        <button className="reservation-refresh" type="button" disabled={loading} onClick={() => setRevision(v => v + 1)}>
                            ↻ Actualizar disponibilidad ahora
                        </button>
                    </fieldset>

                    <div className="im-form-grid">
                        <label className="im-full">
                            07 / Correo electrónico (opcional)
                            <input
                                type="email"
                                autoComplete="email"
                                maxLength={190}
                                placeholder="hola@tucorreo.com"
                                value={formData.email}
                                onChange={e => setFormData(p => ({ ...p, email: e.target.value }))}
                            />
                        </label>
                        <label className="im-full">
                            08 / Notas o pedidos especiales (opcional)
                            <textarea
                                rows={2}
                                maxLength={500}
                                placeholder="Celebración, preferencia de ubicación, etc."
                                value={formData.notes}
                                onChange={e => setFormData(p => ({ ...p, notes: e.target.value }))}
                            />
                        </label>
                    </div>

                    <div className="im-reservation-policy-card">
                        <div className="im-policy-item">
                            <strong>{requiresAdvance ? `Anticipo de ${money(configuredAdvance)}` : 'Reserva directa'}</strong>
                            <p>{requiresAdvance ? 'Descontable al 100% de tu consumo final en el local.' : 'Sujeta a confirmación y puntualidad en recepción.'}</p>
                        </div>
                        <div className="im-policy-item">
                            <strong>Tolerancia de {configuredTolerance} minutos</strong>
                            <p>Tu mesa queda asegurada hasta {configuredTolerance} min después de tu hora reservada.</p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        className="im-reservation-submit"
                        disabled={submitting || loading || !formData.time || (formData.table_ids.length > 0 && capacity < formData.people)}
                    >
                        {submitting
                            ? 'Bloqueando mesa…'
                            : requiresAdvance
                              ? `Continuar al pago de ${money(configuredAdvance)}`
                              : 'Revisar y confirmar reserva'}
                        <span className="im-circle-arrow"><ArrowUpRight size={27} /></span>
                    </button>
                </form>
            )}

            {step === 'payment' && hold && (
                <div className="im-reservation-summary-card">
                    <header className="im-summary-header">
                        <span className="im-eyebrow">RESUMEN DE RESERVA</span>
                        <h2>{Number(hold.advance_amount) > 0 ? 'Anticipo de Reserva' : 'Confirmar Reserva'}</h2>
                        <p>{Number(hold.advance_amount) > 0 ? 'Completa el pago del anticipo para asegurar tu mesa.' : 'Verifica los datos de tu mesa para finalizar.'}</p>
                    </header>

                    {error && <div role="alert" className="reservation-alert">{error}</div>}

                    <div className="im-summary-details">
                        <div className="im-summary-row">
                            <span>Fecha:</span>
                            <strong>{hold.date}</strong>
                        </div>
                        <div className="im-summary-row">
                            <span>Hora:</span>
                            <strong>{hold.time}</strong>
                        </div>
                        <div className="im-summary-row">
                            <span>Personas:</span>
                            <strong>{hold.guest_count}</strong>
                        </div>
                        <div className="im-summary-row">
                            <span>Mesa asignada:</span>
                            <strong>{hold.tables?.join(' + ')}</strong>
                        </div>
                        <div className="im-summary-row im-highlight-row">
                            <span>Anticipo de reserva:</span>
                            <strong className="im-amount">{Number(hold.advance_amount) > 0 ? money(hold.advance_amount) : 'Sin cargo'}</strong>
                        </div>
                    </div>

                    <div className="im-policy-box">
                        {Number(hold.advance_amount) > 0 && (
                            <p><strong>✓ Descuento en tu cuenta:</strong> El monto de {money(hold.advance_amount)} se usará como crédito para tu consumo final.</p>
                        )}
                        <p><strong>✓ Tolerancia de llegada:</strong> {configuredTolerance} minutos tras la hora programada.</p>
                        <p><strong>✓ Cancelación:</strong> Puedes gestionar o reprogramar tu reserva desde Mi Cuenta.</p>
                    </div>

                    {Number(hold.advance_amount) > 0 && (
                        <div className="im-payment-options">
                            <label className="im-pay-option">
                                <input
                                    type="radio"
                                    name="method"
                                    value="tarjeta"
                                    checked={paymentMethod === 'tarjeta'}
                                    onChange={() => setPaymentMethod('tarjeta')}
                                />
                                <span>Tarjeta de Débito / Crédito (Niubiz)</span>
                            </label>
                            <label className="im-pay-option">
                                <input
                                    type="radio"
                                    name="method"
                                    value="direct"
                                    checked={paymentMethod === 'direct'}
                                    onChange={() => setPaymentMethod('direct')}
                                />
                                <span>Yape / Plin / Transferencia Directa</span>
                            </label>
                        </div>
                    )}

                    <label className="im-consent-check">
                        <input
                            type="checkbox"
                            checked={acceptedTerms}
                            onChange={e => setAcceptedTerms(e.target.checked)}
                        />
                        <span>Acepto las condiciones de reserva, tolerancia de {configuredTolerance} minutos y política de cancelación.</span>
                    </label>

                    <div className="im-summary-actions">
                        <button
                            type="button"
                            className="im-reservation-submit"
                            disabled={submitting || !acceptedTerms}
                            onClick={handleProcessPayment}
                        >
                            {submitting
                                ? 'Confirmando…'
                                : Number(hold.advance_amount) > 0
                                  ? `Pagar ${money(hold.advance_amount)} y Confirmar Reserva`
                                  : 'Confirmar Reserva Ahora'}
                            <span className="im-circle-arrow"><ArrowRight size={22} /></span>
                        </button>
                        <button
                            type="button"
                            className="im-btn-secondary"
                            onClick={() => setStep('select')}
                            disabled={submitting}
                        >
                            Modificar selección
                        </button>
                    </div>
                </div>
            )}

            {step === 'confirmed' && confirmedReservation && (
                <div className="im-success-reservation-card">
                    <div className="im-success-circle">
                        <Check size={40} />
                    </div>
                    <span className="im-eyebrow">¡RESERVA CONFIRMADA!</span>
                    <h2>Te esperamos en DESEGO</h2>
                    <p className="im-success-lead">
                        {Number(confirmedReservation.advance_amount) > 0
                            ? `Tu anticipo de ${money(confirmedReservation.advance_amount)} fue verificado exitosamente.`
                            : 'Tu mesa quedó reservada y registrada en nuestro salón.'}
                    </p>

                    <div className="im-code-box">
                        <span>Código de Reserva</span>
                        <strong>{confirmedReservation.reservation_code}</strong>
                    </div>

                    <div className="im-confirmation-details">
                        <div>
                            <span>Cliente:</span>
                            <strong>{confirmedReservation.customer_name}</strong>
                        </div>
                        <div>
                            <span>Personas:</span>
                            <strong>{confirmedReservation.guest_count} comensales</strong>
                        </div>
                        <div>
                            <span>Mesas:</span>
                            <strong>{confirmedReservation.tables?.map(t => t.label).join(' + ')}</strong>
                        </div>
                        <div>
                            <span>Hora límite de llegada:</span>
                            <strong className="text-coral-600">
                                {new Date(confirmedReservation.arrival_deadline).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Lima' })}
                            </strong>
                        </div>
                        <div>
                            <span>Crédito disponible para consumo:</span>
                            <strong className="text-emerald-700">{money(confirmedReservation.advance_amount)}</strong>
                        </div>
                    </div>

                    <p className="im-reminder-text">
                        Guarda tu código o muéstralo en recepción al llegar. Cuentas con 15 minutos de tolerancia.
                    </p>

                    <button
                        type="button"
                        className="im-btn-primary"
                        onClick={() => {
                            setStep('select');
                            setConfirmedReservation(null);
                            setHold(null);
                        }}
                    >
                        Hacer otra reserva
                    </button>
                </div>
            )}
        </section>
    );
}
