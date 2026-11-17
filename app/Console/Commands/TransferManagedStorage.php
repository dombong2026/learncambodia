<?php

namespace App\Console\Commands;

use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Support\ManagedFiles;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TransferManagedStorage extends Command
{
    protected $signature = 'storage:managed-transfer
        {--mode=inventory : inventory, probe, copy, or verify}
        {--direction=to-cloud : to-cloud or to-local}';

    protected $description = 'Inventory, copy or verify LearnCambodia uploads without removing source files';

    public function handle(): int
    {
        $mode = $this->option('mode');
        $direction = $this->option('direction');
        if (!in_array($mode, ['inventory', 'probe', 'copy', 'verify'], true)
            || !in_array($direction, ['to-cloud', 'to-local'], true)) {
            $this->error('Choose a supported mode and transfer direction.');

            return self::INVALID;
        }

        try {
            if ($mode === 'probe') {
                $this->probe(ManagedFiles::disk('r2'));
                $this->info('Cloud storage write, read, and delete checks passed.');

                return self::SUCCESS;
            }

            [$from, $to] = $direction === 'to-cloud' ? ['local', 'r2'] : ['r2', 'local'];
            $source = ManagedFiles::disk($from);
            $target = $mode === 'inventory' ? null : ManagedFiles::disk($to);

            return $this->transfer($source, $target, $mode);
        } catch (Throwable) {
            $this->error('The storage operation could not complete. Check the database, disk settings, endpoint and permissions.');

            return self::FAILURE;
        }
    }

    private function transfer(FilesystemAdapter $source, ?FilesystemAdapter $target, string $mode): int
    {
        $failures = $this->checkReferences($source);
        $total = $bytes = $copied = $verified = 0;
        foreach (ManagedFiles::PREFIXES as $prefix) {
            foreach ($source->getDriver()->listContents($prefix, true) as $object) {
                if (!$object->isFile() || str_starts_with(basename($object->path()), '.cloud-transfer-')) {
                    continue;
                }

                $path = $object->path();
                $total++;
                try {
                    $bytes += $object->fileSize() ?? $source->size($path);
                    if (!$target) {
                        continue;
                    }

                    $digest = $this->digest($source, $path);
                    if (!$target->fileExists($path)) {
                        if ($mode === 'verify') {
                            throw new RuntimeException('Destination object missing.');
                        }

                        $file = $this->open($source, $path);
                        try {
                            if (ManagedFiles::put($target, $path, $file, ifMissing: true)) {
                                $copied++;
                            }
                        } finally {
                            if (is_resource($file)) {
                                fclose($file);
                            }
                        }
                    }

                    if (!hash_equals($digest, $this->digest($target, $path))) {
                        throw new RuntimeException('Destination checksum differs.');
                    }
                    $verified++;
                } catch (Throwable) {
                    $failures++;
                    $this->error("Could not verify or copy $path. A different destination object is never overwritten.");
                }
            }
        }

        $this->line("Files: $total | Bytes: $bytes | Copied: $copied | Verified: $verified | Errors: $failures");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function checkReferences(FilesystemAdapter $source): int
    {
        $errors = 0;
        foreach (EditorMedia::select('id', 'path')->lazyById() as $record) {
            if (!$this->hasReference($source, $record->path, 'editor-media')) {
                $this->error("Media record {$record->id} has a missing or invalid source file.");
                $errors++;
            }
        }
        foreach (User::select('id', 'avatar_path')->whereNotNull('avatar_path')->lazyById() as $user) {
            if (!$this->hasReference($source, $user->avatar_path, 'account-avatars')) {
                $this->error("Avatar for account record {$user->id} has a missing or invalid source file.");
                $errors++;
            }
        }
        foreach (ExamAttempt::select('id', 'answers', 'snapshot')->whereNotNull('answers')->lazyById() as $attempt) {
            foreach ($attempt->snapshot['questions'] ?? [] as $index => $question) {
                $path = $attempt->answers[$index] ?? null;
                if (($question['format'] ?? null) === 'speaking' && $path !== null
                    && !$this->hasReference($source, $path, 'assessment-responses')) {
                    $this->error("Assessment attempt {$attempt->id} has a missing or invalid recording.");
                    $errors++;
                }
            }
        }

        return $errors;
    }

    private function hasReference(FilesystemAdapter $source, mixed $path, string $prefix): bool
    {
        return is_string($path) && str_starts_with($path, "$prefix/")
            && !str_contains($path, '\\') && !in_array('..', explode('/', $path), true)
            && $source->fileExists($path);
    }

    private function digest(FilesystemAdapter $disk, string $path): string
    {
        $file = $this->open($disk, $path);
        try {
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $file);
            if (!feof($file) || $bytes !== $disk->size($path)) {
                throw new RuntimeException('Object read ended before its declared size.');
            }

            return hash_final($hash);
        } finally {
            fclose($file);
        }
    }

    /** @return resource */
    private function open(FilesystemAdapter $disk, string $path)
    {
        $file = $disk->readStream($path);
        if (!is_resource($file)) {
            throw new RuntimeException('Object could not be opened.');
        }

        return $file;
    }

    private function probe(FilesystemAdapter $disk): void
    {
        $path = 'cloud-probes/'.Str::uuid().'.bin';
        $payload = random_bytes(64);

        try {
            if (!ManagedFiles::put($disk, $path, $payload, ifMissing: true)
                || !hash_equals(hash('sha256', $payload), $this->digest($disk, $path))) {
                throw new RuntimeException('Cloud probe verification failed.');
            }
        } finally {
            $disk->delete($path);
        }

        if ($disk->fileExists($path)) {
            throw new RuntimeException('Cloud probe could not be removed.');
        }
    }
}
