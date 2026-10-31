import React, { useState } from 'react';

const QUICK_SEARCH_MODULES = [
    { view: 'home', label: 'Inicio · Panel General', keywords: 'home inicio resumen solar power' },
    { view: 'orders', label: 'Centro de Pedidos y POS', keywords: 'pedidos ventas pos ordenes' },
    { view: 'kitchen', label: 'Pantalla de Cocina (KDS)', keywords: 'cocina kds comandas platos' },
    { view: 'delivery', label: 'Centro de Delivery y Reparto', keywords: 'delivery reparto motorizados envio' },
    { view: 'reservations', label: 'Reservas y Plano de Mesas', keywords: 'reservas mesas salon' },
    { view: 'menu', label: 'Carta, Recetas e Inventario', keywords: 'menu carta platos stock inventario mermas' },
    { view: 'suppliers', label: 'Directorio de Proveedores', keywords: 'proveedores compras insumos' },
    { view: 'purchasing', label: 'Órdenes de Compra y Costeo', keywords: 'compras ordenes fifo promedio costos' },
    { view: 'customers', label: 'Clientes y Direcciones (CRM)', keywords: 'clientes crm direcciones' },
    { view: 'loyalty', label: 'Programa de Fidelización', keywords: 'puntos fidelizacion lealtad' },
    { view: 'reviews', label: 'Moderación de Reseñas', keywords: 'resenas opiniones calificaciones' },
    { view: 'billing', label: 'Facturación y Comprobantes', keywords: 'boletas facturas comprobantes pagos' },
    { view: 'reports', label: 'Reportes Operativos y CSV', keywords: 'reportes ventas exportar' },
    { view: 'analytics', label: 'Analítica Avanzada (BI)', keywords: 'analitica bi rentabilidad tendencias' },
    { view: 'branches', label: 'Sucursales y Sedes', keywords: 'sucursales sedes locales' },
    { view: 'employees', label: 'Personal y Colaboradores', keywords: 'personal empleados rrhh' },
    { view: 'shifts', label: 'Turnos y Control de Asistencia', keywords: 'turnos horarios asistencia' },
    { view: 'notifications', label: 'Centro de Notificaciones', keywords: 'notificaciones avisos' },
    { view: 'support', label: 'Mesa de Ayuda y Soporte', keywords: 'soporte tickets ayuda' },
    { view: 'integrations', label: 'Integraciones y Tokens API', keywords: 'api tokens integraciones' },
    { view: 'audit', label: 'Auditoría y Seguridad', keywords: 'auditoria logs seguridad' },
    { view: 'backups', label: 'Copias de Seguridad Cifradas', keywords: 'backups respaldos copias' },
    { view: 'profile', label: 'Mi Perfil y Configuración', keywords: 'perfil ajustes configuracion' },
];

