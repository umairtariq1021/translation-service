<?php

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $english = Locale::where('code', 'en')->firstOrFail();
        $french = Locale::where('code', 'fr')->firstOrFail();
        $swedish = Locale::where('code', 'sv')->firstOrFail();

        $web = Tag::where('name', 'web')->firstOrFail();
        $mobile = Tag::where('name', 'mobile')->firstOrFail();

        $welcomeEn = Translation::create([
            'locale_id' => $english->id,
            'translation_key' => 'home.welcome',
            'content' => 'Welcome to our application',
        ]);

        $welcomeFr = Translation::create([
            'locale_id' => $french->id,
            'translation_key' => 'home.welcome',
            'content' => 'Bienvenue dans notre application',
        ]);

        $welcomeEs = Translation::create([
            'locale_id' => $swedish->id,
            'translation_key' => 'home.welcome',
            'content' => 'Välkommen till vår applikation',
        ]);

        $welcomeEn->tags()->attach([$web->id, $mobile->id]);
        $welcomeFr->tags()->attach([$web->id]);
        $welcomeEs->tags()->attach([$web->id]);
    }
}