import React, { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Copy, Pencil, Plus, ShieldCheck, X } from './Icons';
import { ErrorList } from './ShopParts';
import { adminDateTime } from '../../lib/adminSpanish';

function RoleEditor({ role, permissions, duplicate, close }) {
    const form = useForm({
        action: role && !duplicate ? 'update' : 'create',
        id: role && !duplicate ? role.id : null,
        name: duplicate ? `${role.name} (copia)` : role?.name || '',
        description: role?.description || '',
        permissions: permissions.filter(permission => role?.permission_ids?.includes(permission.id)).map(permission => permission.code),
    });
    const groups = useMemo(() => permissions.reduce((acc, permission) => {
        const key = permission.module || 'Otros';
        (acc[key] ??= []).push(permission);
        return acc;
    }, {}), [permissions]);
    useEffect(() => {
        const listener = event => { if (event.key === 'Escape') { close(); } };
        document.addEventListener('keydown', listener);
        return () => document.removeEventListener('keydown', listener);
    }, [close]);
    const toggle = code => form.setData('permissions', form.data.permissions.includes(code) ? form.data.permissions.filter(item => item !== code) : [...form.data.permissions, code]);
    return <div className="glass-modal-backdrop" onClick={close}>
        <form className="glass-editor access-role-editor" role="dialog" aria-modal="true" aria-labelledby="role-editor-title" onClick={event => event.stopPropagation()} onSubmit={event => { event.preventDefault(); form.post('/admin/acciones/roles', { preserveScroll: true, onSuccess: close }); }}>
            <header className="glass-editor-head"><div><span className="glass-eyebrow">CONTROL DE ACCESOS</span><h2 id="role-editor-title">{duplicate ? 'Duplicar rol' : role ? 'Editar rol' : 'Crear rol'}</h2></div><button className="glass-icon-btn" type="button" onClick={close} aria-label="Cerrar editor"><X size={18} /></button></header>
            <ErrorList errors={form.errors} />
            <label className="glass-field">Nombre del rol<input className="glass-input" autoFocus required minLength={3} maxLength={80} value={form.data.name} onChange={event => form.setData('name', event.target.value)} /></label>
            <label className="glass-field">Descripción<input className="glass-input" maxLength={255} value={form.data.description} onChange={event => form.setData('description', event.target.value)} /></label>
            <p className="access-muted">Selecciona los accesos que tendrá este rol. Los cambios se aplican en la siguiente solicitud de cada usuario.</p>
            <div className="access-permissions">{Object.entries(groups).map(([module, items]) => <fieldset key={module}><legend>{module}</legend>{items.map(permission => <label key={permission.id}><input type="checkbox" checked={form.data.permissions.includes(permission.code)} onChange={() => toggle(permission.code)} /><span>{permission.name}<small>{permission.code}</small></span></label>)}</fieldset>)}</div>
            <footer className="glass-editor-actions"><span className="access-muted">{form.data.permissions.length} permisos seleccionados</span><button className="glass-btn" type="button" onClick={close}>Cancelar</button><button className="glass-btn glass-btn-primary" disabled={form.processing}>Guardar rol</button></footer>
        </form>
    </div>;
}

function RoleHistoryEntry({ event, permissions }) {
    let details = {};
    try { details = typeof event.details === 'string' ? JSON.parse(event.details) : event.details || {}; } catch {}
    const describe = codes => codes?.map(code => permissions.find(permission => permission.code === code)?.name || code).join(', ') || 'Sin permisos';
    return <li><div><strong>{details.role_name || `Rol ${details.role_id || ''}`} · {event.event_code === 'roles.created' ? 'Creado' : 'Actualizado'}</strong><p className="access-muted">{event.actor_name || 'Sistema'} · {adminDateTime(event.created_at)}</p>{details.permissions_after && <details><summary>Ver cambio de permisos</summary>{details.permissions_before && <p className="access-muted">Antes: {describe(details.permissions_before)}</p>}<p className="access-muted">Después: {describe(details.permissions_after)}</p></details>}</div></li>;
}

export default function AdminRoles({ data }) {
    const [editor, setEditor] = useState(null);
    const [query, setQuery] = useState('');
    const [permissionQuery, setPermissionQuery] = useState('');
    const roles = data.roles || [];
    const permissions = data.permissions || [];
    const filtered = roles.filter(role => `${role.name} ${role.description || ''}`.toLowerCase().includes(query.toLowerCase()));
    const matrixPermissions = permissions.filter(permission => `${permission.name} ${permission.module} ${permission.code}`.toLowerCase().includes(permissionQuery.toLowerCase()));
    return <div className="access-module">
        <header className="access-module-head"><div><span className="glass-eyebrow">ADMINISTRACIÓN</span><h2>Roles y permisos</h2><p className="access-muted">Define qué puede consultar y gestionar cada integrante del equipo.</p></div><button className="glass-btn glass-btn-primary" onClick={() => setEditor({ role: null })}><Plus size={16} /> Crear rol</button></header>
        <section className="glass-panel access-panel"><div className="access-toolbar"><h3>{roles.length} roles disponibles</h3><input aria-label="Buscar roles" className="glass-input" placeholder="Buscar un rol..." value={query} onChange={event => setQuery(event.target.value)} /></div>
            <div className="access-role-grid">{filtered.map(role => <article className="access-role-card" key={role.id}><ShieldCheck size={23} /><h3>{role.name}</h3><p className="access-muted">{role.description || 'Rol personalizado del equipo.'}</p><span className="glass-badge glass-badge-info">{role.permission_ids.length} permisos</span><div className="access-card-actions">{role.code !== 'admin' && <button className="glass-btn" onClick={() => setEditor({ role })}><Pencil size={14} /> Editar</button>}<button className="glass-btn" onClick={() => setEditor({ role, duplicate: true })}><Copy size={14} /> Duplicar</button>{role.code === 'admin' && <small>Administrador protegido</small>}</div></article>)}</div>
            {!filtered.length && <p className="access-empty">No se encontraron roles.</p>}
        </section>
        <section className="glass-panel access-panel"><div className="access-toolbar"><h3>Matriz de permisos</h3><input className="glass-input" aria-label="Filtrar permisos" placeholder="Filtrar por módulo o permiso..." value={permissionQuery} onChange={event => setPermissionQuery(event.target.value)} /></div><div className="access-table-scroll"><table className="access-matrix"><thead><tr><th>Permiso</th>{roles.map(role => <th key={role.id}>{role.name}</th>)}</tr></thead><tbody>{matrixPermissions.map(permission => <tr key={permission.id}><th scope="row">{permission.name}<small>{permission.code}</small></th>{roles.map(role => <td key={role.id}>{role.permission_ids.includes(permission.id) ? <span aria-label="Permitido" className="access-granted">Sí</span> : <span aria-label="Sin permiso">—</span>}</td>)}</tr>)}</tbody></table></div></section>
        <section className="glass-panel access-panel"><h3>Últimos cambios de roles</h3>{data.history?.length ? <ol className="access-history">{data.history.map(event => <RoleHistoryEntry key={event.id} event={event} permissions={permissions} />)}</ol> : <p className="access-muted">Todavía no hay cambios registrados.</p>}</section>
        {editor && <RoleEditor key={`${editor.role?.id || 'new'}-${!!editor.duplicate}`} {...editor} permissions={permissions} close={() => setEditor(null)} />}
    </div>;
}
