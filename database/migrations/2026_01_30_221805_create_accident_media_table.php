<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accident_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('media_type', ['photo', 'video', 'document', 'audio', 'other'])->default('photo');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            $table->index(['accident_id', 'media_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_media');
    }
};