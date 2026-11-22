<?php

namespace Tests\\Feature;

use Illuminate\\Support\\Facades\\Route;
use Tests\\TestCase;

class AttendanceRouteRegistrationTest extends TestCase
{
    public function test_each_attendance_route_is_declared_once_and_remains_module_gated(): void
    {
        $routeNames = [
            'student.attendance.index',
            'teacher.batches.attendance.index',
            'teacher.batches.attendance.store',
            'admin.batches.attendance.index',
            'admin.batches.attendance.store',
            'branch.batches.attendance.index',
            'branch.batches.attendance.store',
            'admin.branch-workspace.batches.attendance.index',
            'admin.branch-workspace.batches.attendance.store',
        ];

        $routes = Route::getRoutes();
        $allRoutes = iterator_to_array($routes);

        foreach ($routeNames as $name) {
            $matches = array_values(array_filter(
                $allRoutes,
                static fn ($route): bool => $route->getName() === $name
            ));

            $this->assertCount(1, $matches, "The {$name} route must be registered exactly once.");
            $this->assertContains(
                'module.enabled:attendance',
                $matches[0]->middleware(),
                "The {$name} route must remain behind the attendance module gate."
            );
        }
    }
}
