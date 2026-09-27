import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

type Params = Record<string, string | number>;

/** Laravel's `:name` placeholders. */
export function trans(line: string, params: Params = {}): string {
    return Object.entries(params).reduce((out, [key, value]) => out.replaceAll(`:${key}`, String(value)), line);
}

/** Laravel's `{1} one|[2,*] many` pluralisation, the subset we use. */
export function choice(line: string, count: number, params: Params = {}): string {
    for (const part of line.split('|')) {
        const match = part.match(/^\s*(\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*(.*)$/s);
        if (!match) continue;
        const [, , exact, from, to, text] = match;
        if (exact !== undefined && Number(exact) === count) return trans(text, { count, ...params });
        if (from !== undefined && count >= Number(from) && (to === '*' || count <= Number(to))) {
            return trans(text, { count, ...params });
        }
    }
    return trans(line, { count, ...params });
}

export function useT() {
    return usePage<SharedProps>().props.t;
}
