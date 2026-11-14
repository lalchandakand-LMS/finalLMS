<?php

namespace Tests\Feature;

use App\Models\QuizContest;
use App\Models\QuizContestEntry;
use App\Models\QuizContestQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizContestLotteryTest extends TestCase
{
    use RefreshDatabase;

    public function test_contest_cannot_be_edited_after_start_or_after_an_entry_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $startedContest = $this->makeContest(now()->subMinute(), now()->addHour());

        $this->actingAs($admin)->get(route('admin.contests.edit', $startedContest))->assertStatus(409);
        $this->put(route('admin.contests.update', $startedContest), [
            'title' => 'Attempted late edit',
            'description' => 'Should remain unchanged',
            'prize_description' => 'A book',
            'winner_count' => 1,
            'starts_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'questions' => [
                ['question' => 'Replacement question?', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 0],
            ],
        ])->assertStatus(409);

        $this->assertDatabaseHas('quiz_contests', [
            'id' => $startedContest->id,
            'title' => 'Test quiz',
        ]);

        $futureContest = $this->makeContest(now()->addHour(), now()->addDay());
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        QuizContestEntry::create([
            'quiz_contest_id' => $futureContest->id,
            'user_id' => $student->id,
            'answers' => [$futureContest->questions[0]->id => 1],
            'score' => 1,
            'submitted_at' => now(),
        ]);

        $this->get(route('admin.contests.edit', $futureContest))->assertStatus(409);
    }

    private function makeContest($startsAt = null, $endsAt = null, int $winnerCount = 1): QuizContest
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $contest = QuizContest::create([
            'created_by' => $admin->id,
            'title' => 'Test quiz',
            'description' => 'Test description',
            'prize_description' => 'A book',
            'winner_count' => $winnerCount,
            'starts_at' => $startsAt ?? now()->subMinute(),
            'ends_at' => $endsAt ?? now()->addHour(),
            'status' => 'published',
        ]);
        QuizContestQuestion::create([
            'quiz_contest_id' => $contest->id,
            'question' => 'Correct answer?',
            'options' => ['Wrong', 'Right', 'Wrong too', 'Also wrong'],
            'correct_option' => 1,
            'position' => 1,
        ]);

        return $contest->load('questions');
    }
}
