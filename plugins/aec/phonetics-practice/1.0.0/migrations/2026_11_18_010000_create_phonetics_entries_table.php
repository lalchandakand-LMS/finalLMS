<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
 public function up(): void {
  if(Schema::hasTable('phonetics_entries'))return;
  Schema::create('phonetics_entries',function(Blueprint $table):void{
   $table->id();$table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
   $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $table->string('entry_type',16)->default('word');$table->string('term',180);$table->text('translation');
   $table->string('uk_ipa',180)->nullable();$table->string('us_ipa',180)->nullable();
   $table->string('uk_audio_path',500)->nullable();$table->string('us_audio_path',500)->nullable();
   $table->text('example_sentence')->nullable();$table->text('notes')->nullable();$table->string('status',16)->default('draft')->index();
   $table->timestamps();$table->index(['course_id','status']);$table->index(['term','status']);
  });
 }
 public function down(): void {
  if(!Schema::hasTable('phonetics_entries'))return;
  if(DB::table('phonetics_entries')->exists())throw new RuntimeException('Phonetics content exists; back up content before removing plugin tables.');
  Schema::dropIfExists('phonetics_entries');
 }
};
