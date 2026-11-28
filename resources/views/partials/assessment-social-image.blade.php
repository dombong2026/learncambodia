@if($assessmentImage = \App\Support\AssessmentSocialImage::url($exam))
    @section('og_image', $assessmentImage)
    @section('assessment_image_alt', filled($exam->featured_alt) ? $exam->featured_alt : $exam->title)
@endif
