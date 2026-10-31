import React, { useState } from 'react';

const PRIMARY_NAV = [
    {
        view: 'home',
        label: 'inicio',
        title: 'Panel Principal',
        active: true,
        svg: (
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 3.2 3 10.5V20a1 1 0 0 0 1 1h5.5v-6h5v6H20a1 1 0 0 0 1-1v-9.5L12 3.2Z" />
            </svg>
        ),
    },
    {
        view: 'reports',
        label: 'resultados',
        title: 'Resultados y Reportes',
        svg: (
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <rect x="4" y="13" width="3.5" height="7" rx="1" />
                <rect x="10.25" y="9" width="3.5" height="11" rx="1" />
                <rect x="16.5" y="4" width="3.5" height="16" rx="1" />
            </svg>
        ),
    },
    {
        view: 'orders',
        label: 'pedidos',
        title: 'Centro de Pedidos y POS',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true">
                <rect x="3" y="4" width="18" height="12" rx="2" />
                <path d="M8 20h8M12 16v4" />
            </svg>
        ),
    },
    {
        view: 'kitchen',
        label: 'cocina',
        title: 'Pantalla de Cocina (KDS)',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true">
                <path d="M4 11h16a1 1 0 0 1 1 1v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3a1 1 0 0 1 1-1Z" />
                <path d="M8 7V5M12 7V4M16 7V5" strokeLinecap="round" />
            </svg>
        ),
    },
    {
        view: 'menu',
        label: 'carta',
        title: 'Menú, Recetas e Inventario',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true">
                <path d="M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" />
                <path d="M8 9h8M8 13h8M8 17h5" strokeLinecap="round" />
            </svg>
        ),
    },
    {
        view: 'reservations',
        label: 'salón',
        title: 'Reservas y Mesas',
        svg: (
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true">
                <rect x="3" y="5" width="18" height="15" rx="2" />
                <path d="M3 10h18M8 3v4M16 3v4" strokeLinecap="round" />
            </svg>
        ),
    },
    {
        view: 'customers',
        label: 'clientes',
        title: 'Clientes y Fidelización',
        svg: (
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="12" cy="8" r="4" />
                <path d="M4.5 20a7.5 7.5 0 0 1 15 0H4.5Z" />
            </svg>
        ),
    },
];

const EXTRA_NAV = [
    { view: 'delivery', label: 'Reparto' },
    { view: 'billing', label: 'Facturación' },
    { view: 'analytics', label: 'Analítica BI' },
    { view: 'loyalty', label: 'Puntos' },
    { view: 'reviews', label: 'Reseñas' },
    { view: 'suppliers', label: 'Proveedores' },
    { view: 'purchasing', label: 'Compras' },
    { view: 'branches', label: 'Sucursales' },
    { view: 'employees', label: 'Personal' },
    { view: 'shifts', label: 'Turnos' },
    { view: 'notifications', label: 'Avisos' },
    { view: 'support', label: 'Soporte' },
    { view: 'integrations', label: 'Integraciones' },
    { view: 'audit', label: 'Auditoría' },
    { view: 'backups', label: 'Respaldos' },
];

