<?php

namespace App\Support;

use Aws\Exception\AwsException;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ManagedFiles
{
    public const PREFIXES = ['editor-media', 'account-avatars', 'assessment-responses'];

    public static function diskName(): string
    {
        $name = config('filesystems.managed');

        if (!in_array($name, ['local', 'r2', 's3'], true)) {
            throw new RuntimeException('MANAGED_STORAGE_DISK must be local, r2, or s3.');
        }

        return $name;
    }

    public static function disk(?string $name = null): FilesystemAdapter
    {
        $name ??= self::diskName();
        if ($name === 'r2' && config('filesystems.disks.r2.driver') === 's3') {
            $config = config('filesystems.disks.r2');
            $endpoint = parse_url((string) $config['endpoint']);

            if (blank($config['key']) || blank($config['secret']) || blank($config['bucket'])
                || ($endpoint['scheme'] ?? null) !== 'https' || empty($endpoint['host'])
                || isset($endpoint['user']) || isset($endpoint['pass']) || isset($endpoint['query']) || isset($endpoint['fragment'])) {
                throw new RuntimeException('Configure the private bucket, HTTPS endpoint and R2 credentials.');
            }
        }

        return Storage::disk($name === 's3' ? 'managed-s3' : $name);
    }

    public static function store(UploadedFile $file, string $directory, ?string $filename = null): string|false
    {
        $path = $filename === null ? $file->hashName($directory) : "$directory/$filename";
        $source = fopen($file->getRealPath(), 'rb');
        if ($source === false) {
            return false;
        }

        try {
            return self::put(self::disk(), $path, $source) ? $path : false;
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }

    /** @param resource|string $contents */
    public static function put(FilesystemAdapter $disk, string $path, mixed $contents, bool $ifMissing = false): bool
    {
        if (!$disk instanceof AwsS3V3Adapter) {
            return $ifMissing ? self::putNewLocalFile($disk, $path, $contents) : $disk->put($path, $contents);
        }

        // The generic Flysystem uploader always supplies an ACL. R2 bucket policy controls access instead.
        $options = $ifMissing ? ['IfNoneMatch' => '*'] : [];

        try {
            $disk->getClient()->putObject(self::object($disk, $path) + ['Body' => $contents] + $options);
        } catch (AwsException $exception) {
            if ($ifMissing && $exception->getStatusCode() === 412) {
                return false;
            }

            throw new RuntimeException('Cloud storage rejected the upload.');
        }

        return true;
    }

    /** @param resource|string $contents */
    private static function putNewLocalFile(FilesystemAdapter $disk, string $path, mixed $contents): bool
    {
        $disk->makeDirectory(dirname($path));
        $destination = $disk->path($path);
        $temporary = tempnam(dirname($destination), '.cloud-transfer-');
        if ($temporary === false) {
            throw new RuntimeException('Could not stage a transfer file.');
        }

        try {
            if (file_put_contents($temporary, $contents) === false) {
                throw new RuntimeException('Could not write a transfer file.');
            }
            if (@link($temporary, $destination)) {
                return true;
            }
            if (is_file($destination)) {
                return false;
            }

            throw new RuntimeException('Could not install a transfer file.');
        } finally {
            unlink($temporary);
        }
    }

    public static function response(
        Request $request,
        string $path,
        array $headers,
        bool $seekable = false,
        ?string $downloadName = null,
    ): Response {
        $disk = self::disk();
        if ($disk instanceof AwsS3V3Adapter && $seekable) {
            return self::cloudStream($request, $disk, $path, $headers);
        }

        abort_unless($disk->fileExists($path), 404);

        if ($downloadName !== null) {
            unset($headers['Content-Disposition']);

            return $disk->download($path, $downloadName, $headers);
        }

        if ($seekable && self::diskName() === 'local') {
            return new BinaryFileResponse($disk->path($path), 200, $headers, false);
        }

        return $disk->response($path, null, $headers);
    }

    private static function cloudStream(Request $request, AwsS3V3Adapter $disk, string $path, array $headers): Response
    {
        $s3 = $disk->getClient();
        $object = self::object($disk, $path);
        $headers['Accept-Ranges'] = 'bytes';

        try {
            if ($request->isMethod('HEAD')) {
                $size = $s3->headObject($object)['ContentLength'];

                return response('', 200, $headers + ['Content-Length' => (string) $size]);
            }

            $range = $request->headers->get('Range', '');
            $options = !$request->headers->has('If-Range') && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/D', $range)
                ? ['Range' => $range] : [];
            $result = $s3->getObject($object + $options + ['@http' => ['stream' => true]]);
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 416) {
                try {
                    $size = $s3->headObject($object)['ContentLength'];
                } catch (AwsException $headError) {
                    self::abortRead($headError);
                }

                return response('', 416, $headers + ['Content-Range' => "bytes */$size"]);
            }
            self::abortRead($exception);
        }

        $headers['Content-Length'] = (string) $result['ContentLength'];
        if (isset($result['ContentRange'])) {
            $headers['Content-Range'] = $result['ContentRange'];
        }

        return new StreamedResponse(static function () use ($result): void {
            $body = $result['Body'];
            try {
                while (!$body->eof() && !connection_aborted()) {
                    echo $body->read(65536);
                }
            } finally {
                $body->close();
            }
        }, isset($headers['Content-Range']) ? 206 : 200, $headers);
    }

    private static function object(AwsS3V3Adapter $disk, string $path): array
    {
        return ['Bucket' => $disk->getConfig()['bucket'], 'Key' => $disk->path($path)];
    }

    private static function abortRead(AwsException $exception): never
    {
        abort($exception->getStatusCode() === 404 || $exception->getAwsErrorCode() === 'NoSuchKey' ? 404 : 503);
    }
}
