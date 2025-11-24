<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DriverTest extends TestCase
{
    public function test_driver_name()
    {
        echo "\nDriver Name: " . DB::getDriverName() . "\n";
        echo "Connection Name: " . DB::getDefaultConnection() . "\n";
        echo "Configured Driver: " . config('database.connections.sqlite.driver') . "\n";
        
        $this->assertTrue(true);
    }
}
