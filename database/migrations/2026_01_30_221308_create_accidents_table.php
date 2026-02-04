<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->string('location');
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['reported', 'dispatched', 'in_progress', 'resolved', 'cancelled'])->default('reported');
            
            // Use geometry column for spatial data (point type)
            $table->geometry('coordinates')->nullable();
            
            // Keep decimal columns for easier access
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            $table->integer('injured_count')->default(0);
            $table->integer('casualty_count')->default(0);
            $table->json('emergency_contacts')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['status', 'severity']);
            $table->index(['latitude', 'longitude']);
        });
        
        // Add spatial index for PostgreSQL
        DB::statement('CREATE INDEX accidents_coordinates_spatialindex ON accidents USING GIST (coordinates)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accidents');
    }
};