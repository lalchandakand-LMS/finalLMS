<?php

namespace Tests\Feature;

use App\Models\QuizContest;
use App\Models\QuizContestEntry;
use App\Models\QuizContestQuestion;
use App\Models\QuizContestWinner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizContestLotteryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_a_quiz_with_multiple_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.contests.store'), [
            'title' => 'English Challenge',
            'description' => 'A free English quiz.',
            'prize_description' => 'A learning gift pack',
            'winner_count' => 2,
            'starts_at' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'questions' => [
                ['question' => 'Plural of child?', 'options' => ['childs', 'children', 'childes', 'child'], 'correct_option' => 1],
                ['question' => 'Past tense of go?', 'options' => ['goed', 'gone', 'went', 'goes'], 'correct_option' => 2],
            ],
        ])->assertRedirect(route('admin.contests.index'));

        $this->assertDatabaseHas('quiz_contests', ['title' => 'English Challenge', 'status' => 'published']);
        $this->assertDatabaseCount('quiz_contest_questions', 2);
    }

    public function test_authenticated_student_can_submit_answers_only_once(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $contest = $this->makeContest();
        $answers = [$contest->questions[0]->id => 1];

        $this->actingAs($student)->post(route('contests.submit', $contest), ['answers' => $answers])
            ->assertRedirect(route('contests.show', $contest));

        $this->assertDatabaseHas('quiz_contest_entries', [
            'quiz_contest_id' => $contest->id,
            'user_id' => $student->id,
            'score' => 1,
        ]);

        $this->post(route('contests.submit', $contest), ['answers' => $answers])->assertStatus(409);
        $this->assertDatabaseCount('quiz_contest_entries', 1);
    }

    public function test_authenticated_teacher_can_participate_too(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $contest = $this->makeContest();

        $this->actingAs($teacher)->post(route('contests.submit', $contest), [
            'answers' => [$contest->questions[0]->id => 1],
        ])->assertRedirect();

        $this->assertDatabaseHas('quiz_contest_entries', ['user_id' => $teacher->id, 'quiz_contest_id' => $contest->id]);
    }

    public function test_admin_contest_screen_discloses_compliance_restrictions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.contests.index'))
            ->assertOk()
            ->assertSee('Compliance safeguard')
            ->assertSee('Only free-entry, skill-based competitions are enabled.')
            ->assertSee('Random winner selection and paid-entry collection are disabled')
            ->assertSee('Results')
            ->assertDontSee('Lottery');
    }

    public function test_admin_can_publish_skill_based_results_once_after_contest_ends(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $contest = $this->makeContest(now()->subDay(), now()->subMinute(), 2);

        $entries = [];
        foreach ([['score' => 0, 'minutes_ago' => 1], ['score' => 1, 'minutes_ago' => 2], ['score' => 1, 'minutes_ago' => 5]] as $candidate) {
            $user = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
            $entries[] = QuizContestEntry::create([
                'quiz_contest_id' => $contest->id,
                'user_id' => $user->id,
                'answers' => [$contest->questions[0]->id => 1],
                'score' => $candidate['score'],
                'submitted_at' => now()->subMinutes($candidate['minutes_ago']),
            ]);
        }

        $this->actingAs($admin)->post(route('admin.contests.results.publish', $contest))
            ->assertRedirect(route('admin.contests.index'));

        $this->assertDatabaseCount('quiz_contest_winners', 2);
        $this->assertDatabaseHas('quiz_contests', ['id' => $contest->id, 'status' => 'closed']);
        $this->assertDatabaseHas('quiz_contest_winners', ['quiz_contest_id' => $contest->id, 'quiz_contest_entry_id' => $entries[2]->id, 'rank' => 1]);
        $this->assertDatabaseHas('quiz_contest_winners', ['quiz_contest_id' => $contest->id, 'quiz_contest_entry_id' => $entries[1]->id, 'rank' => 2]);
        $this->post(route('admin.contests.results.publish', $contest))->assertStatus(409);
        $this->assertDatabaseCount('quiz_contest_winners', 2);
    }

    public function test_results_cannot_be_published_before_the_contest_deadline(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $contest = $this->makeContest(now()->subMinute(), now()->addHour());
        $user = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        QuizContestEntry::create([
            'quiz_contest_id' => $contest->id,
            'user_id' => $user->id,
            'answers' => [$contest->questions[0]->id => 1],
            'score' => 1,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.contests.results.publish', $contest))->assertStatus(422);
        $this->assertDatabaseCount('quiz_contest_winners', 0);
    }

    public function test_admin_can_pause_and_resume_a_contest_before_deadline(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $contest = $this->makeContest(now()->subMinute(), now()->addHour());

        $this->actingAs($admin)->post(route('admin.contests.status', $contest), ['status' => 'paused'])
            ->assertRedirect(route('admin.contests.index'));
        $this->assertDatabaseHas('quiz_contests', ['id' => $contest->id, 'status' => 'paused']);

        $this->post(route('admin.contests.status', $contest), ['status' => 'published'])
            ->assertRedirect(route('admin.contests.index'));
        $this->assertDatabaseHas('quiz_contests', ['id' => $contest->id, 'status' => 'published']);
    }

    public function test_admin_can_close_only_after_deadline_and_publish_results_from_closed_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $contest = $this->makeContest(now()->subDay(), now()->subMinute());
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        QuizContestEntry::create([
            'quiz_contest_id' => $contest->id,
            'user_id' => $student->id,
            'answers' => [$contest->questions[0]->id => 1],
            'score' => 1,
            'submitted_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($admin)->post(route('admin.contests.status', $contest), ['status' => 'closed'])
            ->assertRedirect(route('admin.contests.index'));
        $this->assertDatabaseHas('quiz_contests', ['id' => $contest->id, 'status' => 'closed']);

        $this->post(route('admin.contests.results.publish', $contest))
            ->assertRedirect(route('admin.contests.index'));
        $this->assertDatabaseHas('quiz_contests', ['id' => $contest->id, 'status' => 'closed']);
        $this->assertDatabaseCount('quiz_contest_winners', 1);
    }

    public function test_admin_cannot_close_a_contest_before_deadline(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $contest = $this->makeContest(now()->subMinute(), now()->addHour());

        $this->actingAs($admin)->post(route('admin.contests.status', $contest), ['status' => 'closed'])
            ->assertStatus(422);
        $this->assertDatabaseHas('quiz_contests', ['id' => $contest->id, 'status' => 'published']);
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
