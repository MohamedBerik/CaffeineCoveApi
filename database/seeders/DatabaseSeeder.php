<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
        }
    }
}
