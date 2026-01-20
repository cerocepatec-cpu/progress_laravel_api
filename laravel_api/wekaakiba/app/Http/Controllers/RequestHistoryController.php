<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\funds;
use App\Models\images;
use App\Models\libraries;
use App\Models\Expenditures;
use Illuminate\Http\Request;
use App\Models\requestHistory;
use App\Models\providerspayments;
use App\Models\ProviderController;
use App\Models\ServicesController;
use App\Models\wekamemberaccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\StockHistoryController;
use App\Models\wekaAccountsTransactions;
use App\Http\Requests\StorerequestHistoryRequest;
use App\Http\Requests\UpdaterequestHistoryRequest;
use App\Http\Requests\StoreproviderspaymentsRequest;

class RequestHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $list =requestHistory::join('users','request_histories.user_id','=','users.id')->get(['request_histories.*','users.user_name']);
        return $list;
    }

    public function getOperationById($mouvementId){
        try{
            return response()->json([
                "message"=>"success",
                "status"=>200,
                "error"=>null,
                "data"=>$this->show(requestHistory::find($mouvementId))
            ]);
            
        }catch(Exception $e){
            return response()->json([
                "message"=>"error",
                "status"=>200,
                "error"=>$e->getMessage(),
                "data"=>null
            ]);
        }  
    }

     /**
     * search with pagination
     */
    public function searchingrequesthistories(Request $request){
              
        $searchTerm = $request->query('keyword', '');
        $enterpriseId = $request->query('enterprise_id', 0);  
        $actualuser=$this->getinfosuser($request->query('user_id'));
        if ($actualuser['user_type']=='super_admin') {
            
            $list =requestHistory::query()
                ->join('funds', 'request_histories.fund_id', '=', 'funds.id')
                ->join('moneys', 'funds.money_id', '=', 'moneys.id')
                ->where('request_histories.enterprise_id', '=', $enterpriseId)
                ->where(function($query) use ($searchTerm) {
                    $query->where('request_histories.uuid', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.amount', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.type', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.beneficiary', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.provenance', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.motif', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.status', 'LIKE', "%$searchTerm%")
                        ->orWhere('request_histories.sold', 'LIKE', "%$searchTerm%")
                        ->orWhere('funds.description', 'LIKE', "%$searchTerm%")
                        ->orWhere('moneys.abreviation', 'LIKE', "%$searchTerm%")
                        ->orWhere('moneys.money_name', 'LIKE', "%$searchTerm%");
                })
                ->select('request_histories.*')
                ->paginate(10)
                ->appends($request->query());


            $list->getCollection()->transform(function ($item){
                return $this->show($item);
            });
            return $list;

        } else {
           
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
     * update 
     */
    public function operationsupdate(Request $request){
        $data=[];
        if ($request['criteria']) {
            if ($request['user'] && $this->getEse($request['user']['id'])) {
                if ($request['data'] && count($request['data'])>0) {
                    try {
                        $status="";
                        switch ($request['criteria']) {
                            case 'validate':
                                $status="validated";
                                break;
                            case 'pending':
                                $status="pending";
                                break;
                            case 'cancel':
                                $status="cancelled";
                                break;
                            
                            default:
                                # code...
                                break;
                        }

                        foreach ($request['data'] as $expenditure) {
                            $finded=requestHistory::find($expenditure['id']);
                            if ($finded){
                                $finded['status']=$status;
                                // if ($status=="validated") {
                                //     $finded['is_validated']=true;
                                // }else{
                                //     $finded['is_validated']=false;
                                // } 
                                
                                $finded->save();
                                array_push($data,$finded);
                            }
                         }
                            return response()->json([
                            "status"=>200,
                            "message"=>"success",
                            "error"=>null,
                            "data"=>$data
                        ]);
                    } catch (Exception $th) {
                        return response()->json([
                            "status"=>500,
                            "message"=>"error",
                            "error"=>$th->getMessage(),
                            "data"=>null
                        ]);
                    }
                  
                }else{
                    return response()->json([
                        "status"=>400,
                        "message"=>"error",
                        "error"=>"no data sent",
                        "data"=>null
                    ]); 
                }
            }else{
                //unauthorized user
                return response()->json([
                    "status"=>400,
                    "message"=>"error",
                    "error"=>"unauthrized sent",
                    "data"=>null
                ]); 
            }
        }else{
            //no criteria sent
            return response()->json([
                "status"=>400,
                "message"=>"error",
                "error"=>"no criteria sent",
                "data"=>null
            ]); 
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorerequestHistoryRequest  $request
     * @return \Illuminate\Http\Response
     */
   public function store(StorerequestHistoryRequest $request)
    {
        DB::beginTransaction();

        try {
            $request['done_at'] = date('Y-m-d');
            $user = auth()->user();
            $request['enterprise_id'] = $this->getEse($user->id)['id'];

            switch ($request->nature) {
                case 'transfert':
                    $newvalue = $this->handleTransfer($request);
                    break;

                case 'approvment':
                    $newvalue = $this->handleApprovment($request);
                    break;

                case 'expenditure':
                    $newvalue = $this->handleExpenditure($request);
                    break;

                default:
                $newvalue = $this->handleClassicOperation($request);
                break;
            }

            DB::commit();
            return $this->successResponse('success',$newvalue);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }


    private function handleTransfer($request)
    {
        $fundSender = funds::find($request->fund_id);
        $fundReceiver = funds::find($request->fund_receiver_id);

        if (!$fundSender || !$fundReceiver) {
            throw new \Exception("L'une des caisses sélectionnées n'existe pas.");
        }

        if ($fundSender->sold < $request->amount) {
            throw new \Exception("Solde insuffisant dans la caisse source.");
        }

        // Retrait (withdraw)
        $withdraw = requestHistory::create([
            'user_id'        => $request->user_id,
            'fund_id'        => $fundSender->id,
            'amount'         => $request->amount,
            'motif'          => "Transfert vers {$fundReceiver->name}",
            'type'           => 'withdraw',
            'enterprise_id'  => $request->enterprise_id,
            'sold'           => $fundSender->sold - $request->amount,
            'done_at'        => $request->done_at,
            'uuid'           => $this->getUuId('R', 'TR'),
            'fund_receiver_id' => $fundReceiver->id,
            'nature'         => 'transfert'
        ]);

        // Mise à jour sold
        $fundSender->update(['sold' => $fundSender->sold - $request->amount]);

        // Entrée (entry)
        $entry = requestHistory::create([
            'user_id'        => $request->user_id,
            'fund_id'        => $fundReceiver->id,
            'amount'         => $request->amount,
            'motif'          => "Transfert reçu de {$fundSender->name}",
            'type'           => 'entry',
            'enterprise_id'  => $request->enterprise_id,
            'sold'           => $fundReceiver->sold + $request->amount,
            'done_at'        => $request->done_at,
            'uuid'           => $this->getUuId('E', 'TR'),
            'fund_receiver_id' => $fundReceiver->id,
            'nature'         => 'transfert'
        ]);

        $fundReceiver->update(['sold' => $fundReceiver->sold + $request->amount]);

        return $entry;
    }


   private function handleApprovment($request)
    {
        $user=Auth::user();
        $fund = funds::find($request->fund_id);
        $account = wekamemberaccounts::find($request->member_account_id);

        if (!$fund || !$account) {
            throw new \Exception("Caisse ou compte membre introuvable.");
        }

        if ($fund->sold < $request->amount) {
            throw new \Exception("Solde insuffisant pour l’approvisionnement.");
        }

        // Sortie dans la caisse
        $withdraw = requestHistory::create([
            'user_id'       => $request->user_id,
            'fund_id'       => $fund->id,
            'amount'        => $request->amount,
            'motif'         => "Approvisionnement du compte membre #{$account->account_number}",
            'type'          => 'withdraw',
            'enterprise_id' => $request->enterprise_id,
            'sold'          => $fund->sold - $request->amount,
            'done_at'       => $request->done_at,
            'uuid'          => $this->getUuId('W', 'AP'),
            'member_account_id' => $account->id,
            'nature'        => 'approvment'
        ]);

        $fund->update(['sold' => $fund->sold - $request->amount]);

        // Mise à jour du compte membre
        $sold_before = $account->sold;
        $account->update(['sold' => $sold_before + $request->amount]);
        $account->refresh();

        // Enregistrement dans WekaAccountsTransactions
        $accountTransaction=wekaAccountsTransactions::create([
            'amount'            => $request->amount,
            'sold_before'       => $sold_before,
            'sold_after'        => $sold_before + $request->amount,
            'type'              => 'entry',
            'motif'             =>$request->motif??"Approvisionnement depuis la caisse {$fund->description}",
            'user_id'           => $user->id,
            'member_account_id' => $account->id,
            'enterprise_id'     => $request->enterprise_id,
            'done_at'           => $request->done_at,
            'uuid'              => $this->getUuId('A', 'AP'),
            'transaction_status'=> 'validated',
            'member_id'=>$account->user_id
        ]);

        event(new \App\Events\UserRealtimeNotification(
            $account->user_id,
            'Nouveau dépôt',
            'Vous avez reçu un dépôt de '.$request->amount.' '.wekamemberaccounts::getMoneyAbreviationByAccountNumber($account->account_number),
            'success'
        ));
         
        $transactionCtrl = new WekaAccountsTransactionsController();
        event(new \App\Events\TransactionSent(
            $account->user_id,
            $transactionCtrl->show($accountTransaction)
        ));

        event(new \App\Events\MemberAccountUpdated(
            $account->user_id,
            $account
        )); 

        return $withdraw;
    }

    private function handleExpenditure($request)
    {
        $fund = funds::find($request->fund_id);
        if (!$fund) {
            throw new \Exception("Caisse introuvable pour la dépense.");
        }

        if ($fund->sold < $request->amount) {
            throw new \Exception("Solde insuffisant pour la dépense.");
        }

        // Retrait (withdraw)
        $withdraw = requestHistory::create([
            'user_id'       => $request->user_id,
            'fund_id'       => $fund->id,
            'amount'        => $request->amount,
            'motif'         => $request->motif,
            'type'          => 'withdraw',
            'enterprise_id' => $request->enterprise_id,
            'sold'          => $fund->sold - $request->amount,
            'done_at'       => $request->done_at,
            'uuid'          => $this->getUuId('W', 'EX'),
            'nature'        => 'expenditure',
            'beneficiary'   => $request->beneficiary ?? 'N/A'
        ]);

        $fund->update(['sold' => $fund->sold - $request->amount]);

        // Enregistrement de la dépense
        Expenditures::create([
            'user_id'        => $request->user_id,
            'money_id'       => $fund->money_id,
            'ticket_office_id'=> $fund->id,
            'amount'         => $request->amount,
            'motif'          => $request->motif,
            'account_id'     => $request->account_id ?? null,
            'is_validate'    => false,
            'uuid'           => $this->getUuId('E', 'EX'),
            'done_at'        => $request->done_at,
            'beneficiary'    => $request->beneficiary ?? null,
            'enterprise_id'  => $request->enterprise_id,
            'status'         => 'pending'
        ]);

        return $withdraw;
    }

   private function handleClassicOperation($request)
    {
        // si $request est un FormRequest / Request, on récupère les données
        $data = is_array($request) ? $request : $request->all();

        // Vérifier présence du fund_id
        if (empty($data['fund_id'])) {
            return $this->errorResponse("Identifiant de la caisse (fund_id) manquant.", 422);
        }

        $fund = funds::find($data['fund_id']);
        if (!$fund) {
            return $this->errorResponse("La caisse spécifiée est introuvable.", 404);
        }

        // Normaliser le montant
        $amount = floatval($data['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->errorResponse("Montant invalide.", 422);
        }

        // Traitement selon le type
        if (($data['type'] ?? '') === 'entry') {
            // entrée : incrémenter le sold
            $fund->increment('sold', $amount);
        } else {
            // sortie : vérifier solde puis décrémenter
            if ($fund->sold < $amount) {
                return $this->errorResponse("Solde insuffisant pour ce retrait.", 422);
            }
            $fund->decrement('sold', $amount);
        }

        // Rafraîchir le modèle pour obtenir le sold à jour
        $fund->refresh();
        $data['sold'] = $fund->sold;
        $data['done_at'] = $data['done_at'] ?? now()->toDateString();

        // Créer l'enregistrement dans request_history
        $record = requestHistory::create($data);
        return $record;
    }

    private function makingnewstockhistoryforprovider($request){

        $provider=ProviderController::find($request['provider_id']);
        $service=ServicesController::find($request['service_id']);
        StockHistoryController::create([
            'provider_id'=>$request['provider_id'],
            'service_id'=>$request['service_id'],
            'user_id'=>$request['user_id'],
            'quantity'=>$request['quantity_provided']?$request['quantity_provided']:1,
            'price'=>$request['quantity_provided']?($request['amount_provided']/$request['quantity_provided']):$request['amount_provided'],
            'type'=>'entry',
            'type_approvement'=>'credit',
            'enterprise_id'=>$request['enterprise_id'],
            'motif'=>$request['motif']?$request['motif']:'Location '.$service->name.' auprès du fournisseur '.$provider->providerName,
            'done_at'=>$request['done_at'],
            'date_operation'=>$request['done_at'],
            'uuid'=>$this->getUuId('C','ST'),
            'depot_id'=>$this->defaultdeposit($request['enterprise_id'])['id'],
            'quantity_before'=>0,
            'total'=>$request['quantity_provided']?($request['amount_provided']*$request['quantity_provided']):$request['amount_provided'],
            'requesthistory_id'=>$request->id
        ]);
    }

    private function makingproviderpayments($request){
         //payments to do
         if ($request['provider_id']) {
            $amountSent=$request['amount'];
            //select all his debts
            $stockhistories=collect(StockHistoryController::leftjoin('providerspayments as P','stock_history_controllers.id','=','P.stock_history_id')
            ->select(DB::raw('stock_history_controllers.id as stock_history'),DB::raw('sum(P.amount) as totalpayed'),DB::raw('sum(stock_history_controllers.total) as totaldebts'))
            ->where('stock_history_controllers.type','entry')
            ->where('stock_history_controllers.provider_id',$request['provider_id'])
            ->where('stock_history_controllers.type_approvement','credit')
            ->groupByRaw('stock_history_controllers.id')
            ->havingRaw('totaldebts > totalpayed')
            ->orHavingRaw('totalpayed IS NULL')
            ->orderBy('stock_history_controllers.done_at','ASC')->get());
            while ($amountSent > 0) {
                foreach ($stockhistories as  $stock) {
                    $amountToPaye=0;
                    $soldDebt=($stock->totaldebts)-($stock->totalpayed?$stock->totalpayed:0);
                    if ($soldDebt>=$amountSent) {
                        $amountToPaye=$amountSent;
                    }
                    
                    if ($soldDebt<$amountSent) {
                        $amountToPaye=$soldDebt;   
                    }

                    $newrequest=new StoreproviderspaymentsRequest([
                        'done_by'=>$request['user_id'],
                        'provider_id'=>$request['provider_id'],
                        'stock_history_id'=>$stock['stock_history'],
                        'enterprise_id'=>$request['enterprise_id'],
                        'status'=>'pending',
                        'note'=>$request['motif'],
                        'amount'=>$amountToPaye,
                        'uuid'=>$this->getUuId('C','PP'),
                        'done_at'=>$request['done_at']
                    ]);
                    providerspayments::create($newrequest->all());
                    $amountSent=$amountSent-$amountToPaye;
                }
            }
        }
    }
    /**
     * save multiples
     */
    public function savemultiple(Request $request)
    {
        $data = [];

        if ($request->data && count($request->data) > 0) {
            try {
                foreach ($request->data as $item) {
                    $response = $this->store(new StorerequestHistoryRequest($item));
                    if ($response instanceof \Illuminate\Http\JsonResponse) {
                        $decoded = $response->getData(true);

                        if (isset($decoded['data'])) {
                            $saved = $decoded['data'];
                            if (isset($saved['id'])) {
                                $showResponse = $this->show(requestHistory::find($saved['id']));

                                if ($showResponse instanceof \Illuminate\Http\JsonResponse) {
                                    $showDecoded = $showResponse->getData(true);
                                    if (isset($showDecoded['data'])) {
                                        $data[] = $showDecoded['data'];
                                    }
                                } else {
                                    $data[] = $showResponse;
                                }
                            }
                        }
                    }
                }
                return response()->json([
                    "status" => 200,
                    "message" => "success",
                    "error" => null,
                    "data" => $data
                ]);
            } catch (\Exception $th) {
                return response()->json([
                    "status" => 500,
                    "message" => "error occured",
                    "error" => $th->getMessage(),
                    "data" => null
                ]);
            }
        }

        return response()->json([
            "status" => 500,
            "message" => "error occured",
            "error" => "no data sent",
            "data" => null
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\requestHistory  $requestHistory
     * @return \Illuminate\Http\Response
     */
    public function show(requestHistory $requestHistory)
    {
        $attachments=[];
        $data=requestHistory::join('users','request_histories.user_id','=','users.id')
                            ->join('funds as F','request_histories.fund_id','F.id')
                            ->join('moneys as M','F.money_id','M.id')
                            ->leftjoin('accounts as A','request_histories.account_id','A.id')
                            ->leftjoin('stock_history_controllers as SH','request_histories.id','SH.requesthistory_id')
                            ->leftjoin('provider_controllers as P','SH.provider_id','P.id')
                            ->leftjoin('services_controllers as S','SH.service_id','S.id')
                            ->leftjoin('unit_of_measure_controllers as UOM','S.uom_id','UOM.id')
                            ->where('request_histories.id','=',$requestHistory->id)
                            ->get(['UOM.name as uom_name','UOM.symbol as uom_symbol','SH.quantity as quantity_provided','SH.provider_id','SH.total as amount_provided','P.providerName','S.name as servicename','request_histories.*','A.name as account_name','F.money_id','F.description as fund_name','M.abreviation','users.user_name'])->first();
        if ($data) {
            $attachments=images::join('libraries as L','images.doc_link','=','L.id')->where('images.type_operation','request_history')->where('images.ref_operation',$requestHistory->id)->get('L.*');
        }
        $data['attachments']=$attachments;
        return $data;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\requestHistory  $requestHistory
     * @return \Illuminate\Http\Response
     */
    public function edit(requestHistory $requestHistory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdaterequestHistoryRequest  $request
     * @param  \App\Models\requestHistory  $requestHistory
     * @return \Illuminate\Http\Response
     */
    public function update(UpdaterequestHistoryRequest $request, requestHistory $requestHistory)
    {
        $finded=requestHistory::find($requestHistory->id);
        if ($finded) {
            if ($finded->status=='cancelled' && $finded->status=='validated') {
                return response()->json([
                    "status"=>400,
                    "message"=>"error",
                    "error"=>'closed',
                    "data"=>$this->show($finded)
                ]);
            }else{
                try {
                    $linefind=$this->show($finded);
                    $finded->update($request->only([
                        'motif',
                        'account_id',
                        'done_at',
                        'provenance',
                        'beneficiary',
                        'status'
                    ]));
                    if ($request['provider_id'] || $request['service_id']) {
                        $stockhistory=StockHistoryController::where('requesthistory_id', $linefind['id'])->first();
                            if ($stockhistory) {
                                $stockhistory->update($request->only([
                                    'provider_id','service_id'
                                ]));
                            }else{
                                $linefind['service_id']=$request['service_id'];
                                $linefind['provider_id']=$request['provider_id'];
                                $linefind['amount_provided']=$linefind['amount'];
                                //creating debt or making payment for the provider
                                if ($finded->type=='entry') {
                                   $this->makingnewstockhistoryforprovider($linefind);
                                }else{
                                    $this->makingproviderpayments($linefind);
                                }
                            }
                    }

                    if ($request['attachments'] && count($request['attachments'])>0) {
                        foreach ($request['attachments'] as $key => $attachment) {
                            $libraryfind=libraries::find($attachment['id']);
                            if ($libraryfind) {
                                $newimage=images::create([
                                    'doc_link'=>$libraryfind['id'],
                                    'description'=>$linefind['motif'],
                                    'type_operation'=>'request_history',
                                    'ref_operation'=>$linefind['id'],
                                    'done_by'=>$linefind['user_id'],
                                    'enterprise_id'=>$linefind['enterprise_id'],
                                    'size'=>$libraryfind['size'],
                                    'principal'=>$key==0?true:false
                                ]);
                            }
                        }
                    }

                    return response()->json([
                        "status"=>200,
                        "message"=>"success",
                        "error"=>null,
                        "data"=>$this->show($finded)
                    ]);
                } catch (Exception $th) {
                    return response()->json([
                        "status"=>500,
                        "message"=>"error",
                        "error"=>$th->getMessage(),
                        "data"=>null
                    ]);
                }
            }
           
        }else{
            return response()->json([
                "status"=>500,
                "message"=>"error",
                "error"=>"no data finded",
                "data"=>null
            ]);
        }
    }

    public function getbyfund($fund){
        $list =requestHistory::join('users','request_histories.user_id','=','users.id')->where('fund_id','=',$fund)->get(['request_histories.*','users.user_name']);
        return $list;
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\requestHistory  $requestHistory
     * @return \Illuminate\Http\Response
     */
    public function destroy(requestHistory $requestHistory)
    {
        //
    }
}
