<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportTranslationRequest;
use App\Http\Requests\StoreTranslationRequest;
use App\Http\Requests\TranslationIndexRequest;
use App\Http\Requests\UpdateTranslationRequest;
use App\Http\Resources\TranslationResource;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TranslationController extends Controller
{
    public function __construct(
        private readonly TranslationService $translationService
    ) {
    }

    public function index(
        TranslationIndexRequest $request
    ): AnonymousResourceCollection {
            $start = microtime(true);

        $translations = $this->translationService->list(
            $request->validated()
        );
                $resourceStart = microtime(true);

       $resource = TranslationResource::collection($translations);

    logger()->info('Performance', [
        'service_ms' => round(
            ($resourceStart - $start) * 1000,
            2
        ),
        'resource_creation_ms' => round(
            (microtime(true) - $resourceStart) * 1000,
            2
        ),
    ]);

    return $resource;
    }

    public function store(
        StoreTranslationRequest $request
    ): TranslationResource {
        $translation = $this->translationService->create(
            $request->validated()
        );

        return new TranslationResource($translation);
    }

    public function show(int $translation): TranslationResource
    {
        $translationModel = $this->translationService->find($translation);

        return new TranslationResource($translationModel);
    }

    public function update(
        UpdateTranslationRequest $request,
        int $translation
    ): TranslationResource {
        $translationModel = $this->translationService->find($translation);

        $translationModel = $this->translationService->update(
            $translationModel,
            $request->validated()
        );

        return new TranslationResource($translationModel);
    }

    public function export(
        ExportTranslationRequest $request
    ): JsonResponse {
        $translations = $this->translationService->export(
            $request->validated('locale')
        );

        return response()->json($translations);
    }

    public function destroy(int $translation): JsonResponse
    {
        $translationModel = $this->translationService->find($translation);

        $this->translationService->delete($translationModel);

        return response()->json(null, 204);
    }
}