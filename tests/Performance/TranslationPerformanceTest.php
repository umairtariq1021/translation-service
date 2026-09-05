<?php

namespace Tests\Performance;

use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTranslationCatalog;
use Tests\TestCase;

#[Group('performance')]
class TranslationPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTranslationCatalog;

    private const RECORD_COUNT = 100002;

    private const KEYS_PER_LOCALE = 33334;

    private const LIST_QUERY_MAX_MILLISECONDS = 200;

    private const EXPORT_QUERY_MAX_MILLISECONDS = 500;

    private const HTTP_MAX_MILLISECONDS = 2000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
        $this->actingAsApiUser();

        $this->artisan('translations:generate', [
            'count' => self::RECORD_COUNT,
        ])->assertSuccessful();
    }

    public function test_list_and_export_meet_latency_targets_with_100k_rows(): void
    {
        $this->assertSame(
            self::RECORD_COUNT,
            Translation::query()->count()
        );

        $service = app(TranslationService::class);

        $service->list(['per_page' => 20]);
        $service->export('en');

        $listStarted = hrtime(true);
        $page = $service->list(['per_page' => 20]);
        $listQueryMs = (hrtime(true) - $listStarted) / 1e6;

        $this->assertSame(self::RECORD_COUNT, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertLessThan(
            self::LIST_QUERY_MAX_MILLISECONDS,
            $listQueryMs,
            "Translation list query took {$listQueryMs}ms with "
            . self::RECORD_COUNT
            . ' rows (limit '
            . self::LIST_QUERY_MAX_MILLISECONDS
            . 'ms).'
        );

        $exportStarted = hrtime(true);
        $exported = $service->export('en');
        $exportQueryMs = (hrtime(true) - $exportStarted) / 1e6;

        $this->assertCount(self::KEYS_PER_LOCALE, $exported);
        $this->assertLessThan(
            self::EXPORT_QUERY_MAX_MILLISECONDS,
            $exportQueryMs,
            "Translation export query took {$exportQueryMs}ms with "
            . self::KEYS_PER_LOCALE
            . ' English keys (limit '
            . self::EXPORT_QUERY_MAX_MILLISECONDS
            . 'ms).'
        );

        $this->getJson('/api/translations');
        $this->getJson('/api/translations/export?locale=en');

        $httpListStarted = hrtime(true);
        $listResponse = $this->getJson('/api/translations');
        $httpListMs = (hrtime(true) - $httpListStarted) / 1e6;

        $listResponse
            ->assertOk()
            ->assertJsonCount(20, 'data');

        $this->assertLessThan(
            self::HTTP_MAX_MILLISECONDS,
            $httpListMs,
            "GET /api/translations took {$httpListMs}ms (limit "
            . self::HTTP_MAX_MILLISECONDS
            . 'ms).'
        );

        $httpExportStarted = hrtime(true);
        $exportResponse = $this->getJson(
            '/api/translations/export?locale=en'
        );
        $httpExportMs = (hrtime(true) - $httpExportStarted) / 1e6;

        $exportResponse
            ->assertOk()
            ->assertJsonCount(self::KEYS_PER_LOCALE);

        $this->assertLessThan(
            self::HTTP_MAX_MILLISECONDS,
            $httpExportMs,
            "GET /api/translations/export took {$httpExportMs}ms (limit "
            . self::HTTP_MAX_MILLISECONDS
            . 'ms).'
        );
    }
}
