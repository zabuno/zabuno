import { Avatar as FlowbiteAvatar } from 'flowbite-react';

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg';

export type AvatarProps = {
    /** Accessible name of the person or entity this avatar represents. */
    name: string;
    src?: string;
    size?: AvatarSize;
    className?: string;
};

function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0]!.slice(0, 2).toUpperCase();
    return `${parts[0]![0]}${parts[parts.length - 1]![0]}`.toUpperCase();
}

/**
 * Micro building block: a person/entity avatar. Falls back to initials when
 * no image is provided or the image fails to load — Flowbite's own fallback.
 */
export function Avatar({ name, src, size = 'md', className }: AvatarProps) {
    return (
        <FlowbiteAvatar
            img={src}
            alt={name}
            placeholderInitials={initialsOf(name)}
            size={size}
            rounded
            className={className}
        />
    );
}
