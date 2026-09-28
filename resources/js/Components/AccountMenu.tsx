import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

type Focus = 'first' | 'last';

/**
 * Who is logged in, as one button in the bar; account and log out live behind it.
 * Keyboard follows the usual menu button: arrows and Home/End move between items,
 * Escape closes and returns to the button, Tab closes and moves on.
 */
export default function AccountMenu() {
    const { auth } = usePage<SharedProps>().props;
    const t = useT();
    const [open, setOpen] = useState<Focus | false>(false);
    const root = useRef<HTMLDivElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const menu = useRef<HTMLDivElement>(null);

    const items = () => [...(menu.current?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])];

    useEffect(() => {
        if (!open) return;
        const list = items();
        (open === 'last' ? list.at(-1) : list[0])?.focus();

        const onPointer = (event: PointerEvent) => !root.current?.contains(event.target as Node) && setOpen(false);
        document.addEventListener('pointerdown', onPointer);
        return () => document.removeEventListener('pointerdown', onPointer);
    }, [open]);

    if (!auth) return null;

    const close = (refocus: boolean) => {
        setOpen(false);
        if (refocus) trigger.current?.focus();
    };

    const onTriggerKey = (event: KeyboardEvent) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(event.key === 'ArrowUp' ? 'last' : 'first');
        } else if (event.key === 'Escape' && open) {
            close(true);
        }
    };

    const onMenuKey = (event: KeyboardEvent) => {
        const list = items();
        const current = list.indexOf(document.activeElement as HTMLElement);
        const move = (index: number) => {
            event.preventDefault();
            list[(index + list.length) % list.length]?.focus();
        };

        switch (event.key) {
            case 'ArrowDown':
                return move(current + 1);
            case 'ArrowUp':
                return move(current - 1);
            case 'Home':
                return move(0);
            case 'End':
                return move(list.length - 1);
            case 'Escape':
                event.preventDefault();
                return close(true);
            case 'Tab':
                return close(false);
        }
    };

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
                ref={trigger}
                type="button"
                aria-haspopup="menu"
                aria-expanded={!!open}
                aria-controls="account-menu"
                aria-label={t.common.account_menu}
                onClick={() => setOpen(open ? false : 'first')}
                onKeyDown={onTriggerKey}
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
                <div
                    ref={menu}
                    id="account-menu"
                    role="menu"
                    aria-label={t.common.account_menu}
                    onKeyDown={onMenuKey}
                    className="absolute right-0 mt-2 w-64 overflow-hidden rounded-xl bg-white text-slate-900 shadow-lg ring-1 ring-black/5"
                >
                    <div className="border-b border-line px-4 py-3">
                        <div className="truncate text-sm font-semibold">{auth.customer}</div>
                        <div className="truncate text-xs text-slate-500">{auth.name}</div>
                        <div className="truncate text-xs text-slate-500">{auth.email}</div>
                    </div>
                    <div className="p-1.5">
                        <Link
                            href="/portal/account"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={() => close(false)}
                            className="block rounded-lg px-3 py-2.5 text-sm font-medium outline-none hover:bg-slate-50 focus-visible:bg-slate-100"
                        >
                            {t.nav.account}
                        </Link>
                        <button
                            type="button"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={() => router.post('/portal/logout')}
                            className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-red-700 outline-none hover:bg-red-50 focus-visible:bg-red-50"
                        >
                            {t.nav.logout}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
