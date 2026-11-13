<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Support\Appearance;
use App\Models\Course;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\ContentEntry;
use App\Models\PublicProfile;

class InformationController extends Controller
{
    private const PAGES = [
        'about' => ['About Learn Cambodia', 'Learn Cambodia is developing accessible English-language pathways for students and educators worldwide.', 'The curriculum directory organizes English study, professional communication, language assessments and teacher development around internationally recognizable terminology.'],
        'instructors' => ['Our Instructors', 'Qualified educators and transparent instructor profiles.', 'Instructor profiles are published only after identity, teaching experience and qualification checks. No instructor profiles have been approved for publication yet.'],
        'blog' => ['Blog & Study Tips', 'Clear and practical language-learning guidance.', 'Educational articles will be published following editorial and academic review.'],
        'careers' => ['Careers & Teach With Us', 'Opportunities to support language education.', 'Faculty and staff recruitment information will be published following institutional approval.'],
        'partners' => ['Partners & Schools', 'Building responsible education partnerships.', 'Institutional partners will be named only after verified agreements and publication consent.'],
        'placement' => ['Free Placement Test', 'Finding the right starting level matters.', 'A validated online placement assessment is being designed. The CEFR reference guide is available now. This page does not provide a formal CEFR classification.'],
        'scores' => ['Exam Score Guide', 'Understand examination scores in context.', 'Score scales and score-to-CEFR mappings vary by test and are determined by the relevant examination bodies. Refer to official examination providers for current interpretations.'],
        'faq' => ['Frequently Asked Questions', 'Learning paths, levels and examinations.', 'Browse the course-category directory for pathways, or explore the CEFR level guide. Preparation information on this website does not constitute an official test booking or award.'],
        'contact' => ['Contact Us', 'Connect with the Learn Cambodia team.', 'Verified public school contact channels will be published once the institution has approved them. For information about the project author, visit tonsamith.com.'],
        'privacy' => ['Privacy Notice — Draft', 'Privacy policy pending legal review.', 'Accounts currently capture name, email and password hashes, plus acceptance time. Data-retention periods, contact details and regional disclosures must be approved before public launch.'],
        'terms' => ['Terms of Use — Draft', 'Terms pending legal review.', 'Learn Cambodia provides informational education content and a developing school platform. No third-party certification, exam-centre authorization or assessment result is promised. Final legal terms must be approved before public launch.'],
    ];

    public function show(string $page): View
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        [$title, $lede, $body] = self::PAGES[$page];
        if (in_array($page, ['about', 'contact'], true)) {
            $title = Appearance::value($page, 'heading');
            $lede = Appearance::value($page, 'intro');
        }

        if ($page==='about') {
            $courseCount=Course::discoverable()->count();
            $categoryCount=Category::published()->count();
            $cefrLevelCount=CefrLevel::query()->count();
            $publishedArticles=ContentEntry::query()->where('type','post')->where('status','published')->count();
            $instructors=PublicProfile::visible()->where('kind','instructor')
                ->orderBy('display_name')->limit(4)->get();

            return view('information.about',compact(
                'title','lede','body','page','courseCount','categoryCount','publishedArticles','instructors','cefrLevelCount'
            ));
        }

        if ($page==='contact') {
            return view('information.contact',compact('title','lede','body','page'));
        }

        return view('information.show', compact('title', 'lede', 'body', 'page'));
    }
}
