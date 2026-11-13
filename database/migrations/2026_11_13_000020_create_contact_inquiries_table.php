<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void { Schema::create('contact_inquiries', function(Blueprint $t): void {
$t->id();$t->string('name',120);$t->string('email',255);$t->string('topic',32)->index();
$t->string('subject',160);$t->text('message');$t->timestamp('consented_at');
$t->string('status',24)->default('new')->index();
$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
$t->timestamp('reviewed_at')->nullable();$t->string('review_note',500)->nullable();$t->timestamps();
}); }
public function down(): void { Schema::dropIfExists('contact_inquiries'); }
};