import { useId, type InputHTMLAttributes, type ReactNode } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
    hint?: ReactNode;
}

export default function TextField({ label, error, hint, className = '', ...props }: Props) {
    const id = useId();
    const described = error ? `${id}-error` : hint ? `${id}-hint` : undefined;

    return (
        <div className={className}>
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700">
                {label}
            </label>
            <input
                id={id}
                aria-invalid={error ? true : undefined}
                aria-describedby={described}
                className={`block min-h-11 w-full rounded-lg border bg-white px-3 text-[15px] shadow-xs transition-colors placeholder:text-slate-400 focus:border-sea-500 focus:ring-2 focus:ring-sea-100 focus:outline-none ${
                    error ? 'border-red-400' : 'border-slate-300'
                }`}
                {...props}
            />
            {error ? (
                <p id={`${id}-error`} className="mt-1.5 text-sm text-red-600">
                    {error}
                </p>
            ) : hint ? (
                <p id={`${id}-hint`} className="mt-1.5 text-sm text-slate-500">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}
