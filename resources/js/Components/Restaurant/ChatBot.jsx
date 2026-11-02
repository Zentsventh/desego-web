import React, { useEffect, useRef, useState } from 'react';
import { ArrowUpRight, BookOpen, CalendarDays, RefreshCw, Send, X } from './Icons';
import { navigateToStoreSection, STORE_SECTION_PATHS } from '../../lib/storeNavigation';
import '../../../css/chatbot.css';

const WELCOME = {
    role: 'assistant',
    content: '¡Hola! Soy el asistente de DESEGO 🐟 Te ayudo con la carta, precios, delivery y reservas. ¿Qué se te antoja hoy?',
};
const SUGGESTIONS = [
    { title: 'Encuentra tu plato', detail: 'Nuestra carta y precios', message: '¿Qué ceviches tienen?' },
    { title: 'El mar, hasta tu puerta', detail: 'Delivery y recojo', message: '¿Hacen delivery?' },
    { title: 'Un lugar para ti', detail: 'Reserva tu próxima visita', message: '¿Cómo reservo una mesa?' },
];
const STORAGE_KEY = 'desego.chatbot.messages';
const CONVERSATION_KEY = 'desego.chatbot.conversation';

function storedConversation() {
    try {
        return sessionStorage.getItem(CONVERSATION_KEY) || null;
    } catch {
        return null;
    }
}

