<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('guardian_student_links', function (Blueprint $table): void {
   $table->id();
   $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
   $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
   $table->string('relationship',60)->default('Guardian');
   $table->string('status',20)->default('pending');
   $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
   $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamp('requested_at')->useCurrent();
   $table->timestamp('reviewed_at')->nullable();
   $table->timestamps();
   $table->unique(['guardian_id','student_id']);
   $table->index(['guardian_id','status','requested_at']);
   $table->index(['student_id','status','requested_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('guardian_student_links'); }
};
