<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function csrfCookie(Request $request): JsonResponse
    {
        $request->session()->token();

        return response()->json(['status' => 'ok']);
    }

    public function login(Request $request, RateLimiter $limiter): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $key = Str::lower($email).'|'.$request->ip();

        if ($limiter->tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many sign-in attempts. Try again in '.$limiter->availableIn($key).' seconds.',
            ], 429);
        }

        if (! Auth::guard('web')->attempt([
            'email' => $email,
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            $limiter->hit($key, 60);

            return response()->json(['message' => 'The email or password is incorrect.'], 422);
        }

        $limiter->clear($key);
        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'ok']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($user->fresh())]);
    }

    public function resetUserPassword(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->is_system_admin, 403, 'System administrator access is required.');
        abort_if($request->user()->is($user), 422, 'Use the change-password form to change your own password.');

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $user, $data): void {
            $user->forceFill([
                'password' => $data['password'],
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('account_security_events')->insert([
                'actor_user_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'second_authorized_person' => null,
                'target_user_id' => $user->id,
                'target_name' => $user->name,
                'target_email' => $user->email,
                'event_type' => 'administrator_password_reset',
                'reason' => $data['reason'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['status' => 'ok']);
    }

    private function userPayload(User $user): array
    {
        $today = Institution::localDate();
        $assignments = $user->roleAssignments()
            ->with(['role.permissions', 'organizationalUnit'])
            ->whereHas('role', fn ($query) => $query->where('is_active', true))
            ->whereDate('valid_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
            ->get();

        $permissions = $assignments->flatMap(fn ($assignment) => $assignment->role->permissions->pluck('code'))->unique()->values();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_system_admin' => $user->is_system_admin,
            'must_change_password' => $user->must_change_password,
            'roles' => $assignments->map(fn ($assignment) => [
                'name' => $assignment->role->name,
                'code' => $assignment->role->code,
                'work_scope' => $assignment->work_scope,
                'organizational_unit' => $assignment->organizationalUnit?->name,
            ])->values(),
            'permissions' => $permissions,
            'has_distributions' => \App\Models\DistributionRecipient::query()->where('user_id', $user->id)->exists(),
        ];
    }
}
