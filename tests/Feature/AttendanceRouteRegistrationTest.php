<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AttendanceRouteRegistrationTest extends TestCase
{

    public function test_each_attendance_route_is_declared_once_and_remains_module_gated(): void
    {
        $source = file_get_contents(base_path('routes/web.php'));

        $routeNames = [
            'student.attendance.index',
            'teacher.batches.attendance.index',
            'teacher.batches.attendance.store',
            'admin.batches.attendance.index',
            'admin.batches.attendance.store',
            'admin.branch-workspace.batches.attendance.index',
            'admin.branch-workspace.batches.attendance.store',
        ];

        $this->assertSame(1, substr_count($source, "->name('batches.attendance.index')"));
        $this->assertSame(1, substr_count($source, "->name('batches.attendance.store')"));

        $routes = Route::getRoutes();

        foreach ($routeNames as $name) {
            $this->assertSame(
                1,
                substr_count($source, "->name('".$name."')"),
                "The {$name} route must be declared exactly once."
            );

            $route = $routes->getByName($name);
            $this->assertNotNull($route, "The {$name} route must exist.");
            $this->assertContains(
                'module.enabled:attendance',
                $route->middleware(),
                "The {$name} route must remain behind the attendance module gate."
            );
        }
    }
}
