import { useCallback, useEffect, useMemo, useState } from 'react';

const STORAGE_KEY = 'desego.cart.v1';
const MAX_PER_PRODUCT = 20;

function buildLineKey(productId, selectedOptions = []) {
    const optIds = (selectedOptions || [])
        .map((o) => (typeof o === 'object' ? Number(o.id) : Number(o)))
        .filter((n) => Number.isInteger(n) && n > 0)
        .sort((a, b) => a - b);
    return `${productId}:${optIds.join(',')}`;
}

/**
 * Carrito persistente en el navegador. Soporta cantidad, notas por ítem y adicionales/opciones configuradas.
 * Los precios base y cargos de opciones siempre se validan contra el catálogo activo.
 */
export function useCart(products) {
    const [lines, setLines] = useState(() => {
        try {
            const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
            if (!Array.isArray(stored)) {
                return [];
            }

            return stored
                .filter(
                    (line) =>
                        Number.isInteger(line.id) &&
                        Number.isInteger(line.quantity) &&
                        line.quantity > 0
                )
                .map((line) => {
                    const selectedOptions = Array.isArray(line.selected_options)
                        ? line.selected_options
                        : [];
                    return {
                        key: line.key || buildLineKey(line.id, selectedOptions),
                        id: line.id,
                        quantity: Math.min(line.quantity, MAX_PER_PRODUCT),
                        notes: typeof line.notes === 'string' ? line.notes.slice(0, 180) : '',
                        selected_options: selectedOptions,
                    };
                });
        } catch {
            return [];
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
        } catch {
            // Almacenamiento privado o lleno
        }
    }, [lines]);

    const items = useMemo(() => {
        const byId = new Map(products.map((product) => [product.id, product]));

        return lines
            .filter((line) => byId.has(line.id))
            .map((line) => {
                const product = byId.get(line.id);
                const validOptionsMap = new Map();
                (product.option_groups || []).forEach((grp) => {
                    (grp.options || []).forEach((opt) => {
                        validOptionsMap.set(Number(opt.id), {
                            id: Number(opt.id),
                            group_name: grp.name,
                            name: opt.name,
                            price_delta: Number(opt.price_delta || 0),
                        });
                    });
                });

                const resolvedOptions = (line.selected_options || [])
                    .map((o) => {
                        const id = typeof o === 'object' ? Number(o.id) : Number(o);
                        return validOptionsMap.get(id) || null;
                    })
                    .filter(Boolean);

                const optionsDelta = resolvedOptions.reduce(
                    (acc, o) => acc + Number(o.price_delta || 0),
                    0
                );
                const unitPrice = Number(product.price) + optionsDelta;

                return {
                    key: line.key || buildLineKey(product.id, resolvedOptions),
                    product,
                    quantity: line.quantity,
                    notes: line.notes || '',
                    selected_options: resolvedOptions,
                    unitPrice,
                    lineTotal: unitPrice * line.quantity,
                };
            });
    }, [lines, products]);

    const add = useCallback((id, amount = 1, selectedOptions = [], notes = '') => {
        const cleanNotes = typeof notes === 'string' ? notes.trim().slice(0, 180) : '';
        const lineKey = buildLineKey(id, selectedOptions);

        setLines((current) => {
            const existing = current.find((line) => (line.key || buildLineKey(line.id, line.selected_options)) === lineKey);

            if (!existing) {
                return [
                    ...current,
                    {
                        key: lineKey,
                        id,
                        quantity: Math.min(Math.max(1, Number(amount) || 1), MAX_PER_PRODUCT),
                        notes: cleanNotes,
                        selected_options: selectedOptions,
                    },
                ];
            }

            return current.map((line) =>
                (line.key || buildLineKey(line.id, line.selected_options)) === lineKey
                    ? {
                          ...line,
                          quantity: Math.min(line.quantity + (Number(amount) || 1), MAX_PER_PRODUCT),
                          notes: cleanNotes || line.notes || '',
                      }
                    : line
            );
        });
    }, []);

    const setQuantity = useCallback((keyOrId, quantity) => {
        setLines((current) =>
            quantity <= 0
                ? current.filter((line) => line.key !== keyOrId && line.id !== keyOrId)
                : current.map((line) =>
                      line.key === keyOrId || line.id === keyOrId
                          ? { ...line, quantity: Math.min(quantity, MAX_PER_PRODUCT) }
                          : line
                  )
        );
    }, []);

    const updateNotes = useCallback((keyOrId, notes) => {
        const clean = typeof notes === 'string' ? notes.slice(0, 180) : '';
        setLines((current) =>
            current.map((line) =>
                line.key === keyOrId || line.id === keyOrId
                    ? { ...line, notes: clean }
                    : line
            )
        );
    }, []);

    const clear = useCallback(() => setLines([]), []);

    const count = items.reduce((total, item) => total + item.quantity, 0);
    const subtotal = items.reduce((total, item) => total + item.lineTotal, 0);

    return {
        items,
        count,
        subtotal,
        add,
        setQuantity,
        updateNotes,
        clear,
        maxPerProduct: MAX_PER_PRODUCT,
    };
}
