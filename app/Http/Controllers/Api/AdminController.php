<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyin;
use App\Models\Player;
use App\Models\PokerTable;
use App\Models\TablePlayer;
use App\Models\TableSnapshot;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(
        protected RankingService $rankingService
    ) {}

    /**
     * Valida o Master Admin PIN
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $request->validate(['pin' => 'required|string']);

        $masterPin = env('ADMIN_PIN', '9999');

        if ((string) $request->pin === (string) $masterPin) {
            return response()->json([
                'success' => true,
                'message' => 'PIN de Administrador verificado com sucesso!',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'PIN de Administrador incorreto.',
        ], 403);
    }

    /**
     * Visão Geral da Banca & Métricas Globais
     */
    public function overview(Request $request): JsonResponse
    {
        $totalTables = PokerTable::count();
        $activeTables = PokerTable::where('status', 'active')->count();
        $closedTables = PokerTable::where('status', 'closed')->count();
        $totalPlayers = Player::count();

        $totalVolume = (float) Buyin::sum('amount');
        $averagePot = $totalTables > 0 ? round($totalVolume / $totalTables, 2) : 0.0;

        $hallOfFame = $this->rankingService->calculateHallOfFame();
        $topPlayer = !empty($hallOfFame) ? $hallOfFame[0] : null;

        $recentTables = PokerTable::withCount('players')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_tables' => $totalTables,
                'active_tables' => $activeTables,
                'closed_tables' => $closedTables,
                'total_registered_players' => $totalPlayers,
                'total_volume_brl' => round($totalVolume, 2),
                'average_pot_brl' => $averagePot,
                'top_player' => $topPlayer ? [
                    'name' => $topPlayer['name'],
                    'net_profit' => $topPlayer['net_profit'],
                    'win_rate' => $topPlayer['win_rate'],
                ] : null,
            ],
            'recent_tables' => $recentTables,
        ]);
    }

    /**
     * Reseta histórico se solicitado pelo Administrador
     */
    public function resetData(Request $request): JsonResponse
    {
        $request->validate(['pin' => 'required|string']);
        $masterPin = env('ADMIN_PIN', '9999');

        if ((string) $request->pin !== (string) $masterPin) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        DB::beginTransaction();
        try {
            TableSnapshot::truncate();
            Buyin::truncate();
            TablePlayer::truncate();
            PokerTable::truncate();
            DB::commit();

            return response()->json(['message' => 'Dados de mesas resetados com sucesso!']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao resetar dados: ' . $e->getMessage()], 500);
        }
    }
}
