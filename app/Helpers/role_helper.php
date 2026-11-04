<?php

use Config\Roles;

if (! function_exists('role_config')) {
    function role_config(): Roles
    {
        return config(Roles::class);
    }
}

if (! function_exists('normalize_role')) {
    function normalize_role(?string $role): string
    {
        return strtolower(trim((string) $role));
    }
}

if (! function_exists('format_user_role_label')) {
    function format_user_role_label(?string $role): string
    {
        $normalizedRole = normalize_role($role);

        return match ($normalizedRole) {
            'head_admin' => 'Head Admin',
            'staff' => 'Staff',
            'coordinator' => 'Coordinator',
            'developer', 'system_admin' => 'Developer',
            'alumni' => 'Alumni',
            default => ucwords(str_replace('_', ' ', $normalizedRole)),
        };
    }
}

if (! function_exists('developer_user_role_options')) {
    /**
     * @return list<array{value:string,label:string}>
     */
    function developer_user_role_options(): array
    {
        return [
            ['value' => 'alumni', 'label' => 'Alumni'],
            ['value' => 'head_admin', 'label' => 'Head Admin'],
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'coordinator', 'label' => 'Coordinator'],
            ['value' => 'developer', 'label' => 'Developer'],
        ];
    }
}

if (! function_exists('developer_roles')) {
    /**
     * @return list<string>
     */
    function developer_roles(): array
    {
        return role_config()->developerRoles;
    }
}

if (! function_exists('staff_roles')) {
    /**
     * @return list<string>
     */
    function staff_roles(): array
    {
        return role_config()->staffRoles;
    }
}

if (! function_exists('is_developer_role')) {
    function is_developer_role(?string $role): bool
    {
        return in_array(normalize_role($role), developer_roles(), true);
    }
}

if (! function_exists('is_staff_role')) {
    function is_staff_role(?string $role): bool
    {
        return in_array(normalize_role($role), staff_roles(), true);
    }
}

if (! function_exists('login_redirect_for_role')) {
    function login_redirect_for_role(?string $role): string
    {
        $normalizedRole = normalize_role($role);

        if (is_developer_role($normalizedRole)) {
            return base_url('developer/Dashboard.html');
        }

        if ($normalizedRole === 'head_admin') {
            return base_url('staff/Dashboard.html');
        }

        if ($normalizedRole === 'staff') {
            return base_url('staff/Dashboard.html');
        }

        if ($normalizedRole === 'coordinator') {
            return base_url('coordinator/Dashboard.html');
        }

        return base_url('alumni/Main.html');
    }
}

if (! function_exists('activity_user_type_for_role')) {
    function activity_user_type_for_role(?string $role): string
    {
        $normalizedRole = normalize_role($role);

        if (is_developer_role($normalizedRole)) {
            return 'developer';
        }

        if (is_staff_role($normalizedRole)) {
            return 'staff';
        }

        return 'alumni';
    }
}
