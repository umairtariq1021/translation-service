<?php

namespace Tests\Unit;

use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsTranslationCatalog;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTranslationCatalog;

    private TranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
        $this->service = app(TranslationService::class);
    }

    public function test_create_persists_a_translation_with_tags(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['web', 'mobile'],
        ]);

        $this->assertDatabaseHas('translations', [
            'id' => $translation->id,
            'locale_id' => $this->locale('en')->id,
            'translation_key' => 'home.title',
            'content' => 'Welcome',
        ]);
        $this->assertEqualsCanonicalizing(
            ['web', 'mobile'],
            $translation->tags->pluck('name')->all()
        );
    }

    public function test_create_fails_when_locale_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->create([
            'locale' => 'xx',
            'key' => 'home.title',
            'content' => 'Welcome',
        ]);
    }

    public function test_update_changes_fields_and_replaces_tags(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['web'],
        ]);

        $updated = $this->service->update($translation, [
            'locale' => 'fr',
            'key' => 'home.heading',
            'content' => 'Bienvenue',
            'tags' => ['desktop'],
        ]);

        $this->assertSame('fr', $updated->locale->code);
        $this->assertSame('home.heading', $updated->translation_key);
        $this->assertSame('Bienvenue', $updated->content);
        $this->assertEquals(['desktop'], $updated->tags->pluck('name')->all());
    }

    public function test_update_with_empty_tags_detaches_all_tags(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['web', 'mobile'],
        ]);

        $updated = $this->service->update($translation, [
            'tags' => [],
        ]);

        $this->assertCount(0, $updated->tags);
    }

    public function test_update_without_tags_key_leaves_existing_tags(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['web'],
        ]);

        $updated = $this->service->update($translation, [
            'content' => 'Welcome back',
        ]);

        $this->assertEquals(['web'], $updated->tags->pluck('name')->all());
    }

    public function test_find_returns_translation_with_relations(): void
    {
        $created = $this->service->create([
            'locale' => 'sv',
            'key' => 'auth.login',
            'content' => 'Logga in',
            'tags' => ['mobile'],
        ]);

        $found = $this->service->find($created->id);

        $this->assertTrue($found->relationLoaded('locale'));
        $this->assertTrue($found->relationLoaded('tags'));
        $this->assertSame('sv', $found->locale->code);
        $this->assertEquals(['mobile'], $found->tags->pluck('name')->all());
    }

    public function test_find_fails_for_missing_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->find(999);
    }

    public function test_delete_removes_the_translation(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
        ]);

        $this->service->delete($translation);

        $this->assertDatabaseMissing('translations', [
            'id' => $translation->id,
        ]);
    }

    public function test_list_filters_by_locale_key_search_and_tag(): void
    {
        $web = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome home',
            'tags' => ['web'],
        ]);
        $this->service->create([
            'locale' => 'fr',
            'key' => 'home.title',
            'content' => 'Bienvenue',
            'tags' => ['web'],
        ]);
        $this->service->create([
            'locale' => 'en',
            'key' => 'auth.login',
            'content' => 'Login',
            'tags' => ['mobile'],
        ]);
        $this->service->create([
            'locale' => 'en',
            'key' => 'checkout.title',
            'content' => 'Checkout',
            'tags' => ['desktop'],
        ]);

        $byLocale = $this->service->list(['locale' => 'en']);
        $this->assertSame(3, $byLocale->total());

        $byKey = $this->service->list(['key' => 'home']);
        $this->assertSame(2, $byKey->total());

        $bySearch = $this->service->list(['search' => 'Welcome home']);
        $this->assertSame(1, $bySearch->total());
        $this->assertSame($web->id, $bySearch->items()[0]->id);

        $byTag = $this->service->list(['tag' => 'mobile']);
        $this->assertSame(1, $byTag->total());
        $this->assertSame('auth.login', $byTag->items()[0]->translation_key);
    }

    public function test_list_paginates_results(): void
    {
        foreach (range(1, 5) as $index) {
            $this->service->create([
                'locale' => 'en',
                'key' => "item.{$index}",
                'content' => "Item {$index}",
            ]);
        }

        $page = $this->service->list(['per_page' => 2]);

        $this->assertSame(5, $page->total());
        $this->assertSame(2, $page->perPage());
        $this->assertCount(2, $page->items());
    }

    public function test_export_returns_key_content_map_for_a_locale(): void
    {
        $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
        ]);
        $this->service->create([
            'locale' => 'fr',
            'key' => 'home.title',
            'content' => 'Bienvenue',
        ]);
        $this->service->create([
            'locale' => 'en',
            'key' => 'auth.login',
            'content' => 'Login',
        ]);

        $exported = $this->service->export('en');

        $this->assertSame([
            'home.title' => 'Welcome',
            'auth.login' => 'Login',
        ], $exported);
    }

    public function test_export_fails_when_locale_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->export('xx');
    }

    public function test_create_ignores_unknown_tag_names(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'home.title',
            'content' => 'Welcome',
            'tags' => ['web', 'unknown-tag'],
        ]);

        $this->assertEquals(['web'], $translation->tags->pluck('name')->all());
    }
}
