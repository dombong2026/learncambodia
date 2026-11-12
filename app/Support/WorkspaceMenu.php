<?php

namespace App\Support;

final class WorkspaceMenu
{
    public static function groups(string $role): array
    {
        return config('workspaces.'.$role, []);
    }

    public static function find(string $role, string $section, string $item): ?array
    {
        foreach (self::groups($role) as $group) {
            if ($group['key'] !== $section) {
                continue;
            }

            foreach ($group['items'] as $entry) {
                if ($entry['key'] === $item) {
                    return $entry + ['section_title' => $group['title']];
                }
            }
        }

        return null;
    }

    public static function href(string $role, array $group, array $item): string
    {
        if (isset($item['route'])) {
            return route($item['route'], $item['parameters'] ?? []);
        }

        $prefix = match ($role) {
            'admin' => 'admin',
            'developer' => 'developer',
            'instructor' => 'instructor',
            default => 'account',
        };

        return route($prefix.'.module', [
            'section' => $group['key'], 'item' => $item['key'],
        ]);
    }

    public static function landing(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.overview'),
            'developer' => route('developer.overview'),
            'instructor' => route('instructor.overview'),
            default => route('account.overview'),
        };
    }
}
