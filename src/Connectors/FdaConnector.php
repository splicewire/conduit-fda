<?php

namespace Splicewire\Fda\Connectors;

use Saloon\Http\Connector;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;

/**
 * openFDA connector. No API key is required for the drug shortages endpoint,
 * so this connector doesn't accept or send one. openFDA's published limits for
 * unauthenticated callers are ~40 requests/minute and 1,000/day; the per-minute
 * limit is enforced here (the daily figure is high enough that a manually-run
 * sync command, not a scheduled job — see Commands/SyncShortages — won't
 * realistically approach it in one run).
 */
class FdaConnector extends Connector
{
    use HasRateLimits;

    public function resolveBaseUrl(): string
    {
        return 'https://api.fda.gov';
    }

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(40)->everyMinute(),
        ];
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new LaravelCacheStore(app('cache')->store());
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
