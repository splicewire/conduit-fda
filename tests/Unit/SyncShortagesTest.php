<?php

use Illuminate\Support\Facades\DB;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Splicewire\Fda\Requests\GetDrugShortages;

test('fda:sync-shortages parses a mocked openFDA response into concept_statuses rows', function () {
    MockClient::destroyGlobal();
    MockClient::global([
        GetDrugShortages::class => MockResponse::make([
            'results' => [
                [
                    'generic_name' => 'Tirzepatide',
                    'status' => 'Resolved',
                    'update_date' => '2024-12-19',
                ],
                [
                    'generic_name' => 'Semaglutide',
                    'status' => 'Current',
                    'update_date' => '2025-01-10',
                ],
            ],
        ], 200),
    ]);

    $this->artisan('fda:sync-shortages')->assertSuccessful();

    expect(DB::table('concept_statuses')->count())->toBe(2);

    $resolved = DB::table('concept_statuses')->where('concept_id', 'Tirzepatide')->first();
    expect($resolved->authority)->toBe('FDA')
        ->and($resolved->jurisdiction)->toBe('US-FDA')
        ->and($resolved->classification)->toBe('fda_shortage_resolved')
        ->and($resolved->source)->toBe('openFDA drug shortages')
        ->and($resolved->source_url)->toBe('https://api.fda.gov/drug/drugshortages.json')
        ->and($resolved->status)->toBe('active');

    $current = DB::table('concept_statuses')->where('concept_id', 'Semaglutide')->first();
    expect($current->classification)->toBe('fda_in_shortage');

    MockClient::destroyGlobal();
});

test('fda:sync-shortages passes --search, --limit, and --skip through to the request', function () {
    MockClient::destroyGlobal();
    $mock = MockClient::global([
        GetDrugShortages::class => MockResponse::make(['results' => []], 200),
    ]);

    $this->artisan('fda:sync-shortages', [
        '--search' => 'generic_name:tirzepatide',
        '--limit' => '5',
        '--skip' => '0',
    ])->assertSuccessful();

    $mock->assertSent(function ($request): bool {
        $query = $request->query()->all();

        return $query['search'] === 'generic_name:tirzepatide'
            && $query['limit'] === 5
            && $query['skip'] === 0;
    });

    MockClient::destroyGlobal();
});

test('fda:sync-shortages upserts on re-run instead of duplicating rows', function () {
    MockClient::destroyGlobal();
    MockClient::global([
        GetDrugShortages::class => MockResponse::make([
            'results' => [
                ['generic_name' => 'Tirzepatide', 'status' => 'Resolved', 'update_date' => '2024-12-19'],
            ],
        ], 200),
    ]);

    $this->artisan('fda:sync-shortages')->assertSuccessful();
    $this->artisan('fda:sync-shortages')->assertSuccessful();

    expect(DB::table('concept_statuses')->count())->toBe(1);

    MockClient::destroyGlobal();
});
