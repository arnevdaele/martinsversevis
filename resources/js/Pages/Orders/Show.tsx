import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StatusBadge from '@/Components/StatusBadge';
import PortalLayout from '@/Layouts/PortalLayout';
import { trans, useT } from '@/lib/i18n';
import type { OrderSummary } from '@/types';

interface OrderDetail extends OrderSummary {
    customerNote: string | null;
    subtotal: string;
    vatTotal: string;
    hasUnpricedItems: boolean;
    items: { id: number; name: string; note: string | null; quantity: string; ordered: string | null; unitPrice: string | null; lineTotal: string | null }[];
}

export default function Show({ order, changeUntil }: { order: OrderDetail; changeUntil: string | null; justPlaced: boolean }) {
    const t = useT();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [confirming, setConfirming] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    const cancel = () => {
        setCancelling(true);
        router.post(`/portal/bestellingen/${order.id}/annuleren`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setCancelling(false);
                setConfirming(false);
            },
        });
    };

    return (
        <PortalLayout title={order.number}>
            <Link href="/portal/bestellingen" className="text-sm font-medium text-sea-700 hover:text-sea-900">
                ← {t.orders.back}
            </Link>

            <div className="mt-4 mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">{order.number}</h1>
                        <StatusBadge status={order.status} label={order.statusLabel} />
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                        {order.submittedAt}
                        {order.placedBy && ` · ${trans(t.orders.placed_by, { name: order.placedBy })}`}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {changeUntil && (
                        <Link
                            href={`/portal?edit=${order.id}`}
                            className="inline-flex min-h-11 items-center rounded-lg bg-sea-700 px-4 text-sm font-semibold text-white hover:bg-sea-800"
                        >
                            {t.orders.change}
                        </Link>
                    )}
                <Link
                    href={`/portal?reorder=${order.id}`}
                    className="inline-flex min-h-11 items-center rounded-lg bg-white px-4 text-sm font-semibold text-slate-800 ring-1 ring-line hover:bg-slate-50"
                >
                    {t.order.reorder}
                </Link>
                </div>
            </div>

            <div className="grid items-start gap-6 md:grid-cols-[1fr_280px]">
                <div className="overflow-hidden rounded-xl bg-white ring-1 ring-line">
                    <table className="w-full text-sm">
                        <caption className="sr-only">{t.orders.items}</caption>
                        <thead className="bg-slate-50 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2.5">{t.orders.items}</th>
                                <th scope="col" className="px-4 py-2.5 text-right">#</th>
                                <th scope="col" className="hidden px-4 py-2.5 text-right sm:table-cell">€</th>
                                <th scope="col" className="px-4 py-2.5 text-right">{t.orders.total}</th>
                            </tr>
                        </thead>
                        <tbody className="tabular divide-y divide-line">
                            {order.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-slate-900">{item.name}</div>
                                        {item.note && <div className="text-slate-500">{item.note}</div>}
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        {item.quantity}
                                        {item.ordered && <div className="text-xs text-slate-500">{item.ordered}</div>}
                                    </td>
                                    <td className="hidden px-4 py-3 text-right whitespace-nowrap text-slate-600 sm:table-cell">
                                        {item.unitPrice ?? t.order.day_price}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold whitespace-nowrap">{item.lineTotal ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="space-y-4">
                    {(changeUntil || errors.order) && (
                        <div className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
                            {changeUntil && <p>{changeUntil}</p>}
                            {errors.order && <p className="text-red-700">{errors.order}</p>}
                            {changeUntil &&
                                (confirming ? (
                                    <div className="mt-3" role="group" aria-label={trans(t.orders.cancel_confirm, { number: order.number })}>
                                        <p className="font-semibold">{trans(t.orders.cancel_confirm, { number: order.number })}</p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                onClick={cancel}
                                                disabled={cancelling}
                                                className="inline-flex min-h-10 items-center rounded-lg bg-red-600 px-3 font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                                            >
                                                {t.orders.cancel_yes}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setConfirming(false)}
                                                autoFocus
                                                className="inline-flex min-h-10 items-center rounded-lg bg-white px-3 font-semibold text-slate-800 ring-1 ring-line hover:bg-slate-50"
                                            >
                                                {t.orders.cancel_no}
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => setConfirming(true)}
                                        className="mt-2 font-medium text-red-700 underline underline-offset-2 hover:text-red-800"
                                    >
                                        {t.orders.cancel}
                                    </button>
                                ))}
                        </div>
                    )}
                    <dl className="tabular space-y-1 rounded-xl bg-white p-4 text-sm ring-1 ring-line">
                        <div className="flex justify-between text-slate-600">
                            <dt>{t.order.subtotal}</dt>
                            <dd>{order.subtotal}</dd>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <dt>{t.order.vat}</dt>
                            <dd>{order.vatTotal}</dd>
                        </div>
                        <div className="flex justify-between pt-1 text-base font-bold">
                            <dt>{t.order.total}</dt>
                            <dd>{order.total}</dd>
                        </div>
                        {order.hasUnpricedItems && <p className="pt-2 text-xs text-amber-700">{t.order.estimate}</p>}
                    </dl>

                    <dl className="space-y-3 rounded-xl bg-white p-4 text-sm ring-1 ring-line">
                        <div>
                            <dt className="text-slate-500">{t.orders.delivery}</dt>
                            <dd className="font-medium">{order.deliveryDate ?? '—'}</dd>
                        </div>
                        {order.customerNote && (
                            <div>
                                <dt className="text-slate-500">{t.orders.note}</dt>
                                <dd className="whitespace-pre-line">{order.customerNote}</dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>
        </PortalLayout>
    );
}
