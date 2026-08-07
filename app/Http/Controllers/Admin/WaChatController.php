<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantLead;
use App\Models\WaMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Admin WhatsApp inbox: conversations grouped by phone; incoming messages
 * arrive via the Wablas webhook, replies go out through WhatsAppService.
 */
class WaChatController extends Controller
{
    /** Conversation list + (optionally) one open thread. */
    public function index(Request $request, WhatsAppService $wa): View
    {
        $phone = $wa->normalize((string) $request->query('phone')) ?: null;

        $conversations = WaMessage::query()
            ->select('phone')
            ->selectRaw('MAX(created_at) as last_at')
            ->selectRaw("SUM(CASE WHEN direction = 'in' AND is_read = 0 THEN 1 ELSE 0 END) as unread")
            ->groupBy('phone')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(100)
            ->get();

        // Display names: latest WA pushName per phone, else lead name.
        $names = WaMessage::whereIn('phone', $conversations->pluck('phone'))
            ->whereNotNull('name')->orderBy('created_at')
            ->pluck('name', 'phone');
        $leadNames = AssistantLead::whereIn('phone', $conversations->pluck('phone'))
            ->whereNotNull('name')->pluck('name', 'phone');

        // Last-message preview per conversation (WA-style list).
        $previews = WaMessage::whereIn('phone', $conversations->pluck('phone'))
            ->orderByDesc('id')->get(['phone', 'message', 'direction'])
            ->unique('phone');

        $thread = collect();
        if ($phone) {
            WaMessage::where('phone', $phone)->where('direction', 'in')->where('is_read', false)->update(['is_read' => true]);
            $thread = WaMessage::where('phone', $phone)->orderBy('created_at')->orderBy('id')->limit(300)->get();
        }

        return view('admin.wa-chat', [
            'conversations' => $conversations,
            'names' => $names,
            'leadNames' => $leadNames,
            'previews' => $previews->keyBy('phone'),
            'phone' => $phone,
            'thread' => $thread,
            'waEnabled' => $wa->isEnabled(),
        ]);
    }

    /** JSON poll: new messages in a thread after a given id (marks them read). */
    public function messages(Request $request, WhatsAppService $wa): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);
        $phone = $wa->normalize($data['phone']);
        abort_unless($phone, 422);

        $messages = WaMessage::where('phone', $phone)
            ->where('id', '>', (int) ($data['after_id'] ?? 0))
            ->orderBy('id')
            ->limit(100)
            ->get();

        WaMessage::where('phone', $phone)->where('direction', 'in')->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'messages' => $messages->map(fn (WaMessage $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'message' => $m->message,
                'sent_ok' => $m->sent_ok,
                'time' => $m->created_at?->format('H:i'),
                'date' => $m->created_at?->format('d/m/Y'),
                'media_url' => $m->media_url,
                'media_type' => $m->media_type,
                'media_name' => $m->media_name,
                'is_image' => $m->isImage(),
            ]),
        ]);
    }

    /** Send a reply via Wablas and append it to the thread. */
    public function send(Request $request, WhatsAppService $wa): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);
        $phone = $wa->normalize($data['phone']);
        abort_unless($phone, 422);

        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            return response()->json(['ok' => false, 'error' => 'Pesan tidak boleh kosong.'], 422);
        }

        $ok = $wa->send($phone, $message);

        $row = WaMessage::create([
            'phone' => $phone,
            'direction' => 'out',
            'message' => $message,
            'is_read' => true,
            'sent_ok' => $ok,
            'user_id' => $request->user()?->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => $ok,
            'id' => $row->id,
            'error' => $ok ? null : ($wa->lastResult['body'] ?? 'Gagal mengirim.'),
        ], $ok ? 200 : 422);
    }

    /**
     * Send an attachment: the file is stored on the public disk first because
     * Wablas fetches media by URL, then the message is recorded in the thread.
     */
    public function sendMedia(Request $request, WhatsAppService $wa): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:'.(int) config('rekasurya.media.max_upload_kb', 15360),
                'mimes:jpg,jpeg,png,webp,gif,mp4,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,zip'],
        ]);
        $phone = $wa->normalize($data['phone']);
        abort_unless($phone, 422);

        $file = $request->file('file');
        $path = $file->store('wa-chat', 'public');
        // Wablas downloads the file itself, so the link must be absolute —
        // the disk returns a site-relative path unless it is configured with
        // a full base URL.
        $url = Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = url($url);
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $type = match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => 'image',
            $extension === 'mp4' => 'video',
            default => 'document',
        };

        $caption = trim((string) ($data['caption'] ?? ''));
        $ok = $wa->sendMedia($phone, $url, $type, $caption !== '' ? $caption : $file->getClientOriginalName());

        $row = WaMessage::create([
            'phone' => $phone,
            'direction' => 'out',
            'message' => $caption,
            'media_url' => $url,
            'media_type' => $type,
            'media_name' => $file->getClientOriginalName(),
            'is_read' => true,
            'sent_ok' => $ok,
            'user_id' => $request->user()?->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => $ok,
            'id' => $row->id,
            'error' => $ok ? null : ($wa->lastResult['body'] ?? 'Gagal mengirim media.'),
        ], $ok ? 200 : 422);
    }
}
