<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Test dùng Redis thật (DB 14) để bắt lỗi serialize như production; xóa cache giữa các test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
