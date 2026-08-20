import { cn } from '@/lib/utils';

type Props = {
    name: string;
    logoUrl: string | null;
    brandColor: string;
    className?: string;
};

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Logo with a branded initials fallback. Explicit size classes keep CLS at zero
 * (DESIGN §8).
 */
export function CompanyLogo({ name, logoUrl, brandColor, className }: Props) {
    if (logoUrl !== null) {
        return (
            <img
                src={logoUrl}
                alt={`${name} logo`}
                width={48}
                height={48}
                loading="lazy"
                className={cn(
                    'size-12 shrink-0 rounded-lg border bg-white object-contain p-1',
                    className,
                )}
            />
        );
    }

    return (
        <span
            aria-hidden
            style={{ backgroundColor: brandColor }}
            className={cn(
                'flex size-12 shrink-0 items-center justify-center rounded-lg text-sm font-semibold text-white',
                className,
            )}
        >
            {initials(name)}
        </span>
    );
}
