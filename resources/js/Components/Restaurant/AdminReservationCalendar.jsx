import React, { useEffect, useMemo, useState } from 'react';
import { ArrowLeft, ArrowRight, RefreshCw } from 'lucide-react';
import { adminText } from '../../lib/adminSpanish';

const TIME_ZONE = 'America/Lima';

function dateKeyInLima(date) {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);
    const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    return `${values.year}-${values.month}-${values.day}`;
}

function dateFromKey(key) {
    const [year, month, day] = key.split('-').map(Number);

    return new Date(year, month - 1, day, 12);
}

function toDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function startOfWeek(date) {
    const start = new Date(date);
    start.setDate(start.getDate() - ((start.getDay() + 6) % 7));

    return start;
}

function reservationDateKey(value) {
    if (!value) return '';
    const raw = String(value);
    const date = new Date(raw.includes('T') ? raw : `${raw.replace(' ', 'T')}Z`);

    return Number.isNaN(date.getTime()) ? '' : dateKeyInLima(date);
}

function reservationTime(value) {
    if (!value) return 'Hora pendiente';
    const raw = String(value);
    const date = new Date(raw.includes('T') ? raw : `${raw.replace(' ', 'T')}Z`);

    if (Number.isNaN(date.getTime())) {
        return 'Hora pendiente';
    }

    return new Intl.DateTimeFormat('es-PE', {
        timeZone: TIME_ZONE,
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(date);
}

function reservationStatusTone(status) {
    if (['Confirmada', 'CONFIRMED', 'CHECKED_IN', 'Sentada', 'Completada', 'COMPLETED'].includes(status)) {
        return 'is-confirmed';
    }
    if (['CANCELLED', 'CANCELED', 'Cancelada', 'EXPIRED', 'NO_SHOW'].includes(status)) {
        return 'is-cancelled';
    }

    return 'is-pending';
}

export default function AdminReservationCalendar() {
    const todayKey = useMemo(() => dateKeyInLima(new Date()), []);
    const [weekStart, setWeekStart] = useState(() => startOfWeek(dateFromKey(dateKeyInLima(new Date()))));
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [refreshKey, setRefreshKey] = useState(0);

    const days = useMemo(
        () =>
            Array.from({ length: 7 }, (_, index) => {
                const date = new Date(weekStart);
                date.setDate(weekStart.getDate() + index);

                return {
                    date,
                    key: toDateKey(date),
                    label: new Intl.DateTimeFormat('es-PE', {
                        weekday: 'short',
                        day: '2-digit',
                        month: 'short',
                    }).format(date),
                };
            }),
        [weekStart]
    );

    const rangeLabel = useMemo(() => {
        if (!days.length) {
            return '';
        }

        return new Intl.DateTimeFormat('es-PE', {
            day: 'numeric',
            month: 'long',
        }).formatRange(days[0].date, days[days.length - 1].date);
    }, [days]);

    useEffect(() => {
        const controller = new AbortController();
        const from = days[0]?.key;
        const to = days[days.length - 1]?.key;

        if (!from || !to) {
            return () => controller.abort();
        }

        setLoading(true);
        setError('');

        fetch(`/api/admin/reservas/calendario?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                const result = await response.json();
                if (!response.ok || result.ok !== true || !Array.isArray(result.events)) {
                    throw new Error(result.message || 'No se pudo cargar el calendario de reservas.');
                }

                setEvents(result.events);
            })
            .catch((fetchError) => {
                if (fetchError.name !== 'AbortError') {
                    setError(fetchError.message || 'Ocurrió un error al cargar las reservas.');
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [days, refreshKey]);

    const eventsByDay = useMemo(() => {
        const grouped = new Map(days.map(({ key }) => [key, []]));

        events.forEach((event) => {
            const key = reservationDateKey(event.reservation_at);
            if (grouped.has(key)) {
                grouped.get(key).push(event);
            }
        });

        return grouped;
    }, [days, events]);

    const totalWeekReservations = events.length;

    function moveWeek(amount) {
        setWeekStart((current) => {
            const next = new Date(current);
            next.setDate(next.getDate() + amount * 7);

            return next;
        });
    }

    return (
        <section className="glass-panel admin-reservation-calendar" aria-labelledby="admin-reservation-calendar-title">
            <header className="admin-reservation-calendar-header">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="glass-eyebrow">AGENDA</span>
                        <span className="admin-calendar-total-chip">
                            {totalWeekReservations} {totalWeekReservations === 1 ? 'reserva esta semana' : 'reservas esta semana'}
                        </span>
                    </div>
                    <h3 id="admin-reservation-calendar-title">Calendario de reservas</h3>
                    <p>{rangeLabel}</p>
                </div>
                <div className="admin-reservation-calendar-controls" aria-label="Navegación del calendario">
                    <button type="button" onClick={() => moveWeek(-1)} aria-label="Semana anterior">
                        <ArrowLeft size={15} aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setWeekStart(startOfWeek(dateFromKey(dateKeyInLima(new Date()))))}
                    >
                        Hoy
                    </button>
                    <button type="button" onClick={() => moveWeek(1)} aria-label="Semana siguiente">
                        <ArrowRight size={15} aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setRefreshKey((key) => key + 1)}
                        disabled={loading}
                        aria-label="Actualizar calendario"
                    >
                        <RefreshCw size={14} className={loading ? 'animate-spin' : ''} aria-hidden="true" />
                    </button>
                </div>
            </header>

            {error ? (
                <div className="admin-reservation-calendar-message is-error" role="alert">
                    <span>{error}</span>
                    <button type="button" onClick={() => setRefreshKey((key) => key + 1)}>
                        Reintentar
                    </button>
                </div>
            ) : loading ? (
                <div className="admin-reservation-calendar-message" role="status">
                    <RefreshCw size={16} className="animate-spin" aria-hidden="true" />
                    Cargando reservas…
                </div>
            ) : (
                <div className="admin-reservation-calendar-grid">
                    {days.map(({ key, label }) => {
                        const dayEvents = eventsByDay.get(key) || [];
                        const isToday = key === todayKey;

                        return (
                            <section
                                className={`admin-reservation-calendar-day ${isToday ? 'is-today' : ''}`}
                                key={key}
                                aria-label={label}
                            >
                                <header>
                                    <div className="flex items-center gap-1.5 min-w-0">
                                        <span>{label}</span>
                                        {isToday && <span className="admin-today-pill">Hoy</span>}
                                    </div>
                                    <strong className={dayEvents.length > 0 ? 'has-events' : ''}>
                                        {dayEvents.length}
                                    </strong>
                                </header>
                                {dayEvents.length ? (
                                    <ul>
                                        {dayEvents.map((event) => (
                                            <li className="admin-reservation-calendar-event" key={event.id}>
                                                <div className="flex items-center justify-between gap-1">
                                                    <time>{reservationTime(event.reservation_at)}</time>
                                                    {event.table_label && (
                                                        <span className="admin-event-table-pill">{event.table_label}</span>
                                                    )}
                                                </div>
                                                <strong>{event.customer_name}</strong>
                                                <span className="admin-event-guests">
                                                    {event.guest_count} {Number(event.guest_count) === 1 ? 'persona' : 'personas'}
                                                </span>
                                                <span className={`admin-reservation-calendar-status ${reservationStatusTone(event.status)}`}>
                                                    {adminText(event.status, 'status', 'reservas')}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <div className="admin-reservation-calendar-empty">
                                        <span>—</span>
                                    </div>
                                )}
                            </section>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
