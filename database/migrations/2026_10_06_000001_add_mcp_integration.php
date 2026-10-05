<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::table('collaborations', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(1));
        Schema::create('mcp_token_resources', function (Blueprint $table): void {
            $table->string('token_id', 80)->primary();
            $table->string('resource');
            $table->timestamp('created_at');
        });
        Schema::create('mcp_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->uuid('client_id');
            $table->uuid('request_id');
            $table->string('action');
            $table->string('payload_hash', 64);
            $table->unsignedBigInteger('collaboration_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'client_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_operations');
        Schema::dropIfExists('mcp_token_resources');
        Schema::table('collaborations', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
