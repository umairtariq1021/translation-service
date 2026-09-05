<?php

namespace Tests\Unit;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsTranslationCatalog;
use Tests\TestCase;

class GenerateTranslationsCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTranslationCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
    }

    public function test_command_rejects_a_count_below_three(): void
    {
        $this->artisan('translations:generate', ['count' => 2])
            ->expectsOutput('Count must be at least 3.')
            ->assertFailed();
    }

    public function test_command_rejects_a_count_not_divisible_by_three(): void
    {
        $this->artisan('translations:generate', ['count' => 4])
            ->expectsOutputToContain('Count must be divisible by 3')
            ->assertFailed();
    }

    public function test_command_fails_when_required_locales_are_missing(): void
    {
        Locale::query()->where('code', 'sv')->delete();

        $this->artisan('translations:generate', ['count' => 3])
            ->expectsOutput('Required locales (en, fr, sv) were not found.')
            ->assertFailed();
    }

    public function test_command_fails_when_required_tags_are_missing(): void
    {
        Tag::query()->where('name', 'web')->delete();

        $this->artisan('translations:generate', ['count' => 3])
            ->expectsOutput('Required tags (mobile, desktop, web) were not found.')
            ->assertFailed();
    }

    public function test_command_creates_the_requested_number_of_rows(): void
    {
        $this->artisan('translations:generate', ['count' => 99])
            ->assertSuccessful();

        $this->assertSame(99, Translation::query()->count());
    }

    public function test_command_creates_translations_for_each_locale(): void
    {
        $this->artisan('translations:generate', ['count' => 6])
            ->assertSuccessful();

        $this->assertSame(6, Translation::query()->count());
        $this->assertSame(2, Translation::query()->distinct()->count('translation_key'));

        foreach (['en', 'fr', 'sv'] as $code) {
            $this->assertSame(
                2,
                Translation::query()
                    ->where('locale_id', $this->locale($code)->id)
                    ->count()
            );
        }

        $this->assertTrue(
            Translation::query()->whereHas('tags')->exists()
        );
    }
}
