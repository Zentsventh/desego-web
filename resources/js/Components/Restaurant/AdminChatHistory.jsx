import React, { Fragment, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Archive, MessagesSquare as MessageSquare, RefreshCw, Search } from './Icons';
import { adminDateTime } from '../../lib/adminSpanish';

const sources = { gemini: 'Gemini', groq: 'Groq', catalog: 'Carta local' };
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function localDay(messageDate) {
    const text = String(messageDate);
    const instant = new Date(text.replace(' ', 'T') + (/(Z|[+-]\d{2}:\d{2})$/.test(text) ? '' : 'Z'));
    if (Number.isNaN(instant.getTime())) {
        return { key: text.slice(0, 10), label: text.slice(0, 10) };
    }

    return {
        key: new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' }).format(instant),
        label: new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', dateStyle: 'full' }).format(instant),
    };
}

export default function AdminChatHistory({ data }) {
    const list = data.conversations || { data: [] };
    const status = data.filters?.status || 'open';
    const [query, setQuery] = useState(data.filters?.q || '');
    const [selected, setSelected] = useState(null);
    const [thread, setThread] = useState(null);
    const [loading, setLoading] = useState(false);
    const [acting, setActing] = useState(false);
    const [reply, setReply] = useState('');
    const [error, setError] = useState('');
    const requestRef = useRef(null);
    const lastMessageIdRef = useRef(0);
    const pollingRef = useRef(false);
    const messagesRef = useRef(null);
    const pinnedToBottomRef = useRef(true);
    const scrollToLatestRef = useRef(false);
    const preserveScrollHeightRef = useRef(null);
    const lastReadMessageIdRef = useRef(0);
    const readRequestRef = useRef(false);
    const [newMessagesCount, setNewMessagesCount] = useState(0);

    const markConversationRead = async (messageId) => {
        if (!selected || !messageId || messageId <= lastReadMessageIdRef.current || readRequestRef.current) {
            return;
        }
        readRequestRef.current = true;
        try {
            const response = await fetch(`/admin/chatbot/${selected}/leido`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message_id: messageId }),
            });
            if (!response.ok) {
                throw new Error('No se pudo actualizar el estado de lectura del chat.');
            }
            lastReadMessageIdRef.current = messageId;
            router.reload({ only: ['data'], preserveState: true, preserveScroll: true });
        } catch (exception) {
            setError(exception.message);
        } finally {
            readRequestRef.current = false;
        }
    };

    const scrollToLatest = () => {
        const container = messagesRef.current;
        if (!container) {
            return;
        }
        container.scrollTop = container.scrollHeight;
        pinnedToBottomRef.current = true;
        setNewMessagesCount(0);
        const latestMessageId = Number(thread?.messages?.at(-1)?.id) || lastMessageIdRef.current;
        markConversationRead(latestMessageId);
    };

    const updateList = (nextStatus = status, nextQuery = query) => {
        router.get('/admin/seccion/chatbot', { q: nextQuery, status: nextStatus }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const load = async (conversation, older = false) => {
        requestRef.current?.abort();
        const controller = new AbortController();
        requestRef.current = controller;
        setSelected(conversation.id);
        setLoading(true);
        setError('');
        if (!older) {
            scrollToLatestRef.current = true;
            pinnedToBottomRef.current = true;
            setNewMessagesCount(0);
            lastReadMessageIdRef.current = 0;
            setThread(null);
        } else if (messagesRef.current) {
            preserveScrollHeightRef.current = messagesRef.current.scrollHeight;
        }
        try {
            const cursor = older ? `?before=${thread.messages[0].id}` : '';
            const response = await fetch(`/admin/chatbot/${conversation.id}${cursor}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('No se pudo cargar el chat. Comprueba tu sesión y permisos.');
            }
            const result = await response.json();
            if (!older) {
                lastMessageIdRef.current = Number(result.messages?.at(-1)?.id) || 0;
            }
            setThread(previous => older && previous ? { ...result, messages: [...result.messages, ...previous.messages] } : result);
        } catch (exception) {
            if (exception.name !== 'AbortError') {
                setError(exception.message);
            }
        } finally {
            if (!controller.signal.aborted) {
                setLoading(false);
            }
        }
    };

    const changeArchiveState = async (conversation, shouldArchive) => {
        setActing(true);
        setError('');
        try {
            const action = shouldArchive ? 'cerrar' : 'reabrir';
            const response = await fetch(`/admin/chatbot/${conversation.id}/${action}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                throw new Error(shouldArchive ? 'No se pudo cerrar el chat.' : 'No se pudo reabrir el chat.');
            }

            requestRef.current?.abort();
            setThread(null);
            setSelected(null);
            updateList();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setActing(false);
        }
    };

    const supportAction = async (action) => {
        if (!selected) {
            return;
        }
        setActing(true);
        setError('');
        try {
            const response = await fetch(`/admin/chatbot/${selected}/${action}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ok) {
                throw new Error(result.message || 'No se pudo actualizar la atención del chat.');
            }
            await load({ id: selected });
            updateList();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setActing(false);
        }
    };

    const sendReply = async (event) => {
        event.preventDefault();
        const message = reply.trim();
        if (!message || !selected || acting) {
            return;
        }
        setActing(true);
        setError('');
        try {
            const response = await fetch(`/admin/chatbot/${selected}/responder`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ok) {
                throw new Error(response.status === 409 ? 'Este chat está asignado a otro asesor. Toma el control para responder.' : result.message || 'No se pudo enviar la respuesta.');
            }
            setReply('');
            await load({ id: selected });
        } catch (exception) {
            setError(exception.message);
        } finally {
            setActing(false);
        }
    };

    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['data'], preserveState: true, preserveScroll: true });
        }, 15000);

        return () => clearInterval(interval);
    }, [status, query]);

    useEffect(() => {
        if (!selected) {
            return undefined;
        }
        const interval = setInterval(async () => {
            if (pollingRef.current) {
                return;
            }
            pollingRef.current = true;
            try {
                const response = await fetch(`/admin/chatbot/${selected}?after=${lastMessageIdRef.current}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok || response.redirected) {
                    return;
                }
                const result = await response.json();
                if (result.messages?.length) {
                    lastMessageIdRef.current = Number(result.messages.at(-1).id) || lastMessageIdRef.current;
                    if (pinnedToBottomRef.current) {
                        scrollToLatestRef.current = true;
                    } else {
                        setNewMessagesCount(count => count + result.messages.length);
                    }
                }
                setThread(previous => {
                    if (!previous) {
                        return result;
                    }
                    const receipts = new Map((result.staff_receipts || []).map(receipt => [Number(receipt.id), receipt]));
                    const refreshedMessages = previous.messages.map(message => receipts.has(Number(message.id))
                        ? { ...message, ...receipts.get(Number(message.id)) }
                        : message);

                    return {
                        ...result,
                        messages: [...refreshedMessages, ...(result.messages || [])],
                    };
                });
            } catch (exception) {
                setError('No se pudo actualizar el chat. Comprueba tu conexión.');
            } finally {
                pollingRef.current = false;
            }
        }, 4000);

        return () => clearInterval(interval);
    }, [selected]);

    useEffect(() => () => {
        requestRef.current?.abort();
    }, []);

    useLayoutEffect(() => {
        const container = messagesRef.current;
        if (!container || !thread) {
            return;
        }

        if (preserveScrollHeightRef.current !== null) {
            container.scrollTop += container.scrollHeight - preserveScrollHeightRef.current;
            preserveScrollHeightRef.current = null;
            return;
        }

        if (scrollToLatestRef.current || pinnedToBottomRef.current) {
            container.scrollTop = container.scrollHeight;
            pinnedToBottomRef.current = true;
            scrollToLatestRef.current = false;
            setNewMessagesCount(0);
            markConversationRead(Number(thread.messages.at(-1)?.id) || lastMessageIdRef.current);
        }
    }, [selected, thread?.messages?.length]);

    return (
        <div className="access-module">
            <header className="access-module-head">
                <div>
                    <span className="glass-eyebrow">ATENCIÓN AL CLIENTE</span>
                    <h2>Chats del asistente</h2>
                    <p className="access-muted">Un historial por cliente, agrupado por día. Los chats cerrados se conservan en el archivo.</p>
                </div>
                <button className="glass-btn" type="button" onClick={() => { router.reload({ only: ['data'] }); if (selected) { load({ id: selected }); } }}>
                    <RefreshCw size={15} /> Actualizar
                </button>
            </header>

            <div className="chat-history-layout">
                <section className="glass-panel chat-history-sidebar">
                    <form className="access-chat-search" onSubmit={event => { event.preventDefault(); updateList(); }}>
                        <input
                            className="glass-input"
                            aria-label="Buscar chats"
                            placeholder="Buscar por consulta o cliente..."
                            maxLength={120}
                            value={query}
                            onChange={event => setQuery(event.target.value)}
                        />
                        <button className="glass-icon-btn" type="submit" aria-label="Buscar"><Search size={17} /></button>
                    </form>

                    <div className="chat-history-filters" role="group" aria-label="Filtrar chats">
                        <button type="button" className={status === 'open' ? 'is-active' : ''} aria-pressed={status === 'open'} onClick={() => updateList('open')}>Abiertos</button>
                        <button type="button" className={status === 'closed' ? 'is-active' : ''} aria-pressed={status === 'closed'} onClick={() => updateList('closed')}>Cerrados</button>
                    </div>
                    <p className="access-muted">{list.total || 0} chats {status === 'closed' ? 'cerrados' : 'abiertos'}</p>

                    <div className="chat-conversation-list">
                        {list.data.map(conversation => (
                            <div key={conversation.id} className={`chat-conversation-entry ${selected === conversation.id ? 'is-selected' : ''}`}>
                                <button
                                    type="button"
                                    onClick={() => load(conversation)}
                                    className={`chat-conversation-item ${selected === conversation.id ? 'is-selected' : ''}`}
                                    aria-pressed={selected === conversation.id}
                                >
                                    <strong>{conversation.customer_name || 'Visitante'}</strong>
                                    {conversation.support_status === 'requested' && <span className="chat-support-badge is-requested">Solicita asesor</span>}
                                    {conversation.support_status === 'human' && <span className="chat-support-badge is-human">Atención humana</span>}
                                    {Number(conversation.unread_count) > 0 && <span className="chat-support-badge is-unread">{conversation.unread_count} sin leer</span>}
                                    <span>{conversation.message_count} mensajes</span>
                                    <small>{adminDateTime(conversation.updated_at)}</small>
                                </button>
                                <button
                                    type="button"
                                    className="chat-conversation-archive"
                                    aria-label={status === 'closed' ? `Reabrir chat de ${conversation.customer_name}` : `Cerrar chat de ${conversation.customer_name}`}
                                    title={status === 'closed' ? 'Reabrir chat' : 'Cerrar chat'}
                                    onClick={() => changeArchiveState(conversation, status !== 'closed')}
                                    disabled={acting}
                                >
                                    <Archive size={16} />
                                </button>
                            </div>
                        ))}
                    </div>
                    {!list.data.length && <p className="access-empty">{status === 'closed' ? 'No hay chats cerrados.' : 'No hay chats abiertos.'}</p>}
                    {list.last_page > 1 && (
                        <footer className="chat-history-pagination">
                            <button className="glass-btn" disabled={!list.prev_page_url} onClick={() => router.visit(list.prev_page_url, { preserveState: true })}>Anterior</button>
                            <span>{list.current_page} / {list.last_page}</span>
                            <button className="glass-btn" disabled={!list.next_page_url} onClick={() => router.visit(list.next_page_url, { preserveState: true })}>Siguiente</button>
                        </footer>
                    )}
                </section>

                <section className="glass-panel chat-history-thread" aria-label="Detalle del chat" aria-busy={loading}>
                    {loading && <p className="access-empty" role="status">Cargando chat...</p>}
                    {error && <p role="alert" className="access-empty">{error}</p>}
                    {!loading && !error && !thread && (
                        <div className="access-empty">
                            <MessageSquare size={32} />
                            <h3>Atención ordenada por cliente</h3>
                            <p>Selecciona un chat para revisar el historial por fecha, o ciérralo para retirarlo de la bandeja activa.</p>
                        </div>
                    )}
                    {thread && (
                        <>
                            <header className="chat-history-thread-head">
                                <div>
                                    <span className="glass-eyebrow">{thread.conversation.archived_at ? 'CHAT CERRADO' : thread.conversation.support_status === 'human' ? 'ATENCIÓN HUMANA' : thread.conversation.support_status === 'requested' ? 'ASESOR SOLICITADO' : 'ATENCIÓN CON IA'}</span>
                                    <h3>{thread.conversation.customer_name}</h3>
                                    <p>Conversación iniciada el {adminDateTime(thread.conversation.created_at)}</p>
                                    {thread.conversation.assigned_staff_name && <p>Asignado a {thread.conversation.assigned_staff_name}</p>}
                                </div>
                                <div className="chat-history-head-actions">
                                    {!thread.conversation.archived_at && thread.conversation.support_status === 'ai' && (
                                        <button type="button" className="glass-btn" disabled={acting} onClick={() => supportAction('derivar')}>Derivar a asesor</button>
                                    )}
                                    {!thread.conversation.archived_at && (thread.conversation.support_status === 'requested' || (thread.conversation.support_status === 'human' && thread.conversation.assigned_staff_id !== thread.conversation.current_staff_id)) && (
                                        <button type="button" className="glass-btn chat-support-takeover" disabled={acting} onClick={() => supportAction('tomar-control')}>Tomar el control</button>
                                    )}
                                    {!thread.conversation.archived_at && thread.conversation.support_status === 'human' && thread.conversation.assigned_staff_id === thread.conversation.current_staff_id && (
                                        <button type="button" className="glass-btn" disabled={acting} onClick={() => supportAction('devolver-ia')}>Devolver a la IA</button>
                                    )}
                                    <button
                                        type="button"
                                        className="glass-btn chat-history-close"
                                        disabled={acting}
                                        onClick={() => changeArchiveState(thread.conversation, !thread.conversation.archived_at)}
                                    >
                                        <Archive size={15} /> {thread.conversation.archived_at ? 'Reabrir chat' : 'Cerrar chat'}
                                    </button>
                                </div>
                            </header>
                            <div
                                className="chat-history-messages"
                                ref={messagesRef}
                                onScroll={event => {
                                    const container = event.currentTarget;
                                    const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 48;
                                    pinnedToBottomRef.current = isAtBottom;
                                    if (isAtBottom) {
                                        setNewMessagesCount(0);
                                        markConversationRead(Number(thread.messages.at(-1)?.id) || lastMessageIdRef.current);
                                    }
                                }}
                            >
                                {thread.has_more && <button className="glass-btn chat-history-older" disabled={loading} onClick={() => load({ id: selected }, true)}>Ver mensajes anteriores</button>}
                                {newMessagesCount > 0 && (
                                    <button type="button" className="chat-history-new-messages" onClick={scrollToLatest}>
                                        {newMessagesCount} {newMessagesCount === 1 ? 'mensaje nuevo' : 'mensajes nuevos'} · Ir al más reciente
                                    </button>
                                )}
                                {thread.messages.map((message, index) => {
                                    const day = localDay(message.created_at);
                                    const previousDay = index > 0 ? localDay(thread.messages[index - 1].created_at).key : null;

                                    return (
                                        <Fragment key={message.id}>
                                            {day.key !== previousDay && <div className="chat-history-day"><span>{day.label}</span></div>}
                                            <article key={message.id} className={`chat-history-message ${message.role}${message.is_error ? ' is-error' : ''}${message.source === 'staff' ? ' is-staff' : ''}`}>
                                                <small>{message.role === 'user' ? 'Cliente' : message.source === 'staff' ? 'Asesor' : sources[message.source] || 'Asistente'} · {adminDateTime(message.created_at)}</small>
                                                <p>{message.content}</p>
                                                {message.model && <span>{message.model}</span>}
                                                {message.source === 'staff' && (
                                                    <span className={`chat-message-receipt ${message.read_at ? 'is-read' : ''}`}>
                                                        {message.read_at ? '✓✓ Leído' : message.delivered_at ? '✓✓ Entregado' : '✓ Enviado'}
                                                    </span>
                                                )}
                                            </article>
                                        </Fragment>
                                    );
                                })}
                            </div>
                            {!thread.conversation.archived_at && thread.conversation.support_status === 'human' && thread.conversation.assigned_staff_id === thread.conversation.current_staff_id && (
                                <form className="chat-history-composer" onSubmit={sendReply}>
                                    <textarea className="glass-input" rows={2} maxLength={2000} value={reply} onChange={event => setReply(event.target.value)} placeholder="Escribe una respuesta para el cliente..." aria-label="Respuesta del asesor" />
                                    <button type="submit" className="glass-btn chat-support-takeover" disabled={acting || !reply.trim()}>Enviar respuesta</button>
                                </form>
                            )}
                        </>
                    )}
                </section>
            </div>
        </div>
    );
}
