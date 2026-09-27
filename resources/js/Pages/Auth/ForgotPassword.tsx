import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '@/Components/Button';
import TextField from '@/Components/TextField';
import AuthLayout from '@/Layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function ForgotPassword() {
    const t = useT();
    const form = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/portal/wachtwoord-vergeten');
    };

    return (
        <AuthLayout title={t.auth.forgot_title} intro={t.auth.forgot_intro}>
            <form onSubmit={submit} className="space-y-5" noValidate>
                <TextField
                    label={t.auth.email}
                    type="email"
                    autoComplete="email"
                    autoFocus
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {t.auth.forgot_submit}
                </Button>
                <Link href="/portal/login" className="block text-center text-sm font-medium text-sea-700 hover:text-sea-900">
                    {t.auth.back_to_login}
                </Link>
            </form>
        </AuthLayout>
    );
}
