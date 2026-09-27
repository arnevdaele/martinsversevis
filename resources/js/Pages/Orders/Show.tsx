import { Link } from '@inertiajs/react';
import StatusBadge from '@/Components/StatusBadge';
import PortalLayout from '@/Layouts/PortalLayout';
import { trans, useT } from '@/lib/i18n';
import type { OrderSummary } from '@/types';

interface OrderDetail extends OrderSummary {
    customerNote: string | null;
    subtotal: string;
    vatTotal: string;
    hasUnpricedItems: boolean;
    items: { id: number; name: string; note: string | null; quantity: string; unitPrice: string | null; lineTotal: string | null }[];
}

export default function Show({ order }: { order: OrderDetail; justPlaced: boolean }) {
    const t = useT();

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
                <Link
                    href={`/portal?reorder=${order.id}`}
                    className="inline-flex min-h-11 items-center rounded-lg bg-white px-4 text-sm font-semibold text-slate-800 ring-1 ring-line hover:bg-slate-50"
                >
                    {t.order.reorder}
                </Link>
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
                                    <td className="px-4 py-3 text-right whitespace-nowrap">{item.quantity}</td>
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
