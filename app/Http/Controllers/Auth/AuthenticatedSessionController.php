<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AppAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->user()->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        AppAudit::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'user.login',
            'auditable_type' => $request->user()::class,
            'auditable_id' => $request->user()->id,
            'description' => 'Login realizado',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'redirectTo' => route('dashboard'),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json([
                'redirectTo' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }
}
