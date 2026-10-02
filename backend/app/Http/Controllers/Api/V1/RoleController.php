<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Shared\Models\Permission;
use App\Domains\Shared\Models\Role;
use App\Domains\Shared\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $roles = Role::with('permissions')
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->where(function ($qq) use ($actor) {
                // Agency users see system roles (read-only reference) + their agency roles.
                $qq->whereNull('agency_id')->orWhere('agency_id', $actor->agency_id);
            }))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $r) => $this->payload($r)),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        if (($data['slug'] ?? null) === 'super-admin' && ! $actor->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can create the super-admin role.');
        }

        $role = DB::transaction(function () use ($data, $actor) {
            $role = Role::create([
                'agency_id' => $actor->isSuperAdmin() ? null : $actor->agency_id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $data['permission_slugs'] ?? []);

            return $role;
        });

        $this->audit->logModelChange('roles.create', $role);

        return response()->json([
            'message' => 'Role created.',
            'data' => $this->payload($role->fresh()),
        ], 201);
    }

    public function update(StoreRoleRequest $request, int $role): JsonResponse
    {
        $actor = $request->user();

        $target = Role::with('permissions')->findOrFail($role);

        if ($target->is_system) {
            abort(403, 'System roles cannot be modified.');
        }
        if (! $actor->isSuperAdmin() && $target->agency_id !== $actor->agency_id) {
            abort(404); // never leak cross-agency existence
        }

        $old = $target->toArray();
        $data = $request->validated();

        DB::transaction(function () use ($target, $data) {
            $target->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            if (array_key_exists('permission_slugs', $data)) {
                $this->syncPermissions($target, $data['permission_slugs']);
            }
        });

        $this->audit->log('roles.update', $target, $old);

        return response()->json([
            'message' => 'Role updated.',
            'data' => $this->payload($target->fresh()),
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        return response()->json([
            'data' => $permissions->map(fn (Permission $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'group' => $p->group,
                'description' => $p->description,
            ]),
        ]);
    }

    private function syncPermissions(Role $role, array $slugs): void
    {
        $ids = Permission::whereIn('slug', array_unique($slugs))->pluck('id');

        if ($ids->count() !== count(array_unique($slugs))) {
            abort(422, 'One or more permission slugs are invalid.');
        }

        $role->permissions()->sync($ids->all());
    }

    private function payload(Role $role): array
    {
        $role->loadMissing('permissions');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'agency_id' => $role->agency_id,
            'permissions' => $role->permissions->pluck('slug')->all(),
        ];
    }
}
