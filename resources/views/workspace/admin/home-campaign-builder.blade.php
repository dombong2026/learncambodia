<section class="mt-6 rounded-xl border border-slate-300 p-4 dark:border-slate-700" data-campaign-builder>
    <h2 class="text-lg font-bold">Homepage course builder</h2>
    <p class="my-2 text-sm">Add campaign lessons in playback order. Use the same course title to group lessons in the sidebar. Publish through Home Appearance review.</p>
    <input type="hidden" name="campaign_editor" value="1">
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'campaign'))
            <p role="alert">{{ implode(' ', $messages) }}</p>
        @endif
    @endforeach
    @php $rows = old('campaign', json_decode($settings['campaign_playlist'] ?? '[]', true) ?: []); @endphp
    <div data-rows>
        @foreach((array) $rows as $index => $row)
            @include('workspace.admin.home-campaign-row', ['index' => $index, 'row' => (array) $row])
        @endforeach
    </div>
    <template>@include('workspace.admin.home-campaign-row', ['index' => 'INDEX', 'row' => []])</template>
    <button class="mt-3 font-bold underline" type="button" data-add>Add campaign lesson</button>
</section>
<script defer src="{{ asset('js/home-campaign-builder.js') }}"></script>
