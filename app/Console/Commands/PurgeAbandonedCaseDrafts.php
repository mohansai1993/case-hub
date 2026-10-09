<?php

namespace App\Console\Commands;

use App\Models\LegalCase;
use Illuminate\Console\Command;

class PurgeAbandonedCaseDrafts extends Command
{
    protected $signature = 'cases:purge-abandoned-drafts';

    protected $description = 'Deletes draft cases (and any evidence already uploaded to them) that were started but never submitted within the TTL.';

    public function handle(): int
    {
        $ttlHours = (int) config('casehub.case_drafts.ttl_hours');

        $drafts = LegalCase::whereNull('submitted_at')
            ->where('created_at', '<', now()->subHours($ttlHours))
            ->with('documents')
            ->get();

        foreach ($drafts as $draft) {
            $draft->purgeWithDocuments();
        }

        $this->info("{$drafts->count()} abandoned draft case(s) purged.");

        return self::SUCCESS;
    }
}
