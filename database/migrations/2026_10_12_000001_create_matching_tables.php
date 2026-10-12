<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_batches', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });
        Schema::create('matching_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('matching_batch_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('title');
            $t->json('headers')->nullable();
            $t->string('filename')->nullable();
            $t->unsignedInteger('row_count')->default(0);
            $t->timestamps();
            $t->unique(['matching_batch_id', 'code'], 'mp_batch_code');
        });
        Schema::create('matching_people', function (Blueprint $t) {
            $t->id();
            $t->foreignId('matching_batch_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('matching_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('matching_post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('matching_person_id')->nullable()->constrained('matching_people')->nullOnDelete();
            $t->unsignedInteger('source_row');
            $t->json('data');
            $t->json('identity');
            $t->string('status', 20);
            $t->json('suggestions')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['matching_post_id', 'matching_person_id'], 'mr_post_person');
            $t->index(['matching_post_id', 'status'], 'mr_post_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_records');
        Schema::dropIfExists('matching_people');
        Schema::dropIfExists('matching_posts');
        Schema::dropIfExists('matching_batches');
    }
};
