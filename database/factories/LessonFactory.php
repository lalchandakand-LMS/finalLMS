<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(3),
            'duration_minutes' => $this->faker->numberBetween(10, 60),
            'order' => $this->faker->numberBetween(1, 12),
            'is_preview' => false,
        ];
    }
}
