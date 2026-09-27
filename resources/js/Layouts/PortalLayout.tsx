import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Flash from '@/Components/Flash';
import LanguageSwitch from '@/Components/LanguageSwitch';
import Logo from '@/Components/Logo';
import { useT } from '@/lib/i18n';
import { setMoneyLocale } from '@/lib/money';
import type { SharedProps } from '@/types';

export default function PortalLayout({ title, children, wide = false }: { title: string; children: ReactNode; wide?: boolean }) {
    const { auth, locale } = usePage<SharedProps>().props;
    setMoneyLocale(locale);
    const url = usePage().url;
    const t = useT();

    const nav = [
        { href: '/portal', label: t.nav.order, active: url === '/portal' || url.startsWith('/portal?') },
        { href: '/portal/bestellingen', label: t.nav.orders, active: url.startsWith('/portal/bestellingen') },
        { href: '/portal/account', label: t.nav.account, active: url.startsWith('/portal/account') },
    ];

    return (
        <div className="min-h-dvh">
            <Head title={title} />
            <header className="sticky top-0 z-30 border-b border-white/10 bg-sea-900 text-white">
                <div className={`mx-auto flex h-16 items-center gap-6 px-4 sm:px-6 ${wide ? 'max-w-7xl' : 'max-w-5xl'}`}>
                    <Link href="/portal" className="shrink-0">
                        <Logo inverted />
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex" aria-label={t.common.main_menu}>
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={item.active ? 'page' : undefined}
                                className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                    item.active ? 'bg-white/12 text-white' : 'text-sea-100/80 hover:bg-white/8 hover:text-white'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-3">
                        <LanguageSwitch inverted />
                        {auth && (
                            <div className="hidden text-right leading-tight sm:block">
                                <div className="text-sm font-semibold">{auth.customer}</div>
                                <div className="text-xs text-sea-100/70">{auth.name}</div>
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={() => router.post('/portal/logout')}
                            className="rounded-md px-3 py-2 text-sm font-medium text-sea-100/80 hover:bg-white/8 hover:text-white"
                        >
                            {t.nav.logout}
                        </button>
                    </div>
                </div>

                {/* Phone: the three destinations as a segmented row under the bar. */}
                <nav className="grid grid-cols-3 border-t border-white/10 md:hidden" aria-label={t.common.main_menu}>
                    {nav.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={item.active ? 'page' : undefined}
                            className={`border-b-2 py-2.5 text-center text-[13px] font-medium ${
                                item.active ? 'border-sea-300 text-white' : 'border-transparent text-sea-100/70'
                            }`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </header>

            <main className={`mx-auto px-4 py-6 sm:px-6 sm:py-8 ${wide ? 'max-w-7xl' : 'max-w-5xl'}`}>
                <Flash />
                {children}
            </main>
        </div>
    );
}
