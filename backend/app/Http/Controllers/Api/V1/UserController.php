<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Shared\Models\Role;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $query = User::with(['agency', 'roles'])->orderBy('name');

        if (! $actor->isSuperAdmin()) {
            // AgencyScope already filters, but be explicit for readability.
            $query->where('agency_id', $actor->agency_id);
        }

        $users = $query->paginate(20);

        return response()->json([
            'data' => $users->map(fn (User $u) => $this->payload($u)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        // Non-super-admins can only create users inside their own agency.
        $agencyId = $actor->isSuperAdmin()
            ? ($data['agency_id'] ?? null)
            : $actor->agency_id;

        $user = DB::transaction(function () use ($data, $agencyId, $actor) {
            $user = User::create([
                'agency_id' => $agencyId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'], // 'hashed' cast
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncRoles($user, $data['role_slugs'] ?? [], $actor);

            return $user;
        });

        $this->audit->logModelChange('users.create', $user);

        return response()->json([
            'message' => 'User created.',
            'data' => $this->payload($user->fresh()),
        ], 201);
    }

    public function show(Request $request, int $user): JsonResponse
    {
        $target = $this->findInScope($request, $user);

        return response()->json(['data' => $this->payload($target)]);
    }

    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        $target = $this->findInScope($request, $user);
        $old = $target->toArray();
        $data = $request->validated();

        DB::transaction(function () use ($target, $data, $request) {
            $target->fill(collect($data)->except('role_slugs')->all());
            $target->save();

            if (array_key_exists('role_slugs', $data)) {
                $this->syncRoles($target, $data['role_slugs'], $request->user());
            }
        });

        $this->audit->logModelChange('users.update', $target, $old);

        return response()->json([
            'message' => 'User updated.',
            'data' => $this->payload($target->fresh()),
        ]);
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        $target = $this->findInScope($request, $user);
        $actor = $request->user();

        if ($target->is($actor)) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $old = $target->toArray();
        $target->delete(); // soft delete
        $this->audit->log('users.delete', $target, $old);

        return response()->json(['message' => 'User deactivated (soft deleted).']);
    }

    /** Find a user strictly inside the actor's agency scope (404 otherwise). */
    private function findInScope(Request $request, int $id): User
    {
        $actor = $request->user();

        $query = User::with(['agency', 'roles']);
        if (! $actor->isSuperAdmin()) {
            $query->where('agency_id', $actor->agency_id);
        }

        return $query->findOrFail($id); // 404 — never leaks cross-agency existence
    }

    /**
     * Attach roles by slug. Roles are resolved inside the target user's agency
     * (or system roles); assigning the super-admin role requires Super Admin.
     */
    private function syncRoles(User $user, array $slugs, User $actor): void
    {
        if (empty($slugs)) {
            return;
        }

        if (in_array('super-admin', $slugs, true) && ! $actor->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can grant the super-admin role.');
        }

        $roles = Role::withoutAgencyScope()
            ->whereIn('slug', $slugs)
            ->where(function ($q) use ($user) {
                $q->whereNull('agency_id')->orWhere('agency_id', $user->agency_id);
            })
            ->get();

        if ($roles->count() !== count(array_unique($slugs))) {
            abort(422, 'One or more roles are invalid for this agency.');
        }

        $user->roles()->sync($roles->pluck('id')->all());
    }

    private function payload(User $user): array
    {
        $user->loadMissing(['agency', 'roles']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'agency' => $user->agency ? ['id' => $user->agency->id, 'name' => $user->agency->name] : null,
            'roles' => $user->roles->pluck('slug')->all(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
