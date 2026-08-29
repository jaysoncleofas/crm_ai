<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();   // call | email | meeting | note | task
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('status')->default('planned')->index(); // planned | completed | canceled
            $table->string('direction')->nullable();  // inbound | outbound (calls/emails)
            $table->string('outcome')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('related');   // Contact | Company | Deal

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['owner_id', 'status', 'due_at']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
