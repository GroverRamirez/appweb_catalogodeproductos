<?php

namespace App\Support;

use App\Models\User;

class AdminGuard
{
    /**
     * Roles del staff que pueden acceder al panel administrativo.
     *
     * @var list<string>
     */
    public const STAFF_ROLES = ['propietario', 'encargado', 'vendedor'];

    /**
     * Rol súper-administrador, protegido contra borrado/renombrado.
     */
    public const OWNER_ROLE = 'propietario';

    /**
     * Roles del sistema que no pueden borrarse ni renombrarse.
     *
     * @var list<string>
     */
    public const PROTECTED_ROLES = ['propietario', 'cliente'];

    /**
     * Indica si el usuario puede acceder al panel administrativo: cualquier
     * usuario con al menos un permiso (roles del sistema o personalizados).
     * Los clientes, sin permisos, quedan fuera.
     */
    public static function canAccessAdmin(User $user): bool
    {
        return $user->getAllPermissions()->isNotEmpty();
    }

    /**
     * Indica si el usuario es el último con el rol propietario.
     */
    public static function isLastOwner(User $user): bool
    {
        if (! $user->hasRole(self::OWNER_ROLE)) {
            return false;
        }

        return self::ownerCount() <= 1;
    }

    /**
     * Indica si reasignar el usuario a los nuevos roles dejaría al sistema sin propietarios.
     *
     * @param  list<string>  $newRoles
     */
    public static function wouldRemoveLastOwner(User $user, array $newRoles): bool
    {
        $keepsOwner = in_array(self::OWNER_ROLE, $newRoles, true);

        if ($keepsOwner) {
            return false;
        }

        return self::isLastOwner($user);
    }

    /**
     * Cuenta de usuarios con el rol propietario.
     */
    public static function ownerCount(): int
    {
        return User::role(self::OWNER_ROLE)->count();
    }
}
