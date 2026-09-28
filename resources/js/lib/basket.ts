import { useCallback, useEffect, useState } from 'react';

/*
 * The basket survives a reload, a lost connection or a phone locking mid-order:
 * it lives in localStorage, keyed per portal login so two people sharing a
 * tablet never see each other's picks. Keys are price list item ids.
 */

export interface Basket {
    lines: Record<number, number>;
    notes: Record<number, string>;
}

const empty: Basket = { lines: {}, notes: {} };

// Changing a placed order gets its own scope, so the basket being built for a new order stays untouched.
function storageKey(userId: number, scope?: string) {
    return scope ? `mvv.basket.${userId}.${scope}` : `mvv.basket.${userId}`;
}

function read(userId: number, scope?: string): Basket {
    try {
        const raw = window.localStorage.getItem(storageKey(userId, scope));
        if (!raw) return empty;
        const parsed = JSON.parse(raw) as Partial<Basket>;
        return { lines: parsed.lines ?? {}, notes: parsed.notes ?? {} };
    } catch {
        return empty;
    }
}

function write(userId: number, basket: Basket, scope?: string) {
    try {
        if (Object.keys(basket.lines).length === 0) {
            window.localStorage.removeItem(storageKey(userId, scope));
        } else {
            window.localStorage.setItem(storageKey(userId, scope), JSON.stringify(basket));
        }
    } catch {
        // Private mode or full storage: the basket just won't survive a reload.
    }
}

/**
 * @param validIds items the customer can order today; anything else is dropped,
 *                 so a product that left a price list never lingers in the basket.
 */
export function useBasket(userId: number, validIds: Set<number>, initial?: Basket | null, scope?: string) {
    const [basket, setBasket] = useState<Basket>(() => {
        const stored = initial ?? read(userId, scope);
        const lines = Object.fromEntries(
            Object.entries(stored.lines)
                .map(([id, qty]) => [Number(id), qty] as const)
                .filter(([id, qty]) => validIds.has(id) && qty > 0),
        );
        const notes = Object.fromEntries(Object.entries(stored.notes).filter(([id]) => Number(id) in lines));
        return { lines, notes };
    });

    useEffect(() => write(userId, basket, scope), [userId, basket, scope]);

    const setQuantity = useCallback((id: number, quantity: number) => {
        setBasket((current) => {
            const lines = { ...current.lines };
            const notes = { ...current.notes };
            if (quantity > 0) {
                lines[id] = quantity;
            } else {
                delete lines[id];
                delete notes[id];
            }
            return { lines, notes };
        });
    }, []);

    const setNote = useCallback((id: number, note: string) => {
        setBasket((current) => ({ ...current, notes: { ...current.notes, [id]: note } }));
    }, []);

    const clear = useCallback(() => setBasket(empty), []);

    return { basket, setQuantity, setNote, clear };
}

export function forgetBasket(userId: number, scope?: string) {
    write(userId, empty, scope);
}

/** What is stored for this scope, or null when nothing is. */
export function storedBasket(userId: number, scope: string): Basket | null {
    const basket = read(userId, scope);
    return Object.keys(basket.lines).length ? basket : null;
}
