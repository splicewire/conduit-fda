<?php

namespace Splicewire\Fda\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /drug/drugshortages.json — the exact endpoint named in
 * Splicewire\Knowledge\Determining\Concept\Ingestion\OpenFdaShortageSource's own
 * docblock. Supports openFDA's standard query params: `search` (a Lucene-style
 * query, e.g. "generic_name:tirzepatide"), `limit` (max 1000, openFDA default
 * 1), and `skip` (pagination offset, max 25000). All are optional — with none
 * supplied this pulls openFDA's default page of current results.
 */
class GetDrugShortages extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $search = null,
        protected ?int $limit = null,
        protected ?int $skip = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/drug/drugshortages.json';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'search' => $this->search,
            'limit' => $this->limit,
            'skip' => $this->skip,
        ], fn ($value) => $value !== null);
    }
}
