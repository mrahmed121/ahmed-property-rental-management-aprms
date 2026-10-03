<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

class TenantService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Tenant::withCount(['leases', 'applications'])
            ->orderBy($filters['sort_by'] ?? 'first_name', $filters['sort_dir'] ?? 'asc');

        $this->applyTenantScope($query);

        if (! empty($filters['search'])) {
            $s = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $s)
                ->orWhere('last_name', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('phone', 'like', $s));
        }
        foreach (['status', 'kyc_status', 'city'] as $f) {
            if (! empty($filters[$f])) {
                $query->where($f, $filters[$f]);
            }
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Tenant
    {
        $query = Tenant::with(['leases', 'applications', 'user:id,name,email']);
        $this->applyTenantScope($query);

        return $query->findOrFail($id);
    }

    public function create(array $data): Tenant
    {
        $actor = $this->actor();
        $agencyId = $actor->isSuperAdmin() ? ($data['agency_id'] ?? null) : $actor->agency_id;
        if (! $agencyId) {
            abort(422, 'A tenant must belong to an agency.');
        }
        $this->ensureAgencyAccess($agencyId);

        if (! empty($data['user_id'])) {
            $this->ensurePortalUserInAgency((int) $data['user_id'], $agencyId);
        }

        $tenant = DB::transaction(fn () => Tenant::create([
            ...$data,
            'agency_id' => $agencyId,
        ]));

        $this->audit()->logModelChange('tenants.create', $tenant);

        return $tenant->fresh();
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $this->ensureTenantAccess($tenant);

        unset($data['agency_id']);
        if (array_key_exists('user_id', $data) && ! empty($data['user_id'])) {
            $this->ensurePortalUserInAgency((int) $data['user_id'], $tenant->agency_id);
        }

        $old = $tenant->toArray();
        $tenant->update($data);

        $this->audit()->log('tenants.update', $tenant, $old);

        return $tenant->fresh();
    }

    /** Archive (soft delete). Blocked while active leases exist — history is preserved. */
    public function archive(Tenant $tenant): Tenant
    {
        $this->ensureTenantAccess($tenant);

        if ($tenant->leases()->where('status', 'active')->exists()) {
            abort(422, 'Cannot archive a tenant with active leases. Terminate the leases first.');
        }

        $tenant->delete();
        $this->audit()->log('tenants.archive', $tenant);

        return $tenant;
    }

    public function ensureTenantAccess(Tenant $tenant): void
    {
        $this->ensureAgencyAccess($tenant->agency_id);

        $ids = TenantAccess::accessibleTenantIds($this->actor());
        if (! is_null($ids) && ! in_array($tenant->id, $ids, true)) {
            abort(404);
        }
    }

    private function applyTenantScope($query): void
    {
        $ids = TenantAccess::accessibleTenantIds($this->actor());
        if (! is_null($ids)) {
            $query->whereIn('tenants.id', $ids);
        }
    }

    /** A portal login must belong to the same agency (and ideally hold the tenant role). */
    private function ensurePortalUserInAgency(int $userId, int $agencyId): void
    {
        $user = User::withoutGlobalScopes()
            ->where('id', $userId)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $user) {
            abort(422, 'The selected portal user does not belong to this agency.');
        }

        if (Tenant::withoutAgencyScope()->where('user_id', $userId)->exists()) {
            abort(422, 'This user is already linked to another tenant.');
        }
    }
}
