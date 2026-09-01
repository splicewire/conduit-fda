<?php

namespace Splicewire\Fda\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rushing\Popcorn\Invocables\LocalInvocable;
use Splicewire\Fda\Connectors\FdaConnector;
use Splicewire\Fda\Requests\GetDrugShortages;
use Splicewire\Knowledge\Determination\Concept\ConceptStatus;
use Splicewire\Knowledge\Determining\Concept\Ingestion\OpenFdaShortageSource;
use Splicewire\Knowledge\Determining\Concept\Ingestion\StatusIngestor;

/**
 * Manually-run sync, matching splicewire/conduit-ecfr's `ecfr:sync` precedent
 * exactly: no Schedule:: entry, no change-feed listener. openFDA gives no push
 * signal for shortage changes, so — like eCFR — this is re-run by hand (or by
 * whatever the host wires up one tier up) whenever fresh data is wanted.
 *
 * Persistence: StatusIngestor::pull() only fetches+parses — it returns
 * ConceptStatus DTOs and never touches a database (by design; see
 * laravel-knowledge-engine's package-topology guard, which forbids an *-engine
 * package from requiring illuminate/database). There is also no DTO->Eloquent
 * hydrator anywhere in the family: the Eloquent side (in
 * splicewire/laravel-satellite-knowledge) only offers model->DTO via
 * ConceptStatus::toData(), never the reverse. Rather than add a dependency on
 * that satellite package from this conduit — which would break the
 * conduit-ecfr convention that a conduit stays persistence-agnostic and never
 * reaches sideways into a host's Eloquent tier — this command upserts directly
 * against the documented `concept_statuses` table shape via the query builder.
 * A host that already depends on laravel-satellite-knowledge (e.g. tower) can
 * freely swap this for its own Eloquent-model persistence one tier up.
 *
 * Dedup key: the table's own unique index is `(provider, alias, status_id)`,
 * but OpenFdaShortageSource never populates `statusId` (openFDA's shortage
 * records carry no stable status identifier), so every row's `status_id` is
 * null — and SQL unique constraints treat NULLs as distinct, meaning that
 * index cannot dedup these rows. This command instead upserts on
 * `(provider, alias, concept_id, jurisdiction, authority)`, which is always
 * populated and is the natural "one current status per drug per authority"
 * key for this source. A host persisting via the Eloquent model would need
 * the same adjustment (or a migration adding a matching unique index) to get
 * real dedup on openFDA rows specifically.
 */
class SyncShortages extends Command
{
    protected $signature = 'fda:sync-shortages {--search=} {--limit=} {--skip=}';

    protected $description = 'Sync openFDA drug shortage statuses into concept_statuses.';

    public function handle(): int
    {
        $connector = new FdaConnector;

        $fetch = new LocalInvocable('fda.drugshortages', function (array $query) use ($connector) {
            $response = $connector->send(new GetDrugShortages(
                search: $query['search'] ?? null,
                limit: isset($query['limit']) ? (int) $query['limit'] : null,
                skip: isset($query['skip']) ? (int) $query['skip'] : null,
            ));

            return $response->json();
        });

        $ingestor = new StatusIngestor($fetch, new OpenFdaShortageSource);

        $statuses = $ingestor->pull(array_filter([
            'search' => $this->option('search'),
            'limit' => $this->option('limit'),
            'skip' => $this->option('skip'),
        ], fn ($value) => $value !== null));

        $this->info('Fetched '.count($statuses).' shortage status rows from openFDA.');

        $saved = $this->persist($statuses);

        $this->info("Persisted {$saved} rows into concept_statuses.");

        return self::SUCCESS;
    }

    /**
     * @param  ConceptStatus[]  $statuses
     */
    protected function persist(array $statuses): int
    {
        $now = now();
        $saved = 0;

        foreach ($statuses as $status) {
            DB::table('concept_statuses')->upsert([
                [
                    'id' => (string) Str::uuid(),
                    'provider' => $status->sourceRef->provider ?? 'system',
                    'alias' => $status->sourceRef->alias ?? 'default',
                    'status_id' => $status->statusId,
                    'concept_id' => $status->conceptId,
                    'jurisdiction' => $status->jurisdiction,
                    'authority' => $status->authority,
                    'classification' => $status->classification,
                    'effective_start' => $status->effectiveStart,
                    'effective_end' => $status->effectiveEnd,
                    'ingested_at' => $status->ingestedAt ?? $now,
                    'known_until' => $status->knownUntil,
                    'supersedes' => $status->supersedes,
                    'source' => $status->source,
                    'source_url' => $status->sourceUrl,
                    'change_note' => $status->changeNote,
                    'status' => $status->status->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['provider', 'alias', 'concept_id', 'jurisdiction', 'authority'], [
                'classification', 'effective_start', 'effective_end', 'ingested_at',
                'known_until', 'supersedes', 'source', 'source_url', 'change_note',
                'status', 'updated_at',
            ]);

            $saved++;
        }

        return $saved;
    }
}
