<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 64)->unique();
            $table->json('data');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('token');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verification_tokens');
    }
};