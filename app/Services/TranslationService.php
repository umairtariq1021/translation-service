<?php

namespace App\Services;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    /**
     * Create a new translation.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Translation
    {
        return DB::transaction(function () use ($data): Translation {
            $locale = $this->findLocaleByCode($data['locale']);

            $translation = Translation::query()->create([
                'locale_id' => $locale->id,
                'translation_key' => $data['key'],
                'content' => $data['content'],
            ]);

            $this->syncTags(
                $translation,
                $data['tags'] ?? []
            );

            return $translation->load([
                'locale',
                'tags',
            ]);
        });
    }

    /**
     * Update an existing translation.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        Translation $translation,
        array $data
    ): Translation {
        return DB::transaction(function () use (
            $translation,
            $data
        ): Translation {
            if (isset($data['locale'])) {
                $locale = $this->findLocaleByCode($data['locale']);

                $translation->locale_id = $locale->id;
            }

            if (isset($data['key'])) {
                $translation->translation_key = $data['key'];
            }

            if (isset($data['content'])) {
                $translation->content = $data['content'];
            }

            $translation->save();

            if (array_key_exists('tags', $data)) {
                $this->syncTags(
                    $translation,
                    $data['tags']
                );
            }

            return $translation->load([
                'locale',
                'tags',
            ]);
        });
    }

    /**
     * Delete a translation.
     */
    public function delete(Translation $translation): void
    {
        DB::transaction(function () use ($translation): void {
            $translation->delete();
        });
    }

    /**
     * Find a translation by ID.
     */
    public function find(int $id): Translation
    {
        return Translation::query()
            ->with([
                'locale',
                'tags',
            ])
            ->findOrFail($id);
    }

    /**
     * Get paginated translations using the supplied filters.
     *
     * @param array<string, mixed> $filters
     */
    public function list(
        array $filters
    ): LengthAwarePaginator {
        $start = microtime(true);

        $query = Translation::query()
            ->select('translations.*')
            ->with([
                'locale:id,code',
                'tags:id,name',
            ]);

        if (!empty($filters['locale'])) {
            $query->where(
                'locale_id',
                $this->findLocaleByCode($filters['locale'])->id
            );
        }

        if (!empty($filters['key'])) {
            $query->where(
                'translation_key',
                'like',
                '%' . $filters['key'] . '%'
            );
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where(
                        'translation_key',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'content',
                        'like',
                        '%' . $search . '%'
                    );
            });
        }

        if (!empty($filters['tag'])) {
            $tagId = Tag::query()
                ->where('name', $filters['tag'])
                ->value('id');

            $query->whereExists(function ($existsQuery) use ($tagId): void {
                $existsQuery
                    ->selectRaw('1')
                    ->from('tag_translation')
                    ->whereColumn(
                        'tag_translation.translation_id',
                        'translations.id'
                    )
                    ->where(
                        'tag_translation.tag_id',
                        $tagId
                    );
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        $total = $this->countForList($query, $filters);

        $result = $query
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', null, $total)
            ->withQueryString();

        logger()->info('Translation list query time', [
            'time_ms' => round(
                (microtime(true) - $start) * 1000,
                2
            ),
        ]);

        return $result;
    }

    /**
     * Export translations for a locale.
     *
     * @return array<string, string>
     */
    public function export(string $locale): array
    {
        $localeModel = $this->findLocaleByCode($locale);

        return DB::table('translations')
            ->where('locale_id', $localeModel->id)
            ->orderBy('id')
            ->pluck(
                'content',
                'translation_key'
            )
            ->all();
    }

    /**
     * Count rows for pagination without wrapping ORDER BY in a subquery.
     *
     * @param array<string, mixed> $filters
     */
    private function countForList($query, array $filters): int
    {
        if (
            empty($filters['locale'])
            && empty($filters['key'])
            && empty($filters['search'])
            && empty($filters['tag'])
        ) {
            return (int) DB::table('translations')->count();
        }

        return (int) (clone $query)
            ->reorder()
            ->toBase()
            ->getCountForPagination();
    }

    /**
     * Find a locale by its code.
     */
    private function findLocaleByCode(string $code): Locale
    {
        return Locale::query()
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Synchronize translation tags.
     *
     * @param array<int, string> $tagNames
     */
    private function syncTags(
        Translation $translation,
        array $tagNames
    ): void {
        if ($tagNames === []) {
            $translation->tags()->detach();

            return;
        }

        $tagIds = Tag::query()
            ->whereIn('name', $tagNames)
            ->pluck('id');

        $translation->tags()->sync($tagIds);
    }
}