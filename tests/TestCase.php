<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setting::$memo is a request-lifetime cache. In production the process
        // ends with the request, but the whole suite shares one process, so a
        // setting written by one test would otherwise leak into the next.
        Setting::flushMemo();
    }
}
