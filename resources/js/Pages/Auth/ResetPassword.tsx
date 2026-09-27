import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '@/Components/Button';
import TextField from '@/Components/TextField';
import AuthLayout from '@/Layouts/AuthLayout';
import { useT } from '@/lib/i18n';

/** Both "forgot password" and "accept invitation" land here. */
export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const t = useT();
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/portal/wachtwoord', { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <AuthLayout title={t.auth.reset_title} intro={t.auth.reset_intro}>
            <form onSubmit={submit} className="space-y-5" noValidate>
                <TextField
                    label={t.auth.email}
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />
                <TextField
                    label={t.auth.password}
                    type="password"
                    autoComplete="new-password"
                    autoFocus
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
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {t.auth.reset_submit}
                </Button>
            </form>
        </AuthLayout>
    );
}
