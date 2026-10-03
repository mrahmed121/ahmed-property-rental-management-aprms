<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Services\DocumentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->documents->list($request->only([
            'parent_type', 'parent_id', 'document_type', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($d) => $this->payload($d)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $document = $this->documents->upload(
            $data['parent_type'],
            (int) $data['parent_id'],
            $request->file('file'),
            $data,
        );

        return response()->json([
            'message' => 'Document uploaded.',
            'data' => $this->payload($document),
        ], 201);
    }

    /** Stream the file — agency + portfolio access re-checked, never a public URL. */
    public function download(int $document): BinaryFileResponse
    {
        $model = $this->find($document);
        $path = $this->documents->downloadPath($model);

        return response()->download($path, $model->name);
    }

    public function destroy(int $document): JsonResponse
    {
        $model = $this->find($document);
        $this->documents->delete($model);

        return response()->json(['message' => 'Document deleted.']);
    }

    private function find(int $id): PropertyDocument
    {
        return $this->documents->find($id);
    }

    private function payload($d): array
    {
        return [
            'id' => $d->id,
            'parent_type' => $d->parentKey(),
            'parent_id' => $d->documentable_id,
            'name' => $d->name,
            'document_type' => $d->document_type,
            'mime_type' => $d->mime_type,
            'file_size' => $d->file_size,
            'description' => $d->description,
            'uploaded_by' => $d->uploadedBy ? ['id' => $d->uploadedBy->id, 'name' => $d->uploadedBy->name] : null,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
