/** Placeholder mark until the client's real logo is supplied; swap the SVG, keep the component. */
export default function Logo({ inverted = false }: { inverted?: boolean }) {
    return (
        <span className="inline-flex items-center gap-2.5">
            <svg viewBox="0 0 32 32" className="size-8 shrink-0" aria-hidden="true">
                <rect width="32" height="32" rx="8" fill={inverted ? '#ffffff' : '#0b3347'} />
                <path d="M6 16c3.5-5 8-7 12.5-5.2L24 7v18l-5.5-3.8C14 23 9.5 21 6 16Z" fill={inverted ? '#0f5f7a' : '#7fd1e8'} />
                <circle cx="11" cy="15" r="1.4" fill={inverted ? '#ffffff' : '#0b3347'} />
            </svg>
            <span className={`text-[15px] leading-tight font-semibold tracking-tight ${inverted ? 'text-white' : 'text-sea-900'}`}>
                Martins Verse Vis
            </span>
        </span>
    );
}
