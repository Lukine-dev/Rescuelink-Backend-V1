<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('department');
            $table->string('specialization')->nullable();
            $table->string('badge_number')->unique();
            $table->enum('status', ['active', 'inactive', 'suspended', 'retired'])->default('active');
            $table->enum('availability', ['available', 'on_duty', 'on_break', 'off_duty'])->default('available');
            $table->string('contact_number');
            $table->string('emergency_contact')->nullable();
            
            // Use geometry column for spatial data
            $table->geometry('location_coordinates')->nullable();
            
            // Keep decimal columns
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('joined_date');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['status', 'availability']);
            $table->index(['current_latitude', 'current_longitude']);
        });
        
        // Add spatial index
        DB::statement('CREATE INDEX responders_location_spatialindex ON responders USING GIST (location_coordinates)');
    }

    public function down(): void
    {
        Schema::dropIfExists('responders');
    }
};