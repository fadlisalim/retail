<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaCampaignTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Template pesan promo yang dapat dipakai ulang. */
class WaCampaignTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.wa-campaign.templates', ['templates' => WaCampaignTemplate::with('creator')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('wa-campaign', 'public');
        }
        WaCampaignTemplate::create($data);

        return back()->with('success', 'Template disimpan.');
    }

    public function update(Request $request, WaCampaignTemplate $template): RedirectResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('wa-campaign', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }
        $template->update($data);

        return back()->with('success', 'Template diperbarui.');
    }

    public function destroy(WaCampaignTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
    }
}
