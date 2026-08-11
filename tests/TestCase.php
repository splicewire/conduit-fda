<?php

namespace Splicewire\Fda\Tests;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Fda\PackageServiceProvider;

/**
 * Base test case — boots a minimal Laravel container (for the fda:sync-shortages
 * command, the rate-limit cache store, and the DB facade) plus an in-memory
 * sqlite `concept_statuses` table shaped to match the documented schema in
 * splicewire/laravel-satellite-knowledge's migration. This package has no
 * dependency on that satellite package (see the package-topology note in
 * composer.json), so the shape is reproduced locally here purely for test
 * isolation — it is not a substitute for that migration in a real host app.
 */
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('concept_statuses', function ($table) {
            $table->uuid('id')->primary();
            $table->string('provider')->default('system');
            $table->string('alias')->default('default');
            $table->string('status_id')->nullable()->index();
            $table->string('concept_id');
            $table->string('jurisdiction');
            $table->string('authority');
            $table->string('classification');

            $table->timestamp('effective_start')->nullable();
            $table->timestamp('effective_end')->nullable();
            $table->timestamp('ingested_at')->nullable();
            $table->timestamp('known_until')->nullable();

            $table->string('supersedes')->nullable()->index();
            $table->string('source')->nullable();
            $table->string('source_url')->nullable();
            $table->text('change_note')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['concept_id', 'jurisdiction']);
            $table->unique(['provider', 'alias', 'status_id']);
            // Matches the dedup key SyncShortages actually upserts on — see the
            // dedup-key note on that command for why `status_id` alone can't do it.
            $table->unique(['provider', 'alias', 'concept_id', 'jurisdiction', 'authority']);
        });
    }
}
