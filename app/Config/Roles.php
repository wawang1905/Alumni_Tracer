<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Roles extends BaseConfig
{
    /**
     * Roles that can access the developer console.
     *
     * @var list<string>
     */
    public array $developerRoles = ['developer', 'system_admin'];

    /**
     * Roles that are redirected to the staff dashboard after login.
     *
     * @var list<string>
     */
    public array $staffRoles = ['head_admin', 'staff'];

    /**
     * Default role assigned during alumni signup.
     */
    public string $defaultRole = 'alumni';
}
