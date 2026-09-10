<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyin;
use App\Models\Player;
use App\Models\PokerTable;
use App\Models\TablePlayer;
use App\Models\TableSnapshot;
use App\Services\RankingService;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function __construct(
        protected SettlementService $settlementService,
        protected RankingService $rankingService
    ) {}

    /**
     * Lista mesas recentes ou ativas
     */
    public function index(): JsonResponse
    {
        $tables = PokerTable::withCount('players')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'tables' => $tables,
        ]);
    }

    /**
     * Cria uma nova mesa com PIN de gerência e participantes iniciais opcionais
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'pin' => 'nullable|string|min:4|max:8',
            'currency' => 'nullable|string|max:10',
            'players' => 'nullable|array',
            'players.*.player_id' => 'nullable|exists:players,id',
            'players.*.name' => 'nullable|string|max:100',
            'players.*.buyin' => 'nullable|numeric|min:0.01',
        ]);

        $pin = !empty($validated['pin']) ? trim($validated['pin']) : '1234';

        DB::beginTransaction();
        try {
            $code = strtoupper(Str::random(6));
            while (PokerTable::where('code', $code)->exists()) {
                $code = strtoupper(Str::random(6));
            }

            $table = PokerTable::create([
                'code' => $code,
                'name' => $validated['name'],
                'currency' => $validated['currency'] ?? 'BRL',
                'unit' => 'money',
                'status' => 'active',
                'pin_hash' => Hash::make($pin),
                'pin_plain' => $pin, // para facilitar recuperação na tela admin/host
            ]);

            // Se jogadores iniciais foram selecionados
            if (!empty($validated['players'])) {
                foreach ($validated['players'] as $pData) {
                    $playerName = $pData['name'] ?? null;
                    $playerId = $pData['player_id'] ?? null;

                    if ($playerId && empty($playerName)) {
                        $p = Player::find($playerId);
                        $playerName = $p?->name;
                    }

                    if (empty($playerName)) {
                        continue;
                    }

                    $tablePlayer = TablePlayer::create([
                        'poker_table_id' => $table->id,
                        'player_id' => $playerId,
                        'name' => $playerName,
                        'status' => 'active',
                    ]);

                    $buyin = isset($pData['buyin']) ? (float) $pData['buyin'] : 100.0;
                    if ($buyin > 0) {
                        Buyin::create([
                            'table_player_id' => $tablePlayer->id,
                            'amount' => $buyin,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Mesa criada com sucesso!',
                'table' => $table,
                'code' => $table->code,
                'pin' => $pin,
                'share_url' => url('/mesa/' . $table->code),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao criar mesa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna estado completo da mesa ao vivo por código
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->first();

        if (!$table) {
            return response()->json(['message' => 'Mesa não encontrada'], 404);
        }

        $pinProvided = $request->header('X-Table-Pin') ?? $request->query('pin');
        $isManager = false;
        if ($pinProvided && $table->verifyPin((string) $pinProvided)) {
            $isManager = true;
        }

        $tablePlayers = TablePlayer::where('poker_table_id', $table->id)
            ->with(['player', 'buyins' => fn ($q) => $q->orderBy('created_at', 'asc')])
            ->get();

        $totalMoney = 0.0;
        $playersData = [];

        foreach ($tablePlayers as $tp) {
            $buyinsList = $tp->buyins->map(fn ($b) => [
                'id' => $b->id,
                'amount' => (float) $b->amount,
                'created_at' => $b->created_at->format('H:i'),
                'timestamp' => $b->created_at->timestamp,
            ]);

            $totalBuyin = $tp->totalBuyins();
            $totalMoney += $totalBuyin;
            $profit = $tp->profit();

            $playersData[] = [
                'id' => $tp->id,
                'player_id' => $tp->player_id,
                'name' => $tp->name,
                'nickname' => $tp->player?->nickname,
                'pix_key' => $tp->player?->pix_key,
                'avatar_color' => $tp->player?->avatar_color ?? '#10b981',
                'status' => $tp->status,
                'buyins' => $buyinsList,
                'buyins_count' => $buyinsList->count(),
                'total_buyin' => $totalBuyin,
                'final_amount' => $tp->final_amount,
                'profit' => $profit,
                'cashed_out_at' => $tp->cashed_out_at?->format('H:i'),
            ];
        }

        // Ordenar participantes: ativos primeiro, depois alfabético
        usort($playersData, function ($a, $b) {
            $ao = $a['status'] === 'cashed_out' ? 1 : 0;
            $bo = $b['status'] === 'cashed_out' ? 1 : 0;
            if ($ao !== $bo) return $ao <=> $bo;
            return strcasecmp($a['name'], $b['name']);
        });

        // Ranking e acertos
        $tableRanking = $this->rankingService->calculateTableRanking($tablePlayers);
        $settlements = $this->settlementService->calculate($tablePlayers);

        $activeCount = count(array_filter($playersData, fn ($p) => $p['status'] === 'active'));
        $cashedCount = count(array_filter($playersData, fn ($p) => $p['status'] === 'cashed_out'));

        // Top forra do momento
        $topWinner = collect($tableRanking)->first(fn ($r) => ($r['profit'] ?? 0) > 0);

        return response()->json([
            'table' => [
                'id' => $table->id,
                'code' => $table->code,
                'name' => $table->name,
                'currency' => $table->currency,
                'unit' => $table->unit,
                'status' => $table->status,
                'created_at' => $table->created_at->toISOString(),
                'closed_at' => $table->closed_at?->toISOString(),
            ],
            'is_manager' => $isManager,
            'summary' => [
                'total_money' => round($totalMoney, 2),
                'active_players' => $activeCount,
                'cashed_out_players' => $cashedCount,
                'total_players' => count($playersData),
                'top_winner' => $topWinner ? [
                    'name' => $topWinner['name'],
                    'profit' => $topWinner['profit'],
                ] : null,
            ],
            'players' => $playersData,
            'ranking' => $tableRanking,
            'settlements' => $settlements,
            'share_url' => url('/mesa/' . $table->code),
        ]);
    }

    /**
     * Valida o PIN da mesa para liberar o modo Gerente
     */
    public function verifyPin(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $table = PokerTable::where('code', strtoupper($code))->first();

        if (!$table) {
            return response()->json(['message' => 'Mesa não encontrada'], 404);
        }

        if ($table->verifyPin((string) $request->pin)) {
            return response()->json([
                'success' => true,
                'message' => 'PIN correto! Modo Gerente desbloqueado.',
                'pin' => $request->pin,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'PIN incorreto. Verifique com o organizador da mesa.',
        ], 403);
    }

    /**
     * Encerra a mesa e gera snapshot permanente
     */
    public function close(Request $request, string $code): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->first();

        if (!$table) {
            return response()->json(['message' => 'Mesa não encontrada'], 404);
        }

        // Validação de PIN de gerência
        $pin = $request->header('X-Table-Pin') ?? $request->input('pin');
        if (!$pin || !$table->verifyPin((string) $pin)) {
            return response()->json(['message' => 'Ação não autorizada. PIN inválido.'], 403);
        }

        if ($table->status === 'closed') {
            return response()->json(['message' => 'Mesa já se encontra encerrada.'], 400);
        }

        $validated = $request->validate([
            'final_amounts' => 'nullable|array', // [table_player_id => amount]
        ]);

        DB::beginTransaction();
        try {
            $tablePlayers = TablePlayer::where('poker_table_id', $table->id)->get();

            // Atualiza final_amounts para quem ainda estiver ativo
            if (!empty($validated['final_amounts'])) {
                foreach ($validated['final_amounts'] as $tpId => $amount) {
                    $tp = $tablePlayers->firstWhere('id', $tpId);
                    if ($tp) {
                        $tp->final_amount = (float) $amount;
                        $tp->status = 'cashed_out';
                        $tp->cashed_out_at = now();
                        $tp->save();
                    }
                }
            } else {
                // Caso não tenha sido informado, quem está ativo sai com 0
                foreach ($tablePlayers as $tp) {
                    if ($tp->status === 'active' && $tp->final_amount === null) {
                        $tp->final_amount = 0.0;
                        $tp->status = 'cashed_out';
                        $tp->cashed_out_at = now();
                        $tp->save();
                    }
                }
            }

            // Recarrega participantes com buyins para calcular ranking e liquidação final
            $tablePlayers->load(['player', 'buyins']);

            $ranking = $this->rankingService->calculateTableRanking($tablePlayers);
            $settlements = $this->settlementService->calculate($tablePlayers);
            $totalBuyins = $tablePlayers->sum(fn ($tp) => $tp->totalBuyins());

            $summary = [
                'total_buyins' => $totalBuyins,
                'total_players' => $tablePlayers->count(),
                'closed_at' => now()->toIso8601String(),
                'whatsapp_text' => $this->settlementService->generateWhatsAppSummary(
                    $table->name,
                    $ranking,
                    $settlements,
                    $totalBuyins
                ),
            ];

            TableSnapshot::updateOrCreate(
                ['poker_table_id' => $table->id],
                [
                    'total_buyins' => $totalBuyins,
                    'total_players' => $tablePlayers->count(),
                    'ranking_json' => $ranking,
                    'settlements_json' => $settlements,
                    'summary_json' => $summary,
                ]
            );

            $table->status = 'closed';
            $table->closed_at = now();
            $table->save();

            DB::commit();

            return response()->json([
                'message' => 'Mesa encerrada com sucesso!',
                'summary' => $summary,
                'ranking' => $ranking,
                'settlements' => $settlements,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao encerrar mesa: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Retorna texto pronto para WhatsApp
     */
    public function shareSummary(string $code): JsonResponse
    {
        $table = PokerTable::where('code', strtoupper($code))->first();

        if (!$table) {
            return response()->json(['message' => 'Mesa não encontrada'], 404);
        }

        $tablePlayers = TablePlayer::where('poker_table_id', $table->id)
            ->with(['player', 'buyins'])
            ->get();

        $ranking = $this->rankingService->calculateTableRanking($tablePlayers);
        $settlements = $this->settlementService->calculate($tablePlayers);
        $totalBuyins = $tablePlayers->sum(fn ($tp) => $tp->totalBuyins());

        $text = $this->settlementService->generateWhatsAppSummary(
            $table->name,
            $ranking,
            $settlements,
            $totalBuyins
        );

        return response()->json([
            'table_name' => $table->name,
            'whatsapp_text' => $text,
        ]);
    }
}
