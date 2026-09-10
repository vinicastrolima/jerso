<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\TablePlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerDirectoryController extends Controller
{
    /**
     * Lista jogadores frequentes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Player::query();

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $players = $query->orderBy('name', 'asc')->get()->map(function ($player) {
            $sessions = TablePlayer::where('player_id', $player->id)
                ->whereHas('table', fn ($q) => $q->where('status', 'closed'))
                ->with('buyins')
                ->get();

            $totalBuyins = $sessions->sum(fn ($s) => $s->totalBuyins());
            $totalCashouts = $sessions->sum(fn ($s) => (float) ($s->final_amount ?? 0));
            $netProfit = $totalCashouts - $totalBuyins;

            return [
                'id' => $player->id,
                'name' => $player->name,
                'nickname' => $player->nickname,
                'pix_key' => $player->pix_key,
                'avatar_color' => $player->avatar_color,
                'is_active' => $player->is_active,
                'sessions_count' => $sessions->count(),
                'net_profit' => round($netProfit, 2),
                'total_buyins' => round($totalBuyins, 2),
            ];
        });

        return response()->json([
            'players' => $players,
        ]);
    }

    /**
     * Cadastra um novo jogador frequente
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:players,name',
            'nickname' => 'nullable|string|max:60',
            'pix_key' => 'nullable|string|max:100',
            'avatar_color' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:500',
        ]);

        $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#06b6d4', '#14b8a6', '#f97316'];
        $defaultColor = $colors[array_rand($colors)];

        $player = Player::create([
            'name' => trim($validated['name']),
            'nickname' => !empty($validated['nickname']) ? trim($validated['nickname']) : null,
            'pix_key' => !empty($validated['pix_key']) ? trim($validated['pix_key']) : null,
            'avatar_color' => $validated['avatar_color'] ?? $defaultColor,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Jogador cadastrado com sucesso!',
            'player' => $player,
        ], 201);
    }

    /**
     * Atualiza dados do jogador frequente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $player = Player::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:players,name,' . $player->id,
            'nickname' => 'nullable|string|max:60',
            'pix_key' => 'nullable|string|max:100',
            'avatar_color' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $player->update($validated);

        return response()->json([
            'message' => 'Jogador atualizado com sucesso!',
            'player' => $player,
        ]);
    }

    /**
     * Remove jogador cadastrado
     */
    public function destroy(int $id): JsonResponse
    {
        $player = Player::findOrFail($id);
        $player->delete();

        return response()->json([
            'message' => 'Jogador removido do diretório.',
        ]);
    }
}
