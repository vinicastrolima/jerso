<?php

namespace App\Services;

use App\Models\Player;
use App\Models\TablePlayer;
use Illuminate\Support\Collection;

class RankingService
{
    /**
     * Calcula o ranking da mesa atual
     */
    public function calculateTableRanking(Collection $tablePlayers): array
    {
        $ranking = [];

        foreach ($tablePlayers as $tp) {
            $totalBuyin = $tp->totalBuyins();
            $finalAmount = $tp->final_amount;
            $profit = $tp->profit();

            $ranking[] = [
                'table_player_id' => $tp->id,
                'player_id' => $tp->player_id,
                'name' => $tp->name,
                'nickname' => $tp->player?->nickname,
                'avatar_color' => $tp->player?->avatar_color ?? '#10b981',
                'pix_key' => $tp->player?->pix_key,
                'status' => $tp->status,
                'total_buyin' => $totalBuyin,
                'final_amount' => $finalAmount,
                'profit' => $profit,
                'is_winner' => $profit !== null && $profit > 0,
            ];
        }

        // Ordena por lucro decrescente (jogadores com lucro maior primeiro, quem não saiu fica por último ou por lucro atual)
        usort($ranking, function ($a, $b) {
            if ($a['profit'] === null && $b['profit'] === null) return 0;
            if ($a['profit'] === null) return 1;
            if ($b['profit'] === null) return -1;
            return $b['profit'] <=> $a['profit'];
        });

        return $ranking;
    }

    /**
     * Calcula o Hall da Fama (Ranking Geral) de todos os tempos
     */
    public function calculateHallOfFame(): array
    {
        $players = Player::all();
        $leaderboard = [];

        foreach ($players as $player) {
            $sessions = TablePlayer::where('player_id', $player->id)
                ->whereHas('table', function ($q) {
                    $q->where('status', 'closed');
                })
                ->with('buyins')
                ->get();

            $sessionsCount = $sessions->count();
            if ($sessionsCount === 0) {
                $leaderboard[] = [
                    'player_id' => $player->id,
                    'name' => $player->name,
                    'nickname' => $player->nickname,
                    'pix_key' => $player->pix_key,
                    'avatar_color' => $player->avatar_color,
                    'sessions_count' => 0,
                    'total_buyin' => 0.0,
                    'total_cashout' => 0.0,
                    'net_profit' => 0.0,
                    'wins_count' => 0,
                    'win_rate' => 0.0,
                    'best_win' => 0.0,
                    'biggest_loss' => 0.0,
                ];
                continue;
            }

            $totalBuyin = 0.0;
            $totalCashout = 0.0;
            $winsCount = 0;
            $bestWin = 0.0;
            $biggestLoss = 0.0;

            foreach ($sessions as $session) {
                $b = $session->totalBuyins();
                $c = (float) ($session->final_amount ?? 0);
                $p = $c - $b;

                $totalBuyin += $b;
                $totalCashout += $c;

                if ($p > 0.001) {
                    $winsCount++;
                }

                if ($p > $bestWin) {
                    $bestWin = $p;
                }
                if ($p < $biggestLoss) {
                    $biggestLoss = $p;
                }
            }

            $netProfit = $totalCashout - $totalBuyin;
            $winRate = $sessionsCount > 0 ? round(($winsCount / $sessionsCount) * 100, 1) : 0.0;

            $leaderboard[] = [
                'player_id' => $player->id,
                'name' => $player->name,
                'nickname' => $player->nickname,
                'pix_key' => $player->pix_key,
                'avatar_color' => $player->avatar_color,
                'sessions_count' => $sessionsCount,
                'total_buyin' => round($totalBuyin, 2),
                'total_cashout' => round($totalCashout, 2),
                'net_profit' => round($netProfit, 2),
                'wins_count' => $winsCount,
                'win_rate' => $winRate,
                'best_win' => round($bestWin, 2),
                'biggest_loss' => round($biggestLoss, 2),
            ];
        }

        // Ordena por lucro líquido decrescente
        usort($leaderboard, fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);

        return $leaderboard;
    }
}
