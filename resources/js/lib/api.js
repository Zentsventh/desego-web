let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function setCsrfToken(token) {
    if (token) {
        csrfToken = token;
    }
}

/**
 * Cliente de la API DESEGO (sesión + CSRF). Devuelve siempre el JSON y lanza Error con el mensaje del servidor.
 */
export async function api(path, { method = 'GET', body, query } = {}) {
    const url = new URL(path, window.location.origin);

    Object.entries(query ?? {}).forEach(([key, value]) => url.searchParams.set(key, value));

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    let data = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    if (data?.csrfToken) {
        setCsrfToken(data.csrfToken);
    }

    if (!response.ok || data?.ok === false) {
        const error = new Error(data?.message ?? 'No se pudo completar la solicitud.');
        error.status = response.status;
        throw error;
    }

    return data;
}

const money = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

export const formatMoney = (value) => money.format(Number(value) || 0);

export const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value.replace(' ', 'T'));

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' });
};
