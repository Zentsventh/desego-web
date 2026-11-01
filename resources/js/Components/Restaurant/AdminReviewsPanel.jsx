import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Check, MessageSquareReply, Star, X } from 'lucide-react';

export default function AdminReviewsPanel({ data = {} }) {
    const reviews = data.reviews || [];
    const summary = data.summary || { pending: 0, approved: 0, rejected: 0 };
    const canManage = Boolean(data.can_manage ?? true);

    const [filter, setFilter] = useState('all');
    const [replyingId, setReplyingId] = useState(null);
    const [replyText, setReplyText] = useState('');
    const [busy, setBusy] = useState(false);

    const filtered = useMemo(() => {
        if (filter === 'all') {
            return reviews;
        }
        return reviews.filter((r) => r.status === filter);
    }, [reviews, filter]);

    const moderate = (id, status) => {
        setBusy(true);
        router.post(
            '/admin/acciones/reviews',
            { action: 'review.moderate', id, status },
            { preserveScroll: true, onFinish: () => setBusy(false) }
        );
    };

    const submitReply = (e, id) => {
        e.preventDefault();
        setBusy(true);
        router.post(
            '/admin/acciones/reviews',
            { action: 'review.reply', id, admin_reply: replyText },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReplyingId(null);
                    setReplyText('');
                },
                onFinish: () => setBusy(false),
            }
        );
    };

    return (
        <div className="space-y-5">
            <div className="glass-page-heading">
                <div>
                    <span className="glass-eyebrow">CALIDAD Y REPUTACIÓN</span>
                    <h2>Moderación de Reseñas de Clientes</h2>
                    <p>Revisa valoraciones reales vinculadas a pedidos entregados, aprueba su visibilidad pública y responde en nombre de DeSego.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {[
                        ['all', `Todas (${(summary.pending || 0) + (summary.approved || 0) + (summary.rejected || 0)})`],
                        ['pending', `Pendientes (${summary.pending || 0})`],
                        ['approved', `Aprobadas (${summary.approved || 0})`],
                        ['rejected', `Ocultas (${summary.rejected || 0})`],
                    ].map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setFilter(key)}
                            className={`glass-btn glass-btn-sm ${filter === key ? 'glass-btn-primary' : ''}`}
                            style={filter === key ? { background: '#004797', color: '#fff', borderColor: '#004797' } : undefined}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            {!filtered.length ? (
                <div className="glass-panel glass-panel-pad text-center py-12">
                    <Star size={28} className="mx-auto mb-2 opacity-60" />
                    <strong className="block text-sm">No hay reseñas en esta bandeja</strong>
                    <p className="text-xs opacity-75 mt-1">Las valoraciones aparecerán aquí cuando los clientes califiquen sus pedidos.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4">
                    {filtered.map((rev) => (
                        <article key={rev.id} className="glass-panel glass-panel-pad space-y-3">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <strong className="text-sm">{rev.product_name}</strong>
                                        <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 font-semibold">
                                            {'★'.repeat(Number(rev.rating || 5))}{'☆'.repeat(Math.max(0, 5 - Number(rev.rating || 5)))} ({rev.rating}/5)
                                        </span>
                                        <span className="text-xs opacity-75">
                                            Pedido #{rev.order_number} · {rev.reviewer_name}
                                        </span>
                                    </div>
                                    {rev.title && <p className="text-xs font-semibold mt-1">{rev.title}</p>}
                                    <p className="text-xs mt-1 leading-relaxed">{rev.comment}</p>
                                    {rev.photo_url && (
                                        <a href={rev.photo_url} target="_blank" rel="noopener noreferrer" className="inline-block mt-2">
                                            <img src={rev.photo_url} alt="Foto adjunta de reseña" className="h-20 w-28 object-cover rounded-lg border" />
                                        </a>
                                    )}
                                </div>

                                {canManage && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        {rev.status !== 'approved' && (
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() => moderate(rev.id, 'approved')}
                                                className="glass-btn glass-btn-sm"
                                                style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                                            >
                                                <Check size={13} /> Aprobar
                                            </button>
                                        )}
                                        {rev.status !== 'rejected' && (
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() => moderate(rev.id, 'rejected')}
                                                className="glass-btn glass-btn-sm"
                                            >
                                                <X size={13} /> Ocultar
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setReplyingId(replyingId === rev.id ? null : rev.id);
                                                setReplyText(rev.admin_reply || '');
                                            }}
                                            className="glass-btn glass-btn-sm"
                                        >
                                            <MessageSquareReply size={13} /> {rev.admin_reply ? 'Editar respuesta' : 'Responder'}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {rev.admin_reply && replyingId !== rev.id && (
                                <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-xs">
                                    <strong className="block text-[#004797] mb-0.5">Respuesta oficial de DeSego Cevichería:</strong>
                                    <span>{rev.admin_reply}</span>
                                </div>
                            )}

                            {replyingId === rev.id && (
                                <form onSubmit={(e) => submitReply(e, rev.id)} className="space-y-2 pt-2 border-t">
                                    <textarea
                                        rows={2}
                                        className="glass-input text-xs"
                                        placeholder="Escribe una respuesta cordial y pública para el cliente..."
                                        value={replyText}
                                        onChange={(e) => setReplyText(e.target.value)}
                                    />
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => setReplyingId(null)} className="glass-btn glass-btn-sm">
                                            Cancelar
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={busy}
                                            className="glass-btn glass-btn-sm"
                                            style={{ background: '#004797', color: '#fff', borderColor: '#004797' }}
                                        >
                                            Publicar respuesta
                                        </button>
                                    </div>
                                </form>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}
