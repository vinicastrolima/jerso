<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyin;
use App\Models\Player;
use App\Models\PokerTable;
use App\Models\TablePlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TablePlayerController extends Controller
{
    /**
     * Valida PIN da mesa
     */
    protected function authorizeManager(Request $request, PokerTable $table): void
    {
        $pin = $request->header('X-Table-Pin') ?? $request->input('pin');
        if (!$pin || !$table->verifyPin((string) $pin)) {
            abort(response()->json(['message' => 'Ação bloqueada. É necessário informar o PIN de Gerente.'], 403));
        }
    }

    /**
     * Adiciona um novo jogador à mesa com buy-in inicial
     */
    public function addPlayer(Request $request, string $code): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->firstOrFail();
        $this->authorizeManager($request, $table);

        if ($table->status === 'closed') {
            return response()->json(['message' => 'Esta mesa já foi encerrada.'], 400);
        }

        $validated = $request->validate([
            'player_id' => 'nullable|exists:players,id',
            'name' => 'nullable|string|max:100',
            'buyin_amount' => 'required|numeric|min:0.01',
        ]);

        $name = $validated['name'] ?? null;
        $playerId = $validated['player_id'] ?? null;

        if ($playerId) {
            $p = Player::find($playerId);
            $name = $p?->name ?? $name;
        }

        if (empty($name)) {
            return response()->json(['message' => 'Nome do participante é obrigatório.'], 422);
        }

        // Verifica se já existe ativo com esse nome
        $exists = TablePlayer::where('poker_table_id', $table->id)
            ->where('name', $name)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Já existe um jogador ativo com este nome na mesa.'], 422);
        }

        $tablePlayer = TablePlayer::create([
            'poker_table_id' => $table->id,
            'player_id' => $playerId,
            'name' => $name,
            'status' => 'active',
        ]);

        Buyin::create([
            'table_player_id' => $tablePlayer->id,
            'amount' => (float) $validated['buyin_amount'],
        ]);

        return response()->json([
            'message' => "{$name} adicionado à mesa com sucesso!",
            'player' => $tablePlayer->load(['player', 'buyins']),
        ], 201);
    }

    /**
     * Adiciona mais um Buy-in (+ Dinheiro / Re-buy)
     */
    public function addBuyin(Request $request, string $code, int $tablePlayerId): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->firstOrFail();
        $this->authorizeManager($request, $table);

        if ($table->status === 'closed') {
            return response()->json(['message' => 'Mesa encerrada.'], 400);
        }

        $tablePlayer = TablePlayer::where('poker_table_id', $table->id)
            ->where('id', $tablePlayerId)
            ->firstOrFail();

        if ($tablePlayer->status === 'cashed_out') {
            return response()->json(['message' => 'Jogador já saiu da mesa. Reabra o jogador para adicionar fichas.'], 400);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $buyin = Buyin::create([
            'table_player_id' => $tablePlayer->id,
            'amount' => (float) $validated['amount'],
        ]);

        return response()->json([
            'message' => "Entrada de R$ " . number_format($buyin->amount, 2, ',', '.') . " adicionada para {$tablePlayer->name}.",
            'buyin' => $buyin,
            'total_buyins' => $tablePlayer->totalBuyins(),
        ]);
    }

    /**
     * Atualiza o valor de uma entrada específica
     */
    public function updateBuyin(Request $request, string $code, int $tablePlayerId, int $buyinId): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->firstOrFail();
        $this->authorizeManager($request, $table);

        $tablePlayer = TablePlayer::where('poker_table_id', $table->id)
            ->where('id', $tablePlayerId)
            ->firstOrFail();

        $buyin = Buyin::where('table_player_id', $tablePlayer->id)
            ->where('id', $buyinId)
            ->firstOrFail();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $buyin->amount = (float) $validated['amount'];
        $buyin->save();

        return response()->json([
            'message' => 'Entrada atualizada com sucesso!',
            'buyin' => $buyin,
            'total_buyins' => $tablePlayer->totalBuyins(),
        ]);
    }

    /**
     * Remove uma entrada específica
     */
    public function deleteBuyin(Request $request, string $code, int $tablePlayerId, int $buyinId): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->firstOrFail();
        $this->authorizeManager($request, $table);

        $tablePlayer = TablePlayer::where('poker_table_id', $table->id)
            ->where('id', $tablePlayerId)
            ->firstOrFail();

        if ($tablePlayer->buyins()->count() <= 1) {
            return response()->json(['message' => 'Não é permitido remover a única entrada do participante.'], 422);
        }

        $buyin = Buyin::where('table_player_id', $tablePlayer->id)
            ->where('id', $buyinId)
            ->firstOrFail();

        $buyin->delete();

        return response()->json([
            'message' => 'Entrada removida com sucesso.',
            'total_buyins' => $tablePlayer->totalBuyins(),
        ]);
    }

    /**
     * Registra a saída / cashout do jogador da mesa
     */
    public function cashout(Request $request, string $code, int $tablePlayerId): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->firstOrFail();
        $this->authorizeManager($request, $table);

        $tablePlayer = TablePlayer::where('poker_table_id', $table->id)
            ->where('id', $tablePlayerId)
            ->firstOrFail();

        $validated = $request->validate([
            'final_amount' => 'required|numeric|min:0',
        ]);

        $tablePlayer->final_amount = (float) $validated['final_amount'];
        $tablePlayer->status = 'cashed_out';
        $tablePlayer->cashed_out_at = now();
        $tablePlayer->save();

        $profit = $tablePlayer->profit();
        $statusMsg = $profit > 0 ? "LUCRO de R$ " . number_format($profit, 2, ',', '.') : ($profit < 0 ? "PREJUÍZO de R$ " . number_format(abs($profit), 2, ',', '.') : "ZERO a ZERO");

        return response()->json([
            'message' => "{$tablePlayer->name} saiu com {$statusMsg}.",
            'player' => $tablePlayer->load(['player', 'buyins']),
            'profit' => $profit,
        ]);
    }

    /**
     * Reabre o jogador para ativo (desfazendo a saída)
     */
    public function reopenPlayer(Request $request, string $code, int $tablePlayerId): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->firstOrFail();
        $this->authorizeManager($request, $table);

        $tablePlayer = TablePlayer::where('poker_table_id', $table->id)
            ->where('id', $tablePlayerId)
            ->firstOrFail();

        $tablePlayer->status = 'active';
        $tablePlayer->final_amount = null;
        $tablePlayer->cashed_out_at = null;
        $tablePlayer->save();

        return response()->json([
            'message' => "{$tablePlayer->name} voltou para a mesa como jogador ativo.",
            'player' => $tablePlayer->load(['player', 'buyins']),
        ]);
    }
}
