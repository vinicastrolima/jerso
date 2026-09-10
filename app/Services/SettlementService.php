<?php

namespace App\Services;

use App\Models\TablePlayer;
use Illuminate\Support\Collection;

class SettlementService
{
    /**
     * Calcula os acertos mínimos de liquidação ("quem paga quem")
     *
     * @param Collection|array $tablePlayers
     * @return array
     */
    public function calculate($tablePlayers): array
    {
        $winners = [];
        $losers = [];

        foreach ($tablePlayers as $tp) {
            $name = is_array($tp) ? ($tp['name'] ?? 'Anônimo') : $tp->name;
            $pixKey = null;
            $avatarColor = '#10b981';

            if (!is_array($tp)) {
                $pixKey = $tp->player?->pix_key;
                $avatarColor = $tp->player?->avatar_color ?? '#10b981';
                $profit = $tp->profit();
            } else {
                $pixKey = $tp['pix_key'] ?? null;
                $avatarColor = $tp['avatar_color'] ?? '#10b981';
                $profit = isset($tp['profit']) ? (float) $tp['profit'] : null;
            }

            if ($profit === null) {
                continue;
            }

            // Arredondar para 2 casas para evitar imprecisões de float
            $profit = round($profit, 2);

            if ($profit > 0.001) {
                $winners[] = [
                    'name' => $name,
                    'value' => $profit,
                    'pix_key' => $pixKey,
                    'avatar_color' => $avatarColor,
                ];
            } elseif ($profit < -0.001) {
                $losers[] = [
                    'name' => $name,
                    'value' => abs($profit),
                    'pix_key' => $pixKey,
                    'avatar_color' => $avatarColor,
                ];
            }
        }

        $settlements = [];
        $i = 0;
        $j = 0;

        while ($i < count($losers) && $j < count($winners)) {
            $amount = min($losers[$i]['value'], $winners[$j]['value']);
            $amount = round($amount, 2);

            if ($amount > 0) {
                $settlements[] = [
                    'from' => $losers[$i]['name'],
                    'to' => $winners[$j]['name'],
                    'to_pix' => $winners[$j]['pix_key'],
                    'value' => $amount,
                ];
            }

            $losers[$i]['value'] = round($losers[$i]['value'] - $amount, 2);
            $winners[$j]['value'] = round($winners[$j]['value'] - $amount, 2);

            if ($losers[$i]['value'] <= 0.001) {
                $i++;
            }
            if ($winners[$j]['value'] <= 0.001) {
                $j++;
            }
        }

        return $settlements;
    }

    /**
     * Gera mensagem formatada pronta para enviar no WhatsApp
     */
    public function generateWhatsAppSummary(string $tableName, array $ranking, array $settlements, float $totalPot): string
    {
        $lines = [];
        $lines[] = "♠️♥️ *ALAPOKER - RESUMO DA MESA* ♦️♣️";
        $lines[] = "🏷️ *Mesa:* {$tableName}";
        $lines[] = "💰 *Pote Total Movimentado:* R$ " . number_format($totalPot, 2, ',', '.');
        $lines[] = "";
        $lines[] = "🏆 *CLASSIFICAÇÃO FINAL:*";

        foreach ($ranking as $idx => $r) {
            $pos = $idx + 1;
            $medal = match ($pos) {
                1 => "🥇",
                2 => "🥈",
                3 => "🥉",
                default => "#{$pos}",
            };
            $profit = (float) ($r['profit'] ?? 0);
            $sign = $profit > 0 ? "+" : ($profit < 0 ? "-" : " ");
            $valFormatted = "R$ " . number_format(abs($profit), 2, ',', '.');
            $lines[] = "{$medal} {$r['name']}: {$sign}{$valFormatted}";
        }

        $lines[] = "";
        $lines[] = "🤝 *ACERTOS (QUEM PAGA QUEM):*";

        if (empty($settlements)) {
            $lines[] = "Nenhum acerto pendente.";
        } else {
            foreach ($settlements as $s) {
                $val = number_format($s['value'], 2, ',', '.');
                $pixInfo = !empty($s['to_pix']) ? " (PIX: {$s['to_pix']})" : "";
                $lines[] = "👉 *{$s['from']}* paga *R$ {$val}* para *{$s['to']}*{$pixInfo}";
            }
        }

        $lines[] = "";
        $lines[] = "Gerado via *AlaPoker* 🚀";

        return implode("\n", $lines);
    }
}
