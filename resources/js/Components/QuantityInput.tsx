import { useEffect, useState } from 'react';
import { parseQuantity } from '@/lib/money';

interface Props {
    value: number;
    onChange: (value: number) => void;
    step: number;
    /** The first + jumps straight here, rather than to a quantity the server will refuse. */
    min?: number | null;
    unit: string;
    label: string;
    invalid?: boolean;
}

/**
 * Stepper plus free typing. Typing keeps its own text so "1," can exist
 * mid-keystroke; the number is committed as you go.
 */
export default function QuantityInput({ value, onChange, step, min, unit, label, invalid }: Props) {
    const [text, setText] = useState(value ? String(value).replace('.', ',') : '');

    useEffect(() => {
        if (parseQuantity(text) !== value) {
            setText(value ? String(value).replace('.', ',') : '');
        }
        // Only follow outside changes (stepper, clear, reorder), not our own keystrokes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    const nudge = (direction: 1 | -1) => {
        let next = Math.max(0, Math.round((value + direction * step) * 1000) / 1000);
        if (min && next > 0 && next < min) {
            // Up from nothing lands on the minimum; down from the minimum clears the line.
            next = direction === 1 ? min : 0;
        }
        onChange(next);
    };

    return (
        <div
            className={`flex h-11 items-stretch overflow-hidden rounded-lg border bg-white ${
                invalid ? 'border-red-400' : value > 0 ? 'border-sea-500 ring-2 ring-sea-100' : 'border-slate-300'
            }`}
        >
            <button
                type="button"
                onClick={() => nudge(-1)}
                disabled={value <= 0}
                aria-label={`${label} −`}
                className="w-10 text-lg text-slate-500 hover:bg-slate-50 disabled:text-slate-300"
            >
                −
            </button>
            <label className="flex items-center border-x border-slate-200 px-2">
                <span className="sr-only">{label}</span>
                <input
                    inputMode="decimal"
                    value={text}
                    placeholder="0"
                    onChange={(event) => {
                        const cleaned = event.target.value.replace(/[^0-9.,]/g, '');
                        setText(cleaned);
                        onChange(parseQuantity(cleaned));
                    }}
                    className="tabular w-12 bg-transparent text-right text-[15px] font-semibold text-slate-900 focus:outline-none"
                />
                <span className="ml-1 text-xs text-slate-500">{unit}</span>
            </label>
            <button
                type="button"
                onClick={() => nudge(1)}
                aria-label={`${label} +`}
                className="w-10 text-lg text-sea-700 hover:bg-sea-50"
            >
                +
            </button>
        </div>
    );
}
