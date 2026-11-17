<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Support\PublicId;
use Illuminate\View\View;

final class PublicDocumentController extends Controller
{
    public function page(string $slug): View
    {
        return $this->show('page', $slug);
    }

    public function policy(string $slug): View
    {
        return $this->show('policy', $slug);
    }

    public function policies(): View
    {
        $policies = ContentEntry::query()->where('type', 'policy')->where('status', 'published')
            ->orderBy('title')->paginate(12);

        return view('information.policies', compact('policies'));
    }

    private function show(string $type, string $slug): View
    {
        $document = PublicId::lookup(ContentEntry::with('featuredMedia')
            ->where('type', $type)->where('status', 'published'), $slug);

        return view('information.document', compact('document'));
    }
}
