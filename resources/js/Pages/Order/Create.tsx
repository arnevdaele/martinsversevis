import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import Button from '@/Components/Button';
import QuantityInput from '@/Components/QuantityInput';
import PortalLayout from '@/Layouts/PortalLayout';
import { forgetBasket, storedBasket, useBasket } from '@/lib/basket';
import { choice, trans, useT } from '@/lib/i18n';
import { formatMoney, formatQuantity } from '@/lib/money';
import type { CatalogueItem, DeliveryInfo, DeliveryOption, EditingOrder, Reorder, SharedProps } from '@/types';

interface Props {
    hasLists: boolean;
    items: CatalogueItem[];
    reorder: Reorder | null;
    /** Set when the customer is changing a placed order instead of starting a new one. */
    editing: EditingOrder | null;
    delivery: DeliveryInfo;
}

type Errors = Record<string, string>;

export default function Create({ hasLists, items, reorder, editing, delivery }: Props) {
    const t = useT();
    const { auth } = usePage<SharedProps>().props;
    const userId = auth!.id;

    const byId = useMemo(() => new Map(items.map((item) => [item.id, item])), [items]);
    const validIds = useMemo(() => new Set(items.map((item) => item.id)), [items]);
    const scope = editing ? `order.${editing.id}` : undefined;
    // Changes survive a reload like a new basket does; otherwise start from the order as placed.
    const initial = editing ? (storedBasket(userId, scope!) ?? editing) : reorder;
    const { basket, setQuantity: storeQuantity, setNote, clear } = useBasket(userId, validIds, initial, scope);

    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<number | 'all'>('all');
    const [deliveryDate, setDeliveryDate] = useState(editing?.deliveryDate ?? '');
    const [customerNote, setCustomerNote] = useState(editing?.customerNote ?? '');
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);
    const search = useRef<HTMLInputElement>(null);

    // The reorder is in the basket now; drop it from the address so a reload keeps later edits.
    useEffect(() => {
        if (reorder) router.replace({ url: '/portal', preserveState: true, preserveScroll: true });
    }, [reorder]);

    // "/" (when not already typing) or Ctrl/Cmd+K jumps to the product search.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement;
            const typing = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
            const slash = event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey;
            const modK = event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey);
            if (!slash && !modK) return;
            event.preventDefault();
            search.current?.focus();
            search.current?.select();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    // Touching a line clears the server's complaint about it.
    const setQuantity = (id: number, quantity: number) => {
        storeQuantity(id, quantity);
        setErrors(({ [`lines.${id}`]: _, ...rest }) => rest);
    };

    const categories = useMemo(() => {
        const seen = new Map<number, string>();
        items.forEach((item) => item.category && seen.set(item.category.id, item.category.name));
        return [...seen.entries()].map(([id, name]) => ({ id, name }));
    }, [items]);

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return items.filter(
            (item) =>
                (category === 'all' || item.category?.id === category) &&
                (!needle || item.search.includes(needle)),
        );
    }, [items, query, category]);

    const groups = useMemo(() => {
        const map = new Map<string, CatalogueItem[]>();
        visible.forEach((item) => {
            const key = item.category?.name ?? t.order.uncategorised;
            map.set(key, [...(map.get(key) ?? []), item]);
        });
        return [...map.entries()];
    }, [visible, t]);

    const lines = Object.entries(basket.lines)
        .map(([id, quantity]) => ({ item: byId.get(Number(id))!, quantity }))
        .filter((line) => line.item);

    const totals = lines.reduce(
        (sum, { item, quantity }) => {
            if (item.price === null) return { ...sum, unpriced: true };
            const net = Math.round(item.price * quantity * 100) / 100;
            return { ...sum, subtotal: sum.subtotal + net, vat: sum.vat + Math.round(net * item.vatRate) / 100 };
        },
        { subtotal: 0, vat: 0, unpriced: false },
    );

    const belowMinimum = delivery.minimum !== null && totals.subtotal < delivery.minimum;
    const noDeliveryDays = delivery.hasRules && delivery.options.length === 0;


    const submit = () => {
        setProcessing(true);
        const data = {
            lines: basket.lines,
            notes: basket.notes,
            requested_delivery_date: deliveryDate || null,
            customer_note: customerNote || null,
        };
        router.visit(editing ? `/portal/bestellingen/${editing.id}` : '/portal/bestellingen', {
            method: editing ? 'put' : 'post',
            data,
            preserveScroll: true,
            onSuccess: () => forgetBasket(userId, scope),
            onError: (errs) => {
                setErrors(errs as Errors);
                setSheetOpen(true);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const stopEditing = () => {
        forgetBasket(userId, scope);
        router.visit(`/portal/bestellingen/${editing!.id}`);
    };

    if (!hasLists) {
        return (
            <PortalLayout title={t.order.title}>
                <EmptyState text={t.order.no_lists} />
            </PortalLayout>
        );
    }

    const basketPanel = (
        <BasketPanel
            lines={lines}
            notes={basket.notes}
            totals={totals}
            errors={errors}
            deliveryDate={deliveryDate}
            delivery={delivery}
            belowMinimum={belowMinimum}
            noDeliveryDays={noDeliveryDays}
            customerNote={customerNote}
            processing={processing}
            editing={editing !== null}
            onQuantity={setQuantity}
            onNote={setNote}
            onDeliveryDate={setDeliveryDate}
            onCustomerNote={setCustomerNote}
            onClear={clear}
            onSubmit={submit}
        />
    );

    return (
        <PortalLayout title={t.order.title}>
            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
                <section aria-labelledby="catalogue-title" className="min-w-0">
                    <div className="mb-5">
                        <h1 id="catalogue-title" className="text-2xl font-bold tracking-tight">
                            {t.order.title}
                        </h1>
                        <p className="mt-1 text-[15px] text-slate-600">{t.order.intro}</p>
                        {editing && (
                            <div
                                className="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"
                                role="status"
                            >
                                <div>
                                    <p className="font-semibold">{trans(t.order.editing, { number: editing.number })}</p>
                                    {editing.until && <p className="mt-0.5">{editing.until}</p>}
                                    {editing.missing.length > 0 && (
                                        <p className="mt-1.5">
                                            {choice(t.order.reordered_missing, editing.missing.length, { products: editing.missing.join(', ') })}
                                        </p>
                                    )}
                                </div>
                                <button type="button" onClick={stopEditing} className="font-medium text-amber-900 underline underline-offset-2 hover:text-amber-700">
                                    {t.order.edit_stop}
                                </button>
                            </div>
                        )}
                        {reorder && (Object.keys(reorder.lines).length > 0 || reorder.missing.length > 0) && (
                            <div className="mt-4 rounded-lg bg-sea-50 px-4 py-3 text-sm text-sea-900 ring-1 ring-sea-200" role="status">
                                {Object.keys(reorder.lines).length > 0 && <p>{t.order.reordered}</p>}
                                {reorder.missing.length > 0 && (
                                    <p className={Object.keys(reorder.lines).length > 0 ? 'mt-1.5 text-amber-800' : 'text-amber-800'}>
                                        {choice(t.order.reordered_missing, reorder.missing.length, { products: reorder.missing.join(', ') })}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>


                    <div className="sticky top-[6.5rem] z-20 -mx-4 mb-5 bg-canvas/95 px-4 pb-3 pt-1 backdrop-blur md:top-16 sm:-mx-6 sm:px-6">
                        <div className="relative">
                            <input
                                ref={search}
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key !== 'Escape') return;
                                    if (query) setQuery('');
                                    else e.currentTarget.blur();
                                }}
                                placeholder={t.order.search}
                                aria-label={t.order.search}
                                aria-keyshortcuts="/ Control+K Meta+K"
                                className="peer block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-[15px] shadow-xs focus:border-sea-500 focus:ring-2 focus:ring-sea-100 focus:outline-none md:pr-10"
                            />
                            {!query && (
                                <kbd
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded border border-slate-300 bg-slate-50 px-1.5 font-sans text-xs text-slate-500 peer-focus:hidden md:block"
                                >
                                    /
                                </kbd>
                            )}
                        </div>
                        {categories.length > 1 && (
                            <div className="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none]" role="group" aria-label={t.common.category}>
                                <Chip active={category === 'all'} onClick={() => setCategory('all')}>
                                    {t.order.all_categories}
                                </Chip>
                                {categories.map((cat) => (
                                    <Chip key={cat.id} active={category === cat.id} onClick={() => setCategory(cat.id)}>
                                        {cat.name}
                                    </Chip>
                                ))}
                            </div>
                        )}
                    </div>

                    {groups.length === 0 && <EmptyState text={t.order.no_results} />}

                    <div className="space-y-8 pb-28 lg:pb-0">
                        {groups.map(([name, groupItems]) => (
                            <div key={name}>
                                <h2 className="mb-2 px-1 text-xs font-semibold tracking-wider text-slate-500 uppercase">{name}</h2>
                                <ul className="divide-y divide-line overflow-hidden rounded-xl bg-white ring-1 ring-line">
                                    {groupItems.map((item) => (
                                        <ProductRow
                                            key={item.id}
                                            item={item}
                                            quantity={basket.lines[item.id] ?? 0}
                                            error={errors[`lines.${item.id}`]}
                                            onChange={(qty) => setQuantity(item.id, qty)}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </section>

                <aside className="hidden lg:block" aria-label={t.order.basket}>
                    <div className="sticky top-24">{basketPanel}</div>
                </aside>
            </div>

            {/* Phone: a bar that is always reachable, opening the basket as a sheet. */}
            <div className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden">
                <div className="flex items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <div className="text-sm font-semibold">{lines.length ? choice(t.order.lines, lines.length) : t.order.basket}</div>
                        <div className="tabular text-sm text-slate-600">
                            {formatMoney(totals.subtotal)} {t.common.excl_vat}
                        </div>
                    </div>
                    <Button onClick={() => setSheetOpen(true)} disabled={!lines.length}>
                        {t.order.show_basket}
                    </Button>
                </div>
            </div>

            {sheetOpen && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label={t.order.basket}>
                    <button type="button" className="absolute inset-0 bg-slate-900/40" aria-label={t.common.close} onClick={() => setSheetOpen(false)} />
                    <div className="absolute inset-x-0 bottom-0 max-h-[92dvh] overflow-y-auto rounded-t-2xl bg-canvas p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        <div className="mb-3 flex justify-end">
                            <Button variant="ghost" onClick={() => setSheetOpen(false)}>
                                {t.common.close}
                            </Button>
                        </div>
                        {basketPanel}
                    </div>
                </div>
            )}
        </PortalLayout>
    );
}

function ProductRow({
    item,
    quantity,
    error,
    onChange,
}: {
    item: CatalogueItem;
    quantity: number;
    error?: string;
    onChange: (quantity: number) => void;
}) {
    const t = useT();
    const meta = [
        item.origin && trans(t.order.origin, { origin: item.origin }),
        item.minQuantity && trans(t.order.minimum, { min: formatQuantity(item.minQuantity, item.unit) }),
    ].filter(Boolean);

    return (
        <li className={`flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center ${quantity > 0 ? 'bg-sea-50/50' : ''}`}>
            {item.image && <img src={item.image} alt="" className="hidden size-12 shrink-0 rounded-lg object-cover sm:block" loading="lazy" />}
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-3 sm:block">
                    <p className="font-semibold text-slate-900">{item.name}</p>
                    <p className="tabular shrink-0 text-sm font-semibold text-slate-900 sm:hidden">
                        <Price item={item} />
                    </p>
                </div>
                {meta.length > 0 && <p className="mt-0.5 text-sm text-slate-500">{meta.join(' · ')}</p>}
                {item.note && <p className="mt-0.5 text-sm text-amber-700">{item.note}</p>}
                {error && <p className="mt-1 text-sm font-medium text-red-600">{error}</p>}
            </div>
            <div className="flex items-center justify-end gap-4">
                <p className="tabular hidden w-32 text-right text-sm font-semibold text-slate-900 sm:block">
                    <Price item={item} />
                </p>
                <QuantityInput
                    value={quantity}
                    onChange={onChange}
                    step={item.allowsDecimals ? 0.5 : 1}
                    min={item.minQuantity}
                    unit={item.unit}
                    label={item.name}
                    invalid={Boolean(error)}
                />
            </div>
        </li>
    );
}

function Price({ item }: { item: CatalogueItem }) {
    const t = useT();
    if (item.priceLabel === null) {
        return (
            <span className="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200" title={t.order.day_price_hint}>
                {t.order.day_price}
            </span>
        );
    }
    return (
        <>
            {item.priceLabel} <span className="font-normal text-slate-500">/ {item.unit}</span>
        </>
    );
}

function BasketPanel(props: {
    lines: { item: CatalogueItem; quantity: number }[];
    notes: Record<number, string>;
    totals: { subtotal: number; vat: number; unpriced: boolean };
    errors: Errors;
    deliveryDate: string;
    delivery: DeliveryInfo;
    belowMinimum: boolean;
    noDeliveryDays: boolean;
    customerNote: string;
    processing: boolean;
    editing: boolean;
    onQuantity: (id: number, qty: number) => void;
    onNote: (id: number, note: string) => void;
    onDeliveryDate: (value: string) => void;
    onCustomerNote: (value: string) => void;
    onClear: () => void;
    onSubmit: () => void;
}) {
    const t = useT();
    const { lines, totals, errors } = props;

    return (
        <div className="rounded-xl bg-white ring-1 ring-line">
            <div className="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 className="font-semibold">{t.order.basket}</h2>
                {lines.length > 0 && (
                    <button type="button" onClick={props.onClear} className="text-sm text-slate-500 hover:text-slate-800">
                        {t.order.clear}
                    </button>
                )}
            </div>

            {lines.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-slate-500">{t.order.basket_empty}</p>
            ) : (
                <ul className="max-h-[42vh] divide-y divide-line overflow-y-auto">
                    {lines.map(({ item, quantity }) => (
                        <li key={item.id} className="px-4 py-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold">{item.name}</p>
                                    <p className="tabular text-sm text-slate-600">
                                        {formatQuantity(quantity, item.unit)}
                                        {item.price !== null && ` × ${formatMoney(item.price)}`}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="tabular text-sm font-semibold">
                                        {item.price === null ? t.order.day_price : formatMoney(item.price * quantity)}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => props.onQuantity(item.id, 0)}
                                        className="text-xs text-slate-500 hover:text-red-600"
                                    >
                                        {t.order.remove}
                                    </button>
                                </div>
                            </div>
                            <input
                                type="text"
                                value={props.notes[item.id] ?? ''}
                                onChange={(e) => props.onNote(item.id, e.target.value)}
                                placeholder={t.order.note_placeholder}
                                aria-label={`${item.name}: ${t.order.note_placeholder}`}
                                maxLength={255}
                                className="mt-2 block h-9 w-full rounded-md border border-slate-200 bg-slate-50 px-2.5 text-sm placeholder:text-slate-400 focus:border-sea-500 focus:bg-white focus:outline-none"
                            />
                            {errors[`lines.${item.id}`] && <p className="mt-1 text-sm text-red-600">{errors[`lines.${item.id}`]}</p>}
                        </li>
                    ))}
                </ul>
            )}

            <div className="space-y-4 border-t border-line px-4 py-4">
                {props.delivery.hasRules ? (
                    <DeliveryPicker
                        delivery={props.delivery}
                        value={props.deliveryDate}
                        onChange={props.onDeliveryDate}
                        error={errors.requested_delivery_date}
                    />
                ) : (
                    <label className="block">
                        <span className="mb-1.5 block text-sm font-medium text-slate-700">{t.order.delivery_date}</span>
                        <input
                            type="date"
                            min={props.delivery.minDate}
                            value={props.deliveryDate}
                            onChange={(e) => props.onDeliveryDate(e.target.value)}
                            className="block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-[15px] focus:border-sea-500 focus:ring-2 focus:ring-sea-100 focus:outline-none"
                        />
                        {errors.requested_delivery_date && <span className="mt-1 block text-sm text-red-600">{errors.requested_delivery_date}</span>}
                    </label>
                )}
                <label className="block">
                    <span className="mb-1.5 block text-sm font-medium text-slate-700">{t.order.customer_note}</span>
                    <textarea
                        rows={2}
                        value={props.customerNote}
                        onChange={(e) => props.onCustomerNote(e.target.value)}
                        maxLength={2000}
                        className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[15px] focus:border-sea-500 focus:ring-2 focus:ring-sea-100 focus:outline-none"
                    />
                </label>
            </div>

            <dl className="tabular space-y-1 border-t border-line px-4 py-4 text-sm">
                <div className="flex justify-between text-slate-600">
                    <dt>{t.order.subtotal}</dt>
                    <dd>{formatMoney(totals.subtotal)}</dd>
                </div>
                <div className="flex justify-between text-slate-600">
                    <dt>{t.order.vat}</dt>
                    <dd>{formatMoney(totals.vat)}</dd>
                </div>
                <div className="flex justify-between pt-1 text-base font-bold text-slate-900">
                    <dt>{t.order.total}</dt>
                    <dd>{formatMoney(totals.subtotal + totals.vat)}</dd>
                </div>
                {totals.unpriced && <p className="pt-2 text-xs text-amber-700">{t.order.estimate}</p>}
                {props.delivery.minimumLabel && (
                    <p className={`pt-2 text-xs ${props.belowMinimum && lines.length > 0 ? 'font-medium text-amber-800' : 'text-slate-500'}`}>
                        {props.delivery.minimumLabel}
                        {props.belowMinimum &&
                            lines.length > 0 &&
                            ` ${trans(t.order.order_minimum_remaining, { amount: formatMoney(props.delivery.minimum! - totals.subtotal) })}`}
                    </p>
                )}
            </dl>

            <div className="border-t border-line p-4">
                {errors.order && <p className="mb-3 text-sm text-red-600">{errors.order}</p>}
                {errors.lines && <p className="mb-3 text-sm text-red-600">{errors.lines}</p>}
                <Button
                    className="w-full"
                    onClick={props.onSubmit}
                    disabled={!lines.length || props.processing || props.belowMinimum || props.noDeliveryDays}
                >
                    {props.editing
                        ? props.processing
                            ? t.order.saving
                            : t.order.save
                        : props.processing
                          ? t.order.submitting
                          : t.order.submit}
                </Button>
            </div>
        </div>
    );
}

function Chip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`h-9 shrink-0 rounded-full px-3.5 text-sm font-medium whitespace-nowrap transition-colors ${
                active ? 'bg-sea-800 text-white' : 'bg-white text-slate-700 ring-1 ring-line hover:bg-slate-50'
            }`}
        >
            {children}
        </button>
    );
}

function EmptyState({ text }: { text: string }) {
    return <p className="rounded-xl bg-white px-6 py-12 text-center text-slate-600 ring-1 ring-line">{text}</p>;
}

/**
 * Open delivery days as buttons, each with its own deadline, so nobody has to
 * work out whether Thursday is still possible. A native date input stays in
 * reserve for customers without any delivery rules.
 */
function DeliveryPicker({
    delivery,
    value,
    onChange,
    error,
}: {
    delivery: DeliveryInfo;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const t = useT();
    const [expanded, setExpanded] = useState(false);
    const visible: DeliveryOption[] = expanded ? delivery.options : delivery.options.slice(0, 6);

    return (
        <fieldset>
            <legend className="mb-2 text-sm font-medium text-slate-700">{t.order.delivery_pick}</legend>

            {delivery.closures.map((closure) => (
                <p key={closure} className="mb-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200">
                    {closure}
                </p>
            ))}

            {delivery.options.length === 0 ? (
                <p className="rounded-md bg-slate-50 px-3 py-3 text-sm text-slate-600 ring-1 ring-line">{t.order.delivery_none}</p>
            ) : (
                <div className="grid grid-cols-2 gap-2">
                    {visible.map((option) => {
                        const selected = option.date === value;
                        return (
                            <label
                                key={option.date}
                                className={`relative cursor-pointer rounded-lg px-3 py-2 ring-1 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-sea-500 ${
                                    selected ? 'bg-sea-800 text-white ring-sea-800' : 'bg-white ring-line hover:ring-sea-300'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="delivery_date"
                                    value={option.date}
                                    checked={selected}
                                    onChange={() => onChange(option.date)}
                                    className="sr-only"
                                />
                                <span className="block text-sm font-semibold">
                                    {option.weekday} {option.day}
                                </span>
                                <span className={`block text-xs ${selected ? 'text-sea-100' : 'text-slate-500'}`}>{option.deadline}</span>
                                {option.extra && (
                                    <span
                                        title={option.reason ?? undefined}
                                        className={`mt-1 inline-block rounded px-1.5 text-[11px] font-semibold ${
                                            selected ? 'bg-white/15 text-white' : 'bg-emerald-50 text-emerald-800'
                                        }`}
                                    >
                                        {t.order.delivery_extra}
                                    </span>
                                )}
                            </label>
                        );
                    })}
                </div>
            )}

            {delivery.options.length > 6 && (
                <button
                    type="button"
                    onClick={() => setExpanded((open) => !open)}
                    className="mt-2 text-sm font-medium text-sea-700 hover:text-sea-900"
                >
                    {expanded ? t.order.delivery_less : t.order.delivery_more}
                </button>
            )}

            {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
        </fieldset>
    );
}
