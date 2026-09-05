<?php

namespace Database\Factories;

use App\Models\Translation;
use App\Models\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale_id' => Locale::factory(),
            'translation_key' => fake()->unique()->slug(3),
            'content' => fake()->sentence(),
        ];
    }
}
