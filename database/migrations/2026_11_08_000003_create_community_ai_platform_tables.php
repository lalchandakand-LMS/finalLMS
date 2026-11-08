<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility', 20)->default('public');
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('published');
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('community_post_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('community_post_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('published');
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('teacher_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'student_id']);
        });

        Schema::create('discussion_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('visibility', 20)->default('enrolled');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'created_at']);
        });

        Schema::create('discussion_room_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('discussion_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            $table->unique(['room_id', 'user_id']);
        });

        Schema::create('discussion_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('discussion_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->timestamps();
            $table->index(['room_id', 'id']);
        });

        Schema::create('direct_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'read_at', 'id']);
            $table->index(['sender_id', 'recipient_id', 'id']);
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 180);
            $table->text('description');
            $table->string('category', 40)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open');
            $table->text('diagnostic_summary')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('lms_plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 160);
            $table->string('version', 40)->default('1.0.0');
            $table->string('provider', 160)->nullable();
            $table->text('description')->nullable();
            $table->json('manifest');
            $table->string('status', 20)->default('disabled');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_plugins');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('direct_messages');
        Schema::dropIfExists('discussion_messages');
        Schema::dropIfExists('discussion_room_members');
        Schema::dropIfExists('discussion_rooms');
        Schema::dropIfExists('teacher_follows');
        Schema::dropIfExists('community_post_comments');
        Schema::dropIfExists('community_post_likes');
        Schema::dropIfExists('community_posts');
    }
};
