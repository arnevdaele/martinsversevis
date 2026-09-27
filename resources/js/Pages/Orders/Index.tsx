import { Link } from '@inertiajs/react';
import StatusBadge from '@/Components/StatusBadge';
import PortalLayout from '@/Layouts/PortalLayout';
import { useT } from '@/lib/i18n';
import type { OrderSummary } from '@/types';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    last_page: number;
}

export default function Index({ orders }: { orders: Paginated<OrderSummary> }) {
    const t = useT();

    return (
        <PortalLayout title={t.orders.title}>
            <h1 className="mb-6 text-2xl font-bold tracking-tight">{t.orders.title}</h1>

            {orders.data.length === 0 ? (
                <div className="rounded-xl bg-white px-6 py-12 text-center ring-1 ring-line">
                    <p className="text-slate-600">{t.orders.empty}</p>
                    <Link href="/portal" className="mt-4 inline-block font-semibold text-sea-700 hover:text-sea-900">
                        {t.nav.order} →
                    </Link>
                </div>
            ) : (
                <ul className="divide-y divide-line overflow-hidden rounded-xl bg-white ring-1 ring-line">
                    {orders.data.map((order) => (
                        <li key={order.id}>
                            <Link
                                href={`/portal/bestellingen/${order.id}`}
                                className="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-1 px-4 py-4 hover:bg-slate-50 sm:grid-cols-[9rem_1fr_8rem_7rem_auto]"
                            >
                                <span className="font-semibold text-slate-900">{order.number}</span>
                                <span className="text-right sm:order-last">
                                    <StatusBadge status={order.status} label={order.statusLabel} />
                                </span>
                                <span className="text-sm text-slate-600">
                                    {order.submittedAt}
                                    {order.placedBy && <span className="text-slate-400"> · {order.placedBy}</span>}
                                </span>
                                <span className="text-sm text-slate-600">{order.deliveryDate ?? '—'}</span>
                                <span className="tabular text-right text-sm font-semibold text-slate-900">{order.total}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {orders.last_page > 1 && (
                <nav className="mt-6 flex flex-wrap justify-center gap-1" aria-label={t.common.pagination}>
                    {orders.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                                className={`min-w-10 rounded-md px-3 py-2 text-center text-sm ${
                                    link.active ? 'bg-sea-800 font-semibold text-white' : 'bg-white ring-1 ring-line hover:bg-slate-50'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </nav>
            )}
        </PortalLayout>
    );
}
