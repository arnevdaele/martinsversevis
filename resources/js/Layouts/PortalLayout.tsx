import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AccountMenu from '@/Components/AccountMenu';
import Flash from '@/Components/Flash';
import LanguageSwitch from '@/Components/LanguageSwitch';
import Logo from '@/Components/Logo';
import { useT } from '@/lib/i18n';
import { setMoneyLocale } from '@/lib/money';
import type { SharedProps } from '@/types';

/** One width for the bar and every page, so nothing shifts when you switch tabs. */
const container = 'mx-auto max-w-7xl px-4 sm:px-6';

export default function PortalLayout({ title, children }: { title: string; children: ReactNode }) {
    const { locale } = usePage<SharedProps>().props;
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
                <div className={`${container} flex h-16 items-center gap-6`}>
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

                    <div className="ml-auto flex items-center gap-2 sm:gap-3">
                        <LanguageSwitch inverted />
                        <span className="hidden h-6 w-px bg-white/15 sm:block" aria-hidden="true" />
                        <AccountMenu />
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

            <main className={`${container} py-6 sm:py-8`}>
                <Flash />
                {children}
            </main>
        </div>
    );
}
