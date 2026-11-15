<?php

namespace App\Console\Commands;

use App\Enums\GuestStatus;
use App\Models\Guest;
use Illuminate\Console\Command;

/** UU PDP: personal data of checked-out guests is anonymised after the retention period (stay dates stay for statistics). */
class GuestPrivacyCommand extends Command
{
    protected $signature = 'sangatv:privasi-tamu';

    protected $description = 'Anonimkan data tamu yang sudah check-out melewati masa simpan';

    public function handle(): int
    {
        $days = (int) config('sangatv.guest_retention_days');
        $n = Guest::query()
            ->where('status', GuestStatus::CheckedOut)
            ->where('checked_out_at', '<', now()->subDays($days))
            ->where('name', '!=', 'Tamu (dianonimkan)')
            ->update(['name' => 'Tamu (dianonimkan)', 'salutation' => null, 'external_ref' => null, 'favorite_channel_ids' => null]);

        $this->info("{$n} data tamu dianonimkan (check-out > {$days} hari).");

        return self::SUCCESS;
    }
}
