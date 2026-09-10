<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PokerTable;
use App\Models\TableSnapshot;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function __construct(
        protected RankingService $rankingService
    ) {}

    /**
     * Hall da Fama Geral (Ranking acumulado de todos os tempos)
     */
    public function hallOfFame(): JsonResponse
    {
        $hallOfFame = $this->rankingService->calculateHallOfFame();

        return response()->json([
            'leaderboard' => $hallOfFame,
            'total_players' => count($hallOfFame),
        ]);
    }

    /**
     * Histórico de mesas encerradas
     */
    public function history(): JsonResponse
    {
        $snapshots = TableSnapshot::with('table')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($s) {
                $ranking = $s->ranking_json ?? [];
                $top = !empty($ranking) ? $ranking[0] : null;

                return [
                    'id' => $s->id,
                    'poker_table_id' => $s->poker_table_id,
                    'table_code' => $s->table?->code,
                    'table_name' => $s->table?->name ?? 'Mesa sem nome',
                    'closed_at' => $s->table?->closed_at?->toIso8601String() ?? $s->created_at->toIso8601String(),
                    'total_buyins' => (float) $s->total_buyins,
                    'total_players' => (int) $s->total_players,
                    'top_player' => $top ? [
                        'name' => $top['name'],
                        'profit' => (float) ($top['profit'] ?? 0),
                    ] : null,
                ];
            });

        return response()->json([
            'history' => $snapshots,
        ]);
    }

    /**
     * Detalhes de uma mesa histórica salva
     */
    public function historyDetail(int $id): JsonResponse
    {
        $snapshot = TableSnapshot::with('table')->findOrFail($id);

        return response()->json([
            'id' => $snapshot->id,
            'table_name' => $snapshot->table?->name,
            'table_code' => $snapshot->table?->code,
            'closed_at' => $snapshot->table?->closed_at?->toIso8601String() ?? $snapshot->created_at->toIso8601String(),
            'total_buyins' => (float) $snapshot->total_buyins,
            'total_players' => (int) $snapshot->total_players,
            'ranking' => $snapshot->ranking_json,
            'settlements' => $snapshot->settlements_json,
            'summary' => $snapshot->summary_json,
        ]);
    }
}