export default function AdminSidebar() {
    const [showMore, setShowMore] = useState(false);

    return (
        <>
            <div id="sidebarMobileBackdrop" className="sidebar-mobile-backdrop" aria-hidden="true" />
            <aside className="enterprise-sidebar smart-capsule-sidebar" aria-label="Menú administrativo DESEGO">
                {/* Brand / Logo superior */}
                <div className="capsule-sidebar-top">
                    <div className="capsule-brand" title="DESEGO Cevichería">
                        <img src="/assets/desego-logo.png" alt="DESEGO" />
                        <span>desego</span>
                    </div>
                </div>

                {/* Navegación principal estilo cápsula celeste */}
                <nav className="capsule-nav-scroll" aria-label="Módulos del sistema">
                    <div className="capsule-nav-primary">
                        {PRIMARY_NAV.map((item) => (
                            <button
                                key={item.view}
                                type="button"
                                data-admin-nav={item.view}
                                className={`sidebar-nav-item capsule-nav-btn ${item.active ? 'is-active' : ''}`}
                                title={item.title}
                            >
                                <span className="capsule-nav-icon" aria-hidden="true">
                                    {item.svg}
                                </span>
                                <span className="capsule-nav-label">{item.label}</span>
                            </button>
                        ))}

                        {/* Botón desplegable para los demás módulos ERP */}
                        <button
                            type="button"
                            onClick={() => setShowMore((prev) => !prev)}
                            className="capsule-nav-btn capsule-more-toggle"
                            title="Ver todos los módulos del sistema"
                        >
                            <span className="capsule-nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="6" cy="12" r="2" />
                                    <circle cx="12" cy="12" r="2" />
                                    <circle cx="18" cy="12" r="2" />
                                </svg>
                            </span>
                            <span className="capsule-nav-label">{showMore ? 'menos' : 'módulos'}</span>
                        </button>
                    </div>

                    {/* Lista completa de módulos secundarios (siempre en el DOM para permisos de script.js) */}
                    <div className={`capsule-extra-list ${showMore ? 'is-expanded' : ''}`}>
                        {EXTRA_NAV.map((item) => (
                            <button
                                key={item.view}
                                type="button"
                                data-admin-nav={item.view}
                                className="sidebar-nav-item capsule-sub-btn"
                                title={item.label}
                            >
                                <span className="capsule-sub-dot" />
                                <span className="capsule-nav-label">{item.label}</span>
                            </button>
                        ))}
                        <button
                            type="button"
                            id="sidebarCashModalBtn"
                            className="capsule-sub-btn"
                            title="Control y Arqueo de Caja"
                        >
                            <span className="capsule-sub-dot" />
                            <span className="capsule-nav-label">Arqueo Caja</span>
                        </button>
                        <button
                            type="button"
                            id="sidebarUsersModalBtn"
                            className="capsule-sub-btn"
                            title="Usuarios y Permisos"
                        >
                            <span className="capsule-sub-dot" />
                            <span className="capsule-nav-label">Usuarios</span>
                        </button>
                    </div>
                </nav>

                {/* Settings anclado abajo como en el diseño de referencia */}
                <div className="capsule-sidebar-bottom">
                    <button
                        type="button"
                        data-admin-nav="profile"
                        className="sidebar-nav-item capsule-nav-btn"
                        title="Ajustes y Perfil"
                    >
                        <span className="capsule-nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.4 13c.04-.33.06-.66.06-1s-.02-.67-.06-1l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.6-.22l-2.49 1a7.03 7.03 0 0 0-1.73-1l-.38-2.65A.49.49 0 0 0 14 2h-4a.49.49 0 0 0-.49.42l-.38 2.65c-.63.25-1.21.59-1.73 1l-2.49-1a.5.5 0 0 0-.6.22l-2 3.46a.49.49 0 0 0 .12.64L4.54 11c-.04.33-.06.66-.06 1s.02.67.06 1l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .6.22l2.49-1c.52.41 1.1.75 1.73 1l.38 2.65c.05.24.25.42.49.42h4c.24 0 .44-.18.49-.42l.38-2.65c.63-.25 1.21-.59 1.73-1l2.49 1a.5.5 0 0 0 .6-.22l2-3.46a.5.5 0 0 0-.12-.64L19.4 13ZM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7Z" />
                            </svg>
                        </span>
                        <span className="capsule-nav-label">ajustes</span>
                    </button>

                    {/* Elementos ocultos para mantener compatibilidad con script.js */}
                    <div className="hidden" aria-hidden="true">
                        <span id="sidebarUserInitials">AD</span>
                        <span id="sidebarUserName">Administrador</span>
                        <span id="sidebarUserRole">Gestión</span>
                        <button type="button" id="sidebarCollapseToggle">⇄</button>
                    </div>
                </div>
            </aside>
        </>
    );
}
