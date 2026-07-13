<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    /** group => [category slug, [ [name, unit, type, filterable], ... ]]. */
    private array $groups = [
        'Spesifikasi Panel Surya' => ['panel-surya', [
            ['Daya Maksimum', 'Wp', 'number', true],
            ['Efisiensi Modul', '%', 'number', true],
            ['Tegangan Open Circuit', 'V', 'number', false],
            ['Arus Short Circuit', 'A', 'number', false],
            ['Jenis Sel', '', 'text', true],
            ['Garansi Produk', 'tahun', 'number', false],
            ['Garansi Performa', 'tahun', 'number', false],
        ]],
        'Spesifikasi Inverter' => ['inverter', [
            ['Daya Output', 'W', 'number', true],
            ['Tegangan Input PV', 'V', 'text', false],
            ['Jumlah MPPT', '', 'number', false],
            ['Tegangan Baterai', 'V', 'number', false],
            ['Fasa', '', 'text', true],
            ['Efisiensi', '%', 'number', false],
            ['IP Rating', '', 'text', false],
        ]],
        'Spesifikasi Baterai' => ['baterai', [
            ['Kapasitas', 'kWh', 'number', true],
            ['Tegangan Nominal', 'V', 'number', true],
            ['Kapasitas Ah', 'Ah', 'number', false],
            ['Jenis Sel', '', 'text', true],
            ['Cycle Life', 'siklus', 'number', false],
            ['Depth of Discharge', '%', 'number', false],
            ['BMS', '', 'text', false],
        ]],
    ];

    public function run(): void
    {
        $order = 0;
        foreach ($this->groups as $groupName => [$categorySlug, $attributes]) {
            $group = AttributeGroup::updateOrCreate(['slug' => Str::slug($groupName)], [
                'name' => $groupName, 'sort_order' => $order++,
            ]);

            if ($category = Category::where('slug', $categorySlug)->first()) {
                $group->categories()->syncWithoutDetaching([$category->id]);
            }

            foreach ($attributes as $i => [$name, $unit, $type, $filterable]) {
                Attribute::updateOrCreate(['slug' => Str::slug($groupName.'-'.$name)], [
                    'attribute_group_id' => $group->id,
                    'name' => $name,
                    'unit' => $unit ?: null,
                    'type' => $type,
                    'is_filterable' => $filterable,
                    'is_comparable' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
