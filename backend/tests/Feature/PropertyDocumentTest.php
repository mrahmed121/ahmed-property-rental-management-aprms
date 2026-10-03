<?php

namespace Tests\Feature;

use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyDocumentTest extends TestCase
{
    public function test_document_upload_multipart(): void
    {
        Storage::fake('local');
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $file = UploadedFile::fake()->create('deed.pdf', 100, 'application/pdf');

        $response = $this->call(
            'POST', '/api/v1/documents',
            [
                'parent_type' => 'property',
                'parent_id' => $property->id,
                'name' => 'Test Deed',
                'document_type' => 'deed',
            ],
            [], ['file' => $file],
            $this->jsonServerVars($token)
        );

        $response->assertStatus(201);
        $docId = $response->json('data.id');

        $doc = PropertyDocument::findOrFail($docId);
        $this->assertEquals($property->agency_id, $doc->agency_id);
        $this->assertEquals(Property::class, $doc->documentable_type);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_document_upload_rejects_bad_parent(): void
    {
        Storage::fake('local');
        $token = $this->loginAs('admin@ahmedestates.local');

        $file = UploadedFile::fake()->create('x.pdf', 100, 'application/pdf');

        // Cross-agency parent => 404 (no leak).
        $propertyB = Property::withoutAgencyScope()->where('name', 'Model Town Villas')->firstOrFail();
        $response = $this->call(
            'POST', '/api/v1/documents', ['parent_type' => 'property', 'parent_id' => $propertyB->id],
            [], ['file' => $file], $this->jsonServerVars($token)
        );
        $response->assertStatus(404);

        // Invalid parent type => 422. ('lease' became valid in P3.)
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();
        $file2 = UploadedFile::fake()->create('y.pdf', 100, 'application/pdf');
        $response2 = $this->call(
            'POST', '/api/v1/documents', ['parent_type' => 'invoice', 'parent_id' => $property->id],
            [], ['file' => $file2], $this->jsonServerVars($token)
        );
        $response2->assertStatus(422);
    }

    public function test_document_upload_rejects_disallowed_file_type(): void
    {
        Storage::fake('local');
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $file = UploadedFile::fake()->create('evil.exe', 100, 'application/x-msdownload');
        $response = $this->call(
            'POST', '/api/v1/documents', ['parent_type' => 'property', 'parent_id' => $property->id],
            [], ['file' => $file], $this->jsonServerVars($token)
        );
        $response->assertStatus(422);
    }

    public function test_document_scoping_across_agencies(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        // Seeded docs belong to agency A only.
        $docs = $this->getJson('/api/v1/documents', $this->bearer($tokenA))->json('data');
        $this->assertNotEmpty($docs);

        // Agency B admin sees none of A's documents.
        $tokenB = $this->loginAs('admin@secondagency.local');
        $docsB = $this->getJson('/api/v1/documents', $this->bearer($tokenB))->json('data');
        $this->assertCount(0, $docsB);

        // Direct access to A's document as B => 404.
        $docId = $docs[0]['id'];
        $this->getJson("/api/v1/documents/{$docId}/download", $this->bearer($tokenB))->assertNotFound();
    }

    public function test_document_download_streams_file(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $doc = PropertyDocument::firstOrFail();

        // Seeded demo files live on the real local disk (not the fake).
        $response = $this->call(
            'GET', "/api/v1/documents/{$doc->id}/download", [],
            [], [],
            $this->jsonServerVars($token)
        );

        $response->assertOk();
        $this->assertStringContainsString(
            $doc->name,
            $response->headers->get('content-disposition') ?? ''
        );
    }

    public function test_auditor_cannot_upload_documents(): void
    {
        Storage::fake('local');
        $token = $this->loginAs('auditor@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $file = UploadedFile::fake()->create('audit.pdf', 100, 'application/pdf');
        $response = $this->call(
            'POST', '/api/v1/documents', ['parent_type' => 'property', 'parent_id' => $property->id],
            [], ['file' => $file], $this->jsonServerVars($token)
        );
        $response->assertStatus(403);
    }

    /** Server vars for multipart calls: bearer token + JSON accept header. */
    private function jsonServerVars(string $token): array
    {
        return $this->transformHeadersToServerVars(
            $this->bearer($token) + ['Accept' => 'application/json']
        );
    }
}
