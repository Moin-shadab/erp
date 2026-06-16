<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect('/login');
        }

        $roleSlug = DB::table('roles')->where('id', $user->role_id)->value('slug');
        if (!in_array($roleSlug, ['super-admin', 'admin'])) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'Unauthorized action.'], 403);
            }
            return redirect('/')->with('error', 'Unauthorized action.');
        }

        return $next($request);
    }
}
