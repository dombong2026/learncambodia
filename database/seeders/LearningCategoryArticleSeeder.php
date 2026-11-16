<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\EditorMedia;
use App\Models\User;
use App\Support\ApprovalAudit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LearningCategoryArticleSeeder extends Seeder
{
    private const COVER_ALT = [
        'english-courses' => 'Learners practising a conversation in a classroom',
        'exam-preparation' => 'A learner studying with a laptop and notebook',
        'teacher-development' => 'Teachers planning a lesson together',
        'specialist-english' => 'A professional working at a desk',
        'frameworks' => 'A notebook and study materials for planning language goals',
    ];

    public function run(int $authorId): void
    {
        $author = User::where('role', User::ROLE_ADMIN)->findOrFail($authorId);
        $source = base_path('support/editorial/category-articles');
        $entries = $this->readArticles($source.'/articles.txt');
        $categories = Category::published()->whereIn('code', array_keys($entries))->get();
        $files = [];
        $report = [];

        try {
            DB::transaction(function () use ($author, $source, $entries, $categories, &$files, &$report): void {
                $covers = [];
                foreach ($categories as $category) {
                    $category = Category::query()->lockForUpdate()->findOrFail($category->id);
                    if (!$category->is_published) {
                        continue;
                    }

                    $created = 0;
                    foreach ($entries[$category->code] as [$title, $summary, $explanation, $exercise]) {
                        $slug = Str::slug($category->code.'-'.$title);
                        if (ContentEntry::withTrashed()->where('type', 'post')->where('slug', $slug)->exists()) {
                            continue;
                        }

                        $cover = $covers[$category->group] ??= $this->storeCover($source, $category->group, $author, $files);
                        $article = ContentEntry::create([
                            'type' => 'post',
                            'category_id' => $category->id,
                            'title' => $title,
                            'slug' => $slug,
                            'summary' => $summary,
                            'body' => implode("\n\n", [$summary, $explanation, $exercise]),
                            'status' => 'published',
                            'published_at' => now(),
                            'created_by' => $author->id,
                            'updated_by' => $author->id,
                            'featured_kind' => 'image',
                            'featured_media_id' => $cover->id,
                            'featured_alt' => self::COVER_ALT[$category->group],
                        ]);
                        ApprovalAudit::record('editorial_post', $article->id, $author, 'published', 'draft', 'published');
                        $created++;
                    }
                    $report[$category->code] = $created;
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($files);
            throw $exception;
        }

        foreach ($report as $code => $count) {
            $this->command?->line($code.'='.$count);
        }
        $this->command?->info('Newly published articles: '.array_sum($report));
        $unmatched = Category::published()->whereNotIn('code', array_keys($entries))->pluck('code')->all();
        if ($unmatched) {
            $this->command?->warn('Active categories without prepared articles: '.implode(', ', $unmatched));
        }
    }

    private function readArticles(string $source): array
    {
        $entries = [];
        $code = null;
        foreach (file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\[([A-Z0-9]+)\]$/D', $line, $matches)) {
                $code = $matches[1];
                if (isset($entries[$code])) {
                    throw new RuntimeException('Duplicate category '.$code);
                }
                $entries[$code] = [];
                continue;
            }

            $fields = array_map('trim', explode('|', $line));
            if (!$code || count($fields) !== 4 || in_array('', $fields, true)
                || Str::length($fields[0]) > 200 || Str::length($fields[1]) > 400) {
                throw new RuntimeException('Invalid article row for '.$code);
            }
            $entries[$code][] = $fields;
        }

        foreach ($entries as $code => $articles) {
            if (count($articles) !== 5 || count(array_unique(array_column($articles, 0))) !== 5) {
                throw new RuntimeException('Exactly five distinct articles are required for '.$code);
            }
        }

        return $entries;
    }

    private function storeCover(string $source, string $group, User $author, array &$files): EditorMedia
    {
        if (!isset(self::COVER_ALT[$group])) {
            throw new RuntimeException('No cover is configured for '.$group);
        }

        $file = $source.'/'.$group.'.webp';
        $bytes = file_get_contents($file);
        if ($bytes === false || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'image/webp') {
            throw new RuntimeException('Invalid cover for '.$group);
        }

        $path = 'editor-media/learning-'.Str::uuid().'.webp';
        $files[] = $path;
        if (!Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Could not store the cover for '.$group);
        }

        return EditorMedia::create([
            'uploaded_by' => $author->id,
            'context' => 'library',
            'path' => $path,
            'mime' => 'image/webp',
            'original_name' => 'Learning Articles - '.Category::GROUPS[$group].'.webp',
            'size' => strlen($bytes),
        ]);
    }
}
