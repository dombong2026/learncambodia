<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlatformBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_update_branding(): void
    {
        $url = route('admin.system-settings.branding.update');
        $this->put($url, [])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->put($url, [])->assertForbidden();
    }

    public function test_branding_updates_public_identity_and_localized_metadata(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $edit = route('admin.system-settings.branding', ['lang' => 'en']);
        $update = route('admin.system-settings.branding.update', ['lang' => 'en']);

        $this->get($edit)->assertOk()
            ->assertSee('Save changes')
            ->assertDontSee('Edit header')
            ->assertDontSee('Edit footer')
            ->assertDontSee('Edit typography');

        $this->from($edit)->put($update, [
            'name' => 'Learn Cambodia Academy',
            'short_name' => 'LCA Academy',
            'translations' => [
                'en' => ['tagline' => 'Learn with purpose', 'meta_title' => 'English & Exam Preparation', 'meta_description' => 'Explore our English courses.'],
                'km' => ['meta_title' => 'រៀនភាសាអង់គ្លេស', 'meta_description' => 'ស្វែងរកវគ្គសិក្សា'],
            ],
        ])->assertRedirect($edit)->assertSessionHas('status', 'Branding saved.');

        $this->get('/?lang=en')->assertOk()
            ->assertSee('<title>English &amp; Exam Preparation</title>', false)
            ->assertSee('<meta name="description" content="Explore our English courses.">', false)
            ->assertSee('Learn with purpose')
            ->assertSee('brand/platform.webmanifest', false);

        $this->get('/?lang=km')->assertOk()
            ->assertSee('<title>រៀនភាសាអង់គ្លេស</title>', false)
            ->assertDontSee('Learn with purpose');

        $this->get(route('information.show', ['page' => 'about', 'lang' => 'en']))->assertOk()
            ->assertDontSee('<title>English &amp; Exam Preparation</title>', false)
            ->assertDontSee('<meta name="description" content="Explore our English courses.">', false);

        $this->get(route('brand.manifest'))->assertOk()->assertJsonPath('short_name', 'LCA Academy');
    }

    public function test_assets_are_validated_versioned_and_retained_on_text_only_updates(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $edit = route('admin.system-settings.branding');
        $url = route('admin.system-settings.branding.update');
        $identity = ['name' => 'Learn Cambodia', 'short_name' => 'LearnCambodia'];

        $this->from($edit)->put($url, $identity + [
            'uploads' => ['favicon-16' => $this->bundledImage('favicon-32.png')],
        ])->assertRedirect($edit)->assertSessionHasErrors('uploads.favicon-16');
        $this->assertSame([], Storage::disk('local')->allFiles('site-branding'));

        $this->from($edit)->put($url, $identity + [
            'uploads' => [
                'favicon-16' => $this->bundledImage('favicon-16.png'),
                'app-icon-192' => $this->bundledImage('android-chrome-192x192.png'),
                'share-image' => $this->bundledImage('logo-light.png'),
            ],
        ])->assertRedirect($edit)->assertSessionHasNoErrors();

        $this->put($url, $identity)->assertRedirect($edit)->assertSessionHasNoErrors();
        $homepage = $this->get('/?lang=en')->assertOk()
            ->assertSee('/brand/assets/favicon-16/', false)
            ->assertDontSee('brand/favicon.ico', false);

        preg_match('~property="og:image" content="([^"]+/brand/assets/share-image/[A-Z0-9]+)"~', $homepage->getContent(), $shareImage);
        $this->assertNotEmpty($shareImage);
        $this->get($shareImage[1])->assertOk()->assertHeader('Content-Type', 'image/png');

        $icons = $this->get(route('brand.manifest'))->assertOk()->json('icons');
        $this->assertStringContainsString('/brand/assets/app-icon-192/', $icons[0]['src']);
        $this->assertNotContains('maskable/192x192', array_map(fn (array $icon) => $icon['purpose'].'/'.$icon['sizes'], $icons));
    }

    private function bundledImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('brand/'.$name)));
    }
}
