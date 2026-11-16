<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class EnsurePluginResourceAccess
{
    public function __construct(private readonly PluginResourceAccess $access) {}

    public function handle(Request $request, Closure $next, string $parameter): mixed
    {
        $actor = $request->user();
        $resource = $request->route($parameter);

        abort_unless($actor instanceof User && $resource instanceof Model, 404, 'Resource not found.');
        $this->access->authorize($actor, $resource);

        return $next($request);
    }
}
