<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RoleDashboardFixture;
use Tests\TestCase;

final class RoleDashboardGlassTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_cards_preserve_real_titles_dates_counts_and_destinations(): void
    {
        $fixture = RoleDashboardFixture::create();
        foreach ([
            'student' => ['/account', 'Dashboard fixture deadline', route('account.homework.show', $fixture['assignment'])],
            'instructor' => ['/instructor', 'Dashboard fixture grading', route('instructor.grading.show', $fixture['submission'])],
            'developer' => ['/developer', __('dashboard.active_tokens'), route('developer.tokens.index')],
        ] as $role => [$landing, $label, $url]) {
            $html = $this->actingAs($fixture['accounts'][$role])->get($landing.'?lang=en')
                ->assertOk()->assertSee('data-dashboard-role-rows', false)
                ->assertDontSee('data-dashboard-review-row', false)->getContent();

            $document = new \DOMDocument();
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $rows = $xpath->query('//*[@data-dashboard-role-row]');
            $found = false;
            foreach ($rows as $row) {
                $actualLabel = trim($xpath->query('.//*[@data-dashboard-role-label]', $row)->item(0)->textContent);
                if ($actualLabel !== $label) {
                    continue;
                }
                $found = true;
                $this->assertSame(1, $xpath->query('.//*[local-name()="svg"]', $row)->length);
                $this->assertSame(1, $xpath->query('.//*[@data-dashboard-role-detail]', $row)->length);
                $meta = trim($xpath->query('.//*[@data-dashboard-role-meta]', $row)->item(0)->textContent);
                $this->assertSame(parse_url($url, PHP_URL_PATH), parse_url($row->getAttribute('href'), PHP_URL_PATH));
                if ($role === 'developer') {
                    $this->assertSame('0', $meta);
                    $this->assertCount(3, $rows);
                } else {
                    $date = $role === 'student' ? $fixture['assignment']->due_at : $fixture['submission']->submitted_at;
                    $this->assertSame($date->format('M j, H:i'), $meta);
                }
            }
            $this->assertTrue($found, $role.' role record missing');
        }
        $this->actingAs($fixture['accounts']['admin'])->get('/dashboard')
            ->assertOk()->assertSee('data-dashboard-review-row', false)
            ->assertDontSee('data-dashboard-role-rows', false);
    }

    public function test_empty_student_and_instructor_timelines_remain_honest(): void
    {
        foreach (['student' => ['/account', 'dashboard.no_deadlines'], 'instructor' => ['/instructor', 'dashboard.no_grading']] as $role => [$url, $message]) {
            $user = User::factory()->create();
            $user->forceFill(['role' => $role])->save();
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('data-dashboard-role-empty', false)
                ->assertSee(__($message))
                ->assertDontSee('data-dashboard-role-row ', false);
        }
    }
}
