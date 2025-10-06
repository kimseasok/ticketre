<?php

use Illuminate\Database\ConnectionInterface;

it('returns healthy response', function () {
    config(['cache.stores.redis' => null]);

    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'db' => 'ok',
            'redis' => 'skipped',
        ]);
});

it('handles dependency failure gracefully', function () {
    config(['cache.stores.redis' => ['driver' => 'redis', 'connection' => 'cache']]);

    $failingConnection = \Mockery::mock(ConnectionInterface::class)->shouldIgnoreMissing();
    $failingConnection->shouldReceive('select')->andThrow(new RuntimeException('DB down'));

    app()->instance(ConnectionInterface::class, $failingConnection);

    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(503)
        ->assertJson(['status' => 'fail', 'db' => 'error', 'redis' => 'error']);
});
