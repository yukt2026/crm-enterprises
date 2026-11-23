<?php

namespace App\Domains\Tenancy\Services;

use App\Domains\Tenancy\Models\Tenant;

class TenantContext
{
    private static ?Tenant $tenant = null;
    private static bool $isPlatformAdmin = false;

    public static function setTenant(?Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function setTenantId(?int $id): void
    {
        if ($id) {
            self::$tenant = Tenant::find($id);
        } else {
            self::$tenant = null;
        }
    }

    public static function getTenant(): ?Tenant
    {
        return self::$tenant;
    }

    public static function getTenantId(): ?int
    {
        return self::$tenant?->id;
    }

    public static function setPlatformAdmin(bool $isPlatformAdmin): void
    {
        self::$isPlatformAdmin = $isPlatformAdmin;
    }

    public static function isPlatformAdmin(): bool
    {
        return self::$isPlatformAdmin;
    }

    public static function clear(): void
    {
        self::$tenant = null;
        self::$isPlatformAdmin = false;
    }
}
