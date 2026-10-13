<?php

namespace App\Console\Commands;

use App\Models\WaCampaignLog;
use App\Models\WaContact;
use App\Services\SettingService;
use App\Services\WaCampaign\WaContactService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sinkron kontak WA Campaign dari DB customer lama ridlabs (koneksi 'ridl',
 * tabel customers). Hanya customer yang SUDAH DIKLAIM sales (claimed_at terisi).
 * Dasar izin promo: hubungan bisnis existing (pernah dilayani sales) — dicatat
 * sebagai bukti per kontak; kontak yang sudah STOP tidak pernah dihidupkan lagi,
 * izin yang sudah ada (checkout/daftar/manual) tidak ditimpa. Hanya membaca DB ridl.
 */
class WaCampaignSyncRidl extends Command
{
    protected $signature = 'wa-campaign:sync-ridl
        {--since= : Hanya claimed_at ≥ tanggal ini (Y-m-d)}
        {--limit= : Maks. baris (terbaru dulu) — untuk sinkron bertahap}
        {--tanpa-izin : Hanya buat/perbarui kontak, jangan catat izin promo}
        {--dry-run : Hitung saja, tidak menulis}';

    protected $description = 'Tarik customer lama ridlabs yang sudah diklaim sales ke kontak WA Campaign (izin: hubungan bisnis existing)';

    public function handle(WaContactService $contacts, SettingService $settings): int
    {
        if (! config('services.ridl.enabled')) {
            $this->warn('RIDL_DB_DATABASE belum diisi di .env — sinkron ridlabs dilewati.');

            return self::SUCCESS;
        }

        $table = (string) config('services.ridl.table', 'customers');
        $query = DB::connection('ridl')->table($table)
            ->whereNotNull('claimed_at')->whereNotNull('phone')->where('phone', '!=', '')
            ->orderByDesc('claimed_at')->orderByDesc('id');
        if ($this->option('since')) {
            $query->where('claimed_at', '>=', $this->option('since'));
        }
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $n = ['dibaca' => 0, 'baru' => 0, 'diperbarui' => 0, 'izin_baru' => 0, 'tetap_stop' => 0, 'sudah_izin' => 0, 'invalid' => 0];
        $dry = (bool) $this->option('dry-run');
        $withConsent = ! $this->option('tanpa-izin');

        foreach ($query->cursor() as $row) {
            $n['dibaca']++;
            $phone = $contacts->normalize((string) $row->phone);
            if (! $phone) {
                $n['invalid']++;

                continue;
            }
            $existing = WaContact::where('phone', $phone)->first();
            $sales = trim((string) ($row->id_sales ?? ''));
            $claimed = $row->claimed_at ? substr((string) $row->claimed_at, 0, 10) : '';

            if ($dry) {
                $existing ? $n['diperbarui']++ : $n['baru']++;
                if ($withConsent) {
                    match (true) {
                        $existing?->isOptedOut() => $n['tetap_stop']++,
                        $existing?->isOptedIn() => $n['sudah_izin']++,
                        default => $n['izin_baru']++,
                    };
                }

                continue;
            }

            $contact = $contacts->upsert($phone, [
                'name' => $row->nama_customer ?: null,
                'source' => 'ridl',
                'tags' => array_values(array_filter(['legacy', $sales !== '' ? 'sales-'.strtoupper($sales) : null])),
                'notes' => $row->perusahaan ? 'Perusahaan: '.$row->perusahaan : null,
            ]);
            if (! $contact) {
                $n['invalid']++;

                continue;
            }
            $existing ? $n['diperbarui']++ : $n['baru']++;

            if ($withConsent) {
                if ($contact->isOptedOut()) {
                    $n['tetap_stop']++;
                } elseif ($contact->isOptedIn()) {
                    $n['sudah_izin']++;
                } else {
                    $contacts->optIn($contact, 'ridl', 'Customer lama ridlabs — sudah diklaim/dilayani sales '.($sales ?: '-').($claimed ? ' pada '.$claimed : '').' (hubungan bisnis existing)');
                    $n['izin_baru']++;
                }
            }
        }

        $summary = "ridlabs: dibaca {$n['dibaca']}, kontak baru {$n['baru']}, diperbarui {$n['diperbarui']}, izin baru {$n['izin_baru']}, sudah izin {$n['sudah_izin']}, tetap STOP {$n['tetap_stop']}, nomor invalid {$n['invalid']}".($dry ? ' (DRY RUN)' : '');
        $this->info($summary);
        if (! $dry) {
            $settings->set('wa_campaign.ridl_last_sync', now()->format('d/m/Y H:i').' — '.$summary, 'string', 'wa_campaign');
            WaCampaignLog::write(null, 'Sinkron '.$summary, $n);
        }

        return self::SUCCESS;
    }
}
