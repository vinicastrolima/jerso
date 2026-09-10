<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Jogadores frequentes (Catálogo / Diretório)
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('pix_key')->nullable();
            $table->string('avatar_color')->default('#10b981');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Mesas de Poker
        Schema::create('poker_tables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('currency', 10)->default('BRL');
            $table->string('unit', 20)->default('money');
            $table->string('status', 20)->default('active'); // active, closed
            $table->string('pin_hash');
            $table->string('pin_plain')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // 3. Participantes da Mesa
        Schema::create('table_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poker_table_id')->constrained('poker_tables')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('active'); // active, cashed_out
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->timestamp('cashed_out_at')->nullable();
            $table->timestamps();
        });

        // 4. Entradas / Buy-ins
        Schema::create('buyins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_player_id')->constrained('table_players')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // 5. Snapshots de Mesas Encerradas
        Schema::create('table_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poker_table_id')->constrained('poker_tables')->cascadeOnDelete();
            $table->decimal('total_buyins', 12, 2)->default(0);
            $table->integer('total_players')->default(0);
            $table->json('ranking_json')->nullable();
            $table->json('settlements_json')->nullable();
            $table->json('summary_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_snapshots');
        Schema::dropIfExists('buyins');
        Schema::dropIfExists('table_players');
        Schema::dropIfExists('poker_tables');
        Schema::dropIfExists('players');
    }
};
