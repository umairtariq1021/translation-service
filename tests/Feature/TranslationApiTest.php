<?php

namespace Tests\Feature;

use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsTranslationCatalog;
use Tests\TestCase;

class TranslationApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTranslationCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
        $this->actingAsApiUser();
    }

    public function test_index_returns_paginated_translations(): void
    {
        Translation::factory()->create([
            'locale_id' => $this->locale('en')->id,
            'translation_key' => 'home.title',
            'content' => 'Welcome',
        ]);

        $this->getJson('/api/translations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'locale',
                        'key',
                        'content',
                        'tags',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_filters_by_locale_key_search_and_tag(): void
    {
        $english = Translation::factory()->create([
            'locale_id' => $this->locale('en')->id,
            'translation_key' => 'home.title',
            'content' => 'Welcome home',
        ]);
        $english->tags()->attach($this->tag('web'));

        Translation::factory()->create([
            'locale_id' => $this->locale('fr')->id,
            'translation_key' => 'home.title',
            'content' => 'Bienvenue',
        ]);

        $this->getJson('/api/translations?locale=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'home.title');

        $this->getJson('/api/translations?key=home')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/translations?search=Welcome%20home')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $english->id);

        $this->getJson('/api/translations?tag=web')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_rejects_invalid_filters(): void
    {
        $this->getJson('/api/translations?locale=xx')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);

        $this->getJson('/api/translations?tag=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag']);

        $this->getJson('/api/translations?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_store_creates_a_translation(): void
    {
        $response = $this->postJson('/api/translations', [
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['web', 'mobile'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.key', 'home.title')
            ->assertJsonPath('data.content', 'Welcome');

        $this->assertEqualsCanonicalizing(
            ['web', 'mobile'],
            $response->json('data.tags')
        );

        $this->assertDatabaseHas('translations', [
            'translation_key' => 'home.title',
            'content' => 'Welcome',
        ]);
    }

    public function test_store_validates_payload(): void
    {
        $this->postJson('/api/translations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale', 'key', 'content']);

        $this->postJson('/api/translations', [
            'locale' => 'xx',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['unknown'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale', 'tags.0']);
    }

    public function test_show_returns_a_translation(): void
    {
        $translation = Translation::factory()->create([
            'locale_id' => $this->locale('fr')->id,
            'translation_key' => 'auth.login',
            'content' => 'Connexion',
        ]);
        $translation->tags()->attach($this->tag('desktop'));

        $this->getJson("/api/translations/{$translation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $translation->id)
            ->assertJsonPath('data.locale', 'fr')
            ->assertJsonPath('data.key', 'auth.login')
            ->assertJsonPath('data.content', 'Connexion')
            ->assertJsonPath('data.tags', ['desktop']);
    }

    public function test_show_returns_not_found_for_missing_translation(): void
    {
        $this->getJson('/api/translations/999')
            ->assertNotFound();
    }

    public function test_update_modifies_a_translation(): void
    {
        $translation = Translation::factory()->create([
            'locale_id' => $this->locale('en')->id,
            'translation_key' => 'home.title',
            'content' => 'Welcome',
        ]);
        $translation->tags()->attach($this->tag('web'));

        $this->putJson("/api/translations/{$translation->id}", [
            'locale' => 'sv',
            'key' => 'home.heading',
            'content' => 'Välkommen',
            'tags' => ['mobile'],
        ])
            ->assertOk()
            ->assertJsonPath('data.locale', 'sv')
            ->assertJsonPath('data.key', 'home.heading')
            ->assertJsonPath('data.content', 'Välkommen')
            ->assertJsonPath('data.tags', ['mobile']);
    }

    public function test_destroy_deletes_a_translation(): void
    {
        $translation = Translation::factory()->create([
            'locale_id' => $this->locale('en')->id,
        ]);

        $this->deleteJson("/api/translations/{$translation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('translations', [
            'id' => $translation->id,
        ]);
    }

    public function test_export_returns_a_locale_map(): void
    {
        Translation::factory()->create([
            'locale_id' => $this->locale('en')->id,
            'translation_key' => 'home.title',
            'content' => 'Welcome',
        ]);
        Translation::factory()->create([
            'locale_id' => $this->locale('fr')->id,
            'translation_key' => 'home.title',
            'content' => 'Bienvenue',
        ]);
        Translation::factory()->create([
            'locale_id' => $this->locale('en')->id,
            'translation_key' => 'auth.login',
            'content' => 'Login',
        ]);

        $this->getJson('/api/translations/export?locale=en')
            ->assertOk()
            ->assertExactJson([
                'home.title' => 'Welcome',
                'auth.login' => 'Login',
            ]);
    }

    public function test_export_requires_a_valid_locale(): void
    {
        $this->getJson('/api/translations/export')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);

        $this->getJson('/api/translations/export?locale=xx')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }
}
