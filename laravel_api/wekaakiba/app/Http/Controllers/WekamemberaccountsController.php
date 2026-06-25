<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\User;
use App\Models\moneys;
use Illuminate\Http\Request;
use App\Models\wekamemberaccounts;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
 use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\wekaAccountsTransactions;
use App\Http\Requests\StorewekamemberaccountsRequest;
use App\Http\Requests\UpdatewekamemberaccountsRequest;

class WekamemberaccountsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($enterprise)
    {
        //
    }

    public function getaccountsumsbyuser($usersent){
        $user = Auth::user();
        if (!$user || $user->status=='disabled') {
            return $this->errorResponse('Utilisateur invalide ou non trouvé.');
        }

        // Récupérer les comptes actifs de l'utilisateur avec les infos de devise
        $accounts =wekamemberaccounts::getBalancesByUser($user);

        if (!$accounts) {
            return $this->errorResponse('Aucun compte trouvé pour cet utilisateur.');
        }

        return $this->successResponse('success', $accounts);
    }

    /**
     * get all accounts paginated
     */
    public function allaccounts($user){
        $list=[];
        $actualuser=Auth::user();
        if (!$actualuser || $actualuser->status=='disabled') {
            return $this->errorResponse('Utilisateur non authentifié!',400);
        }

        $ese=$this->getEse($user);
        if (!$ese || $ese->status==="disabled") {
            return $this->errorResponse('Entreprise desactivee',400);
        }
        
        if ($actualuser) {
            $list= wekamemberaccounts::leftjoin('users as U', 'wekamemberaccounts.user_id','=','U.id')
            ->leftjoin('moneys as M', 'wekamemberaccounts.money_id','=','M.id')
            ->where('user_id','=',$user)
            ->get(['M.abreviation as money_abreviation', 'U.user_name', 'wekamemberaccounts.*']);
        }
         
        return $this->successResponse("success",$list) ;
    }

    public function searchaccountsbyenterprise(Request $request)
    {
        $keyword = $request->query('keyword');
        $limit = $request->query('limit', 50);
        $actualuser =Auth::user();
        if (!$actualuser) {
            return $this->errorResponse("Utilisateur non authentifié!",400);
        }

        $ese = $this->getEse($actualuser->id);
        if (!$ese) {
           return $this->errorResponse("Vous n'êtes pas autorisé à faire cette opération!",400);
        }

        if(!$keyword){
            return $this->errorResponse("Aucune clé de recherche fournie!",400);
        }

        $subquery =DB::table('usersenterprises')
            ->select('user_id')
            ->where('enterprise_id', $ese->id);

        $query = wekamemberaccounts::leftJoin('users as U', 'wekamemberaccounts.user_id', '=', 'U.id')
            ->leftJoin('moneys as M', 'wekamemberaccounts.money_id', '=', 'M.id')
            ->whereIn('wekamemberaccounts.user_id', $subquery);

        if ($actualuser['user_type'] !== 'super_admin') {
            $query->where('wekamemberaccounts.user_id', $actualuser->id);
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('U.user_name', 'LIKE', "%{$keyword}%")
                ->orWhere('U.full_name', 'LIKE', "%{$keyword}%")
                ->orWhere('wekamemberaccounts.description', 'LIKE', "%{$keyword}%");
            });
        }

        $list = $query->limit($limit)->get([
            'M.abreviation as money_abreviation',
            'U.user_name',
            'U.full_name',
            'wekamemberaccounts.*'
        ]);

        return response()->json($list);
    }
        
    public function searchSingleAccount(Request $request)
    {
        $request->validate(['keyword' => 'required|string']);
        $actualuser = Auth::user();

        if (!$actualuser) {
            return $this->errorResponse("Utilisateur non authentifié!", 400);
        }

        $ese = $this->getEse($actualuser->id);
        if (!$ese) {
            return $this->errorResponse("Impossible de vous identifier.", 400);
        }

        try {
            $account = wekamemberaccounts::join('users as U', 'wekamemberaccounts.user_id', '=', 'U.id')
                ->where('wekamemberaccounts.enterprise_id', $ese->id)
                ->where(function ($query) use ($request) {
                    $keyword = $request->keyword;
                    $query->where('wekamemberaccounts.description', $keyword)
                        ->orWhere('wekamemberaccounts.account_number', $keyword)
                        ->orWhere('U.uuid', $keyword)
                        ->orWhere('U.user_name', $keyword)
                        ->orWhere('U.full_name', $keyword);
                })
                ->select('wekamemberaccounts.*')
                ->first();

            if (!$account) {
                return $this->errorResponse("Compte introuvable.", 404);
            }
             unset($account->sold);
            return $this->successResponse('success', $this->show($account));

        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * 
     */
    public function membersaccounts($user){
        try {
             $list=[];
            $actualuser=$this->getinfosuser($user);
            $ese=$this->getEse($user);
            if ($actualuser) {
                 $list= wekamemberaccounts::leftjoin('users as U', 'wekamemberaccounts.user_id','=','U.id')
                ->leftjoin('moneys as M', 'wekamemberaccounts.money_id','=','M.id')
                ->where('user_id','=',$user)
                ->get(['M.abreviation as money_abreviation', 'U.user_name', 'wekamemberaccounts.*']);
                return response()->json([
                    'error' => null,
                    'status' => 200,
                    'message' => 'success',
                    'data' => $list
                ]);
            }else{
                return $this->errorResponse('user not found',404);
            }
            
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(),404);
        } 
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorewekamemberaccountsRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorewekamemberaccountsRequest $request)
    {
        if (!$request->has('sold') || $request->sold <= 0) {
            $request->merge(['sold' => 0]);
        }

        if (empty($request->blocked_from)) {
            $request->request->remove('blocked_from');
        }
        
        if (empty($request->blocked_to)) {
            $request->request->remove('blocked_to');
        }

        if ($request->type === 'blocked' && $request->blocked_step > 0) {
            $from = Carbon::now();

            if ($request->blocked_periocity === 'year') {
                $to = $from->copy()->addYears($request->blocked_step);
            } elseif ($request->blocked_periocity === 'month') {
                $to = $from->copy()->addMonths($request->blocked_step);
            } else {
                $to = $from->copy()->addYear();
            }

            $request->merge([
                'blocked_from' => $from->format('Y-m-d'),
                'blocked_to'   => $to->format('Y-m-d'),
            ]);
        }

        $newaccount = wekamemberaccounts::create($request->all());

        if ($newaccount->sold > 0) {
            wekaAccountsTransactions::create([
                'amount'        => $newaccount->sold,
                'done_at'       => date('Y-m-d'),
                'user_id'       => $request->created_by,
                'motif'         => 'Balance d\'ouverture',
                'type'          => 'entry',
                'enterprise_id' => $request->enterprise_id,
                'uuid'          => $this->getUuId('C', 'AT'),
                'sold_before'   => 0,
                'sold_after'    => $newaccount->sold,
            ]);
        }

        return $this->show($newaccount);
    }


    function calculEscompteAnticipe($montantCredit, $taux, $dureeInitiale, $joursEcoules) {
        // Calcul du montant total dû à échéance
        $interetTotal = $montantCredit * ($taux / 100);
        $montantTotal = $montantCredit + $interetTotal;

        // Calcul des jours restants
        $joursRestants = $dureeInitiale - $joursEcoules;

        // Calcul de l'escompte
        $escompte = $montantCredit * ($taux / 100) * ($joursRestants / $dureeInitiale);

        // Montant à rembourser avec escompte appliqué
        $montantARembourser = $montantTotal - $escompte;

        return [
            'montant_total'       => round($montantTotal, 2),
            'jours_restants'      => $joursRestants,
            'escompte'            => round($escompte, 2),
            'montant_rembourse'   => round($montantARembourser, 2),
        ];
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\wekamemberaccounts  $wekamemberaccounts
     * @return \Illuminate\Http\Response
     */
    public function show(wekamemberaccounts $wekamemberaccounts)
    {
       $account=wekamemberaccounts::leftjoin('users as U', 'wekamemberaccounts.user_id','=','U.id')
        ->leftjoin('moneys as M', 'wekamemberaccounts.money_id','=','M.id')
        ->where('wekamemberaccounts.id',$wekamemberaccounts->id)->first(['M.abreviation as money_abreviation', 'U.user_name', 'wekamemberaccounts.*']);
        //  unset($account->sold);
        return $account;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\wekamemberaccounts  $wekamemberaccounts
     * @return \Illuminate\Http\Response
     */
    public function AccountUpdateSold(Request $request)
    {
        $request -> validate(
            [
                'user_id'=> 'required|integer|exists:users,id',
                'enterprise_id' => 'required|integer|exists:wekamemberaccounts,enterprise_id'

            ]);

            $data[] = $request['data'];
            $membersData = [];
            $seenCodes = [];
            $seenNames = []; 
            $warnigs = [];
            $problems = [];
            $success = [];
            $updated = false; 
            $alert = new stdClass();
            foreach ($data as $memberData) {

                if (isset($memberData['code'])){ 
                    $code_members = $memberData['code'];
                    $name_members  = $memberData['name'];
                    // Vérification des doublons par code et nom
                    if (in_array($code_members, $seenCodes) || in_array($name_members, $seenNames) ) {
                        $memberData['status'] = 'error';
                        $memberData['message'] = "le membre avec le code " . $code_members . " est repeter .";
                        // $membersData[] = $memberData;
                        array_push($problems,$memberData);
                        
                        continue; 
                    }
                    $seenCodes[] = $code_members; 
                    $seenNames[] = $name_members;
       
                    $member = User::where('uuid',$memberData['code'])->first();
                    
                    if ($member) {

                        $memberAccont = wekamemberaccounts::where('user_id',$member->id  )->get();
                        
                        foreach ($memberAccont as $foundaccount) 
                        {
                            $moneys = moneys::where('id' , $foundaccount->money_id )->first();
                            if ( $moneys->abreviation != $memberData['usd'] && $moneys->abreviation != $memberData['cdf'] ) 
                            {
                                $memberData['status'] = 'warning';
                                $memberData['actual_usd'] = $member->usd;
                                $memberData['message'] = "Le compte en ".$moneys->abreviation." du membre " . $member->full_name . " a un solde different avec celui dans votre fichier uploder";
                                array_push($warnigs,$memberData);
                            }else {
                                if ($updated) {
                                    $solde = $moneys->abreviation == $memberData['usd'] ? $memberData['usd'] : $memberData['usd'] ;
                                   
                                    $updating =  $foundaccount->update([
                                        'sold' => $solde , 
                                    ]);
                                    if ($updating) {
                                        $updatingHistory = wekaAccountsTransactions::create([
                                            'amount'=>$solde,
                                            'sold_before'=>0,
                                            'sold_after'=>$foundaccount->sold,
                                            'user_id'=>$member->id,
                                            'member_account_id'=>  $foundaccount->id,
                                            'enterprise_id'=>  $foundaccount->enterprise_id,
                                            'account_id'=>$foundaccount->account_number,
                                            'transaction_status'=>"pending",
                                            'sync_status'=>false,
                                        ]);
                                        $updatingHistory['statut'] = 'success';
                                        $updatingHistory['message'] = 'le solde a été mise à jour avec succée';
                                        array_push($success,$updatingHistory);
                                    }
                                    

                                }
                            }
                            
                        }
                        
                } else {
                    $memberData['status'] = 'error';
                    $memberData['message'] = "Le le membre " . $memberData['name'] . " n'a pas été trouvé";
                    }
                    // $membersData[] = $memberData;
                    // array_push($problems,$memberData);
    
                
            } else {
                if (empty($memberData['code'])) {
                    foreach ($memberData as $value) 
                    {
                        $value['message'] = "Le code pour le membre n'a pas été trouvé";
                        $alert->status = 'error';
                        # code...
                        // array_push($value,$alert);
                    }
                    // $memberData['message'] = "".gettype($memberData)."Le code pour le membre n'a pas été trouvé"  ;
                }else {
                $memberData['status'] = 'error';
                $memberData['message'] = "Le le membre  n'a pas été trouvé";
                }
                // $membersData[] = $memberData;
                array_push($problems,$memberData);
            }
       
           }
        
           return response()->json([
            "message" => 'ok',
            "succeded" => $success,
            "problems"=> $problems,
            "warnings"=> $warnigs,
            "status" => "success",
            "code" => 200
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\wekamemberaccounts  $wekamemberaccounts
     * @return \Illuminate\Http\Response
     */
    public function edit(wekamemberaccounts $wekamemberaccounts)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatewekamemberaccountsRequest  $request
     * @param  \App\Models\wekamemberaccounts  $wekamemberaccounts
     * @return \Illuminate\Http\Response
     */

public function update(UpdatewekamemberaccountsRequest $request, wekamemberaccounts $wekamemberaccounts)
{
    $wekamemberaccounts=wekamemberaccounts::find($request->id);
    //tester si un compte peut etre modifie dja
    if (!$request['criteria']) {
        return $this->errorResponse('criteria not sent');
    }

    if (!$wekamemberaccounts) {
        return $this->errorResponse('account not sent');
    }

    $canbeEdited=$wekamemberaccounts->canBeUnblocked();
    if ($canbeEdited) {
          DB::beginTransaction();

            try {
                switch ($request->criteria) {
                    case 'type':
                        if ($request['type'] === "internal") {
                            $wekamemberaccounts->update(['type'=>$request->type]);
                        }

                        if ($request['type'] === 'blocked' && $request['blocked_step'] > 0) {
                            
                            $from = Carbon::now();

                            if ($request['blocked_periocity'] === 'year') {
                                $to = $from->copy()->addYears($request['blocked_step']);
                            } elseif ($request['blocked_periocity'] === 'month') {
                                $to = $from->copy()->addMonths($request->blocked_step);
                            } else {
                                $to = $from->copy()->addYear();
                            }

                            $request->merge([
                                'blocked_from' => $from->format('Y-m-d'),
                                'blocked_to'   => $to->format('Y-m-d'),
                            ]);

                            $wekamemberaccounts->update([
                                'blocked_from'=>$from,
                                'blocked_to' => $to,
                                'type'=>$request->type,
                                'blocked_periocity'=>$request['blocked_periocity'],
                                'blocked_step'=>$request['blocked_step']
                            ]);
                        }
                        break;

                    case 'status':
                        $wekamemberaccounts->update(['account_status'=>$request->account_status]);
                        break;

                    default:
                        // Critère inconnu, ne rien faire
                        break;
                }

                DB::commit();
                
                return response()->json([
                    "status" => 200,
                    "message" => "success",
                    "error" => null,
                    "data" => $this->show(wekamemberaccounts::find($request->id))
                ]);

            } catch (\Exception $th) {
                DB::rollBack();

                return response()->json([
                    "status" => 500,
                    "message" => "error",
                    "error" => $th->getMessage(),
                    "data" => null
                ]);
            }
    }else{
         return $this->errorResponse('unauthorized action');
    }
  
}


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\wekamemberaccounts  $wekamemberaccounts
     * @return \Illuminate\Http\Response
     */
    public function destroy(wekamemberaccounts $wekamemberaccounts)
    {
        //
    }
}
