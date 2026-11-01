import React, { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { adminText, adminModuleTitle } from '../../lib/adminSpanish';
import {
    Archive, ArrowUpRight, BookOpen, CalendarDays, ChartNoAxesCombined, ChefHat,
    CircleHelp, ClipboardList, DatabaseBackup, Gift, LayoutDashboard,
    LogOut, Package, PanelsTopLeft, Settings2, ShieldCheck, ShoppingCart,
    Star, TicketPercent, Truck, UsersRound, UserRoundCog, Wallet, X, MessagesSquare,
} from 'lucide-react';

const groups = [
    { title: 'Resumen', keys: ['dashboard', 'reportes'] },
    { title: 'Operación', keys: ['salon', 'pedidos', 'cocina', 'delivery', 'reservas', 'caja'] },
    { title: 'Catálogo', keys: ['carta', 'categorias', 'insumos', 'alergenos', 'inventario', 'compras'] },
    { title: 'Clientes', keys: ['clientes', 'promociones', 'fidelizacion', 'resenas', 'chatbot', 'soporte'] },
    { title: 'Administración', keys: ['configuracion', 'equipo', 'usuarios', 'roles', 'auditoria', 'backups'] },
];

const icons = {
    dashboard: LayoutDashboard, reportes: ChartNoAxesCombined,
    pedidos: ClipboardList, cocina: ChefHat, delivery: Truck,
    reservas: CalendarDays, caja: Wallet, carta: BookOpen,
    salon: PanelsTopLeft,
    inventario: Package, compras: ShoppingCart, clientes: UsersRound,
    promociones: TicketPercent, fidelizacion: Gift, resenas: Star, soporte: CircleHelp,
    configuracion: Settings2, equipo: UserRoundCog, roles: ShieldCheck, auditoria: Archive,
    backups: DatabaseBackup,
    usuarios: UsersRound, chatbot: MessagesSquare,
    categorias: BookOpen, insumos: Package, alergenos: ShieldCheck,
};

function groupModules(navigation) {
    const knownKeys = new Set(groups.flatMap((group) => group.keys));
    const remainingItems = navigation.filter((item) => !knownKeys.has(item.key));
    const visibleGroups = groups.map((group) => ({
        ...group,
        items: group.keys.map((key) => navigation.find((item) => item.key === key)).filter(Boolean).map((item) => ({ ...item, title: adminModuleTitle(item) })),
    })).filter((group) => group.items.length);

    if (remainingItems.length) {
        visibleGroups.push({ title: 'Otros módulos', items: remainingItems });
    }

    return visibleGroups;
}

export function AdminTools({ navigation }) {
    return (
        <div className="glass-tools">
            <div className="glass-page-heading">
                <div>
                    <span className="glass-eyebrow">ADMINISTRACIÓN</span>
                    <h2>Herramientas</h2>
                    <p>Accede a los módulos disponibles para tu rol.</p>
                </div>
            </div>
            {groupModules(navigation).map((group) => (
                <section key={group.title} className="glass-tools-group" aria-label={group.title}>
                    <h3>{group.title}</h3>
                    <div className="glass-tools-grid">
                        {group.items.map((item) => {
                            const Icon = icons[item.key] || PanelsTopLeft;
                            return (
                                <Link key={item.key} className="glass-panel glass-tool-card" href={item.key === 'dashboard' ? '/admin' : `/admin/seccion/${item.key}`}>
                                    <span className="glass-metric-icon"><Icon size={20} strokeWidth={1.6} aria-hidden="true" /></span>
                                    <div><strong>{item.title}</strong><span>Abrir módulo</span></div>
                                    <ArrowUpRight size={17} aria-hidden="true" />
                                </Link>
                            );
                        })}
                    </div>
                </section>
            ))}
            {!navigation.length && <p>No hay módulos disponibles para tu rol.</p>}
        </div>
    );
}

export default function AdminNavigation({ navigation, section, user, open, onClose }) {
    const navRef = useRef(null);
    const closeRef = useRef(null);
    const [isMobile, setIsMobile] = useState(() => typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches);
    const visibleGroups = groupModules(navigation);

    useEffect(() => {
        const media = window.matchMedia('(max-width: 1023px)');
        const update = () => setIsMobile(media.matches);
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        if (!isMobile || !open) {
            return;
        }
        const workspace = document.querySelector('.dg-glass-admin .enterprise-workspace');
        const previousOverflow = document.body.style.overflow;
        if (workspace) {
            workspace.inert = true;
        }
        document.body.style.overflow = 'hidden';
        closeRef.current?.focus();
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            if (workspace) {
                workspace.inert = false;
            }
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', closeOnEscape);
            document.getElementById('admin-navigation-toggle')?.focus();
        };
    }, [isMobile, open, onClose]);

    useEffect(() => {
        const nav = navRef.current;
        const active = nav?.querySelector('[aria-current="page"]');
        if (!active || (isMobile && !open)) {
            return;
        }
        const bounds = nav.getBoundingClientRect();
        const itemBounds = active.getBoundingClientRect();
        if (itemBounds.top < bounds.top || itemBounds.bottom > bounds.bottom) {
            nav.scrollTop += itemBounds.top - bounds.top - bounds.height / 2 + itemBounds.height / 2;
        }
    }, [section, isMobile, open]);

    return (
        <aside id="admin-navigation" className="enterprise-sidebar smart-capsule-sidebar glass-navigation" aria-label="Navegación administrativa" aria-hidden={isMobile && !open ? true : undefined} inert={isMobile && !open}>
            <div className="glass-navigation-brand">
                <Link href={navigation.some((item) => item.key === 'dashboard') ? '/admin' : `/admin/seccion/${section}`} onClick={onClose} className="capsule-brand">
                    <img src="/assets/desego-logo.png" alt="DESEGO Cevichería" width="800" height="300" />
                </Link>
                <button ref={closeRef} type="button" className="glass-sidebar-close" onClick={onClose} aria-label="Cerrar navegación"><X size={18} aria-hidden="true" /></button>
            </div>

            <nav ref={navRef} className="glass-module-navigation" aria-label="Módulos disponibles">
                {visibleGroups.map((group) => (
                    <section key={group.title} className="glass-nav-group" aria-label={group.title}>
                        <h2>{group.title}</h2>
                        {group.items.map((item) => {
                            const Icon = icons[item.key] || PanelsTopLeft;
                            return (
                                <Link key={item.key} href={item.key === 'dashboard' ? '/admin' : `/admin/seccion/${item.key}`} onClick={onClose} className={`glass-module-link ${section === item.key ? 'is-active' : ''}`} aria-current={section === item.key ? 'page' : undefined}>
                                    <Icon size={18} strokeWidth={1.6} aria-hidden="true" />
                                    <span>{item.title}</span>
                                </Link>
                            );
                        })}
                    </section>
                ))}
            </nav>

            <div className="glass-navigation-bottom">
                <Link href="/admin/herramientas" className={`glass-module-link ${section === 'herramientas' ? 'is-active' : ''}`} aria-current={section === 'herramientas' ? 'page' : undefined} onClick={onClose}><Settings2 size={18} strokeWidth={1.6} aria-hidden="true" /><span>Herramientas</span></Link>
                <div className="glass-navigation-user">
                    <div><strong>{user?.name || 'Usuario'}</strong><span>{user?.role_name ? adminText(user.role_name, 'role_name') : 'Equipo DESEGO'}</span></div>
                    <button type="button" onClick={() => router.post('/salir')} aria-label="Cerrar sesión" title="Cerrar sesión"><LogOut size={18} strokeWidth={1.6} aria-hidden="true" /></button>
                </div>
            </div>
        </aside>
    );
}
