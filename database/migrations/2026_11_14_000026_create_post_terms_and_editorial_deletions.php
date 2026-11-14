<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('content_entries', fn (Blueprint $table) => $table->softDeletes());
        Schema::create('post_terms', function (Blueprint $table): void {
            $table->id(); $table->string('kind',16); $table->string('name',120);
            $table->string('slug',160); $table->string('description',500)->nullable();
            $table->timestamps(); $table->unique(['kind','slug']);
        });
        Schema::create('content_entry_post_term', function (Blueprint $table): void {
            $table->foreignId('content_entry_id')->constrained('content_entries')->cascadeOnDelete();
            $table->foreignId('post_term_id')->constrained('post_terms')->cascadeOnDelete();
            $table->primary(['content_entry_id','post_term_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('content_entry_post_term');
        Schema::dropIfExists('post_terms');
        Schema::table('content_entries', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};