<?php

namespace Tests\Feature;

use App\Models\EditorMedia;
use App\Models\User;
use App\Support\ManagedFiles;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ManagedStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_checks_all_managed_prefixes_without_overwriting_conflicts_or_copying_server_files(): void
    {
        $local = Storage::fake('local');
        $cloud = Storage::fake('r2');
        config(['filesystems.disks.r2.driver' => 'local']);

        foreach (ManagedFiles::PREFIXES as $prefix) {
            $local->put("$prefix/sample.bin", "$prefix bytes");
        }
        $local->put('learncambodia.installed', 'local state');
        $local->put('application.env', 'local secret');

        $this->artisan('storage:managed-transfer')->expectsOutput('Files: 3 | Bytes: 65 | Copied: 0 | Verified: 0 | Errors: 0')->assertSuccessful();
        $this->assertSame([], $cloud->allFiles());

        $this->artisan('storage:managed-transfer', ['--mode' => 'probe'])->assertSuccessful();
        $this->assertSame([], $cloud->allFiles());
        $this->artisan('storage:managed-transfer', ['--mode' => 'copy'])->assertSuccessful();
        $this->assertCount(3, $cloud->allFiles());
        foreach (ManagedFiles::PREFIXES as $prefix) {
            $this->assertSame($local->get("$prefix/sample.bin"), $cloud->get("$prefix/sample.bin"));
        }

        $this->artisan('storage:managed-transfer', ['--mode' => 'copy'])
            ->expectsOutput('Files: 3 | Bytes: 65 | Copied: 0 | Verified: 3 | Errors: 0')->assertSuccessful();

        $cloud->put('editor-media/sample.bin', 'different destination object');
        $this->artisan('storage:managed-transfer', ['--mode' => 'copy'])->assertFailed();
        $this->assertSame('different destination object', $cloud->get('editor-media/sample.bin'));
        $this->assertSame('editor-media bytes', $local->get('editor-media/sample.bin'));

        $cloud->put('editor-media/sample.bin', 'editor-media bytes');
        $cloud->put('editor-media/cloud-only.bin', 'written after cutover');
        $this->artisan('storage:managed-transfer', ['--mode' => 'copy', '--direction' => 'to-local'])->assertSuccessful();
        $this->assertSame('written after cutover', $local->get('editor-media/cloud-only.bin'));
        $this->artisan('storage:managed-transfer', ['--mode' => 'verify', '--direction' => 'to-local'])->assertSuccessful();
    }

    public function test_missing_database_reference_fails_verification(): void
    {
        Storage::fake('local');
        Storage::fake('r2');
        config(['filesystems.disks.r2.driver' => 'local']);
        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => 'account-avatars/missing.png'])->save();

        $this->artisan('storage:managed-transfer', ['--mode' => 'verify'])->assertFailed();
    }

    public function test_cloud_mode_stores_new_avatar_and_uploads_and_keeps_existing_access_rules(): void
    {
        $local = Storage::fake('local');
        $cloud = Storage::fake('r2');
        config(['filesystems.managed' => 'r2', 'filesystems.disks.r2.driver' => 'local']);
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.profile.update'), [
            'name' => $admin->name, 'avatar' => UploadedFile::fake()->image('portrait.png'),
        ])->assertSessionHasNoErrors();

        $avatar = $admin->fresh()->avatar_path;
        $cloud->assertExists($avatar);
        $local->assertMissing($avatar);
        $this->actingAs($admin->fresh())->get(route('workspace.avatar'))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->postJson(route('admin.media-library.store'), ['file' => UploadedFile::fake()->image('cover.png')])->assertCreated();
        $media = EditorMedia::sole();
        $cloud->assertExists($media->path);
        $local->assertMissing($media->path);
        $this->actingAs($student)->get(route('editor-media.show', $media))->assertForbidden();
        $this->actingAs($admin)->get(route('editor-media.show', $media))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->delete(route('admin.media-library.destroy', $media))->assertRedirect();
        $cloud->assertMissing($media->path);
    }

    public function test_s3_requests_use_private_upload_and_forward_media_ranges(): void
    {
        $fake = Storage::fake('r2');
        $handler = new MockHandler([
            new Result([]),
            new Result(['Body' => Utils::streamFor('3456'), 'ContentLength' => 4, 'ContentRange' => 'bytes 3-6/10']),
            new Result(['ContentLength' => 10]),
        ]);
        $client = new S3Client([
            'version' => 'latest', 'region' => 'auto', 'endpoint' => 'https://objects.example.test',
            'credentials' => ['key' => 'isolated-fixture', 'secret' => 'isolated-fixture'],
            'use_path_style_endpoint' => true, 'handler' => $handler,
        ]);
        $disk = new AwsS3V3Adapter($fake->getDriver(), $fake->getAdapter(), ['bucket' => 'private', 'root' => 'site'], $client);
        Storage::set('r2', $disk);
        config(['filesystems.managed' => 'r2', 'filesystems.disks.r2.driver' => 'local']);

        $path = 'editor-media/movie.mp4';
        $this->assertTrue(ManagedFiles::put($disk, $path, '0123456789', ifMissing: true));
        $this->assertSame('site/'.$path, $handler->getLastCommand()['Key']);
        $this->assertSame('*', $handler->getLastRequest()->getHeaderLine('If-None-Match'));
        $this->assertFalse($handler->getLastRequest()->hasHeader('x-amz-acl'));

        $request = Request::create('/editor-media/1', 'GET', server: ['HTTP_RANGE' => 'bytes=3-6']);
        $response = ManagedFiles::response($request, $path, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store'], seekable: true);
        $this->assertSame('bytes=3-6', $handler->getLastRequest()->getHeaderLine('Range'));
        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 3-6/10', $response->headers->get('Content-Range'));
        ob_start();
        $response->sendContent();
        $this->assertSame('3456', ob_get_clean());

        $head = ManagedFiles::response(Request::create('/editor-media/1', 'HEAD'), $path, [], seekable: true);
        $this->assertSame('10', $head->headers->get('Content-Length'));
        $this->assertSame('HeadObject', $handler->getLastCommand()->getName());
    }
}
