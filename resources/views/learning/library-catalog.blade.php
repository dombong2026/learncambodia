@extends('layouts.public')
@section('title', 'Digital Library')
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
    <link rel="stylesheet" href="{{ asset('css/library-catalog.css?v=6') }}">
@endpush

@section('content')
@php
    $hasFilters = $search !== '' || !empty($filters['kind']) || !empty($filters['access']) || ($filters['sort'] ?? '') === 'title';
@endphp
<div class="lc-library lc-library-catalog">
    <div class="lc-lib-frame lc-lib-section">
        <header class="lc-lib-head lc-catalog-header">
            <h1 id="library-title">Digital Library</h1>
        </header>
        <div class="lc-catalog-layout">
            <aside class="lc-catalog-sidebar" aria-labelledby="library-filters-title">
                <div class="lc-catalog-filter-panel">
                    <div class="lc-lib-head lc-catalog-filter-head">
                        <h2 id="library-filters-title">Filters</h2>
                        @if($hasFilters)
                            <a class="lc-catalog-reset" href="{{ route('learning.library', ['lang' => app()->getLocale()]) }}#collection">Clear filters</a>
                        @endif
                    </div>
                    <form action="{{ route('learning.library') }}#collection" method="get" role="search" class="lc-lib-find lc-catalog-filter">
                        <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                        <div class="lc-lib-find-query">
                            <label for="library-search">Search library</label>
                            <input type="search" id="library-search" name="q" maxlength="100" value="{{ $search }}" placeholder="Title, author, or topic">
                        </div>
                        <div>
                            <label for="library-kind">Material</label>
                            <select id="library-kind" name="kind">
                                <option value="">All materials</option>
                                @foreach(\App\Models\LibraryItem::KINDS as $key => $label)
                                    <option value="{{ $key }}" @selected(($filters['kind'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="library-access">Access</label>
                            <select id="library-access" name="access">
                                <option value="">All access types</option>
                                @foreach(\App\Models\LibraryItem::ACCESS as $key => $label)
                                    <option value="{{ $key }}" @selected(($filters['access'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="library-sort">Sort by</label>
                            <select id="library-sort" name="sort">
                                <option value="latest">Recently added</option>
                                <option value="title" @selected(($filters['sort'] ?? '') === 'title')>Title A–Z</option>
                            </select>
                        </div>
                        <button class="lc-lib-button lc-lib-button--primary" type="submit">Apply filters</button>
                    </form>
                </div>
            </aside>
            <section class="lc-catalog-results" id="collection" aria-label="Library results">
                @if($items->isNotEmpty())
                    <div class="lc-lib-grid">
                        @foreach($items as $item)
                            <x-student-library-card :item="$item" :show-open-access="false" :show-author-avatar="true"/>
                        @endforeach
                    </div>
                @else
                    <div class="lc-lib-empty">
                        <x-workspace-icon name="library"/>
                        <p>{{ $search !== '' || !empty($filters['kind']) || !empty($filters['access'])
                            ? 'No library items match your search.' : 'Books and files will appear as they are published.' }}</p>
                    </div>
                @endif
                <x-student-library-pagination :items="$items" :jump-query="['lang' => app()->getLocale()] + $filters"/>
            </section>
        </div>
    </div>
</div>
@endsection
