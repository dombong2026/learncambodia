@php
    $correct = 0;
    $total = 0;
    foreach ($attempt->snapshot['questions'] as $index => $question) {
        if (!\App\Support\AssessmentQuestions::manual($question)) {
            $total += $question['points'];
            if (\App\Support\AssessmentQuestions::correct($question, $attempt->answers[$index] ?? '')) {
                $correct += $question['points'];
            }
        }
    }
@endphp
<p><strong>Screening score {{ $correct }} / {{ $total }}</strong></p>
@foreach ($attempt->snapshot['questions'] as $index => $question)
    @if (\App\Support\AssessmentQuestions::manual($question))
        @php($label = ucfirst($question['format']))
        @if (isset($attempt->grading[$index]))
            <p>{{ $label }} score {{ $attempt->grading[$index]['points'] }} / {{ $question['points'] }}</p>
        @elseif (filled($attempt->answers[$index] ?? null))
            <p>{{ $label }} is awaiting instructor review.</p>
        @else
            <p>No {{ strtolower($label) }} response was submitted.</p>
        @endif
    @endif
@endforeach
<p>Use the writing feedback to help choose an EAP starting level.</p>
