<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\funds;
use App\Models\moneys;
use Illuminate\Http\Request;
use App\Models\decision_team;
use App\Models\requestHistory;
use App\Models\providerspayments;
use Illuminate\Support\Facades\DB;
use App\Models\StockHistoryController;
use App\Http\Requests\StorefundsRequest;
use App\Http\Requests\UpdatefundsRequest;
use App\Models\Closure;
use App\Models\UserClosure;
use Barryvdh\DomPDF\Facade\Pdf;

class FundsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($enterprise)
    {
        try {
            $list = Funds::where('enterprise_id', $enterprise)->get();

            if ($list->isEmpty()) {
                return $this->errorResponse('Aucune caisse trouvée pour cette entreprise', 404);
            }

            $formattedList = $list->map(function ($fund) {
                return $this->show($fund) ?? null;
            })->filter();

            return $this->successResponse('success', $formattedList);

        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du chargement des caisses : '.$e->getMessage(), 500);
        }
    }

    public function getUserFunds(Request $request, $userId)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return $this->errorResponse('Utilisateur non authentifié.', 401);
        }

        $user =User::find($userId);
        if (!$user || $user->status !== 'enabled') {
            return $this->errorResponse('Utilisateur introuvable ou désactivé.', 404);
        }

        if ($authUser->id !== $userId) {
            return $this->errorResponse('Accès refusé.', 403);
        }

        $funds =funds::getUserFundsWithMoney($userId);

        return $this->successResponse($funds, 'success');
    }

    /**
     * Récupère les soldes de plusieurs utilisateurs,
     * groupés par devise (money_id / abreviation).
     */
    public function getMultipleUsersBalances(Request $request)
    {
        try {
            // Vérifier que l’utilisateur connecté via le token existe
            $authUser = $request->user();
            if (!$authUser) {
                return $this->errorResponse("Utilisateur non authentifié.", 401);
            }

            // Récupérer la liste des IDs depuis le body JSON
            $userIds = $request->input('user_ids');
            if (!is_array($userIds) || empty($userIds)) {
                return $this->errorResponse("Veuillez fournir une liste valide d'utilisateurs.", 400);
            }

            $results = [];
            $invalidUsers = [];

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if (!$user) {
                    $invalidUsers[] = [
                        'user_id' => $userId,
                        'reason' => 'Utilisateur introuvable'
                    ];
                    continue;
                }

                if ($user->status !== "enabled") {
                    $invalidUsers[] = [
                        'user_id' => $userId,
                        'reason' => 'Utilisateur désactivé'
                    ];
                    continue;
                }

                // Récupérer les soldes pour cet utilisateur
                $balances =funds::getUserBalancesGroupedByMoney($userId);

                $results[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name ?? null,
                    'balances' => $balances
                ];
            }

            return $this->successResponse('success',[
                'valid_users' => $results,
                'invalid_users' => $invalidUsers
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse("Erreur interne du serveur : " . $e->getMessage(), 500);
        }
    }

     /**
     * Récupère les soldes d’un utilisateur (groupés par monnaie)
     */
    public function getUserBalances(Request $request, $user_id)
    {
        try {
            // Vérifier que l’utilisateur connecté via le token existe
            $authUser = $request->user();
            if (!$authUser) {
                return $this->errorResponse("Utilisateur non authentifié.", 401);
            }

            // Vérifier que l’utilisateur cible existe
            $user =User::find($user_id);
            if (!$user) {
                return $this->errorResponse("Utilisateur introuvable.", 404);
            }

            // Vérifier le statut de l’utilisateur
            if ($user->status !== "enabled") {
                return $this->errorResponse("Utilisateur désactivé.", 403);
            }

            // Récupérer les soldes groupés par devise
            $balances =funds::getUserBalancesGroupedByMoney($user_id);

            return $this->successResponse("success",$balances);

        } catch (\Exception $e) {
            return $this->errorResponse("Erreur interne du serveur : " . $e->getMessage(), 500);
        }
    }

    public function mines($user)
    {
        $list = [];
        $actualuser = $this->getinfosuser($user);
        $ese = $this->getEse($user);

        if ($actualuser) {
            $list = funds::join('users as U', 'funds.user_id', '=', 'U.id')
                ->join('moneys as M', 'funds.money_id', '=', 'M.id')
                ->where('funds.user_id', $actualuser->id)
                ->where('funds.fund_status', 'enabled')
                ->get([
                    'M.abreviation as money_abreviation',
                    'M.billages',
                    'U.user_name',
                    'funds.*'
                ]);

            $list->transform(function ($item) {
                if (is_string($item->billages)) {
                    $decoded = json_decode($item->billages, true);
                    $item->billages = is_array($decoded) ? $decoded : [];
                }
                return $item;
            });
        }

        return $list;
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   public function store(Request $request)
    {
        DB::beginTransaction(); // ✅ Démarrer la transaction
        try {
            // Vérifier si le solde est renseigné
            if (isset($request['sold']) && $request['sold'] > 0) {
                // ok
            } else {
                $request['sold'] = 0;
            }

            // Vérifier le cas du principal
            if (funds::count() > 0) {
                if ($request['principal'] == true) {
                    // mettre tous les autres funds en non-principal
                    $this->updatllfundstofalse();
                }
            } else {
                $request['principal'] = 1;
            }

            // Vérification de la duplication de fonds pour le type "automatic"
            if ($request['type'] === 'automatic') {
                $exists = funds::where('enterprise_id', $request['enterprise_id'])
                    ->where('money_id', $request['money_id'])
                    ->where('type', 'automatic')
                    ->exists();

                if ($exists) {
                    DB::rollBack(); // Annuler la transaction
                    return $this->errorResponse('automatic fund duplicated', 422);
                }
            }

            // Ajouter la vérification pour la duplication de la caisse (même entreprise et même description)
            $existingFund = funds::where('enterprise_id', $request['enterprise_id'])
                ->where('description', $request['description']) // Vérifier la description
                ->exists();

            if ($existingFund) {
                DB::rollBack(); // Annuler la transaction
                return $this->errorResponse('duplicated', 422); // Message d'erreur "duplicated"
            }

            // Création du fund
            $fund = funds::create($request->all());

            // Historique si solde initial > 0
            if ($fund->sold > 0) {
                requestHistory::create([
                    'done_at' => date('Y-m-d'),
                    'user_id' => $request->created_by,
                    'fund_id' => $fund->id,
                    'amount' => $fund->sold,
                    'motif' => 'Premier approvisionnement',
                    'type' => 'entry',
                    'enterprise_id' => $request->enterprise_id,
                    'uuid' => $this->getUuId('C', 'RH'),
                    'sold' => $fund->sold
                ]);
            }

            DB::commit();

            return $this->successResponse('success', $this->show($fund));

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'error',
                500
            );
        }
    }



    /**
     * update all principal field funds to false
     */
    public function updatllfundstofalse(){
        DB::update('update funds set principal =0'); 
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\funds  $funds
     * @return \Illuminate\Http\Response
     */
    public function show(funds $funds)
    {
        return funds::leftjoin('users as U', 'funds.user_id','=','U.id')
        ->leftjoin('moneys as M', 'funds.money_id','=','M.id')
        ->where('funds.id','=',$funds->id)
        ->get(['M.abreviation as money_abreviation', 'U.user_name', 'funds.*'])[0];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\funds  $funds
     * @return \Illuminate\Http\Response
     */
    public function edit(funds $funds)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatefundsRequest  $request
     * @param  \App\Models\funds  $funds
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatefundsRequest $request, funds $funds)
    {
        $element = funds::find($funds);
        return $element->update($request->all());
    }

    /**
     * Update a specific fund
     */
    public function update2(Request $request, $funds)
    {
        DB::beginTransaction(); // ✅ Début de la transaction
        try {
            $element = funds::find($funds);

            if (!$element) {
                DB::rollBack();
                return $this->errorResponse('fund not found', 404);
            }

            // Si la caisse doit devenir principale
            if ($request['principal'] == 1) {
                $this->updatllfundstofalse();
                $request['principal'] = true;
            }

            // Vérifier si une autre caisse avec la même description existe dans la même entreprise
            $existingFund = funds::where('enterprise_id', $request['enterprise_id'])
                ->where('description', $request['description'])
                ->where('id', '!=', $funds) // Exclure l'élément en cours de modification
                ->exists();

            if ($existingFund) {
                DB::rollBack(); // Annuler la transaction
                return $this->errorResponse('duplicated', 422);
            }

            $element->update($request->all());

            DB::commit(); // ✅ Valider la transaction

            return $this->successResponse('success', $this->show($element));

        } catch (\Exception $e) {
            DB::rollBack(); // ❌ Annuler la transaction en cas d’erreur
            return $this->errorResponse('error', 500);
        }
    }


    /**
     * Reset a specific fund
     */
    public function reset(Request $request){
        $requestHistoryCtrl= new RequestHistoryController();
        DB::update('update funds set sold=? where id =? ',[$request['amount'],$request['fund_id']]);
        $tub=funds::find($request['fund_id']);
       
         //archive the operation in request history
         $history = new Request();
         $history['user_id'] = $request['user_id'];
         $history['fund_id'] = $request['fund_id'];
         $history['amount'] =$request['amount'];
         $history['type'] ='entry';
         $history['uuid'] =$this->getUuId('C','RS');
         $history['enterprise_id'] =$this->getEse($request['user_id'])['id'];
         $history['motif'] = 'opening balance';
         $history['done_at'] =date('Y-m-d');
         $mouv_history=requestHistory::create($history->all());

        return ['tub'=>$this->show($tub),'history'=>$requestHistoryCtrl->show($mouv_history)];
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\funds  $funds
     * @return \Illuminate\Http\Response
     */
    public function destroy(funds $funds)
    {
        return funds::destroy($funds);
    }

    /**
     * Remove the specified resource from storage by forcing
     */
    public function destroy2($id){

        $funds=funds::find($id);
       
        $histories=requestHistory::where('fund_id',$funds->id)->get();
        if (count($histories)>0) {
            requestHistory::where('fund_id',$funds->id)->delete();
        } 
        return  funds::find($id)->delete();
    }

    /**
     * getting a specific resource in using the Id
     */
    public function getByid($id) {
        
        $data = funds::find($id);
        if(is_null($data)) {
            return response()->json(['message' => 'Data not found'], 200);
        }
        return response()->json($data::find($id), 200);
    }

   
    /**
     * request histories by agent
     */
    public function requesthistoriesbyagent(Request $request){
        $listfunds=[];
        if(isset($request->from)==false && empty($request->from) && isset($request->to)==false && empty($request->to)){
            $request['from']= date('Y-m-d');
            $request['to']=date('Y-m-d');
        }

        if (isset($request->user_id)) {
            $actualuser=$this->getinfosuser($request->user_id);
            if ($actualuser) {
                $ese=$this->getEse($actualuser->id);
                if ($ese) {
                    $moneys=collect(moneys::where('enterprise_id',$ese->id)->get());
                    if ($actualuser['user_type']!=='super_admin') {
                        $list= funds::leftjoin('users as U', 'funds.user_id','=','U.id')
                        ->leftjoin('moneys as M', 'funds.money_id','=','M.id')
                        ->where('user_id','=',$request->user_id)
                        ->get(['M.abreviation as money_abreviation', 'U.user_name', 'funds.*']);
                        if ($request['funds'] && count($request['funds'])>0) {
                            $listfunds=$request['funds'];
                        }else{
                            $listfunds=$list->pluck('id')->toArray();
                        }
                    }
                    else{
                        $list= funds::leftjoin('users as U', 'funds.user_id','=','U.id')
                        ->leftjoin('moneys as M', 'funds.money_id','=','M.id')
                        ->where('funds.enterprise_id',$ese->id)
                        ->get(['M.abreviation as money_abreviation', 'U.user_name', 'funds.*']);

                        if ($request['funds'] && count($request['funds'])>0) {
                            $listfunds=$request['funds'];
                        }else{
                            $listfunds=$list->pluck('id')->toArray();
                        }
                    }

                    if (count($listfunds)>0) {
                        # get request histories for the funds
                        try {
                            
                            $requestHistoryCtrl = new RequestHistoryController();
                            $histories=collect(requestHistory::whereIn('fund_id',$listfunds)
                            ->whereBetween('done_at',[$request['from'].' 00:00:00',$request['to'].' 23:59:59'])
                            ->get()); 

                            if($request['accounts'] && count($request['accounts'])>0){
                                $histories=collect(requestHistory::whereIn('fund_id',$listfunds)
                                ->whereIn('account_id',$request['accounts'])
                                ->whereBetween('done_at',[$request['from'].' 00:00:00',$request['to'].' 23:59:59'])
                                ->get());
                            }
                            
                            if($request['agents'] && count($request['agents'])>0){
                                $histories=collect(requestHistory::whereIn('fund_id',$listfunds)
                                ->whereIn('user_id',$request['agents'])
                                ->whereBetween('done_at',[$request['from'].' 00:00:00',$request['to'].' 23:59:59'])
                                ->get());
                            }
                           
                            $histories=$histories->transform(function ($item) use($requestHistoryCtrl){
                               return  $requestHistoryCtrl->show($item);
                            });

                            //beneficiary marges calculation
                            $providersdebts=StockHistoryController::select(DB::raw('sum(total) as total_debts'))
                                ->where('type','=','entry')
                                ->where('enterprise_id','=',$ese->id)
                                ->where('type_approvement','=','credit')
                                ->whereBetween('done_at',[$request['from'].' 00:00:00',$request['to'].' 23:59:59'])
                                ->get()->first();

                            $advances=providerspayments::select(DB::raw('sum(amount) as total_advances'))
                            ->where('enterprise_id','=',$ese->id)
                            ->whereBetween('done_at',[$request['from'].' 00:00:00',$request['to'].' 23:59:59'])
                            ->get()->first();

                            $soldDebts=($providersdebts['total_debts']?$providersdebts['total_debts']:0)-($advances['total_advances']?$advances['total_advances']:0);

                            $summaries=response()->json([
                                "totaltovalidate"=>$histories->where('status','pending')->count(),
                                "totalvalidated"=>$histories->where('status','validated')->count(),
                                "totalcancelled"=>$histories->where('status','cancelled')->count(),
                                "totalwithoutaccount"=>$histories->where('account_id',null)->count(),
                                "totalentries"=>$histories->where('type','entry')->count(),
                                "totalwithdraw"=>$histories->where('type','withdraw')->count()
                            ]);

                            $subtotalsentries=$this->generalmethodgroupedbymoneys(new Request([
                                    "filter"=>"entries_requesthistory",
                                    "columnsumb"=>"amount",
                                    "data"=>$histories->where('type','entry'),
                                    "enterprise_id"=>$ese->id
                            ]));

                            $subtotalswithdraw=$this->generalmethodgroupedbymoneys(new Request([
                                    "filter"=>"withdraw_requesthistory",
                                    "columnsumb"=>"amount",
                                    "data"=>$histories->where('type','withdraw'),
                                    "enterprise_id"=>$ese->id
                            ]));

                            $subtotalsbank=$this->generalmethodgroupedbymoneys(new Request([
                                    "filter"=>"funds",
                                    "columnsumb"=>"sold",
                                    "data"=>$this->listfunds($ese->id,'bank'),
                                    "enterprise_id"=>$ese->id
                            ]));

                            $totalgenerals=$moneys->transform(function ($money) use($soldDebts,$subtotalsentries,$subtotalswithdraw,$subtotalsbank){
                                $money['totalgeneral']=$money['totalgeneral']+($subtotalsentries->where('id',$money['id'])->sum('total'));
                                $money['totalgeneral']=$money['totalgeneral']+($subtotalsbank->where('id',$money['id'])->sum('total'));
                                $money['totalgeneral']=$money['totalgeneral']-($subtotalswithdraw->where('id',$money['id'])->sum('total'));
                               if ($money['principal']==1) {
                                $money['totalgeneral_after_paying']=$money['totalgeneral']-$soldDebts;
                               }else{
                                $money['totalgeneral_after_paying']=$money['totalgeneral'];
                               }

                                return $money;
                            });

                            return response()->json([
                                "status"=>200,
                                "message"=>"success",
                                "error"=>null,
                                "subtotaldebts"=>$providersdebts['total_debts']?$providersdebts['total_debts']:0,
                                "subtotalpayments"=>$advances['total_advances']?$advances['total_advances']:0,  
                                "subtotalprovidersdebts"=>$providersdebts['total_debts']?$providersdebts['total_debts']:0,
                                "subtotalproviderspayments"=>$advances['total_advances']?$advances['total_advances']:0,
                                "subtotalsentries"=>$subtotalsentries,
                                "subtotalswithdraw"=>$subtotalswithdraw,
                                "subtotalsbank"=>$subtotalsbank,
                                "subtotalgenerals"=>$totalgenerals,
                                "soldDebtsproviders"=>$soldDebts,
                                "data"=>$histories,
                                "summary"=>$summaries->original
                            ]);
                        } catch (Exception $th) {
                            return response()->json([
                                "status"=>500,
                                "message"=>"error occured",
                                "error"=>$th->getMessage(),
                                "data"=>null
                            ]);
                        }
                    }
                }else{
                    return response()->json([
                        "status"=>400,
                        "message"=>"unknown enterprise",
                        "error"=>"unknown enterprise",
                        "data"=>null
                    ]);
                }
                
            }else{
                return response()->json([
                    "status"=>400,
                    "message"=>"unknown user",
                    "error"=>"unknown user",
                    "data"=>null
                ]);
            }
        }else{
            return response()->json([
                "status"=>400,
                "message"=>"unknown user",
                "error"=>"unknown user",
                "data"=>null
            ]);
        }
    }

    public function getSold($id){
        $data=funds::find($id);
        return $data['sold'];  
    }

}
