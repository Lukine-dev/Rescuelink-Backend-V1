<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accident-Responder Many-to-Many
        Schema::create('accident_responder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->onDelete('cascade');
            $table->foreignId('responder_id')->constrained('responders')->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->enum('status', ['assigned', 'en_route', 'on_scene', 'treating', 'transporting', 'completed'])->default('assigned');
            $table->text('notes')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->unique(['accident_id', 'responder_id']);
            $table->index(['status', 'assigned_at']);
        });

        // Accident-Vehicle Many-to-Many
        Schema::create('accident_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accident_id')->constrained('accidents')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->timestamp('dispatched_at')->useCurrent();
            $table->enum('status', ['dispatched', 'en_route', 'on_scene', 'transporting', 'returning', 'completed'])->default('dispatched');
            $table->decimal('distance_traveled', 8, 2)->nullable()->comment('In kilometers');
            $table->integer('fuel_used')->nullable()->comment('In liters');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            
            $table->unique(['accident_id', 'vehicle_id']);
            $table->index(['status', 'dispatched_at']);
        });

        // Responder-Vehicle Many-to-Many (regular assignments)
        Schema::create('responder_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('responder_id')->constrained('responders')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->date('assigned_date');
            $table->date('unassigned_date')->nullable();
            $table->enum('assignment_type', ['primary', 'secondary', 'backup'])->default('primary');
            $table->timestamps();
            
            $table->unique(['responder_id', 'vehicle_id', 'assigned_date']);
            $table->index(['assignment_type', 'assigned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responder_vehicle');
        Schema::dropIfExists('accident_vehicle');
        Schema::dropIfExists('accident_responder');
    }
};