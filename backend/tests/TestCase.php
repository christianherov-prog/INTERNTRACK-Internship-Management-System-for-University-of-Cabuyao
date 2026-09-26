<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // The production cache is request-lifetime; migrations and earlier tests
        // must not leave stale table-presence answers in the PHPUnit process.
        \App\Support\SchemaCache::flush();
    }
}
