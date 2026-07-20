<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use Illuminate\Http\RedirectResponse;

class ShortLinkController extends Controller
{
    /** Resolve a short code and redirect to its destination (counting the click). */
    public function resolve(string $code): RedirectResponse
    {
        $link = ShortLink::where('code', $code)->firstOrFail();
        $link->increment('clicks');

        // Redirect to the destination; if it carries ?ref=, the global
        // CaptureReferral middleware attributes it on arrival.
        return redirect()->to($link->url);
    }
}
