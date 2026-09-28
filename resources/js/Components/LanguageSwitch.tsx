import { router, usePage } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

/** "NL · FR": two words everyone recognises, no flags (Belgium has three languages and one flag). */
export default function LanguageSwitch({ inverted = false }: { inverted?: boolean }) {
    const { locale, locales } = usePage<SharedProps>().props;
    const t = useT();

    if (locales.length < 2) return null;

    return (
        <div
            role="group"
            aria-label={t.common.language}
            className={`flex items-center gap-0.5 ${inverted ? 'rounded-lg bg-white/5 p-0.5 ring-1 ring-white/10' : ''}`}
        >
            {locales.map((option) => {
                const active = option.code === locale;
                return (
                    <button
                        key={option.code}
                        type="button"
                        lang={option.code}
                        title={option.label}
                        aria-pressed={active}
                        onClick={() => !active && router.post(`/portal/taal/${option.code}`, {}, { preserveScroll: true })}
                        className={`rounded-md px-2 py-1 text-xs font-semibold tracking-wide uppercase transition-colors ${
                            inverted
                                ? active
                                    ? 'bg-white/15 text-white'
                                    : 'text-sea-100/70 hover:text-white'
                                : active
                                  ? 'bg-sea-100 text-sea-900'
                                  : 'text-slate-500 hover:text-slate-900'
                        }`}
                    >
                        {option.code}
                    </button>
                );
            })}
        </div>
    );
}
