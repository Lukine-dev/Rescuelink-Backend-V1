<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('type')->default('SOS');
            $table->text('description')->nullable();


            // Spatial data
            $table->geometry('coordinates')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);

            $table->enum('status', [
                'sent',
                'acknowledged',
                'responding',
                'resolved',
                'cancelled'
            ])->default('sent');

            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['latitude', 'longitude']);
        });

        DB::statement(
            'CREATE INDEX sos_coordinates_spatialindex 
             ON sos_requests USING GIST (coordinates)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_requests');
    }
};
