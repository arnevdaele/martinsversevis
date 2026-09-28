/** lang/nl/portal.php, as shared by HandleInertiaRequests: section → key → string. */
export type Translations = Record<string, Record<string, string>>;

export interface SharedProps {
    appName: string;
    locale: string;
    locales: { code: string; label: string }[];
    auth: { id: number; name: string; email: string; customer: string } | null;
    flash: { success: string | null; error: string | null };
    t: Translations;
    [key: string]: unknown;
}

/** "Order again": a previous order mapped onto today's items, plus what could not be. */
export interface Reorder {
    lines: Record<number, number>;
    notes: Record<number, string>;
    missing: string[];
}

export interface EditingOrder extends Reorder {
    id: number;
    number: string;
    deliveryDate: string | null;
    customerNote: string | null;
    /** "You can change this until …", or null. */
    until: string | null;
}

export interface CatalogueItem {
    id: number;
    priceListId: number;
    productId: number;
    name: string;
    /** Lower-cased name(s), code and origin for the search box. */
    search: string;
    sku: string | null;
    description: string | null;
    origin: string | null;
    image: string | null;
    category: { id: number; name: string } | null;
    unit: string;
    allowsDecimals: boolean;
    price: number | null;
    priceLabel: string | null;
    vatRate: number;
    minQuantity: number | null;
    note: string | null;
}

export interface OrderSummary {
    id: number;
    number: string;
    status: 'new' | 'confirmed' | 'delivered' | 'cancelled';
    statusLabel: string;
    submittedAt: string | null;
    deliveryDate: string | null;
    total: string;
    itemsCount: number;
    placedBy: string | null;
    /** The customer may still change or cancel it. */
    changeable: boolean;
}

export interface DeliveryOption {
    date: string;
    weekday: string;
    day: string;
    deadline: string;
    extra: boolean;
    reason: string | null;
}

export interface DeliveryInfo {
    hasRules: boolean;
    minDate: string;
    options: DeliveryOption[];
    closures: string[];
    minimum: number | null;
    minimumLabel: string | null;
}
