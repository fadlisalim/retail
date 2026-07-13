<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Ties a review to a specific purchased line so one item = one review.
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            // Hidden reviews are excluded from rating aggregates.
            $table->boolean('is_visible')->default(true);
            $table->text('admin_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamp('edit_deadline_at')->nullable();
            $table->timestamps();

            $table->unique('order_item_id'); // one review per purchased item
            $table->index(['product_id', 'is_visible']);
            $table->index(['product_id', 'rating']);
        });

        Schema::create('review_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('image'); // image|video
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('review_helpfulness', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->text('detail')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->text('question');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_visible']);
        });

        Schema::create('product_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_staff')->default(false);
            $table->text('answer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_answers');
        Schema::dropIfExists('product_questions');
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_helpfulness');
        Schema::dropIfExists('review_media');
        Schema::dropIfExists('reviews');
    }
};
