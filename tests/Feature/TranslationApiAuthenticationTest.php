<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_endpoints_require_authentication(): void
    {
        $this->getJson('/api/translations')->assertUnauthorized();
        $this->postJson('/api/translations', [])->assertUnauthorized();
        $this->getJson('/api/translations/1')->assertUnauthorized();
        $this->putJson('/api/translations/1', [])->assertUnauthorized();
        $this->deleteJson('/api/translations/1')->assertUnauthorized();
        $this->getJson('/api/translations/export?locale=en')->assertUnauthorized();
    }
}
