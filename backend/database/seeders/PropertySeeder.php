<?php

namespace Database\Seeders;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic APRMS demo property data (local development only).
 * No leases, no rent transactions, no payments — P3/P4 will own those.
 */
class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $agencyA = Agency::where('slug', 'ahmed-estates')->firstOrFail();
        $agencyB = Agency::where('slug', 'second-agency')->firstOrFail();
        $ownerA = User::where('email', 'owner@ahmedestates.local')->first();
        $adminA = User::where('email', 'admin@ahmedestates.local')->first();

        // --- 1. Residential, owned by the demo owner ---
        $gulshan = $this->property($agencyA, [
            'name' => 'Gulshan Residency',
            'property_type' => 'residential',
            'address' => 'Plot 12-C, Block 6, Gulshan-e-Iqbal',
            'city' => 'Karachi',
            'postal_code' => '75300',
            'description' => 'Demo residential complex: two mid-rise blocks near Maskan Chowrangi.',
            'owner_id' => $ownerA?->id,
        ]);
        $blockA = $this->building($agencyA, $gulshan, 'Block A', 5);
        $blockB = $this->building($agencyA, $gulshan, 'Block B', 5);
        $this->units($agencyA, $gulshan, $blockA, 'apartment', [
            ['A-101', 'vacant', 45000, 2, 2], ['A-102', 'occupied', 45000, 2, 2],
            ['A-201', 'occupied', 52000, 3, 2], ['A-202', 'maintenance', 52000, 3, 2],
        ]);
        $this->units($agencyA, $gulshan, $blockB, 'apartment', [
            ['B-101', 'occupied', 45000, 2, 2], ['B-102', 'occupied', 48000, 2, 2],
            ['B-201', 'reserved', 52000, 3, 2], ['B-202', 'vacant', 52000, 3, 2],
        ]);

        // --- 2. Commercial tower, agency-managed ---
        $tower = $this->property($agencyA, [
            'name' => 'DHA Trade Tower',
            'property_type' => 'commercial',
            'address' => 'Plot 44, Khayaban-e-Ittehad, DHA Phase 6',
            'city' => 'Karachi',
            'postal_code' => '75500',
            'description' => 'Demo commercial tower with corporate office floors.',
        ]);
        $tower1 = $this->building($agencyA, $tower, 'Tower 1', 12);
        $this->units($agencyA, $tower, $tower1, 'office', [
            ['O-1201', 'occupied', 120000, null, 2], ['O-1202', 'occupied', 120000, null, 2],
            ['O-1203', 'vacant', 135000, null, 2], ['O-1204', 'vacant', 135000, null, 2],
            ['O-1205', 'reserved', 150000, null, 3], ['O-1206', 'occupied', 150000, null, 3],
        ]);

        // --- 3. Mixed-use, multi-building ---
        $greens = $this->property($agencyA, [
            'name' => 'Bahria Greens Estate',
            'property_type' => 'mixed-use',
            'address' => 'Sector C, Bahria Town',
            'city' => 'Lahore',
            'postal_code' => '53720',
            'description' => 'Demo mixed-use estate: two residential blocks plus a commercial plaza.',
            'owner_id' => $ownerA?->id,
        ]);
        $rose = $this->building($agencyA, $greens, 'Rose Block', 4);
        $jasmine = $this->building($agencyA, $greens, 'Jasmine Block', 4);
        $plaza = $this->building($agencyA, $greens, 'Commercial Plaza', 2);
        $this->units($agencyA, $greens, $rose, 'apartment', [
            ['R-101', 'vacant', 38000, 2, 1], ['R-102', 'occupied', 38000, 2, 1],
        ]);
        $this->units($agencyA, $greens, $jasmine, 'apartment', [
            ['J-101', 'occupied', 40000, 2, 2], ['J-102', 'maintenance', 40000, 3, 2],
        ]);
        $this->units($agencyA, $greens, $plaza, 'shop', [
            ['S-01', 'occupied', 85000, null, 1], ['S-02', 'vacant', 90000, null, 1],
        ]);

        // --- 4. Second agency (isolation demo) ---
        $villas = $this->property($agencyB, [
            'name' => 'Model Town Villas',
            'property_type' => 'residential',
            'address' => 'Block D, Model Town',
            'city' => 'Lahore',
            'postal_code' => '54700',
            'description' => 'Demo property of the second agency (isolation testing).',
        ]);
        $villaBlock = $this->building($agencyB, $villas, 'Villa Block', 2);
        $this->units($agencyB, $villas, $villaBlock, 'apartment', [
            ['V-101', 'vacant', 55000, 3, 2], ['V-102', 'occupied', 55000, 3, 2],
        ]);

        // --- Demo documents (real files, clearly demo content) ---
        $this->demoDocument($agencyA, $gulshan, $adminA, 'demo-deed-note.txt',
            "DEMO DOCUMENT — Gulshan Residency\nThis is a placeholder deed note for local development.\nNot a legal document.");
        $this->demoDocument($agencyA, $tower1, $adminA, 'demo-floor-plan.txt',
            "DEMO DOCUMENT — DHA Trade Tower, Tower 1\nPlaceholder floor-plan note for local development.");
    }

    private function property(Agency $agency, array $attrs): Property
    {
        return Property::firstOrCreate(
            ['agency_id' => $agency->id, 'name' => $attrs['name']],
            [...$attrs, 'agency_id' => $agency->id, 'status' => 'active']
        );
    }

    private function building(Agency $agency, Property $property, string $name, ?int $floors): Building
    {
        return Building::firstOrCreate(
            ['property_id' => $property->id, 'name' => $name],
            ['agency_id' => $agency->id, 'property_id' => $property->id, 'floors' => $floors, 'status' => 'active']
        );
    }

    /** $specs: [unit_number, status, market_rent, bedrooms, bathrooms] */
    private function units(Agency $agency, Property $property, Building $building, string $type, array $specs): void
    {
        foreach ($specs as [$number, $status, $rent, $bed, $bath]) {
            Unit::firstOrCreate(
                ['building_id' => $building->id, 'unit_number' => $number],
                [
                    'agency_id' => $agency->id,
                    'property_id' => $property->id,
                    'unit_type' => $type,
                    'status' => $status,
                    'market_rent' => $rent,
                    'bedrooms' => $bed,
                    'bathrooms' => $bath,
                    'area_sqft' => $type === 'office' ? 900 : ($type === 'shop' ? 450 : 1100),
                ]
            );
        }
    }

    private function demoDocument(Agency $agency, $parent, $uploader, string $filename, string $content): void
    {
        $path = "documents/{$agency->id}/{$filename}";
        Storage::disk('local')->put($path, $content);

        PropertyDocument::firstOrCreate(
            ['agency_id' => $agency->id, 'file_path' => $path],
            [
                'documentable_type' => get_class($parent),
                'documentable_id' => $parent->id,
                'name' => $filename,
                'document_type' => 'other',
                'mime_type' => 'text/plain',
                'file_size' => strlen($content),
                'description' => 'Demo document for local development.',
                'uploaded_by' => $uploader?->id,
            ]
        );
    }
}
