<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttributeController extends Controller
{
    public function index(): View
    {
        $attributes = Attribute::with('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Attribute $attribute) => $attribute->group?->name ?? 'Tanpa Grup');

        return view('admin.attributes.index', compact('attributes'));
    }

    public function create(): View
    {
        $attribute = new Attribute(['type' => 'text', 'is_comparable' => true, 'sort_order' => 0]);

        return view('admin.attributes.create', [
            'attribute' => $attribute,
            'groups' => $this->groupOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Attribute::create($this->validated($request, null));

        return redirect()->route('admin.attributes.index')
            ->with('success', 'Atribut berhasil ditambahkan.');
    }

    public function edit(Attribute $atribut): View
    {
        return view('admin.attributes.edit', [
            'attribute' => $atribut,
            'groups' => $this->groupOptions(),
        ]);
    }

    public function update(Request $request, Attribute $atribut): RedirectResponse
    {
        $atribut->update($this->validated($request, $atribut));

        return redirect()->route('admin.attributes.index')
            ->with('success', 'Atribut berhasil diperbarui.');
    }

    public function destroy(Attribute $atribut): RedirectResponse
    {
        $atribut->delete();

        return redirect()->route('admin.attributes.index')
            ->with('success', 'Atribut berhasil dihapus.');
    }

    private function groupOptions(): array
    {
        return AttributeGroup::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }

    private function validated(Request $request, ?Attribute $attribute): array
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'attribute_group_id' => ['nullable', 'integer', 'exists:attribute_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('attributes', 'slug')->ignore($attribute?->id)],
            'unit' => ['nullable', 'string', 'max:30'],
            'type' => ['required', Rule::in(['text', 'number', 'select', 'boolean'])],
            'is_filterable' => ['boolean'],
            'is_comparable' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['attribute_group_id'] = $data['attribute_group_id'] ?? null;
        $data['is_filterable'] = $request->boolean('is_filterable');
        $data['is_comparable'] = $request->boolean('is_comparable');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
