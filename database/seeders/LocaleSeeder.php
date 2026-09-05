<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Locale;


class LocaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Locale::upsert([
            [
                'code' => 'en',
                'name' => 'English',
            ],
            [
                'code' => 'fr',
                'name' => 'French',
            ],
            [
                'code' => 'sv',
                'name' => 'Swedish',
            ],
        ], ['code'], ['name']);
    }
}
