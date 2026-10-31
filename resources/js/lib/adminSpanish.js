const states = {
    active: 'Activo',
    inactive: 'Inactivo',
    available: 'Disponible',
    unavailable: 'No disponible',
    occupied: 'Ocupada',
    reserved: 'Reservada',
    maintenance: 'En mantenimiento',
    pending: 'Pendiente',
    approved: 'Aprobado',
    rejected: 'Rechazado',
    open: 'Abierto',
    closed: 'Cerrado',
    completed: 'Completado',
    failed: 'Fallido',
    deleted: 'Eliminado',
    valid: 'Válido',
    invalid: 'No válido',
    not_available: 'No disponible',
    unverified: 'Sin verificar',
    draft: 'Borrador',
    sent: 'Enviado',
    partially_received: 'Recibido parcialmente',
    received: 'Recibido',
    cancelled: 'Cancelado',
    canceled: 'Cancelado',
    scheduled: 'Programado',
    in_progress: 'En curso',
    waiting_user: 'En espera del usuario',
    resolved: 'Resuelto',
    paid: 'Pagado',
    refunded: 'Reembolsado',
    revoked: 'Revocado',
    issued: 'Emitido',
    voided: 'Anulado',
    used: 'Utilizado',
    success: 'Correcto',
    warning: 'Advertencia',
    error: 'Error',
    info: 'Información',
    not_checked: 'Sin verificar',
    configured: 'Configurado',
    needs_configuration: 'Requiere configuración',
    SIN_DOCUMENTO: 'Sin documento',
};

const codedFields = {
    priority: {
        low: 'Baja',
        normal: 'Normal',
        medium: 'Media',
        high: 'Alta',
        urgent: 'Urgente',
    },
    order_type: {
        Delivery: 'Entrega a domicilio',
        delivery: 'Entrega a domicilio',
        takeaway: 'Para llevar',
        pickup: 'Para llevar',
        dine_in: 'En mesa',
        online: 'En línea',
        reservation: 'Reserva',
    },
    role_name: {
        admin: 'Administrador',
        Admin: 'Administrador',
        customer: 'Cliente',
        Customer: 'Cliente',
        owner: 'Dueño',
        Owner: 'Dueño',
        collaborator: 'Colaborador',
        cashier: 'Cajero',
        waiter: 'Mesero',
        cook: 'Cocinero',
        courier: 'Repartidor',
        manager: 'Supervisor',
    },
    unit: {
        unit: 'unidad',
        units: 'unidades',
        unid: 'unid.',
        piece: 'pieza',
        pieces: 'piezas',
        pack: 'paquete',
        bottle: 'botella',
        liter: 'litro',
        liters: 'litros',
        kilogram: 'kilogramo',
        kg: 'kg',
        lt: 'L',
        porcion: 'porción',
        atado: 'atado',
    },
    movement_type: {
        initial: 'Inicial',
        entry: 'Entrada',
        exit: 'Salida',
        adjustment: 'Ajuste',
        waste: 'Merma',
        sale: 'Venta',
        purchase: 'Compra',
        income: 'Ingreso',
        expense: 'Egreso',
    },
    employment_type: {
        full_time: 'Tiempo completo',
        part_time: 'Tiempo parcial',
        temporary: 'Temporal',
        contractor: 'Contratista',
        hourly: 'Por horas',
    },
    backup_kind: {
        manual: 'Manual',
        automatic: 'Automática',
        pre_restore: 'Antes de restaurar',
    },
    order_channel: {
        admin: 'Administración',
        pos: 'Salón / POS',
        web: 'Tienda en línea',
        mobile: 'App Móvil',
    },
    payment_method: {
        cash: 'Efectivo',
        card: 'Tarjeta',
        transfer: 'Transferencia',
        wallet: 'Yape / Plin',
    },
    payment_preference: {
        cash: 'Efectivo',
        card: 'Tarjeta',
        transfer: 'Transferencia',
        wallet: 'Yape / Plin',
    },
    promotion_type: {
        automatic: 'Automática',
        coupon: 'Cupón',
    },
    discount_type: {
        percentage: 'Porcentaje (%)',
        fixed: 'Monto fijo (S/)',
    },
    reward_type: {
        discount_soles: 'Descuento en soles',
        percentage: 'Descuento porcentual',
        free_product: 'Producto de cortesía',
    },
    tier: {
        bronze: 'Bronce',
        silver: 'Plata',
        gold: 'Oro',
        platinum: 'Platino',
    },
    costing_method: {
        average: 'Costo promedio ponderado',
        fifo: 'PEPS (FIFO)',
    },
};

