import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '@/Components/Button';
import TextField from '@/Components/TextField';
import PortalLayout from '@/Layouts/PortalLayout';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface Props {
    account: { name: string; email: string; receivesOrderConfirmations: boolean; locale: string };
    customer: { name: string; vatNumber: string | null; address: string; phone: string | null };
}

export default function Edit({ account, customer }: Props) {
    const t = useT();
    const { locales } = usePage<SharedProps>().props;
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/portal/account/wachtwoord', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <PortalLayout title={t.account.title}>
            <h1 className="mb-6 text-2xl font-bold tracking-tight">{t.account.title}</h1>

            <div className="grid gap-6 md:grid-cols-2">
                <section className="rounded-xl bg-white p-5 ring-1 ring-line">
                    <h2 className="mb-4 font-semibold">{t.account.details}</h2>
                    <dl className="space-y-3 text-sm">
                        <Row label={t.auth.email} value={account.email} />
                        <Row label={t.account.company} value={customer.name} />
                        {customer.vatNumber && <Row label={t.common.vat} value={customer.vatNumber} />}
                        {customer.address && <Row label={t.common.address} value={customer.address} />}
                    </dl>
                    <p className="mt-4 text-sm text-slate-500">{t.account.contact}</p>

                    <label className="mt-6 flex items-start gap-3 border-t border-line pt-5 text-sm">
                        <input
                            type="checkbox"
                            defaultChecked={account.receivesOrderConfirmations}
                            onChange={(e) =>
                                router.put('/portal/account/voorkeuren', { receives_order_confirmations: e.target.checked }, { preserveScroll: true })
                            }
                            className="mt-0.5 size-4 rounded border-slate-300 text-sea-700 focus:ring-sea-500"
                        />
                        {t.account.confirmations}
                    </label>

                    <label className="mt-5 block text-sm">
                        <span className="mb-1.5 block font-medium text-slate-700">{t.account.language}</span>
                        <select
                            value={account.locale}
                            onChange={(e) => router.put('/portal/account/voorkeuren', { locale: e.target.value }, { preserveScroll: true })}
                            className="block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-[15px] focus:border-sea-500 focus:ring-2 focus:ring-sea-100 focus:outline-none"
                        >
                            {locales.map((option) => (
                                <option key={option.code} value={option.code}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </section>

                <section className="rounded-xl bg-white p-5 ring-1 ring-line">
                    <h2 className="mb-4 font-semibold">{t.account.change_password}</h2>
                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <TextField
                            label={t.account.current_password}
                            type="password"
                            autoComplete="current-password"
                            value={form.data.current_password}
                            onChange={(e) => form.setData('current_password', e.target.value)}
                            error={form.errors.current_password}
                        />
                        <TextField
                            label={t.account.new_password}
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            error={form.errors.password}
                        />
                        <TextField
                            label={t.auth.password_confirmation}
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        />
                        <Button type="submit" disabled={form.processing}>
                            {t.account.save}
                        </Button>
                    </form>
                </section>
            </div>
        </PortalLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-slate-500">{label}</dt>
            <dd className="font-medium text-slate-900">{value}</dd>
        </div>
    );
}
