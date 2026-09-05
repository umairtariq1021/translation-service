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
use OpenApi\Attributes as OA;

class TranslationController extends Controller
{
    public function __construct(
        private readonly TranslationService $translationService
    ) {
    }

    #[OA\Get(
        path: '/api/translations',
        summary: 'List translations',
        tags: ['Translations'],
        security: [
            ['bearerAuth' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'locale',
                in: 'query',
                required: false,
                description: 'Locale code',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'en'
                )
            ),
            new OA\Parameter(
                name: 'tag',
                in: 'query',
                required: false,
                description: 'Filter by tag',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'web'
                )
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Search translation key or content',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'welcome'
                )
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    example: 20
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Translations retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
        ]
    )]
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

    #[OA\Post(
        path: '/api/translations',
        summary: 'Create a translation',
        tags: ['Translations'],
        security: [
            ['bearerAuth' => []],
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['locale', 'key', 'content'],
                properties: [
                    new OA\Property(
                        property: 'locale',
                        type: 'string',
                        example: 'en'
                    ),
                    new OA\Property(
                        property: 'key',
                        type: 'string',
                        example: 'home.title'
                    ),
                    new OA\Property(
                        property: 'content',
                        type: 'string',
                        example: 'Welcome Home'
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        items: new OA\Items(
                            type: 'string',
                            example: 'web'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Translation created successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
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