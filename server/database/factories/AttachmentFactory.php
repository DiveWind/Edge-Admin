<?php

namespace Database\Factories;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->lexify('????????').'.png';

        return [
            'disk' => 'public',
            'path' => 'uploads/'.now()->format('Y/m').'/'.fake()->unique()->lexify('????????????????').'.png',
            'name' => $name,
            'mime' => 'image/png',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'uploaded_by' => null,
        ];
    }
}
