<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('email');
            $table->string('phone')->nullable()->after('job_title');
            $table->boolean('is_active')->default(true)->index()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn([
                'job_title', 'phone', 'is_active', 'last_login_at',
                'deleted_at', 'created_by', 'updated_by', 'deleted_by',
            ]);
        });
    }
};
