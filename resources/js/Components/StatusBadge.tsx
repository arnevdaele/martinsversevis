import type { OrderSummary } from '@/types';

const colors: Record<OrderSummary['status'], string> = {
    new: 'bg-sea-50 text-sea-800 ring-sea-200',
    confirmed: 'bg-amber-50 text-amber-800 ring-amber-200',
    delivered: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    cancelled: 'bg-slate-100 text-slate-600 ring-slate-200',
};

export default function StatusBadge({ status, label }: { status: OrderSummary['status']; label: string }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${colors[status]}`}>
            {label}
        </span>
    );
}
