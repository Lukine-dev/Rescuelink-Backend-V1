<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('license_plate')->unique();
            $table->enum('vehicle_type', [
                'ambulance', 
                'fire_truck', 
                'police_car', 
                'rescue_van', 
                'motorcycle',
                'helicopter',
                'other'
            ])->default('ambulance');
            $table->string('model');
            $table->year('year');
            $table->enum('status', ['available', 'in_use', 'maintenance', 'out_of_service'])->default('available');
            $table->string('current_location')->nullable();
            
            // Use geometry column for spatial data
            $table->geometry('vehicle_coordinates')->nullable();
            
            // Keep decimal columns
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            
            $table->integer('fuel_level')->nullable()->comment('Percentage 0-100');
            $table->integer('odometer_reading')->nullable();
            $table->date('last_maintenance')->nullable();
            $table->date('next_maintenance')->nullable();
            $table->text('equipment_list')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['status', 'vehicle_type']);
            $table->index(['current_latitude', 'current_longitude']);
        });
        
        // Add spatial index
        DB::statement('CREATE INDEX vehicles_location_spatialindex ON vehicles USING GIST (vehicle_coordinates)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};