<?php

use Illuminate\Support\Facades\DB;

echo "DB_HOST: " . env('DB_HOST') . "<br>";
echo "Testing connection...<br>";

try {
    DB::connection()->getPdo();
    echo "✅ Connected successfully to: " . DB::connection()->getDatabaseName();
} catch (\Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
