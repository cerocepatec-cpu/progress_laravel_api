<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\EntryAccountMember;
use App\Models\WayoutAccountMember;
use App\Services\AccountingService;
use App\Services\MlmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AccountingController extends ApiController
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly MlmService $mlm,
    ) {}

    public function summary(Request $request)
    {
        return $this->ok($this->accounting->summary($request->user()));
    }

    public function reports(Request $request)
    {
        $filters = $request->validate([
            'type' => 'nullable|string|in:entries,wayouts,transactions,cash',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'accounts' => 'nullable|array',
            'accounts.*' => 'nullable',
            'agent_id' => 'nullable|string',
            'done_by' => 'nullable|string',
            'city_id' => 'nullable|integer',
        ]);

        $filters['agent_id'] = $filters['agent_id']
            ?? $filters['done_by']
            ?? null;

        unset($filters['done_by']);

        return $this->ok(
            $this->accounting->reports(
                $request->user(),
                $filters
            )
        );
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'wording' => ['required', 'string'],
            'provenance_entry' => ['nullable', 'string'],
            'point' => ['nullable', 'numeric'],
        ]);

        $entry = $this->accounting->storeEntry($request->user(), $data);

        return $this->ok($entry, 'success', 201);
    }

    public function downloadVipPacket(Request $request)
    {
        $member = $request->user();

        if ((float) ($member->pdfpaquet ?? 0) < 1) {
            throw ValidationException::withMessages([
                'vip_packet' => 'Vous ne disposez d aucun coffret VIP disponible.',
            ]);
        }

        $zipPath = storage_path('app/public/coffretvip.zip');

        if (! file_exists($zipPath)) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier coffret VIP est introuvable.',
            ]);
        }

        return response()->download(
            $zipPath,
            'coffretvip.zip',
            [
                'Content-Type' => 'application/zip',
            ]
        );
    }

    public function storeWayout(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'wording' => ['required', 'string'],
            'destination' => ['nullable', 'string'],
        ]);

        $wayout = $this->accounting->storeWayout($request->user(), $data);

        return $this->ok($wayout, 'success', 201);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'wording' => ['required', 'string'],
            'member_id_destination' => ['required', 'string'],
        ]);

        $result = $this->accounting->transfer($request->user(), $data);

        return $this->ok($result, 'Transfert effectue.');
    }

    public function memberLedger(Request $request, string $identifier)
    {
        $member = $this->mlm->resolveMember($identifier);

        return $this->ok($this->accounting->memberLedger($request, $member));
    }

    public function cashOperations(Request $request)
    {
        return $this->ok($this->accounting->cashOperations($request->user()));
    }

    public function showCashOperation(Request $request, int $cashId)
    {
        return $this->ok($this->accounting->cashOperation($request->user(), $cashId));
    }

    public function storeCashOperation(Request $request)
    {
        $data = $request->validate([
            'cash_mount' => ['required', 'numeric'],
            'cash_mode' => ['required', 'string'],
            'bank_name' => ['nullable', 'string'],
            'own_bank_account' => ['nullable', 'string'],
            'bank_account_number' => ['nullable', 'string'],
            'mobile_number' => ['nullable', 'string'],
        ]);

        $cashId = $this->accounting->storeCashOperation($request->user(), $data);

        return $this->ok(['cash_id' => $cashId], 'Operation CASH creee.', 201);
    }

    public function validateCashOperation(int $cashId)
    {
        $this->accounting->validateCashOperation($cashId);

        return $this->ok(null, 'Operation CASH validee.');
    }

    public function deleteCashOperation(int $cashId)
    {
        $this->accounting->deleteCashOperation($cashId);

        return $this->ok(null, 'Operation CASH supprimee.');
    }

    public function vipPackets(Request $request)
    {
        return $this->ok($this->accounting->vipPackets($request->user()));
    }

    // public function buyVipPackets(Request $request)
    // {
    //     $zipPath = storage_path('app/public/coffretvip.zip');

    //     if (! file_exists($zipPath)) {
    //         throw ValidationException::withMessages([
    //             'file' => 'Le fichier coffret VIP est introuvable.',
    //         ]);
    //     }

    //     $payload = $request->validate([
    //         'number' => ['required', 'integer', 'min:1'],
    //         'password_ewallet' => ['required', 'string'],
    //     ]);

    //     $this->accounting->buyVipPackets(
    //         $request->user(),
    //         $payload
    //     );

    //     return response()->download(
    //         $zipPath,
    //         'coffretvip.zip',
    //         [
    //             'Content-Type' => 'application/zip',
    //         ]
    //     );
    // }
    public function buyVipPackets(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1'],
            'total' => ['nullable', 'numeric', 'gt:0'],
            'password_ewallet' => ['required', 'string', 'min:4'],
        ]);

        return $this->ok(
            $this->accounting->buyVipPackets($request->user(), $data),
            'Coffret VIP achete avec succes.',
            201
        );
    }

    public function memberMonthlyStats(Request $request)
    {
        $user = $request->user();

        $memberIds = $request->input('member_ids');

        if (empty($memberIds)) {
            $memberIds = [$user->member_id];
        } elseif (is_string($memberIds)) {
            $memberIds = explode(',', $memberIds);
        }

        $period = $request->input('period', 'today');

        [$dateFrom, $dateTo] = $this->resolveAccountingPeriod($period, $request);

        $entries = EntryAccountMember::query()
            ->selectRaw('MONTH(date_entry) as month')
            ->selectRaw('SUM(amount) as total')
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date_entry', [$dateFrom, $dateTo])
            ->groupByRaw('MONTH(date_entry)')
            ->pluck('total', 'month');

        $wayouts = WayoutAccountMember::query()
            ->selectRaw('MONTH(date_wayout) as month')
            ->selectRaw('SUM(amount) as total')
            ->whereIn('member_id', $memberIds)
            ->whereBetween('date_wayout', [$dateFrom, $dateTo])
            ->groupByRaw('MONTH(date_wayout)')
            ->pluck('total', 'month');

        $months = [
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Août',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Déc',
        ];

        $items = collect($months)->map(function ($label, $month) use ($entries, $wayouts) {
            return [
                'month' => $label,
                'entries' => round((float) ($entries[$month] ?? 0), 2),
                'wayouts' => round((float) ($wayouts[$month] ?? 0), 2),
            ];
        })->values();

        return $this->ok([
            'items' => $items,
            'totals' => [
                'entries' => round($items->sum('entries'), 2),
                'wayouts' => round($items->sum('wayouts'), 2),
                'balance' => round($items->sum('entries') - $items->sum('wayouts'), 2),
            ],
            'filters' => [
                'period' => $period,
                'date_from' => $dateFrom->toDateTimeString(),
                'date_to' => $dateTo->toDateTimeString(),
                'member_ids' => $memberIds,
            ],
        ]);
    }

    private function resolveAccountingPeriod(string $period, Request $request): array
    {
        $now = now();

        return match ($period) {
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],

            'this_week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ],

            'last_week' => [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],

            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],

            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],

            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],

            'custom' => [
                Carbon::parse($request->input('date_from'))->startOfDay(),
                Carbon::parse($request->input('date_to'))->endOfDay(),
            ],

            default => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
        };
    }
}
