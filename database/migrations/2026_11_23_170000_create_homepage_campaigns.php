<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\AppearanceRevision;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('homepage_campaigns')) {
            Schema::create('homepage_campaigns', function(Blueprint $table) {
                $table->id();
                $table->unsignedInteger('version')->default(0);
                $table->json('draft')->nullable();
                $table->json('published');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('published_at')->nullable();
            });
        }
        $revisions=AppearanceRevision::where('section','home');
        $live=(clone $revisions)->approved()->latest('id')->first();
        $draft=(clone $revisions)->whereIn('status',['draft','pending_review'])->when($live,fn($q)=>$q->where('id','>',$live->id))->latest('id')->first();
        DB::table('homepage_campaigns')->insertOrIgnore(['id'=>1,'published'=>$live?->settings['campaign_playlist'] ?? '[]','draft'=>$draft?->settings['campaign_playlist'] ?? null]);
    }
    public function down(): void { Schema::dropIfExists('homepage_campaigns'); }
};
