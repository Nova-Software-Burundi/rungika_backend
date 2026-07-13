<?php

namespace App\Providers;

use App\Models\MoneyTransfer;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use App\Observers\RolePermissionObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('pragmarx.google2fa', function () {
            return new Google2FA();
        });
    }

    public function boot(): void
    {
        Role::observe(RolePermissionObserver::class);
        Permission::observe(RolePermissionObserver::class);

        Relation::morphMap([
            'money-transfer' => MoneyTransfer::class,
        ]);
    }
}
