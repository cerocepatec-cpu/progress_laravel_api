<?php

namespace App\Http\Controllers;

use App\Models\funds;
use App\Models\Closure;
use App\Models\UserClosure;
use Illuminate\Http\Request;
use App\Exports\ClosuresExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ClosuresController extends Controller
{
    public function index(Request $request)
    {
        $user=Auth::user();
        
        if (!$user) {
          return $this->errorResponse("Utilisateur non authentifié.", 401);  # code...
        }

        $query = Closure::with(['fund','currency','user','fund.enterprise']);

        // 🔹 Filtres
        if ($request->filled('agent_id')) {
             $agentIds = $request->agent_id;
            // Si c’est une chaîne "1,2,3", on la découpe :
            if (is_string($agentIds)) {
                $agentIds = array_map('intval', explode(',', $agentIds));
            }
            $query->whereIn('user_id', $agentIds);
        }

        if ($request->filled('fund_id')) {
            $fundIds = $request->fund_id;

            if (is_string($fundIds)) {
                $fundIds = array_map('intval', explode(',', $fundIds));
            }

            $query->whereIn('fund_id', $fundIds);
        }

        
        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('closed_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('closed_at', '<=', $request->end_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name','like',"%$search%"))
                ->orWhereHas('fund', fn($q) => $q->where('name','like',"%$search%"));
        }

        // 🔹 Pagination
        $closures = $query->orderBy('closed_at','desc')->paginate(20);

        // 🔹 Indicateur solde créditeur/débiteur
        $closures->getCollection()->transform(function($closure){
            $closure->solde_status = $closure->total_amount < ($closure->received_amount ?? 0) ? 'créditeur' :
                                    ($closure->total_amount > ($closure->received_amount ?? 0) ? 'débiteur' : 'équilibré');
            return $closure;
        });

        return response()->json($closures);
    }

    public function store(Request $request)
    {
        $request->validate([
            'closures' => 'required|array',
            'closures.*.fund_id' => 'required|exists:funds,id',
            'closures.*.total_amount' => 'required|numeric',
            'closures.*.billages' => 'required|array',
            'closures.*.currency_id' => 'required|exists:moneys,id',
            'closures.*.closure_note' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }

        // Vérifier si l'utilisateur a déjà clôturé aujourd'hui
        $alreadyClosed = UserClosure::hasClosedForDate($user->id, date('Y-m-d'));
        if ($alreadyClosed) {
            return $this->errorResponse("Vous avez déjà clôturé cette journée", 400);
        }

        $createdClosures = [];

        foreach ($request->closures as $closureData) {
            $fund =funds::find($closureData['fund_id']);

            // Vérifier la propriété de la caisse
            if ($fund->user_id !== $user->id) {
                return $this->errorResponse("Vous n'êtes pas autorisé à clôturer la caisse ID {$fund->id}.", 403);
            }

            // Vérifier si la caisse n'est pas déjà clôturée
            $existing = Closure::where('fund_id', $fund->id)
                        ->where('closed_at',date('Y-m-d'))
                        ->whereIn('status', ['pending', 'validated'])
                        ->first();

            if ($existing) {
                return $this->errorResponse("La caisse ID {$fund->id} est déjà clôturée.", 400);
            }

            // Création de la clôture
            $closure = Closure::create([
                'user_id' => $user->id,
                'fund_id' => $fund->id,
                'total_amount' => $closureData['total_amount'],
                'billages' => $closureData['billages'],
                'currency_id' => $closureData['currency_id'],
                'status' => 'pending',
                'closed_at' => now(),
                'closure_note' => $closureData['closure_note'] ?? null,
            ]);

            // Charger les infos pour l’impression
            $closure->load([
                'fund:id,description',
                'currency:id,abreviation,money_name'
            ]);

            $createdClosures[] = $closure;

            // Mettre à jour ou créer l'entrée UserClosure
            $userClosure = UserClosure::firstOrNew([
                'user_id' => $user->id,
                'currency_id' => $closureData['currency_id'],
                'closure_date' => now()->toDateString(),
            ]);

            $userClosure->total_amount += $closureData['total_amount'];
            $userClosure->closure_count += 1;
            $userClosure->status = 'pending';
            $userClosure->closure_note = $closureData['closure_note'] ?? null;
            $userClosure->save();
        }

        // 🔹 Format spécial "impression"
        $printData = collect($createdClosures)->map(function ($closure) use ($user) {
            return [
                'closure_id'    => $closure->id,
                'caisse'        => $closure->fund->description ?? 'Inconnue',
                'monnaie'       => $closure->currency->abreviation ?? '',
                'monnaie_name'  => $closure->currency->money_name ?? '',
                'montant_total' => number_format($closure->total_amount, 2, ',', ' '),
                'note'          => $closure->closure_note,
                'date'          => $closure->closed_at->format('d/m/Y H:i'),
                'user'          => $user->name ?? $user->user_name ?? 'Utilisateur',
                'billages'      => collect($closure->billages)->map(function ($b) {
                    return [
                        'nominal'  => $b['nominal'],
                        'quantity' => $b['quantity'],
                        'total'    => $b['nominal'] * $b['quantity']
                    ];
                })->toArray(),
            ];
        });

        return $this->successResponse('success', $printData);
    }
    
    public function receiveClosure(Request $request, $closure_id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }
        $closure =Closure::find($closure_id);

        if (!$closure) {
            return $this->errorResponse('Clôture introuvable', 404);
        }

        // Vérifier que la clôture est en pending
        if ($closure->status !== 'pending') {
            return $this->errorResponse('Cette clôture a déjà été validée ou refusée.', 400);
        }

        // Vérifier que le récepteur n'est pas le propriétaire
        if ($closure->user_id === $user->id) {
            return $this->errorResponse('Vous ne pouvez pas réceptionner votre propre clôture.', 403);
        }

        $request->validate([
            'received_amount' => 'required|numeric',
            'receiver_note' => 'nullable|string',
        ]);

        // Mettre à jour la clôture détaillée
        $closure->update([
            'received_amount' => $request->received_amount,
            'receiver_note' => $request->receiver_note ?? null,
            'received_at' => now(),
            'status' => 'validated',
        ]);

        // Mettre à jour UserClosure
        $userClosure =UserClosure::where('user_id', $closure->user_id)
                        ->where('currency_id', $closure->currency_id)
                        ->where('closure_date', $closure->closed_at->toDateString())
                        ->first();

        if ($userClosure) {
            $userClosure->receiver_id = $user->id;
            $userClosure->total_received += $request->received_amount;
            $userClosure->received_at = now();
            $userClosure->receiver_note = $request->receiver_note ?? null;
            $userClosure->status = 'validated';
            $userClosure->save();
        }

        return $this->successResponse('success.',$closure);
    }
    
    public function printClosure($id)
    {
        $user =Auth::user();
        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }
        $closure = Closure::with([
            'fund:id,description',
            'currency:id,abreviation',
            'user:id,name,email',
            'fund.enterprise:id,name,adresse,phone,mail,logo'
        ])->find($id);

        if (!$closure) {
            return $this->errorResponse("Clôture introuvable.", 404);
        }

        $enterprise =$this->getEse($user->id);
        try {
             $data = [
            'closure' => $closure,
            'fund' => $closure->fund,
            'currency' => $closure->currency,
            'user' => $closure->user,
            'connected' => $user,
            'enterprise' => $enterprise,
            'date' => now()->format('d/m/Y H:i'),
            ];

            $pdf = Pdf::loadView('pdf.closure_premium', $data)->setPaper('A4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();

            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont("Helvetica", "normal");

            $canvas->page_text(520, 820, "Page {PAGE_NUM} sur {PAGE_COUNT}", $font, 10, [0, 0, 0]);

            $canvas->page_script(function($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $text = "Généré le " . now()->format('d/m/Y H:i');
                $font = $fontMetrics->getFont("Helvetica", "normal");
                $size = 8;
                $canvas->text(40, 820, $text, $font, $size);
            });

            $filename = 'closure_' . $closure->id . '.pdf';

            return $pdf->stream($filename);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'trace' => $th->getTraceAsString(),
            ], 500);
        }
    }
    
    public function show($id)
    {
        $user =Auth::user();
        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }

        $closure = Closure::with([
            'fund:id,description',
            'currency:id,abreviation',
            'user:id,name,email',
            'fund.enterprise:id,name,adresse,phone,mail,logo'
        ])->find($id);

        if (!$closure) {
            return $this->errorResponse("Clôture introuvable.", 404);
        }

        $enterprise =$this->getEse($user->id);
        try {
             $data = [
            'closure' => $closure,
            'fund' => $closure->fund,
            'currency' => $closure->currency,
            'user' => $closure->user,
            'connected' => $user,
            'enterprise' => $enterprise,
            'date' => now()->format('d/m/Y H:i'),
            ];

            return $this->successResponse("success",$data);
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(),500);
        }
    }

    public function printClosureTicket($id)
    {
        $user =Auth::user();
        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }
        $closure = Closure::with([
            'fund:id,description',
            'currency:id,abreviation',
            'user:id,name,email',
            'fund.enterprise:id,name,adresse,phone,mail,logo'
        ])->find($id);

        if (!$closure) {
            return $this->errorResponse("Clôture introuvable.", 404);
        }

        $enterprise =$this->getEse($user->id);
        try {
             $data = [
            'closure' => $closure,
            'fund' => $closure->fund,
            'currency' => $closure->currency,
            'user' => $closure->user,
            'connected' => $user,
            'enterprise' => $enterprise,
            'date' => now()->format('d/m/Y H:i'),
            ];

            $pdf = Pdf::loadView('pdf.closure_ticket', $data)->setPaper('A4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $canvas = $dompdf->getCanvas();

            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont("Helvetica", "normal");

            $canvas->page_text(520, 820, "Page {PAGE_NUM} sur {PAGE_COUNT}", $font, 10, [0, 0, 0]);

            $canvas->page_script(function($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $text = "Généré le " . now()->format('d/m/Y H:i');
                $font = $fontMetrics->getFont("Helvetica", "normal");
                $size = 8;
                $canvas->text(40, 820, $text, $font, $size);
            });

            $filename = 'closure_ticket_' . $closure->id . '.pdf';

            return $pdf->stream($filename);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'trace' => $th->getTraceAsString(),
            ], 500);
        }
    }
    
    public function rejectClosure(Request $request, $closure_id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse("Utilisateur non authentifié.", 401);
        }
        $closure =Closure::find($closure_id);

        if (!$closure) {
            return $this->errorResponse('Clôture introuvable', 404);
        }

        // Vérifier que la clôture est en pending
        if ($closure->status !== 'pending') {
            return $this->errorResponse('Cette clôture a déjà été validée ou refusée.', 400);
        }

        // Vérifier que le récepteur n'est pas le propriétaire
        if ($closure->user_id === $user->id) {
            return $this->errorResponse('Vous ne pouvez pas modifier votre propre clôture.', 403);
        }

        $request->validate([
            'receiver_note' => 'required|string',
        ]);

        // Mettre à jour la clôture détaillée
        $closure->update([
            'received_amount' =>0,
            'receiver_note' => $request->receiver_note ?? null,
            'received_at' => now(),
            'status' => 'rejected',
        ]);

        return $this->successResponse('success',$closure);
    }

    public function showClosure($id)
    {
        $closure = Closure::with([
            'fund:id,name',       // Nom de la caisse
            'currency:id,name,abreviation', // Détails de la monnaie
            'user:id,name,email'  // Utilisateur qui a clôturé
        ])->find($id);

        if (!$closure) {
            return $this->errorResponse("Clôture introuvable.", 404);
        }

        return $this->successResponse("Détails de la clôture", [
            'id' => $closure->id,
            'user' => $closure->user->name ?? null,
            'fund' => $closure->fund->name ?? null,
            'currency' => $closure->currency->abreviation ?? null,
            'total_amount' => $closure->total_amount,
            'billages' => $closure->billages,
            'status' => $closure->status,
            'closed_at' => $closure->closed_at,
            'closure_note' => $closure->closure_note,
            'receiver_note' => $closure->receiver_note,
            'received_amount' => $closure->received_amount,
            'received_at' => $closure->received_at,
        ]);
    }

    // 🔹 Export CSV/XLS
    public function export(Request $request)
    {
        $filename = 'closures_export_'.now()->format('Ymd_His').'.xlsx';
        return Excel::download(new ClosuresExport($request->all()), $filename);
    }

    public function dashboardStats(Request $request)
    {
        $query = Closure::query();

        if ($request->filled('agent_id')) $query->where('user_id', $request->agent_id);
        if ($request->filled('fund_id')) $query->where('fund_id', $request->fund_id);
        if ($request->filled('currency_id')) $query->where('currency_id', $request->currency_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        $groupBy = $request->group_by ?? 'day'; // day, week, month, year

        $closures = $query->select(
            DB::raw("DATE(closed_at) as date"),
            DB::raw("user_id"),
            DB::raw("SUM(total_amount) as total_closures"),
            DB::raw("SUM(received_amount) as total_received"),
            DB::raw("SUM(total_amount - IFNULL(received_amount,0)) as solde")
        )
        ->groupBy(DB::raw($this->groupByRaw($groupBy).", user_id"))
        ->orderBy('date','asc')
        ->get();

        return response()->json($closures);
    }

    public function dashboardKPI(Request $request)
    {
        $query = Closure::query();

        if ($request->filled('start_date')) $query->whereDate('closed_at','>=',$request->start_date);
        if ($request->filled('end_date')) $query->whereDate('closed_at','<=',$request->end_date);

        $totalClosures = $query->sum('total_amount');
        $totalReceived = $query->sum('received_amount');
        $soldeTotal = $totalClosures - $totalReceived;

        return response()->json([
            'totalClosures' => $totalClosures,
            'totalReceived' => $totalReceived,
            'soldeTotal' => $soldeTotal,
            'soldeStatus' => $soldeTotal > 0 ? 'créditeur' : ($soldeTotal < 0 ? 'débiteur' : 'équilibré')
        ]);
    }
}
