<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Backup the database using Laravel';

    public function handle()
    {
        $dbName = config('database.connections.mysql.database');
        $filename = "backup_{$dbName}_" . now()->format('Y-m-d_H-i-s') . ".sql";
        $path = storage_path("app/backups/{$filename}");

        if (!File::exists(dirname($path))) {
            File::makeDirectory(dirname($path), 0755, true);
        }

        try {
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $output = '';

            foreach ($tables as $table) {
                $tableName = $table->{'Tables_in_' . $dbName};

                // Get create table statement
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $output .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $output .= $createTable[0]->{'Create Table'} . ";\n\n";

                // Get table data
                $rows = DB::table($tableName)->get();
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $columns = array_keys($rowArray);
                    $values = array_map(function ($value) {
                        return $value !== null ? "'" . addslashes($value) . "'" : 'NULL';
                    }, array_values($rowArray));

                    $output .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }

            File::put($path, $output);

            $this->info("Backup created: {$filename}");
            Log::info("Database backup created: {$filename}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            Log::error("Database backup failed: " . $e->getMessage());
            return 1;
        }
    }
}
