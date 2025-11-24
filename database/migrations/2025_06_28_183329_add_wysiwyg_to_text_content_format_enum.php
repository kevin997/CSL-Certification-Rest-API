<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        echo "Driver: " . DB::getDriverName() . "\n";
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Get all existing enum values
        $current = DB::selectOne("SHOW COLUMNS FROM `text_contents` LIKE 'format'");
        preg_match("/^enum\(\'(.+)'\)$/", $current->Type, $matches);
        $values = explode("','", $matches[1]);

        // Add new value
        $values[] = 'wysiwyg';

        $enum = "ENUM('" . implode("','", $values) . "')";

        // Apply new column definition
        DB::statement("ALTER TABLE `text_contents` MODIFY `format` $enum NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Remove 'wysiwyg' by rebuilding enum without it
        $current = DB::selectOne("SHOW COLUMNS FROM `text_contents` LIKE 'format'");
        preg_match("/^enum\(\'(.+)'\)$/", $current->Type, $matches);
        $values = explode("','", $matches[1]);

        // Drop the new value
        $values = array_filter($values, fn($v) => $v !== 'wysiwyg');

        $enum = "ENUM('" . implode("','", $values) . "')";

        DB::statement("ALTER TABLE `text_contents` MODIFY `format` $enum NOT NULL");
    }
};
