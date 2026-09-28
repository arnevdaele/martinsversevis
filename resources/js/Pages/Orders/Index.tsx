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
                        <li key={order.id} className="flex items-stretch hover:bg-slate-50">
                            <Link
                                href={`/portal/bestellingen/${order.id}`}
                                className="grid min-w-0 flex-1 grid-cols-[1fr_auto] items-center gap-x-4 gap-y-1 py-4 pl-4 sm:grid-cols-[9rem_1fr_8rem_7rem_auto]"
                            >
                                <span className="font-semibold text-slate-900">{order.number}</span>
                                <span className="text-right sm:order-last">
                                    <StatusBadge status={order.status} label={order.statusLabel} />
                                    {order.changeable && <span className="mt-0.5 block text-xs text-amber-700">{t.orders.changeable}</span>}
                                </span>
                                <span className="text-sm text-slate-600">
                                    {order.submittedAt}
                                    {order.placedBy && <span className="text-slate-400"> · {order.placedBy}</span>}
                                </span>
                                <span className="text-sm text-slate-600">{order.deliveryDate ?? '—'}</span>
                                <span className="tabular text-right text-sm font-semibold text-slate-900">{order.total}</span>
                            </Link>
                            {/* Most weeks look like the last one: straight into the basket from here. */}
                            <Link
                                href={`/portal?reorder=${order.id}`}
                                title={t.order.reorder}
                                aria-label={`${t.order.reorder}: ${order.number}`}
                                className="m-2 flex min-h-11 shrink-0 items-center gap-2 self-center rounded-lg px-3 text-sm font-semibold text-sea-700 hover:bg-sea-50 hover:text-sea-900"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" className="size-5" aria-hidden="true">
                                    <path fillRule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clipRule="evenodd" />
                                </svg>
                                <span className="hidden lg:inline">{t.order.reorder}</span>
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
