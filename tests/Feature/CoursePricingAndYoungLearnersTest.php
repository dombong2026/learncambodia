<?php

namespace Tests\Feature;

use App\Console\Commands\ExpandYoungLearnersCourse;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\CoursePricing;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CoursePricingAndYoungLearnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_changes_at_the_configured_cambodia_times(): void
    {
        $pricing = [
            'model' => 'paid', 'currency' => 'KHR', 'base_minor' => 50000, 'adjusted_minor' => 40000,
            'strategy' => 'seasonal', 'starts' => '2026-12-01T08:00', 'ends' => '2026-12-03T14:00', 'label' => 'Holiday offer',
        ];
        $at = fn ($date) => CoursePricing::quote($pricing, CarbonImmutable::parse($date, 'UTC'));
        $this->assertSame(50000, $at('2026-12-01 00:59')['amount_minor']);
        $this->assertSame('Holiday offer', $at('2026-12-01 01:00')['offer']);
        $this->assertSame(40000, $at('2026-12-03 06:59')['amount_minor']);
        $this->assertSame(50000, $at('2026-12-03 07:00')['amount_minor']);
        $this->assertSame(70000, CoursePricing::quote(array_replace($pricing, ['strategy' => 'increase', 'adjusted_minor' => 70000]),
            CarbonImmutable::parse('2026-12-01 01:00', 'UTC'))['amount_minor']);
    }

    public function test_pricing_is_saved_and_enrollment_uses_the_reviewed_quote(): void
    {
        $this->seed(CategorySeeder::class);
        $author = User::factory()->create(['role' => 'admin']);
        $data = [
            'title' => 'Course Pricing', 'summary' => 'Learner summary', 'description' => 'Full description',
            'category_id' => Category::where('code', 'TEYL')->valueOrFail('id'), 'delivery_mode' => 'online',
            'pricing' => ['model' => 'paid', 'currency' => 'USD', 'strategy' => 'discount', 'base' => '15.25', 'adjusted' => '12.50'],
        ];
        $invalid = $data;
        $invalid['pricing']['adjusted'] = '23.00';
        $this->actingAs($author)->postJson(route('admin.course-builder.store'), $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors('pricing.adjusted');
        $this->post(route('admin.course-builder.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $course = Course::sole();
        $this->assertSame(1250, CoursePricing::quote($course->pricing)['amount_minor']);
        $this->get(route('admin.course-builder.edit', $course))->assertOk()->assertSee('Membership');
        $course->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $learner = User::factory()->create(['role' => 'student']);
        $this->actingAs($learner)->get(route('courses.show', $course->slug))->assertOk()
            ->assertSee('USD 15.25')->assertSee('USD 12.50');
        $request = route('account.enrollments.store', $course);
        $this->post($request, ['pricing_quote' => '{}'])->assertConflict();
        $this->post($request, [
            'pricing_quote' => json_encode(CoursePricing::quote($course->pricing)),
            'payment_provider' => 'stripe',
            'payment_status' => 'paid',
            'payment_reference' => 'customer-supplied',
        ])->assertRedirect(route('account.enrollments.index'));
        $enrollment = CourseEnrollment::sole();
        $this->assertSame(1250, $enrollment->pricing_quote['amount_minor']);
        $this->assertSame('pending', $enrollment->status);
        $this->get(route('account.enrollments.show', $enrollment))->assertForbidden();
        $review = route('admin.reviews.enrollment', $enrollment);
        $this->actingAs($author)->postJson($review, ['decision' => 'approve'])
            ->assertUnprocessable()->assertJsonValidationErrors('pricing_reference');
        $this->post($review, ['decision' => 'approve', 'pricing_reference' => 'Receipt QA-123'])->assertRedirect();
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertStringContainsString('QA-123', $enrollment->fresh()->review_note);
    }

    public function test_expansion_can_be_reapplied_and_published_through_the_normal_review(): void
    {
        $this->seed(CategorySeeder::class);
        $author = User::factory()->create(['role' => 'admin']);
        $reviewer = User::factory()->create(['role' => 'admin']);
        $course = Course::create([
            'slug' => ExpandYoungLearnersCourse::SLUG, 'title' => 'Teaching Young Learners',
            'summary' => 'Course summary', 'description' => 'Full description', 'status' => 'draft',
            'category_id' => Category::where('code', 'TEYL')->valueOrFail('id'), 'created_by' => $author->id,
        ]);
        $module = $course->modules()->create(['title' => 'Stories and prediction', 'position' => 1]);
        $module->lessons()->create(['title' => 'Existing lesson', 'content' => 'Existing lesson content.', 'duration_minutes' => 12]);
        $this->artisan('courses:expand-young-learners')->expectsOutput('TEYL_LESSONS_ADDED=8')->assertSuccessful();
        $this->artisan('courses:expand-young-learners')->expectsOutput('TEYL_LESSONS_ADDED=0')->assertSuccessful();
        $this->assertSame(9, $course->lessons()->count());
        $this->assertSame(8, $course->lessons()->whereNotNull('quiz_questions')->count());
        $this->actingAs($author)->post(route('admin.course-builder.submit', $course))->assertRedirect();
        $this->actingAs($reviewer)->post(route('admin.reviews.course', $course), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('published', $course->fresh()->status);
        $this->get(route('courses.show', $course->slug))->assertOk()
            ->assertSee('Classroom application and microteaching')->assertSee('Free');
    }
}
