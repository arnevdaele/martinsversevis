import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '@/Components/Button';
import TextField from '@/Components/TextField';
import AuthLayout from '@/Layouts/AuthLayout';
import { useT } from '@/lib/i18n';

export default function Login() {
    const t = useT();
    const form = useForm({ email: '', password: '', remember: true });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/portal/login', { onFinish: () => form.reset('password') });
    };

    return (
        <AuthLayout title={t.auth.login_title} intro={t.auth.login_intro}>
            <form onSubmit={submit} className="space-y-5" noValidate>
                <TextField
                    label={t.auth.email}
                    type="email"
                    autoComplete="username"
                    autoFocus
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />
                <TextField
                    label={t.auth.password}
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.remember}
                            onChange={(e) => form.setData('remember', e.target.checked)}
                            className="size-4 rounded border-slate-300 text-sea-700 focus:ring-sea-500"
                        />
                        {t.auth.remember}
                    </label>
                    <Link href="/portal/wachtwoord-vergeten" className="text-sm font-medium text-sea-700 hover:text-sea-900">
                        {t.auth.forgot}
                    </Link>
                </div>
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {t.auth.submit}
                </Button>
            </form>
        </AuthLayout>
    );
}
