<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('job_title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lifecycle_stage')->default('lead')->index();
            $table->string('lead_status')->default('new')->index();
            $table->unsignedSmallInteger('lead_score')->default(0);
            $table->string('source')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamp('last_contacted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['deleted_at', 'last_name', 'first_name']);
            $table->index(['company_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