export default function AdminTopbar() {
    const [query, setQuery] = useState('');
    const [focused, setFocused] = useState(false);

    const matches = query.trim()
        ? QUICK_SEARCH_MODULES.filter((m) =>
              `${m.label} ${m.keywords}`.toLowerCase().includes(query.trim().toLowerCase())
          ).slice(0, 6)
        : [];

    const handleSelectModule = (view) => {
        setQuery('');
        setFocused(false);
        if (typeof window.desegoSwitchAdminView === 'function') {
            window.desegoSwitchAdminView(view);
        }
    };

    return (
        <>
            <header className="topbar smart-topbar">
                {/* Saludo principal estilo "Hi, Jonathan" */}
                <div className="smart-topbar-left">
                    <button
                        type="button"
                        id="mobileSidebarToggle"
                        className="mobile-sidebar-btn"
                        aria-label="Abrir menú de navegación"
                    >
                        ☰
                    </button>

                    <div className="smart-greeting-block">
                        <h1 className="smart-greeting-title">
                            Hola, <span id="topbarGreetingName">Administrador</span>
                        </h1>
                        <div className="smart-greeting-meta">
                            <span id="topbarCurrentViewGroup">Operación General</span>
                            <span>·</span>
                            <strong id="topbarCurrentViewTitle">Panel General</strong>
                            <span>·</span>
                            <span className="inline-flex items-center gap-1">
                                <i id="connectionDot" className="connection-dot is-online" />
                                <span id="connectionLabel">En línea</span>
                            </span>
                        </div>
                    </div>
                </div>

                {/* Buscador pill + Iconos de campana, correo y avatar circular azul */}
                <div className="smart-topbar-right">
                    <div className="smart-search-wrapper">
                        <span className="smart-search-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-3.5-3.5" strokeLinecap="round" />
                            </svg>
                        </span>
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onFocus={() => setFocused(true)}
                            onBlur={() => setTimeout(() => setFocused(false), 180)}
                            placeholder="Buscar módulo…"
                            aria-label="Buscar módulo del panel"
                            className="smart-search-input"
                        />
                        {focused && matches.length > 0 && (
                            <div className="smart-search-dropdown">
                                {matches.map((item) => (
                                    <button
                                        key={item.view}
                                        type="button"
                                        onMouseDown={() => handleSelectModule(item.view)}
                                        className="smart-search-option"
                                    >
                                        <span>{item.label}</span>
                                        <small>Ir →</small>
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <a href="/" className="smart-store-pill" title="Abrir Tienda Pública">
                        <span>Tienda</span>
                        <span>↗</span>
                    </a>

                    <button
                        type="button"
                        id="connectionToggle"
                        className="smart-icon-btn"
                        title="Estado de conexión con el servidor"
                        aria-label="Cambiar estado de conexión"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96Z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        id="notificationsButton"
                        className="smart-icon-btn notification-button"
                        title="Notificaciones"
                        aria-label="Ver notificaciones"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2Zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2Z" />
                        </svg>
                        <span className="notification-badge is-hidden">0</span>
                    </button>

                    <button
                        type="button"
                        data-go="support"
                        className="smart-icon-btn"
                        title="Soporte y Mensajes"
                        aria-label="Abrir soporte"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        id="themeToggleButton"
                        className="smart-icon-btn"
                        title="Personalizar tono azul"
                        aria-label="Personalizar apariencia"
                        aria-expanded="false"
                        aria-controls="themePopover"
                    >
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 3a9 9 0 0 0 0 18c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8Z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-go="profile"
                        className="smart-avatar-circle"
                        title="Mi Perfil"
                        aria-label="Ir a mi perfil"
                    >
                        <span id="topbarUserInitials">JD</span>
                    </button>

                    <div className="hidden" aria-hidden="true">
                        <span id="topbarUserName">Administrador</span>
                        <span id="topbarUserRole">Gestión</span>
                    </div>
                </div>
            </header>

            <aside id="themePopover" className="theme-popover is-hidden" aria-label="Personalizar acento corporativo">
                <div className="theme-popover-head">
                    <div>
                        <span>APARIENCIA</span>
                        <h2>Tono del Panel</h2>
                    </div>
                    <button type="button" id="themeCloseButton" className="theme-close" aria-label="Cerrar selector">
                        ×
                    </button>
                </div>
                <div className="theme-current-row">
                    <label htmlFor="themeDarkness">Intensidad del azul</label>
                    <strong id="themeToneLabel">Celeste Smart</strong>
                </div>
                <input
                    id="themeDarkness"
                    className="theme-range"
                    type="range"
                    min="0"
                    max="100"
                    step="1"
                    defaultValue="0"
                    aria-label="Intensidad del acento"
                />
                <div className="theme-range-labels">
                    <span>Celeste (#62AAF2)</span>
                    <span>Corporativo (#004797)</span>
                </div>
                <div className="theme-presets" aria-label="Tonos predeterminados">
                    <button type="button" className="theme-preset is-active" data-theme-preset="0">
                        <span className="preset-swatch swatch-original" />
                        <small>Celeste</small>
                    </button>
                    <button type="button" className="theme-preset" data-theme-preset="50">
                        <span className="preset-swatch swatch-deep" />
                        <small>Institucional</small>
                    </button>
                    <button type="button" className="theme-preset" data-theme-preset="100">
                        <span className="preset-swatch swatch-night" />
                        <small>Ejecutivo</small>
                    </button>
                </div>
                <p className="theme-help">La preferencia se guarda automáticamente en tu perfil.</p>
            </aside>
        </>
    );
}
