<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('open')->index(); // open | won | lost
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('expected_close_date')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->string('won_reason')->nullable();
            $table->string('lost_reason')->nullable();
            $table->string('source')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('custom_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['pipeline_stage_id', 'deleted_at']);
            $table->index(['owner_id', 'status', 'deleted_at']);
        });

        Schema::create('contact_deal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['deal_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_deal');
        Schema::dropIfExists('deals');
    }
};
