<?php

namespace App\Console\Commands;

use App\Services\WaCampaign\WaContactService;
use Illuminate\Console\Command;

/** Tarik kontak dari data existing (customer, pesanan lunas, lead Kirana, WA chat). Izin promo tetap "belum ada". */
class WaCampaignSyncContacts extends Command
{
    protected $signature = 'wa-campaign:sync-contacts';

    protected $description = 'Sinkron kontak WA Campaign dari customer, pesanan, lead Kirana, dan WA chat (tanpa mengubah izin promo)';

    public function handle(WaContactService $contacts): int
    {
        $n = $contacts->syncFromExisting();
        $this->info("Kontak disinkron — customer {$n['customers']}, pesanan {$n['orders']}, lead {$n['leads']}, WA chat {$n['chats']}. Izin promo tidak diubah: kontak tanpa izin tidak akan dikirimi.");

        return self::SUCCESS;
    }
}
