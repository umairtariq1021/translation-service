<?php

namespace Tests\Concerns;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\LocaleSeeder;
use Database\Seeders\TagSeeder;

trait SeedsTranslationCatalog
{
    protected function seedCatalog(): void
    {
        $this->seed([
            LocaleSeeder::class,
            TagSeeder::class,
        ]);
    }

    protected function locale(string $code = 'en'): Locale
    {
        return Locale::query()
            ->where('code', $code)
            ->firstOrFail();
    }

    protected function tag(string $name = 'web'): Tag
    {
        return Tag::query()
            ->where('name', $name)
            ->firstOrFail();
    }

    protected function actingAsApiUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