const eventModules = {
    auth: 'Autenticación',
    backup: 'Copia de seguridad',
    cash: 'Caja',
    categories: 'Categorías',
    customers: 'Clientes',
    delivery: 'Entregas',
    ingredients: 'Ingredientes',
    inventory: 'Inventario',
    invoice: 'Comprobante',
    kitchen: 'Cocina',
    loyalty: 'Fidelización',
    orders: 'Pedidos',
    product: 'Producto',
    products: 'Productos',
    product_review: 'Reseña del producto',
    profile: 'Perfil',
    promotion: 'Promoción',
    purchase_order: 'Orden de compra',
    reservation: 'Reserva',
    reservations: 'Reservas',
    retention: 'Retención de datos',
    roles: 'Roles',
    storefront: 'Tienda',
    supplier: 'Proveedor',
    table: 'Mesa',
    users: 'Usuarios',
};

const eventActions = {
    login: 'Inicio de sesión',
    login_failed: 'Inicio de sesión fallido',
    login_blocked: 'Acceso bloqueado',
    logout: 'Cierre de sesión',
    register: 'Registro',
    password_reset_completed: 'Contraseña restablecida',
    password_reset_requested: 'Recuperación de contraseña solicitada',
    created: 'Registro creado',
    create: 'Registro creado',
    updated: 'Registro actualizado',
    update: 'Registro actualizado',
    deleted: 'Registro eliminado',
    downloaded: 'Archivo descargado',
    failed: 'Operación fallida',
    restored: 'Restauración realizada',
    verified: 'Integridad verificada',
    closed: 'Cierre realizado',
    opened: 'Apertura realizada',
    movement_registered: 'Movimiento registrado',
    status_changed: 'Estado actualizado',
    status: 'Estado actualizado',
    address_default: 'Dirección predeterminada actualizada',
    address_status: 'Estado de dirección actualizado',
    details_saved: 'Detalles guardados',
    adjusted: 'Existencias ajustadas',
    costing_method_changed: 'Método de valoración actualizado',
    issued_internal: 'Comprobante emitido',
    voided_internal: 'Comprobante anulado',
    points_adjusted: 'Puntos ajustados',
    redemption_status_changed: 'Estado del canje actualizado',
    reward_redeemed: 'Recompensa canjeada',
    reward_status_changed: 'Estado de recompensa actualizado',
    settings_updated: 'Configuración actualizada',
    cancelled: 'Cancelado',
    customer_cancelled: 'Cancelado por el cliente',
    paid: 'Pago registrado',
    refunded: 'Reembolso realizado',
    moderated: 'Reseña moderada',
    password_change_failed: 'Cambio de contraseña fallido',
    password_changed: 'Contraseña actualizada',
    preferences_updated: 'Preferencias actualizadas',
    marked_sent_manual: 'Envío registrado manualmente',
    received: 'Recepción registrada',
    sent: 'Enviado',
    store_created: 'Reserva creada desde la tienda',
    applied: 'Aplicado',
    favorite_toggled: 'Favoritos actualizados',
    order_created: 'Pedido creado',
    review_submitted: 'Reseña enviada',
};

export function adminText(value, field = '', section = '') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No';
    }
    if (
        [
            'status',
            'effective_status',
            'payment_status',
            'verification_status',
            'operational_status',
            'last_check_status',
            'delivery_status',
        ].includes(field)
    ) {
        return states[value] ?? states[String(value).toLowerCase()] ?? String(value);
    }
    if (field === 'event_code') {
        const [module, ...action] = String(value).split('.');
        return `${eventModules[module] ?? 'Sistema'} · ${eventActions[action.join('.')] ?? 'Actividad registrada'}`;
    }
    if (field === 'category' && section === 'soporte') {
        return (
            {
                technical: 'Problema técnico',
                orders: 'Pedidos y ventas',
                inventory: 'Inventario',
                billing: 'Pagos y comprobantes',
                access: 'Acceso y permisos',
                other: 'Otro',
            }[value] ?? String(value)
        );
    }
    return (
        codedFields[field]?.[value] ??
        codedFields[field]?.[String(value).toLowerCase()] ??
        states[value] ??
        states[String(value).toLowerCase()] ??
        String(value)
    );
}

export function adminModuleTitle(item) {
    return (
        {
            delivery: 'Entregas a domicilio',
            fidelizacion: 'Fidelización',
            auditoria: 'Auditoría',
        }[item.key] ?? item.title
    );
}
