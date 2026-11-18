<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

final class PublicDocumentController extends Controller
{
    public function page(string $slug): View
    {
        $document = PublicId::lookup(ContentEntry::with('featuredMedia')
            ->where('type', 'page')->where('status', 'published'), $slug);

        return view('information.document', compact('document'));
    }

    public function policy(string $slug): View
    {
        $document = PublicId::lookup($this->publishedPolicies()->with('featuredMedia'), $slug);
        $policyNavigation = $this->publishedPolicies()->get(['id', 'public_id', 'type', 'title']);

        return view('information.document', compact('document', 'policyNavigation'));
    }

    public function policies(): View
    {
        $policies = $this->publishedPolicies()->paginate(12)->appends(['lang' => app()->getLocale()]);

        return view('information.policies', compact('policies'));
    }

    private function publishedPolicies(): Builder
    {
        return ContentEntry::query()->where('type', 'policy')->where('status', 'published')
            ->orderBy('title')->orderBy('id');
    }
}
