<?php

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Splicewire\Fda\Connectors\FdaConnector;
use Splicewire\Fda\Requests\GetDrugShortages;

test('GetDrugShortages hits the documented openFDA endpoint with no params by default', function () {
    $mock = new MockClient([
        GetDrugShortages::class => MockResponse::make(['results' => []], 200),
    ]);

    $connector = new FdaConnector;
    $connector->withMockClient($mock);

    $response = $connector->send(new GetDrugShortages);

    $mock->assertSent(function ($request): bool {
        return $request instanceof GetDrugShortages
            && $request->resolveEndpoint() === '/drug/drugshortages.json';
    });

    expect($connector->resolveBaseUrl())->toBe('https://api.fda.gov')
        ->and($response->json())->toBe(['results' => []]);
});

test('GetDrugShortages forwards search, limit, and skip as query params', function () {
    $mock = new MockClient([
        GetDrugShortages::class => MockResponse::make(['results' => []], 200),
    ]);

    $connector = new FdaConnector;
    $connector->withMockClient($mock);

    $connector->send(new GetDrugShortages(search: 'generic_name:tirzepatide', limit: 10, skip: 5));

    $mock->assertSent(function ($request): bool {
        $query = $request->query()->all();

        return $query['search'] === 'generic_name:tirzepatide'
            && $query['limit'] === 10
            && $query['skip'] === 5;
    });
});

test('GetDrugShortages omits query params that were never supplied', function () {
    $mock = new MockClient([
        GetDrugShortages::class => MockResponse::make(['results' => []], 200),
    ]);

    $connector = new FdaConnector;
    $connector->withMockClient($mock);

    $connector->send(new GetDrugShortages);

    $mock->assertSent(function ($request): bool {
        return $request->query()->all() === [];
    });
});

test('FdaConnector sends the expected Accept header', function () {
    $mock = new MockClient([
        GetDrugShortages::class => MockResponse::make(['results' => []], 200),
    ]);

    $connector = new FdaConnector;
    $connector->withMockClient($mock);

    $connector->send(new GetDrugShortages);

    expect($mock->getLastPendingRequest()->headers()->get('Accept'))->toBe('application/json');
});
