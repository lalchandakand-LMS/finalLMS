<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\ScormPackage;
use App\Services\Scorm\Scorm12PackageValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

final class ScormPackageController extends Controller
{
    public function index(Course $course, Request $request): View
    {
        $this->authorizeCourse($course, $request);
        return view('admin.scorm-packages.index', [
            'course' => $course,
            'packages' => $course->scormPackages()->with('uploader')->latest()->get(),
        ]);
    }

    public function store(Course $course, Request $request, Scorm12PackageValidator $validator): RedirectResponse
    {
        $this->authorizeCourse($course, $request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'package' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ]);

        $file = $data['package'];
        try {
            $manifest = $validator->inspect($file->getRealPath());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['package' => $exception->getMessage()]);
        }

        $path = $file->store('scorm-packages/'.$course->id, 'local');
        if (! $path) {
            return back()->withErrors(['package' => __('The SCORM archive could not be stored securely.')]);
        }

        ScormPackage::query()->create([
            'course_id' => $course->id,
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'] ?: ($manifest['title'] ?: __('SCORM package')),
            'manifest_title' => $manifest['title'] ?: null,
            'entry_point' => $manifest['entry_point'],
            'archive_path' => $path,
            'archive_sha256' => hash_file('sha256', $file->getRealPath()),
            'files' => $manifest['files'],
        ]);

        return back()->with('success', __('SCORM 1.2 package validated and attached to this course.'));
    }

    public function destroy(Course $course, ScormPackage $package, Request $request): RedirectResponse
    {
        $this->authorizeCourse($course, $request);
        abort_unless((int) $package->course_id === (int) $course->id, 404);
        Storage::disk('local')->delete($package->archive_path);
        $package->delete();
        return back()->with('success', __('SCORM package removed.'));
    }

    private function authorizeCourse(Course $course, Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || ($user->isTeacher() && (int) $course->teacher_id === (int) $user->id)), 403);
    }
}
