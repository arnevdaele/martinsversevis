import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export default function Flash() {
    const { flash } = usePage<SharedProps>().props;

    if (!flash.success && !flash.error) return null;

    return (
        <div
            role="status"
            className={`mb-6 rounded-lg px-4 py-3 text-sm font-medium ${
                flash.error ? 'bg-red-50 text-red-800 ring-1 ring-red-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200'
            }`}
        >
            {flash.error ?? flash.success}
        </div>
    );
}
