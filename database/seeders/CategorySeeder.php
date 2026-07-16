<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /** Category tree: name => [children...]. Slugs are reused where they already exist. */
    private array $tree = [
        'Panel Surya' => ['Monocrystalline', 'Polycrystalline', 'Bifacial', 'Flexible'],
        'Inverter' => ['On-Grid', 'Off-Grid', 'Hybrid', 'Microinverter', 'Single Phase', 'Three Phase'],
        'Baterai' => ['Lithium LiFePO4', 'Rack Mounted', 'Wall Mounted', 'All-in-One (ESS)'],
        'Solar Charge Controller' => ['MPPT', 'PWM'],
        'Paket PLTS' => ['On-Grid', 'Off-Grid', 'Hybrid', 'Rumah', 'Kantor', 'Industri'],
        'Mounting & Rangka' => ['Atap / Rooftop', 'Ground Mounting', 'Carport / Canopy'],
        'Kabel, Konektor & Proteksi' => ['Kabel PV', 'Konektor MC4', 'MCB / MCCB DC', 'SPD / Arrester', 'Combiner Box'],
        'Pompa Air Tenaga Surya' => ['Submersible', 'Surface'],
        'PJU Tenaga Surya' => ['PJU All-in-One', 'PJU Two-in-One', 'Lampu Taman', 'Lampu Sorot'],
        'Portable Power' => ['Power Station', 'Solar Generator'],
    ];

    private array $featured = ['Panel Surya', 'Inverter', 'Baterai', 'Paket PLTS', 'PJU Tenaga Surya'];

    /**
     * Demo/removed categories — deactivated (not deleted) to keep the nav clean.
     * "Barang Sisa Proyek" is retired: surplus is now expressed via product
     * condition (e.g. "Baru - Sisa Proyek"), not a category.
     */
    private array $retired = ['aksesoris', 'spare-part', 'barang-sisa-proyek'];

    public function run(): void
    {
        $order = 0;
        foreach ($this->tree as $parentName => $children) {
            $parent = $this->make($parentName, null, $order++, in_array($parentName, $this->featured, true));

            $childOrder = 0;
            foreach ($children as $childName) {
                // Child slug is namespaced to keep the global unique constraint happy
                // (e.g. Inverter > Hybrid and Paket PLTS > Hybrid).
                $this->make($childName, $parent, $childOrder++, false, $parentName);
            }
        }

        // Hide retired demo categories and their children (reversible in the admin).
        $retiredIds = Category::whereIn('slug', $this->retired)->pluck('id');
        Category::whereIn('slug', $this->retired)
            ->orWhereIn('parent_id', $retiredIds)
            ->update(['is_active' => false, 'is_featured' => false]);
    }

    private function make(string $name, ?Category $parent, int $order, bool $featured, ?string $parentName = null): Category
    {
        $slug = $parentName ? Str::slug($parentName.'-'.$name) : Str::slug($name);
        $brand = config('rekasurya.company.brand_name', 'Energi.Click');

        $category = Category::updateOrCreate(['slug' => $slug], [
            'parent_id' => $parent?->id,
            'name' => $name,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'is_featured' => $featured,
            'is_active' => true,
            'sort_order' => $order,
            'meta_title' => "$name — $brand",
            'meta_description' => "Beli $name berkualitas di $brand.",
        ]);

        $category->update(['path' => $parent ? $parent->path.'/'.$category->id : (string) $category->id]);

        return $category;
    }
}
