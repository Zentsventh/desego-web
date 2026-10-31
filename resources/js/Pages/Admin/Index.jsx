import React, { useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AdminAuthScreen from '@/Components/Admin/AdminAuthScreen';
import AdminSidebar from '@/Components/Admin/AdminSidebar';
import AdminTopbar from '@/Components/Admin/AdminTopbar';
import AdminOperationsViews from '@/Components/Admin/AdminOperationsViews';
import AdminCrmFinanceViews from '@/Components/Admin/AdminCrmFinanceViews';
import AdminWorkforceSystemViews from '@/Components/Admin/AdminWorkforceSystemViews';

export default function AdminIndex({ csrf_token }) {
    useEffect(() => {
        if (csrf_token) {
            let meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) {
                meta = document.createElement('meta');
                meta.name = 'csrf-token';
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', csrf_token);
        }

        const existingScript = document.querySelector('script[data-desego-admin-script="1"]');
        if (existingScript && typeof window.desegoInitAdmin === 'function') {
            window.desegoInitAdmin();
            return;
        }

        const script = document.createElement('script');
        script.src = '/script.js';
        script.async = false;
        script.dataset.desegoAdminScript = '1';
        script.onload = () => {
            if (typeof window.desegoInitAdmin === 'function') {
                window.desegoInitAdmin();
            }
        };
        document.body.appendChild(script);
    }, [csrf_token]);

    return (
        <>
            <Head title="DESEGO CEVICHERÍA · Suite Empresarial"><link rel="stylesheet" href="/styles.css"/></Head>
            <div id="app" className="app-shell">
                {/* PANTALLA DE ACCESO CORPORATIVO */}
                <AdminAuthScreen />

                {/* WORKSPACE EMPRESARIAL CON SIDEBAR Y TOPBAR */}
                <div id="dashboardScreen" className="dashboard-screen is-hidden">
                    <AdminSidebar />

                    <div className="enterprise-workspace">
                        <AdminTopbar />

                        <main className="screen-content">
                            <AdminOperationsViews />
                            <AdminCrmFinanceViews />
                            <AdminWorkforceSystemViews />
                        </main>

                        <nav className="bottom-bar" aria-label="Navegación principal">
                            <button type="button" className="bottom-tab is-active" data-view="home">
                                <span className="tab-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z" />
                                    </svg>
                                </span>
                                <span>Inicio</span>
                            </button>
                            <button type="button" className="bottom-tab" data-view="orders">
                                <span className="tab-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 3h12a2 2 0 0 1 2 2v16H4V5a2 2 0 0 1 2-2Z" />
                                        <path d="M8 8h8M8 12h8M8 16h5" />
                                    </svg>
                                </span>
                                <span>Pedidos</span>
                            </button>
                            <button type="button" className="bottom-tab" data-view="menu">
                                <span className="tab-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5" />
                                    </svg>
                                </span>
                                <span>Menú</span>
                            </button>
                            <button type="button" className="bottom-tab" data-view="reservations">
                                <span className="tab-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="16" rx="2" />
                                        <path d="M7 3v4M17 3v4M3 10h18M8 14h3M8 17h3" />
                                    </svg>
                                </span>
                                <span>Reservas</span>
                            </button>
                            <button type="button" className="bottom-tab" data-view="profile">
                                <span className="tab-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="8" r="4" />
                                        <path d="M4 21a8 8 0 0 1 16 0" />
                                    </svg>
                                </span>
                                <span>Perfil</span>
                            </button>
                        </nav>
                    </div>
                </div>

                {/* MODALES Y FEEDBACK */}
                <div id="modalRoot" className="modal-root is-hidden" role="presentation">
                    <div className="modal-scrim" data-close-modal></div>
                    <section className="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
                        <button type="button" className="modal-x" data-close-modal aria-label="Cerrar ventana">
                            ×
                        </button>
                        <div id="modalBody"></div>
                    </section>
                </div>

                <div id="toast" className="toast" role="status" aria-live="polite">
                    <span id="toastIcon" className="toast-symbol">
                        ✓
                    </span>
                    <span className="toast-copy">
                        <strong id="toastTitle">Listo</strong>
                        <small id="toastMessage">Operación realizada.</small>
                    </span>
                </div>
            </div>
        </>
    );
}
