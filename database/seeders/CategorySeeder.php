<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /** Category tree: name => [children...]. */
    private array $tree = [
        'Panel Surya' => ['Monocrystalline', 'Bifacial', 'Flexible'],
        'Inverter' => ['On-Grid', 'Off-Grid', 'Hybrid', 'Single Phase', 'Three Phase'],
        'Baterai' => ['Lithium LiFePO4', 'Rack Mounted', 'Wall Mounted'],
        'Solar Charge Controller' => ['MPPT', 'PWM'],
        'Paket PLTS' => ['On-Grid', 'Off-Grid', 'Hybrid', 'Rumah', 'Kantor', 'Industri'],
        'Pompa Air Tenaga Surya' => [],
        'PJU Tenaga Surya' => [],
        'Aksesoris' => ['Kabel', 'Konektor', 'Proteksi', 'Mounting', 'Combiner Box'],
        'Barang Sisa Proyek' => ['Baru', 'Open Box', 'Bekas Display', 'Bekas Pakai'],
        'Spare Part' => [],
    ];

    private array $featured = ['Panel Surya', 'Inverter', 'Baterai', 'Paket PLTS', 'Barang Sisa Proyek', 'Aksesoris'];

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
    }

    private function make(string $name, ?Category $parent, int $order, bool $featured, ?string $parentName = null): Category
    {
        $slug = $parentName ? Str::slug($parentName.'-'.$name) : Str::slug($name);

        $category = Category::updateOrCreate(['slug' => $slug], [
            'parent_id' => $parent?->id,
            'name' => $name,
            'depth' => $parent ? $parent->depth + 1 : 0,
            'is_featured' => $featured,
            'is_active' => true,
            'sort_order' => $order,
            'meta_title' => "$name — Rekasurya Store",
            'meta_description' => "Beli $name berkualitas di Rekasurya Store.",
        ]);

        $category->update(['path' => $parent ? $parent->path.'/'.$category->id : (string) $category->id]);

        return $category;
    }
}
