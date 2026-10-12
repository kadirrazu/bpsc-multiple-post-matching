<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->first();
            if (! $user || ! $user->is_active || ! in_array($user->role, [UserRole::Admin, UserRole::Operator], true) || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }
            $user->forceFill(['last_login_at' => now()])->save();

            return $user;
        });
        Fortify::loginView(fn () => view('auth.login'));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(Str::transliterate(Str::lower((string) $r->input('email')).'|'.$r->ip())));
    }
}
