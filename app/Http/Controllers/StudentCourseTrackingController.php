<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentCourseAttendance;
use App\Models\StudentCourseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentCourseTrackingController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $sessions = StudentCourseSession::with(['course','batch','attendances.student'])
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('course', fn ($c) => $c->where('teacher_id',$user->id)))
            ->latest('starts_at')->paginate(25);
        $courses = Course::where('status','published')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('teacher_id',$user->id))
            ->orderBy('title')->get();
        return view('admin.course-tracking.index', compact('sessions','courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required','integer',Rule::exists('courses','id')->where('status','published')],
            'batch_id' => ['nullable','integer','exists:batches,id'],
            'title' => ['required','string','max:180'],
            'starts_at' => ['required','date'],
            'ends_at' => ['nullable','date','after:starts_at'],
        ]);
        $course = Course::findOrFail($data['course_id']);
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id), 403);
        if (! empty($data['batch_id'])) {
            $batch = \App\Models\Batch::findOrFail($data['batch_id']);
            abort_unless($batch->course_id === $course->id, 422, __('Selected batch does not belong to this course.'));
            abort_unless($user->isAdmin() || $batch->teacher_id === $user->id, 403);
        }
        StudentCourseSession::create([
            ...$data,
            'created_by' => $user->id,
            'status' => 'scheduled',
        ]);
        return back()->with('success', __('Course session scheduled. Students can check in and check out during the session window.'));
    }

    public function studentIndex(): View
    {
        $student = request()->user();
        $enrolledCourseIds = Enrollment::where('user_id',$student->id)->whereIn('status',['active','completed'])->pluck('course_id');
        $sessions = StudentCourseSession::with(['course','batch','attendances' => fn ($q) => $q->where('student_id',$student->id)])
            ->whereIn('course_id',$enrolledCourseIds)->where('starts_at','>=',now()->subDays(45))
            ->orderByDesc('starts_at')->paginate(30);
        return view('student.course-tracking.index', compact('sessions'));
    }

    public function checkIn(StudentCourseSession $session): RedirectResponse
    {
        $student = request()->user();
        abort_unless($student->isStudent(), 403);
        $enrolled = Enrollment::where('user_id',$student->id)->where('course_id',$session->course_id)->whereIn('status',['active','completed'])->exists();
        abort_unless($enrolled,403);
        abort_if($session->status !== 'scheduled' || now()->lt($session->starts_at->copy()->subMinutes(30)) || ($session->ends_at && now()->gt($session->ends_at)), 422, __('This session is not currently open for check-in.'));
        $attendance = StudentCourseAttendance::firstOrCreate(
            ['session_id'=>$session->id,'student_id'=>$student->id],
            ['checked_in_at'=>now(),'check_in_method'=>'student']
        );
        if (! $attendance->wasRecentlyCreated) {
            return back()->with('info', __('You have already checked in for this session.'));
        }
        return back()->with('success', __('Check-in recorded at :time.', ['time'=>now()->format('H:i')]));
    }

    public function checkOut(StudentCourseSession $session): RedirectResponse
    {
        $student = request()->user();
        abort_unless($student->isStudent(),403);
        $attendance = StudentCourseAttendance::where('session_id',$session->id)->where('student_id',$student->id)->firstOrFail();
        abort_if(! $attendance->checked_in_at || $attendance->checked_out_at,422,__('No open check-in was found for this session.'));
        $attendance->update(['checked_out_at'=>now()]);
        return back()->with('success', __('Check-out recorded at :time.', ['time'=>now()->format('H:i')]));
    }
}
