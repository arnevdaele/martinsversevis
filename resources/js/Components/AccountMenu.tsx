import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

/** Who is logged in, as one button in the bar; account and log out live behind it. */
export default function AccountMenu() {
    const { auth } = usePage<SharedProps>().props;
    const t = useT();
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onPointer = (event: PointerEvent) => !root.current?.contains(event.target as Node) && setOpen(false);
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    if (!auth) return null;

    const initials = auth.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label={t.common.account_menu}
                onClick={() => setOpen(!open)}
                className={`flex items-center gap-2.5 rounded-full py-1 pr-1 pl-1 transition-colors sm:rounded-lg sm:pr-2.5 ${
                    open ? 'bg-white/12' : 'hover:bg-white/8'
                }`}
            >
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-sea-300 text-xs font-bold text-sea-950">
                    {initials}
                </span>
                <span className="hidden max-w-48 text-left leading-tight sm:block">
                    <span className="block truncate text-sm font-semibold text-white">{auth.customer}</span>
                    <span className="block truncate text-xs text-sea-100/70">{auth.name}</span>
                </span>
                <svg viewBox="0 0 20 20" fill="currentColor" className={`hidden size-4 text-sea-100/70 transition-transform sm:block ${open ? 'rotate-180' : ''}`} aria-hidden="true">
                    <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                </svg>
            </button>

            {open && (
                <div role="menu" className="absolute right-0 mt-2 w-64 overflow-hidden rounded-xl bg-white text-slate-900 shadow-lg ring-1 ring-black/5">
                    <div className="border-b border-line px-4 py-3">
                        <div className="truncate text-sm font-semibold">{auth.customer}</div>
                        <div className="truncate text-xs text-slate-500">{auth.name}</div>
                        <div className="truncate text-xs text-slate-500">{auth.email}</div>
                    </div>
                    <div className="p-1.5">
                        <Link
                            href="/portal/account"
                            role="menuitem"
                            onClick={() => setOpen(false)}
                            className="block rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-50"
                        >
                            {t.nav.account}
                        </Link>
                        <button
                            type="button"
                            role="menuitem"
                            onClick={() => router.post('/portal/logout')}
                            className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-red-700 hover:bg-red-50"
                        >
                            {t.nav.logout}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
