<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MigrationHelper
{
    /**
     * Check if the table exists and skip creation if it does
     * This is useful when initializing from a SQL dump and then running migrations
     *
     * @param string $tableName
     * @return bool Whether the table already exists
     */
    public static function tableExists(string $tableName): bool
    {
        return Schema::hasTable($tableName);
    }
    
    /**
     * Check if a column exists in a table
     * This is useful for migrations that add or modify columns
     *
     * @param string $tableName
     * @param string $columnName
     * @return bool Whether the column already exists
     */
    public static function columnExists(string $tableName, string $columnName): bool
    {
        return Schema::hasColumn($tableName, $columnName);
    }
    
    /**
     * Check if a foreign key exists on a table
     * 
     * @param string $tableName
     * @param string $foreignKeyName
     * @return bool Whether the foreign key already exists
     */
    public static function foreignKeyExists(string $tableName, string $foreignKeyName): bool
    {
        // Normalize the foreign key name for consistency
        $fullForeignKeyName = $tableName . '_' . $foreignKeyName . '_foreign';
        
        try {
            $foreignKeys = DB::select(
                "SHOW CREATE TABLE {$tableName}"
            );
            
            if (!empty($foreignKeys)) {
                return strpos($foreignKeys[0]->{'Create Table'}, $fullForeignKeyName) !== false;
            }
            
            return false;
        } catch (\Exception $e) {
            // If there's an error querying, assume it doesn't exist
            return false;
        }
    }
}
