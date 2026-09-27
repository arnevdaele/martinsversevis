/*
 * Client-side money is only ever an estimate for the basket; the server
 * formats every stored amount. Pinned to the Belgian variant of the portal
 * language, so numbers never follow the visitor's browser settings.
 */
const formats = new Map<string, { euro: Intl.NumberFormat; quantity: Intl.NumberFormat }>();

function formatsFor(locale: string) {
    if (!formats.has(locale)) {
        const tag = `${locale}-BE`;
        formats.set(locale, {
            euro: new Intl.NumberFormat(tag, { style: 'currency', currency: 'EUR' }),
            quantity: new Intl.NumberFormat(tag, { maximumFractionDigits: 3 }),
        });
    }
    return formats.get(locale)!;
}

let current = 'nl';

/** Called by the layout whenever the page's language changes. */
export function setMoneyLocale(locale: string) {
    current = locale;
}

export const formatMoney = (amount: number) => formatsFor(current).euro.format(amount);

export const formatQuantity = (quantity: number, unit: string) => `${formatsFor(current).quantity.format(quantity)} ${unit}`;

/** Accepts "1,5" as well as "1.5": Belgian keyboards type a comma. */
export function parseQuantity(input: string): number {
    const value = Number.parseFloat(input.replace(',', '.'));
    return Number.isFinite(value) && value > 0 ? value : 0;
}
