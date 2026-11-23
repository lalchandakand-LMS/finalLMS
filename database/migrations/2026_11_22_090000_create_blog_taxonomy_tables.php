<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('blog_categories', function (Blueprint $table): void { $table->id(); $table->string('name',100); $table->string('slug',120)->unique(); $table->text('description')->nullable(); $table->timestamps(); });
  Schema::create('blog_tags', function (Blueprint $table): void { $table->id(); $table->string('name',80); $table->string('slug',100)->unique(); $table->timestamps(); });
  Schema::create('blog_category_post', function (Blueprint $table): void { $table->foreignId('blog_category_id')->constrained('blog_categories')->cascadeOnDelete(); $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete(); $table->primary(['blog_category_id','blog_post_id']); });
  Schema::create('blog_post_tag', function (Blueprint $table): void { $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete(); $table->foreignId('blog_tag_id')->constrained('blog_tags')->cascadeOnDelete(); $table->primary(['blog_post_id','blog_tag_id']); });
 }
 public function down(): void { Schema::dropIfExists('blog_post_tag'); Schema::dropIfExists('blog_category_post'); Schema::dropIfExists('blog_tags'); Schema::dropIfExists('blog_categories'); }
};
