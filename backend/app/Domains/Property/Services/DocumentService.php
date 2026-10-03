<?php

namespace App\Domains\Property\Services;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DocumentService — property-side document foundation.
 * Files live on the local disk under documents/{agency_id}/; access is
 * always re-checked against agency + portfolio (never a public URL).
 */
class DocumentService extends DomainService
{
    public const MAX_SIZE_KB = 10240; // 10 MB
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    public function list(array $filters = [])
    {
        $query = PropertyDocument::with(['uploadedBy:id,name'])
            ->orderByDesc('id');

        $this->applyPortfolioScope($query);

        if (! empty($filters['parent_type']) && ! empty($filters['parent_id'])) {
            $parent = $this->resolveParent($filters['parent_type'], (int) $filters['parent_id']);
            $query->where('documentable_type', get_class($parent))
                ->where('documentable_id', $parent->id);
        }
        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function upload(string $parentType, int $parentId, UploadedFile $file, array $data): PropertyDocument
    {
        $parent = $this->resolveParent($parentType, $parentId);
        $this->ensureParentAccess($parent);

        $this->validateFile($file);

        $agencyId = $parent->agency_id;
        $path = $file->storeAs(
            "documents/{$agencyId}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local'
        );

        $document = DB::transaction(fn () => PropertyDocument::create([
            'agency_id' => $agencyId,
            'documentable_type' => get_class($parent),
            'documentable_id' => $parent->id,
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'document_type' => $data['document_type'] ?? 'other',
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $data['description'] ?? null,
            'uploaded_by' => $this->actor()?->id,
        ]));

        $this->audit()->logModelChange('documents.upload', $document);

        return $document->fresh();
    }

    /** Find one document, enforcing agency + portfolio access (404 otherwise). */
    public function find(int $id): PropertyDocument
    {
        $query = PropertyDocument::with(['uploadedBy:id,name']);
        $this->applyPortfolioScope($query);

        $document = $query->findOrFail($id);
        $this->ensureAgencyAccess($document->agency_id);

        return $document;
    }

    /** Returns the absolute file path after verifying access (controller streams it). */
    public function downloadPath(PropertyDocument $document): string
    {
        $this->ensureDocumentAccess($document);

        $path = Storage::disk('local')->path($document->file_path);
        if (! is_file($path)) {
            abort(404, 'File not found on disk.');
        }

        return $path;
    }

    public function delete(PropertyDocument $document): void
    {
        $this->ensureDocumentAccess($document);

        DB::transaction(function () use ($document) {
            Storage::disk('local')->delete($document->file_path);
            $document->delete();
        });

        $this->audit()->log('documents.delete', $document);
    }

    /** Resolve a parent entity by key, enforcing agency + portfolio access. */
    public function resolveParent(string $parentType, int $parentId): Property|Building|Unit|\App\Domains\Leasing\Models\Tenant|\App\Domains\Leasing\Models\TenantApplication|\App\Domains\Leasing\Models\Lease|\App\Domains\Leasing\Models\MoveOutInspection
    {
        $class = PropertyDocument::ALLOWED_PARENTS[$parentType] ?? null;
        if (! $class) {
            abort(422, 'Invalid parent type. Use: property, building, unit.');
        }

        $query = $class::query();
        if ($class === Property::class) {
            PropertyAccess::applyToPropertyQuery($query, $this->actor());
        } else {
            PropertyAccess::applyToPropertyQuery($query, $this->actor(), 'property_id');
        }

        return $query->findOrFail($parentId);
    }

    public function ensureDocumentAccess(PropertyDocument $document): void
    {
        $this->ensureAgencyAccess($document->agency_id);

        $parent = $document->documentable;
        if (! $parent) {
            abort(404);
        }
        $this->ensureParentAccess($parent);
    }

    private function ensureParentAccess(Property|Building|Unit|\App\Domains\Leasing\Models\Tenant|\App\Domains\Leasing\Models\TenantApplication|\App\Domains\Leasing\Models\Lease|\App\Domains\Leasing\Models\MoveOutInspection $parent): void
    {
        if ($parent instanceof Property) {
            app(PropertyService::class)->ensurePropertyAccess($parent);
        } elseif ($parent instanceof Building) {
            app(BuildingService::class)->ensureBuildingAccess($parent);
        } elseif ($parent instanceof Unit) {
            app(UnitService::class)->ensureUnitAccess($parent);
        } elseif ($parent instanceof \App\Domains\Leasing\Models\Tenant) {
            app(\App\Domains\Leasing\Services\TenantService::class)->ensureTenantAccess($parent);
        } elseif ($parent instanceof \App\Domains\Leasing\Models\TenantApplication) {
            app(\App\Domains\Leasing\Services\ApplicationService::class)->ensureApplicationAccess($parent);
        } elseif ($parent instanceof \App\Domains\Leasing\Models\Lease) {
            app(\App\Domains\Leasing\Services\LeaseService::class)->ensureLeaseAccess($parent);
        } else {
            app(\App\Domains\Leasing\Services\MoveOutInspectionService::class)->ensureInspectionAccess($parent);
        }
    }

    /** Portfolio-scope a document query via its polymorphic parent. */
    private function applyPortfolioScope($query): void
    {
        $propertyIds = PropertyAccess::accessiblePropertyIds($this->actor());
        $tenantIds = \App\Domains\Leasing\Services\TenantAccess::accessibleTenantIds($this->actor());

        if (is_null($propertyIds) && is_null($tenantIds)) {
            return;
        }

        $query->where(function ($q) use ($propertyIds, $tenantIds) {
            $first = true;

            if (! is_null($propertyIds)) {
                $q->where(fn ($qq) => $qq
                        ->where('documentable_type', Property::class)
                        ->whereIn('documentable_id', $propertyIds))
                    ->orWhere(fn ($qq) => $qq
                        ->where('documentable_type', Building::class)
                        ->whereIn('documentable_id',
                            Building::withoutAgencyScope()->whereIn('property_id', $propertyIds)->pluck('id')))
                    ->orWhere(fn ($qq) => $qq
                        ->where('documentable_type', Unit::class)
                        ->whereIn('documentable_id',
                            Unit::withoutAgencyScope()->whereIn('property_id', $propertyIds)->pluck('id')));
                $first = false;
            }

            if (! is_null($tenantIds)) {
                $tenant = \App\Domains\Leasing\Models\Tenant::class;
                $application = \App\Domains\Leasing\Models\TenantApplication::class;
                $lease = \App\Domains\Leasing\Models\Lease::class;
                $inspection = \App\Domains\Leasing\Models\MoveOutInspection::class;

                $clause = fn ($qq) => $qq
                    ->where(fn ($w) => $w->where('documentable_type', $tenant)->whereIn('documentable_id', $tenantIds))
                    ->orWhere(fn ($w) => $w->where('documentable_type', $application)
                        ->whereIn('documentable_id', \App\Domains\Leasing\Models\TenantApplication::withoutAgencyScope()->whereIn('tenant_id', $tenantIds)->pluck('id')))
                    ->orWhere(fn ($w) => $w->where('documentable_type', $lease)
                        ->whereIn('documentable_id', \App\Domains\Leasing\Models\Lease::withoutAgencyScope()->whereIn('tenant_id', $tenantIds)->pluck('id')))
                    ->orWhere(fn ($w) => $w->where('documentable_type', $inspection)
                        ->whereIn('documentable_id', \App\Domains\Leasing\Models\MoveOutInspection::withoutAgencyScope()
                            ->whereHas('lease', fn ($l) => $l->whereIn('tenant_id', $tenantIds))->pluck('id')));

                if ($first) {
                    $q->where($clause);
                } else {
                    $q->orWhere($clause);
                }
            }
        });
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            abort(422, 'The uploaded file is invalid.');
        }
        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            abort(422, 'The file may not be larger than 10 MB.');
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            abort(422, 'File type not allowed. Allowed: PDF, JPG, PNG, WEBP, DOC, DOCX, TXT.');
        }
    }
}
