<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\OrganizationalUnit;
use App\Models\Institution;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserAdministrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAdministrator($request);

        return response()->json([
            'users' => User::with(['roleAssignments.role', 'roleAssignments.organizationalUnit'])->orderBy('name')->get()
                ->map(fn (User $user) => $this->userPayload($user)),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'organizational_units' => OrganizationalUnit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit_type']),
            'effective_date' => Institution::localDate(),
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->requireAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
            'is_system_admin' => false,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        return response()->json(['user' => $this->userPayload($user)], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->requireAdministrator($request);
        abort_if($request->user()->is($user), 422, 'Use your account settings to manage your own account.');
        abort_if($user->is_system_admin, 422, 'System administrator accounts cannot be changed here.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'is_active' => $data['is_active'],
        ]);

        return response()->json(['user' => $this->userPayload($user->fresh()->load('roleAssignments.role'))]);
    }

    public function storeAssignment(Request $request, User $user): JsonResponse
    {
        $this->requireAdministrator($request);
        abort_if($user->is_system_admin, 422, 'System administrator accounts do not receive document roles here.');

        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'work_scope' => ['required', 'string', 'max:255'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ]);
        abort_unless(Role::whereKey($data['role_id'])->where('is_active', true)->exists(), 422, 'Choose an active role.');
        if (isset($data['organizational_unit_id'])) {
            abort_unless(OrganizationalUnit::whereKey($data['organizational_unit_id'])->where('is_active', true)->exists(), 422, 'Choose an active organizational unit.');
        }

        $assignment = $user->roleAssignments()->create($data);

        return response()->json(['assignment' => $assignment->load('role')], 201);
    }

    public function endAssignment(Request $request, UserRoleAssignment $assignment): JsonResponse
    {
        $this->requireAdministrator($request);
        $data = $request->validate(['valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from']]);
        $assignment->update($data);

        return response()->json(['assignment' => $assignment->fresh()->load('role')]);
    }

    private function requireAdministrator(Request $request): void
    {
        abort_unless($request->user()?->is_system_admin, 403, 'System administrator access is required.');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_system_admin' => $user->is_system_admin,
            'role_assignments' => $user->roleAssignments->map(fn (UserRoleAssignment $assignment) => [
                'id' => $assignment->id,
                'role_id' => $assignment->role_id,
                'role_code' => $assignment->role->code,
                'role_name' => $assignment->role->name,
                'organizational_unit_id' => $assignment->organizational_unit_id,
                'organizational_unit_name' => $assignment->organizationalUnit?->name,
                'work_scope' => $assignment->work_scope,
                'valid_from' => $assignment->valid_from?->format('Y-m-d'),
                'valid_until' => $assignment->valid_until?->format('Y-m-d'),
            ]),
        ];
    }
}