function storedMessages() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY));
        if (Array.isArray(saved)) {
            const valid = saved.filter((message) => ['user', 'assistant'].includes(message?.role) && typeof message.content === 'string' && message.content.length <= 12000).slice(-40);
            return valid.length ? valid : [WELCOME];
        }
    } catch {
        // El chat también funciona cuando el navegador bloquea el almacenamiento.
    }
    return [WELCOME];
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function renderInlineMarkdown(content) {
    const tokenPattern = /(\*\*[^*]+\*\*|\*[^*\n]+\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/g;
    const parts = content.split(tokenPattern).filter((part) => part !== '');

    return parts.map((part, index) => {
        if (part.startsWith('**') && part.endsWith('**')) {
            return <strong key={index}>{part.slice(2, -2)}</strong>;
        }
        if (part.startsWith('*') && part.endsWith('*')) {
            return <em key={index}>{part.slice(1, -1)}</em>;
        }
        if (part.startsWith('`') && part.endsWith('`')) {
            return <code key={index}>{part.slice(1, -1)}</code>;
        }
        const link = part.match(/^\[([^\]]+)\]\((https?:\/\/[^)\s]+|#[\w-]+)\)$/);
        if (link) {
            const external = /^https?:\/\//i.test(link[2]);
            const legacySection = link[2].slice(1);
            const href = external ? link[2] : STORE_SECTION_PATHS[legacySection];
            if (!href) {
                return link[1];
            }

            return (
                <a
                    key={index}
                    href={href}
                    onClick={
                        external
                            ? undefined
                            : (event) => {
                                  navigateToStoreSection(legacySection, event);
                              }
                    }
                    {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                >
                    {link[1]}
                </a>
            );
        }

        return part;
    });
}

function parseMarkdownBlocks(text) {
    if (!text || typeof text !== 'string') return [];

    const cleaned = text.replace(/```(?:json)?\s*[\s\S]*?```/gi, '').trim();
    const lines = cleaned.split(/\r?\n/);
    const blocks = [];
    let currentList = null;

    const flushList = () => {
        if (currentList) {
            blocks.push(currentList);
            currentList = null;
        }
    };

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        if (!trimmed) {
            flushList();
            continue;
        }

        const headingMatch = line.match(/^#{1,4}\s+(.+)$/);
        const ulMatch = line.match(/^\s*[-*•]\s+(.+)$/);
        const olMatch = line.match(/^\s*(\d+)[.)]\s+(.+)$/);

        if (headingMatch) {
            flushList();
            blocks.push({ type: 'heading', text: headingMatch[1] });
        } else if (ulMatch) {
            if (!currentList || currentList.type !== 'ul') {
                flushList();
                currentList = { type: 'ul', items: [] };
            }
            currentList.items.push(ulMatch[1]);
        } else if (olMatch) {
            if (!currentList || currentList.type !== 'ol') {
                flushList();
                currentList = { type: 'ol', items: [] };
            }
            currentList.items.push(olMatch[2]);
        } else {
            flushList();
            blocks.push({ type: 'paragraph', text: trimmed });
        }
    }
    flushList();

    return blocks;
}

function MarkdownMessage({ content }) {
    const blocks = parseMarkdownBlocks(content);

    return (
        <div className="dgbot-markdown">
            {blocks.map((block, idx) => {
                if (block.type === 'heading') {
                    return <h4 key={idx}>{renderInlineMarkdown(block.text)}</h4>;
                }
                if (block.type === 'ul') {
                    return (
                        <ul key={idx}>
                            {block.items.map((item, itemIdx) => (
                                <li key={itemIdx}>{renderInlineMarkdown(item)}</li>
                            ))}
                        </ul>
                    );
                }
                if (block.type === 'ol') {
                    return (
                        <ol key={idx}>
                            {block.items.map((item, itemIdx) => (
                                <li key={itemIdx}>{renderInlineMarkdown(item)}</li>
                            ))}
                        </ol>
                    );
                }
                return <p key={idx}>{renderInlineMarkdown(block.text)}</p>;
            })}
        </div>
    );
}

export default function ChatBot() {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState(storedMessages);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [supportStatus, setSupportStatus] = useState('ai');
    const [assignedStaffName, setAssignedStaffName] = useState(null);
    const listRef = useRef(null);
    const inputRef = useRef(null);
    const launcherRef = useRef(null);
    const sendingRef = useRef(false);
    const requestRef = useRef(null);
    const conversationRef = useRef(storedConversation());
    const [conversationId, setConversationId] = useState(conversationRef.current);
    const lastMessageIdRef = useRef(0);
    const pollRef = useRef(null);
    const lastReadStaffMessageIdRef = useRef(0);

    const newConversation = () => {
        if (supportStatus !== 'ai') {
            return;
        }
        conversationRef.current = null;
        setConversationId(null);
        lastMessageIdRef.current = 0;
        setSupportStatus('ai');
        setAssignedStaffName(null);
        try { sessionStorage.removeItem(CONVERSATION_KEY); } catch {}
        setMessages([WELCOME]);
        setInput('');
        inputRef.current?.focus();
    };

    useEffect(() => () => {
        requestRef.current?.abort();
        pollRef.current?.abort();
    }, []);

    useEffect(() => {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.filter((message) => !message.error).slice(-40)));
        } catch {
            // El historial en memoria sigue disponible durante la visita.
        }
    }, [messages]);

    const close = () => {
        setOpen(false);
        launcherRef.current?.focus();
    };

    useEffect(() => {
        if (listRef.current) {
            listRef.current.scrollTop = messages.length === 1 ? 0 : listRef.current.scrollHeight;
        }
    }, [messages, sending, open]);

    useEffect(() => {
        if (!open || !conversationId) {
            return undefined;
        }

        const markMessagesRead = async () => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            const latestStaffMessageId = messages.reduce((latestId, message) => (
                message.source === 'staff' ? Math.max(latestId, Number(message.message_id) || 0) : latestId
            ), 0);
            if (latestStaffMessageId <= lastReadStaffMessageIdRef.current) {
                return;
            }

            try {
                const response = await fetch(`/chatbot/${conversationId}/leido`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ message_id: latestStaffMessageId }),
                });
                if (!response.ok) {
                    throw new Error('No se pudo actualizar la confirmación de lectura.');
                }
                lastReadStaffMessageIdRef.current = latestStaffMessageId;
            } catch (error) {
                console.error('No se pudo marcar como leídos los mensajes del asesor.', error);
            }
        };

        markMessagesRead();
        document.addEventListener('visibilitychange', markMessagesRead);

        return () => document.removeEventListener('visibilitychange', markMessagesRead);
    }, [open, conversationId, messages]);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    useEffect(() => {
        if (!open || !conversationId || sending) {
            return undefined;
        }

        let cancelled = false;
        let initialized = false;
        const sync = async () => {
            pollRef.current?.abort();
            const controller = new AbortController();
            pollRef.current = controller;
            try {
                const after = initialized ? lastMessageIdRef.current : 0;
                const response = await fetch(`/chatbot/${conversationId}/actualizaciones?after=${after}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok || response.redirected) {
                    return;
                }
                const data = await response.json();
                if (cancelled || !data.ok) {
                    return;
                }
                const incoming = Array.isArray(data.messages) ? data.messages : [];
                if (!initialized) {
                    setMessages([WELCOME, ...incoming.map((message) => ({
                        role: message.role,
                        content: message.content,
                        source: message.source,
                        message_id: Number(message.id),
                    }))]);
                    initialized = true;
                } else {
                    const newMessages = incoming.filter((message) => Number(message.id) > lastMessageIdRef.current);
                    if (newMessages.length) {
                        setMessages((current) => [...current, ...newMessages.map((message) => ({
                            role: message.role,
                            content: message.content,
                            source: message.source,
                            message_id: Number(message.id),
                        }))]);
                    }
                }
                lastMessageIdRef.current = Math.max(lastMessageIdRef.current, Number(data.last_message_id) || 0);
                setSupportStatus(data.status || 'ai');
                setAssignedStaffName(data.assigned_staff_name || null);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('No se pudo actualizar la conversación del chatbot.', error);
                }
            }
        };

        sync();
        const interval = setInterval(sync, 4000);

        return () => {
            cancelled = true;
            clearInterval(interval);
            pollRef.current?.abort();
        };
    }, [open, conversationId, sending]);

    const send = async (text) => {
        const message = text.trim();
        if (!message || message.length > 600 || sendingRef.current) {
            return;
        }
        sendingRef.current = true;
        const controller = new AbortController();
        requestRef.current = controller;
        const timeout = setTimeout(() => controller.abort(), 60000);
        setMessages((current) => [...current, { role: 'user', content: message }]);
        setInput('');
        setSending(true);
        try {
            const response = await fetch('/chatbot/mensaje', {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message, conversation_id: conversationRef.current }),
            });
            const data = await response.json().catch(() => ({}));
            if (data.conversation_id) {
                conversationRef.current = data.conversation_id;
                setConversationId(data.conversation_id);
                try { sessionStorage.setItem(CONVERSATION_KEY, data.conversation_id); } catch {}
            }
            if (Number.isInteger(Number(data.last_message_id))) {
                lastMessageIdRef.current = Number(data.last_message_id);
            }
            if (response.status === 404) {
                conversationRef.current = null;
                try { sessionStorage.removeItem(CONVERSATION_KEY); } catch {}
                throw new Error('La conversación anterior ya no está disponible. Envía de nuevo tu mensaje para comenzar otra.');
            }
            if (!response.ok || !data.ok || (data.reply !== null && data.reply !== undefined && typeof data.reply !== 'string')) {
                const error = response.status === 429
                    ? 'Has enviado varios mensajes seguidos. Espera un minuto y vuelve a intentarlo.'
                    : response.status === 419
                        ? 'Tu sesión expiró. Recarga la página para continuar.'
                        : data.reply || 'No pude responder en este momento. Intenta de nuevo.';
                throw new Error(error);
            }
            setSupportStatus(data.handoff_status || 'ai');
            if (typeof data.reply === 'string') {
                setMessages((current) => [
                    ...current,
                    { role: 'assistant', content: data.reply, message_id: lastMessageIdRef.current },
                ]);
            }
        } catch (error) {
            setMessages((current) => [
                ...current,
                { role: 'assistant', error: true, content: error.name === 'AbortError' ? 'La respuesta está tardando demasiado. Intenta de nuevo.' : error instanceof TypeError ? 'Parece que no hay conexión. Intenta de nuevo en un momento.' : error.message },
            ]);
            setInput(message);
        } finally {
            clearTimeout(timeout);
            sendingRef.current = false;
            requestRef.current = null;
            setSending(false);
        }
    };

    return (
        <div className="dgbot">
            {open && (
                <section id="desego-chatbot" className="dgbot-panel" role="dialog" aria-label="Asistente virtual DESEGO" onKeyDown={(event) => { if (event.key === 'Escape') { event.stopPropagation(); close(); } }}>
                    <header className="dgbot-header">
                        <img src="/images/chatbot.webp" alt="" className="dgbot-avatar" />
                        <div className="dgbot-header-copy">
                            <strong>DESEGO<span className="dgbot-brand-dot">.</span></strong>
                            <span className={`dgbot-status is-${supportStatus}`}><i aria-hidden="true" /> {supportStatus === 'human' ? `Te atiende ${assignedStaffName || 'un asesor'}` : supportStatus === 'requested' ? 'Esperando a un asesor' : 'Tu asistente virtual'}</span>
                        </div>
                        <button type="button" className="dgbot-reset" disabled={sending || supportStatus !== 'ai'} onClick={newConversation} aria-label="Nueva conversación" title="Nueva conversación"><RefreshCw size={17} /></button>
                        <button type="button" className="dgbot-close" onClick={close} aria-label="Cerrar chat">
                            <X size={18} />
                        </button>
                    </header>
                    <div className="dgbot-messages" ref={listRef} role="log" aria-label="Conversación" aria-live="polite" aria-relevant="additions" aria-busy={sending}>
                        {supportStatus !== 'ai' && (
                            <div className="dgbot-handoff-notice" role="status">
                                <strong>{supportStatus === 'human' ? `${assignedStaffName || 'Un asesor'} está atendiendo` : 'Solicitud de asesor enviada'}</strong>
                                <span>{supportStatus === 'human' ? 'Puedes continuar escribiendo; el asesor responderá en este chat.' : 'Un asesor responderá por este chat en cuanto esté disponible.'}</span>
                            </div>
                        )}
                        {messages.length === 1 && messages[0].content === WELCOME.content && (
                            <div className="dgbot-intro">
                                <span className="dgbot-eyebrow">BIENVENIDO A NUESTRA MESA</span>
                                <h2>¿Qué se te<br />antoja hoy?</h2>
                                <p>Descubre la carta, resuelve tus dudas y planea tu próxima visita.</p>
                            </div>
                        )}
                        {messages.map((m, index) => m.content === WELCOME.content ? null : (
                            <div key={index} className={`dgbot-row ${m.role}${m.error ? ' is-error' : ''}`}>
                                {m.role === 'assistant' && <img src="/images/chatbot.webp" alt="" className="dgbot-mini" />}
                                <div className="dgbot-message-copy">
                                    <span className="dgbot-message-author">{m.role === 'assistant' ? (m.source === 'staff' ? 'ASESOR' : 'DESEGO') : 'Tú'}</span>
                                    {m.role === 'assistant' ? <MarkdownMessage content={m.content} /> : <p>{m.content}</p>}
                                </div>
                            </div>
                        ))}
                        {sending && (
                            <div className="dgbot-row assistant">
                                <img src="/images/chatbot.webp" alt="" className="dgbot-mini" />
                                <p className="dgbot-typing" aria-label="Escribiendo"><span /><span /><span /></p>
                            </div>
                        )}
                        {messages.length === 1 && messages[0].content === WELCOME.content && !sending && (
                            <div className="dgbot-suggestions">
                                {SUGGESTIONS.map((s) => (
                                    <button key={s.title} type="button" onClick={() => send(s.message)}><span><strong>{s.title}</strong><small>{s.detail}</small></span><ArrowUpRight size={17} /></button>
                                ))}
                            </div>
                        )}
                    </div>
                    <nav className="dgbot-actions" aria-label="Accesos del asistente">
                        <a
                            href={STORE_SECTION_PATHS.carta}
                            onClick={(event) => {
                                navigateToStoreSection('carta', event);
                                close();
                            }}
                        >
                            <BookOpen size={15} /> Ver carta <ArrowUpRight size={13} />
                        </a>
                        <a
                            href={STORE_SECTION_PATHS.reservas}
                            onClick={(event) => {
                                navigateToStoreSection('reservas', event);
                                close();
                            }}
                        >
                            <CalendarDays size={15} /> Reservar <ArrowUpRight size={13} />
                        </a>
                    </nav>
                    <form className="dgbot-form" onSubmit={(e) => { e.preventDefault(); send(input); }}>
                        <div className="dgbot-composer">
                        <input
                            ref={inputRef}
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Conversemos..."
                            maxLength={600}
                            disabled={sending}
                            aria-label="Mensaje para el asistente"
                        />
                        <button type="submit" disabled={sending || !input.trim()} aria-label="Enviar mensaje">
                            <Send size={17} />
                        </button>
                        </div>
                        <p className="dgbot-footer-note">La conversación se guarda para dar seguimiento a tu atención.</p>
                    </form>
                </section>
            )}
            <button
                ref={launcherRef}
                type="button"
                className={`dgbot-launcher ${open ? 'is-open' : ''}`}
                onClick={() => { if (open) { close(); } else { setOpen(true); } }}
                aria-label={open ? 'Cerrar asistente' : 'Abrir asistente virtual'}
                aria-expanded={open}
                aria-controls="desego-chatbot"
            >
                <img src="/images/chatbot.webp" alt="" />
            </button>
        </div>
    );
}
