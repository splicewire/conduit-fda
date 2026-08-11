<?php

namespace Splicewire\Fda;

use Illuminate\Support\ServiceProvider;
use Splicewire\Fda\Commands\SyncShortages;

class PackageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            SyncShortages::class,
        ]);
    }
}
