<?php

namespace Database\Seeders;

use App\Models\Buyin;
use App\Models\Player;
use App\Models\PokerTable;
use App\Models\TablePlayer;
use App\Models\TableSnapshot;
use App\Services\RankingService;
use App\Services\SettlementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PokerSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cadastra jogadores frequentes
        $playersData = [
            [
                'name' => 'Vini Castro',
                'nickname' => 'vinicastrolima',
                'pix_key' => 'vini@pix.com',
                'avatar_color' => '#10b981',
            ],
            [
                'name' => 'Lucas Rocha',
                'nickname' => 'rocha_poker',
                'pix_key' => '11988887777',
                'avatar_color' => '#3b82f6',
            ],
            [
                'name' => 'Gabriel Ramos',
                'nickname' => 'raminhos',
                'pix_key' => 'gabriel@email.com',
                'avatar_color' => '#8b5cf6',
            ],
            [
                'name' => 'Rodrigo Silva',
                'nickname' => 'rodriguinho',
                'pix_key' => '11977776666',
                'avatar_color' => '#f59e0b',
            ],
            [
                'name' => 'Matheus Costa',
                'nickname' => 'costinha',
                'pix_key' => 'matheus@pix.me',
                'avatar_color' => '#ec4899',
            ],
        ];

        $createdPlayers = [];
        foreach ($playersData as $data) {
            $createdPlayers[] = Player::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }

        // 2. Cria uma mesa histórica encerrada para alimentar o Hall da Fama e histórico
        $historyTable = PokerTable::firstOrCreate(
            ['code' => 'MESA01'],
            [
                'name' => 'Poker Night - Sexta Clássica',
                'currency' => 'BRL',
                'unit' => 'money',
                'status' => 'closed',
                'pin_hash' => Hash::make('1234'),
                'pin_plain' => '1234',
                'closed_at' => now()->subDays(3),
            ]
        );

        if ($historyTable->wasRecentlyCreated || $historyTable->players()->count() === 0) {
            // Adiciona participantes e buyins
            // Vini: 100 in, 250 out (+150)
            $tp1 = TablePlayer::create([
                'poker_table_id' => $historyTable->id,
                'player_id' => $createdPlayers[0]->id,
                'name' => $createdPlayers[0]->name,
                'status' => 'cashed_out',
                'final_amount' => 250.0,
                'cashed_out_at' => now()->subDays(3)->addHours(4),
            ]);
            Buyin::create(['table_player_id' => $tp1->id, 'amount' => 100.0]);

            // Lucas: 200 in, 180 out (-20)
            $tp2 = TablePlayer::create([
                'poker_table_id' => $historyTable->id,
                'player_id' => $createdPlayers[1]->id,
                'name' => $createdPlayers[1]->name,
                'status' => 'cashed_out',
                'final_amount' => 180.0,
                'cashed_out_at' => now()->subDays(3)->addHours(4),
            ]);
            Buyin::create(['table_player_id' => $tp2->id, 'amount' => 100.0]);
            Buyin::create(['table_player_id' => $tp2->id, 'amount' => 100.0]);

            // Gabriel: 100 in, 0 out (-100)
            $tp3 = TablePlayer::create([
                'poker_table_id' => $historyTable->id,
                'player_id' => $createdPlayers[2]->id,
                'name' => $createdPlayers[2]->name,
                'status' => 'cashed_out',
                'final_amount' => 0.0,
                'cashed_out_at' => now()->subDays(3)->addHours(3),
            ]);
            Buyin::create(['table_player_id' => $tp3->id, 'amount' => 100.0]);

            // Rodrigo: 100 in, 70 out (-30)
            $tp4 = TablePlayer::create([
                'poker_table_id' => $historyTable->id,
                'player_id' => $createdPlayers[3]->id,
                'name' => $createdPlayers[3]->name,
                'status' => 'cashed_out',
                'final_amount' => 70.0,
                'cashed_out_at' => now()->subDays(3)->addHours(4),
            ]);
            Buyin::create(['table_player_id' => $tp4->id, 'amount' => 100.0]);

            $rankingService = app(RankingService::class);
            $settlementService = app(SettlementService::class);

            $tps = TablePlayer::where('poker_table_id', $historyTable->id)->with(['player', 'buyins'])->get();
            $ranking = $rankingService->calculateTableRanking($tps);
            $settlements = $settlementService->calculate($tps);

            TableSnapshot::create([
                'poker_table_id' => $historyTable->id,
                'total_buyins' => 500.0,
                'total_players' => 4,
                'ranking_json' => $ranking,
                'settlements_json' => $settlements,
                'summary_json' => [
                    'total_buyins' => 500.0,
                    'total_players' => 4,
                    'closed_at' => now()->subDays(3)->toIso8601String(),
                ],
            ]);
        }

        // 3. Cria uma mesa ativa pronta para teste e compartilhamento ao vivo
        $activeTable = PokerTable::firstOrCreate(
            ['code' => 'DEMO77'],
            [
                'name' => 'Mesa Principal - Ao Vivo',
                'currency' => 'BRL',
                'unit' => 'money',
                'status' => 'active',
                'pin_hash' => Hash::make('1234'),
                'pin_plain' => '1234',
            ]
        );

        if ($activeTable->wasRecentlyCreated || $activeTable->players()->count() === 0) {
            // Vini na mesa com R$ 100
            $atp1 = TablePlayer::create([
                'poker_table_id' => $activeTable->id,
                'player_id' => $createdPlayers[0]->id,
                'name' => $createdPlayers[0]->name,
                'status' => 'active',
            ]);
            Buyin::create(['table_player_id' => $atp1->id, 'amount' => 100.0]);

            // Lucas com 2 entradas (100 + 100 = 200)
            $atp2 = TablePlayer::create([
                'poker_table_id' => $activeTable->id,
                'player_id' => $createdPlayers[1]->id,
                'name' => $createdPlayers[1]->name,
                'status' => 'active',
            ]);
            Buyin::create(['table_player_id' => $atp2->id, 'amount' => 100.0]);
            Buyin::create(['table_player_id' => $atp2->id, 'amount' => 100.0]);

            // Gabriel com R$ 100
            $atp3 = TablePlayer::create([
                'poker_table_id' => $activeTable->id,
                'player_id' => $createdPlayers[2]->id,
                'name' => $createdPlayers[2]->name,
                'status' => 'active',
            ]);
            Buyin::create(['table_player_id' => $atp3->id, 'amount' => 100.0]);
        }
    }
}
