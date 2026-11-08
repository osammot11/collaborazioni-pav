<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_resource_bindings', function (Blueprint $table) {
            $table->string('credential_hash', 64)->primary();
            $table->string('resource');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_resource_bindings');
    }
};
