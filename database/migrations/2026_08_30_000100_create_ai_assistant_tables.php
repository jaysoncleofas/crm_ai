<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('model');
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->longText('content')->nullable();

            // Which tools ran for this turn, and which records they touched, so
            // the UI can show its working and link straight to the records.
            $table->json('tool_calls')->nullable();
            $table->json('citations')->nullable();
            $table->json('usage')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['ai_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
