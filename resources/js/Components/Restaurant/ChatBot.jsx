import React, { useEffect, useRef, useState } from 'react';
import { Send, X } from './Icons';
import '../../../css/chatbot.css';

const WELCOME = {
    role: 'assistant',
    content: '¡Hola! Soy el asistente de DESEGO 🐟 Te ayudo con la carta, precios, delivery y reservas. ¿Qué se te antoja hoy?',
};
const SUGGESTIONS = ['¿Qué ceviches tienen?', '¿Hacen delivery?', '¿Cómo reservo una mesa?'];

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export default function ChatBot() {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([WELCOME]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const listRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {
        if (listRef.current) {
            listRef.current.scrollTop = listRef.current.scrollHeight;
        }
    }, [messages, sending, open]);

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    const send = async (text) => {
        const message = text.trim();
        if (!message || sending) {
            return;
        }
        const history = messages.filter((m) => m !== WELCOME).slice(-10);
        setMessages((current) => [...current, { role: 'user', content: message }]);
        setInput('');
        setSending(true);
        try {
            const response = await fetch('/chatbot/mensaje', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message, history }),
            });
            const data = await response.json().catch(() => ({}));
            setMessages((current) => [
                ...current,
                { role: 'assistant', content: data.reply || 'No pude responder en este momento. Intenta de nuevo.' },
            ]);
        } catch {
            setMessages((current) => [
                ...current,
                { role: 'assistant', content: 'Parece que no hay conexión. Intenta de nuevo en un momento.' },
            ]);
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="dgbot">
            {open && (
                <section className="dgbot-panel" role="dialog" aria-label="Asistente virtual DESEGO">
                    <header className="dgbot-header">
                        <img src="/images/chatbot.webp" alt="" className="dgbot-avatar" />
                        <div>
                            <strong>Asistente DESEGO</strong>
                            <span><i aria-hidden="true" /> En línea</span>
                        </div>
                        <button type="button" className="dgbot-close" onClick={() => setOpen(false)} aria-label="Cerrar chat">
                            <X size={18} />
                        </button>
                    </header>
                    <div className="dgbot-messages" ref={listRef} aria-live="polite">
                        {messages.map((m, index) => (
                            <div key={index} className={`dgbot-row ${m.role}`}>
                                {m.role === 'assistant' && <img src="/images/chatbot.webp" alt="" className="dgbot-mini" />}
                                <p>{m.content}</p>
                            </div>
                        ))}
                        {sending && (
                            <div className="dgbot-row assistant">
                                <img src="/images/chatbot.webp" alt="" className="dgbot-mini" />
                                <p className="dgbot-typing" aria-label="Escribiendo"><span /><span /><span /></p>
                            </div>
                        )}
                        {messages.length === 1 && !sending && (
                            <div className="dgbot-suggestions">
                                {SUGGESTIONS.map((s) => (
                                    <button key={s} type="button" onClick={() => send(s)}>{s}</button>
                                ))}
                            </div>
                        )}
                    </div>
                    <form className="dgbot-form" onSubmit={(e) => { e.preventDefault(); send(input); }}>
                        <input
                            ref={inputRef}
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Escribe tu mensaje..."
                            maxLength={600}
                            aria-label="Mensaje para el asistente"
                        />
                        <button type="submit" disabled={sending || !input.trim()} aria-label="Enviar mensaje">
                            <Send size={17} />
                        </button>
                    </form>
                </section>
            )}
            <button
                type="button"
                className={`dgbot-launcher ${open ? 'is-open' : ''}`}
                onClick={() => setOpen((value) => !value)}
                aria-label={open ? 'Cerrar asistente' : 'Abrir asistente virtual'}
                aria-expanded={open}
            >
                <img src="/images/chatbot.webp" alt="" />
            </button>
        </div>
    );
}
