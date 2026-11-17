<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class RunManagedStorageTransfer implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1800;
    public bool $failOnTimeout = true;

    public function __construct(public int $transferId)
    {
        $this->onConnection('managed-storage')->onQueue('managed-storage');
    }

    public function handle(): void
    {
        $transfers = DB::table('managed_storage_transfers');
        if (!(clone $transfers)->where('id', $this->transferId)->where('status', 'queued')
            ->update(['status' => 'running', 'updated_at' => now()])) {
            return;
        }

        if (config('filesystems.managed') !== 'local') {
            $this->finish('failed', 'The active disk changed before this operation started.');

            return;
        }

        $transfer = (clone $transfers)->find($this->transferId);
        $output = new BufferedOutput;
        $exitCode = Artisan::call('storage:managed-transfer', [
            '--mode' => $transfer->operation,
            '--provider' => $transfer->provider,
        ], $output);

        preg_match('/^Files: \d+ \| Bytes: \d+ \| Copied: \d+ \| Verified: \d+ \| Errors: \d+$/m', $output->fetch(), $summary);
        $this->finish($exitCode === 0 ? 'passed' : 'failed', $summary[0] ?? null);
    }

    public function failed(?Throwable $exception): void
    {
        $this->finish('failed', null);
    }

    private function finish(string $status, ?string $summary): void
    {
        DB::table('managed_storage_transfers')->where('id', $this->transferId)
            ->update(['status' => $status, 'summary' => $summary, 'updated_at' => now()]);
    }
}
