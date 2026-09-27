import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Flash from '@/Components/Flash';
import LanguageSwitch from '@/Components/LanguageSwitch';
import Logo from '@/Components/Logo';
import { useT } from '@/lib/i18n';

export default function AuthLayout({ title, intro, children }: { title: string; intro?: string; children: ReactNode }) {
    const t = useT();

    return (
        <div className="flex min-h-dvh flex-col bg-sea-900 lg:flex-row">
            <Head title={title} />
            <aside className="relative hidden overflow-hidden lg:flex lg:w-[44%] lg:flex-col lg:justify-between lg:p-12">
                <Logo inverted />
                <Waves />
                <p className="relative max-w-sm text-2xl leading-snug font-semibold text-white">{t.auth.tagline}</p>
            </aside>

            <main className="flex flex-1 items-start justify-center bg-canvas px-4 py-10 sm:items-center lg:rounded-l-3xl">
                <div className="w-full max-w-sm">
                    <div className="mb-8 flex items-center justify-between lg:justify-end">
                        <span className="lg:hidden">
                            <Logo />
                        </span>
                        <LanguageSwitch />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>
                    {intro && <p className="mt-2 text-[15px] text-slate-600">{intro}</p>}
                    <div className="mt-8">
                        <Flash />
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}

function Waves() {
    return (
        <svg className="absolute inset-x-0 bottom-0 h-2/3 w-full" viewBox="0 0 400 300" preserveAspectRatio="none" aria-hidden="true">
            {[0, 1, 2, 3].map((i) => (
                <path
                    key={i}
                    d={`M0 ${120 + i * 45} C 80 ${95 + i * 45}, 150 ${150 + i * 45}, 230 ${120 + i * 45} S 360 ${90 + i * 45}, 400 ${115 + i * 45} V300 H0Z`}
                    fill="#1f86a8"
                    opacity={0.1 + i * 0.07}
                />
            ))}
        </svg>
    );
}
