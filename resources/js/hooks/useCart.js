import { useCallback, useEffect, useMemo, useState } from 'react';

const STORAGE_KEY = 'desego.cart.v1';
const MAX_PER_PRODUCT = 20;

/**
 * Carrito persistente en el navegador. Guarda solo { id, quantity }; los precios siempre se leen del catálogo.
 */
export function useCart(products) {
    const [lines, setLines] = useState(() => {
        try {
            const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');

            return Array.isArray(stored) ? stored.filter(line => Number.isInteger(line.id) && Number.isInteger(line.quantity) && line.quantity > 0).map(line => ({ id:line.id, quantity:Math.min(line.quantity, MAX_PER_PRODUCT) })) : [];
        } catch {
            return [];
        }
    });

    useEffect(() => {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(lines)); } catch {}
    }, [lines]);

    const items = useMemo(() => {
        const byId = new Map(products.map((product) => [product.id, product]));

        return lines
            .filter((line) => byId.has(line.id))
            .map((line) => ({ product: byId.get(line.id), quantity: line.quantity }));
    }, [lines, products]);

    const add = useCallback((id, amount = 1) => {
        setLines((current) => {
            const existing = current.find((line) => line.id === id);

            if (!existing) {
                return [...current, { id, quantity: Math.min(amount, MAX_PER_PRODUCT) }];
            }

            return current.map((line) =>
                line.id === id ? { ...line, quantity: Math.min(line.quantity + amount, MAX_PER_PRODUCT) } : line,
            );
        });
    }, []);

    const setQuantity = useCallback((id, quantity) => {
        setLines((current) =>
            quantity <= 0
                ? current.filter((line) => line.id !== id)
                : current.map((line) => (line.id === id ? { ...line, quantity: Math.min(quantity, MAX_PER_PRODUCT) } : line)),
        );
    }, []);

    const clear = useCallback(() => setLines([]), []);

    const count = items.reduce((total, item) => total + item.quantity, 0);
    const subtotal = items.reduce((total, item) => total + item.product.price * item.quantity, 0);

    return { items, count, subtotal, add, setQuantity, clear, maxPerProduct: MAX_PER_PRODUCT };
}

