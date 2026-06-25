<?php

namespace App\Http\Controllers;

use App\Models\DepositController;
use App\Models\User;
use App\Models\moneys;
use App\Models\Enterprises;
use App\Models\enterprisesettings;
use App\Models\enterprisesinvoices;
use App\Models\funds;
use App\Models\Invoices;
use App\Models\libraries;
use Illuminate\Support\Str;
use App\Models\PricesCategories;
use App\Models\requestHistory;
use App\Models\ServicesController;
use App\Models\usersenterprise;
use App\Models\UsersMobileMoneyProviders;
use App\Models\wekaAccountsTransactions;
use App\Models\wekamemberaccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="CERO UZISHA REST API DOCUMENTATION",
 *      description="L5 Swagger OpenApi for Cero Point of sale",
 *      @OA\Contact(
 *          email="kilimbanyifabrice@gmail.com"
 *      ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="https://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function generateUuid($prefix = 'WEKA')
    {
        $now = now();
        $random = strtoupper(substr(uniqid(), -3));
        $uuid = $prefix . $now->format('YdmHis') . $random;
        return $uuid;
    }

    /**
     * look for user and mobile money provider
     * @param Request $request
     */
    public function findUserAndProvider($user_id, $mobile_money_provider_id){
         return UsersMobileMoneyProviders::where('user_id',$user_id)->where('mobile_money_provider_id',$mobile_money_provider_id)->first();
    }
     /**
     * general method grouped by moneys
     */
    public function getConversionRate($fromCurrencyId, $toCurrencyId)
    {
        // ⚠️ À adapter selon ta logique métier ou ta table `currency_rates`
        if ($fromCurrencyId === $toCurrencyId) return 1;

        // Exemple fictif :
        $rate = DB::table('currency_rates')
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->value('rate');

        return $rate ?: null;
    }
    
    function createEnterpriseInvoice($enterpriseId, $planId, $amount,$description="", $currency = 'USD')
    {
        $invoice =enterprisesinvoices::create([
            'enterprise_id' => $enterpriseId,
            'plan_id' => $planId,
            'amount' => $amount,
            'currency' => $currency,
            'invoice_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(7),
            'status' => 'pending',
            'payment_method' => null,
            'details' => [
                'tax' => '20%',
                'discount' => '0%'
            ],
            'description'=>$description,
            'uuid'=>$this->getUuId('IE','C')
        ]);
    
        return $invoice;
    }
    
     public function errorResponse($message, $code = 400)
    {
        return response()->json([
            "status" => $code,
            "message" => "error",
            "error" => $message,
            "data" => null
        ]);
    } 
    
    public function successResponse($message='success', $data=null,$code = 200)
    {
        return response()->json([
            "status" => $code,
            "message" =>$message,
            "error" =>null ,
            "data" => $data
        ]);
    }
    
    public function generalmethodgroupedbymoneys(Request $request){
        $moneys=collect(moneys::where('enterprise_id',$request['enterprise_id'])->get());
        if ($request['filter']=='entries_requesthistory') {

            $moneys->transform(function ($money) use($request){ 
                $money['total']=0;
                $money['total']=$money['total']+($request['data']->where('money_id','=', $money['id'])->sum($request['columnsumb'])); 
                return $money;
            });
            return $moneys;
        }  
        
        if ($request['filter']=='withdraw_requesthistory') {

            $moneys->transform(function ($money) use($request){ 
                $money['total']=0;
                $money['total']= $money['total']+($request['data']->where('money_id','=', $money['id'])->sum($request['columnsumb']));
                return $money;
            });
            return $moneys;
        } 
        
        if ($request['filter']=='funds') {
        
            $moneys->transform(function ($money) use($request){ 
                $money['total']=0;
                $money['total']= $money['total']+($request['data']->where('money_id','=', $money['id'])->sum($request['columnsumb']));
                return $money;
            });
            return $moneys;
        }

        if ($request['filter']=="solds_net_from_request_histories") {
            $moneys->transform(function ($money) use($request){ 
                $money['totalgeneral']=0;
                // $money['totalgeneral']=$money['subtotalsold']+($request['data']->where('money_id','=', $money['id'])->sum($request['columnsumb']));
                return $money;
            });
            return $moneys;
        }
           
    }

    public function listfunds($enterpriseId, string $criteria){
        if ($criteria==="all") {
            return funds::where('enterprise_id',$enterpriseId)->get();
        }
        
        if ($criteria==="bank") {
            return funds::where('enterprise_id',$enterpriseId)->where('type','bank')->get();
        }

    }
    
    public function userenterpriseaffectation($user_id,$enterpriseId){
        return usersenterprise::where('enterprise_id',$enterpriseId)->where('user_id',$user_id)->first();
    }
    
    public function enterpriseSettings($enterpriseid){
        $storage=enterprisesettings::where('enterprise_id',$enterpriseid)->first();
        $images = Libraries::where('enterprise_id',$enterpriseid)->whereIn('extension',['png','jpg','jpeg'])->get();
        $docs = Libraries::where('enterprise_id',$enterpriseid)->whereNotIn('extension',['png','jpg','jpeg'])->get();
        $sizeimages=$images->sum('size');
        $sizedocs=$docs->sum('size');

        $totalstorage=($storage->storage)/1024000;
        $totalmedias=($sizeimages)/1024000;
        $totaldocs=($sizedocs)/1024000;
        $totalused=($totalmedias/1024000)+($totaldocs/1024000);
        $totalremain=$totalstorage-$totalused;

        return json_encode([
            "storage_allocated"=>$totalstorage,
            "medias_used"=>$totalmedias,
            "docs_used"=>$totaldocs,
            "total_used"=>$totalused,
            "remaining"=>$totalremain,
        ]);
    }

    public function reamingstorage($enterpriseId){
        $storage=json_decode($this->enterpriseSettings($enterpriseId));
        return $storage->remaining;
    }

    public function updaterequeststatus(int $requestid,string $status){

        $update=DB::update('update requests set status = ? where id = ?',[$status,$requestid]);
    }

    public function defaultmoney($enterpriseid){
        return moneys::where('enterprise_id','=',$enterpriseid)->where('principal','=',1)->first();
    }

    public function showService(ServicesController $servicesController)
    {
        $prices=PricesCategories::leftjoin('moneys as M','prices_categories.money_id','=','M.id')
        ->where('prices_categories.service_id','=',$servicesController->id)
        ->get(['M.money_name','M.abreviation','prices_categories.*']);

        $service=ServicesController::leftjoin('categories_services_controllers as C', 'services_controllers.category_id','=','C.id')
        ->leftjoin('unit_of_measure_controllers as U','services_controllers.uom_id','=','U.id')
        ->leftjoin('deposit_services','services_controllers.id','=','deposit_services.service_id')
        ->where('services_controllers.id', '=', $servicesController->id)
        ->get(['deposit_services.available_qte','C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','services_controllers.*'])[0];
        
        return ['service'=>$service,'prices'=>$prices];
    }

    public function getinfosuser($user_id){
        return User::find($user_id);
    }
    
    public function getUserByUsernameOrMail($keyword){
        $input = strtolower(trim($keyword));

        return User::where(function ($query) use ($input) {
                $query->whereRaw('LOWER(TRIM(user_name)) = ?', [$input])
                    ->orWhereRaw('LOWER(TRIM(user_mail)) = ?', [$input]);
            })
            ->where('status','enabled')
            ->first();
    }

    public function getEse($user_id){
        $enterprise=Enterprises::leftjoin('usersenterprises as UE', 'enterprises.id','=','UE.enterprise_id')->where('UE.user_id','=',$user_id)->get(['enterprises.*'])->first();
        if ($enterprise) {
            $enterprise['settings']=enterprisesettings::where('enterprise_id',$enterprise->id)->get()->first();
            return $enterprise;
        }else{
            return response()->json((object)[]);
        }  
    }

    public function usersInSameEnterprise($user1_id, $user2_id)
    {
        $enterprise1 = Enterprises::leftJoin('usersenterprises as UE', 'enterprises.id', '=', 'UE.enterprise_id')
            ->where('UE.user_id', '=', $user1_id)
            ->value('enterprises.id');

        $enterprise2 = Enterprises::leftJoin('usersenterprises as UE', 'enterprises.id', '=', 'UE.enterprise_id')
            ->where('UE.user_id', '=', $user2_id)
            ->value('enterprises.id');

        return $enterprise1 && $enterprise2 && $enterprise1 === $enterprise2;
    }


    public function isactivatedEse($EseId){
       $activation=Enterprises::where('id','=',$EseId)->get('status')[0];
       if($activation['status']=='enabled'){
        return true;
       }else{
        return false;
       }
    } 
    
    public function EseNumberUsers($EseId){
        
       $users=user::leftjoin('usersenterprises as UE','users.id','=','UE.user_id')
                    ->where('UE.enterprise_id','=',$EseId) 
                    ->get();
        return $users->count();
    
    }  
    
    public function EseNumberAccounts($EseId){
        
       $accounts=wekamemberaccounts::where('enterprise_id','=',$EseId)->get();
        return $accounts->count();
    }

    public function getStringUUID(){
        // return (string) Str::uuid();
        return (string) Str::orderedUuid();
    }

   public function getUuId(string $c1, string $c2): string
    {
        return sprintf(
            '%s%s.%s.%s',
            $c1,
            now()->format('Ymd'),
            str_replace('.', '', microtime(true)),
            $c2
        );
    }
 
    public function getinvoiceUuid($EseId){
        $lastinvoice= DB::table('invoices')->latest('created_at')->first();
       if($lastinvoice){
        return ;
        $newinvoicenumber='F'.Carbon::now()->format('YmdHis').'C'.$lastinvoice['id']+1+$EseId;
       }
       else{
        $newinvoicenumber='F'.Carbon::now()->format('YmdHis').'C'.$EseId;
       }
       
        return $newinvoicenumber;
    }

    public function getdefaultmoney($EseId){
        return moneys::where('enterprise_id','=',$EseId)->where('principal','=',1)->get()[0];
    }

    public function defaultdeposit($EseId){
        return DepositController::where('enterprise_id','=',$EseId)->first();
    }

    public function accountmembersold($account,$member){
        return wekamemberaccounts::where('id','=',$account)->where('user_id',$member)->first();
    }

    public function sendSms($phone, $message)
    {
        try {
            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));

            $twilio->messages->create(
                $phone,
                [
                    "from" => env("TWILIO_FROM"),
                    "body" => $message
                ]
            );

        } catch (\Exception $e) {
            Log::error("SMS Error: " . $e->getMessage());
        }
    }

    public function sendTransactionEmail(
        $user, 
        $title, 
        $subtitle, 
        $transaction, 
        $fees, 
        $before, 
        $after,
        $sourceAccount,
        $beneficiaryAccount)
    {
        try {
            Mail::send('emails.transaction', [
                "title" => $title,
                "subtitle" => $subtitle,
                "amount" => $transaction->amount,
                "fees" => $transaction->fees,
                "before" => $transaction->sold_before,
                "after" => $transaction->sold_after,
                "motif" => $transaction->motif,
                "currency" =>wekamemberaccounts::getMoneyAbreviationByAccountNumber($sourceAccount),
                "uuid" => $transaction->uuid,
                "source_account"=>$sourceAccount,
                "beneficiary_account"=>$beneficiaryAccount
            ], function ($msg) use ($user, $title) {
                $msg->to($user->email)
                    ->subject($title);
            });
        } catch (\Exception $e) {
            Log::error("EMAIL Error: " . $e->getMessage());
        }
    }


    public function createTransaction(
        $amount,
        $soldBefore,
        $soldAfter,
        $type,
        $motif,
        $userId,
        $memberAccountId,
        $memberId,
        $accountId,
        $operationDoneBy,
        $fees,
        $phone,
        $adresse,
        $status = "validated",
        $from_to_id = null,
        $sent_to_id = null
    ) {
        return wekaAccountsTransactions::create([
            'amount'             => $amount,
            'sold_before'        => $soldBefore,
            'sold_after'         => $soldAfter,
            'type'               => $type,
            'motif'              => $motif,
            'user_id'            => $userId,
            'member_account_id'  => $memberAccountId,
            'member_id'          => $memberId,
            'enterprise_id'      => $this->getEse($userId)['id'],
            'done_at'            => date('Y-m-d'),
            'account_id'         => $accountId,
            'operation_done_by'  => $operationDoneBy,
            'uuid'               => $this->getUuId('WT', 'WK'),
            'fees'               => $fees,
            'transaction_status' => $status,
            'phone'              => $phone,
            'adresse'            => $adresse,
            'from_to_id'     => $from_to_id,   
            'sent_to_id'    => $sent_to_id
        ]);
    }

    public function createLocalRequestHistory($userId,$fundId,$amount,$motif,$type,$requestId,$fenceId,$invoiceId,$sold,$accountId,$beneficiary,$provenance,$fendReceiverId,$expenditureId,$memberAccountId,$nature){
        return  requestHistory::create([
            'user_id'=>$userId,
            'fund_id'=>$fundId,
            'amount'=>$amount,
            'motif'=>$motif,
            'type'=>$type,
            'request_id'=>$requestId,
            'fence_id'=>$fenceId,
            'invoice_id'=>$invoiceId,
            'enterprise_id'=>$this->getEse($userId)['id'],
            'sold'=>$sold,
            'done_at'=>date('Y-m-d'),
            'account_id'=>$accountId,
            'status'=>'validated',
            'beneficiary'=>$beneficiary,
            'provenance'=>$provenance,
            'uuid'=>$this->getUuId('RH','FH'),
            'fund_receiver_id'=>$fendReceiverId,
            'expenditure_id'=>$expenditureId,
            'member_account_id'=>$memberAccountId,
            'nature'=>$nature,
        ]);
    }

    public function detectDevice($userAgent)
    {
        if (preg_match('/mobile/i', $userAgent)) return 'Mobile';
        if (preg_match('/tablet/i', $userAgent)) return 'Tablet';
        return 'Desktop';
    }

    public function detectOS($userAgent)
    {
        $oses = [
            'Windows' => '/windows nt/i',
            'MacOS'   => '/macintosh|mac os x/i',
            'Linux'   => '/linux/i',
            'Android' => '/android/i',
            'iOS'     => '/iphone|ipad/i',
        ];

        foreach ($oses as $os => $regex) {
            if (preg_match($regex, $userAgent)) {
                return $os;
            }
        }

        return 'Unknown OS';
    }

    public function detectBrowser($userAgent)
    {
        $browsers = [
            'Edge'    => '/edge/i',
            'Chrome'  => '/chrome/i',
            'Safari'  => '/safari/i',
            'Firefox' => '/firefox/i',
            'Opera'   => '/opera|opr/i',
        ];

        foreach ($browsers as $browser => $regex) {
            if (preg_match($regex, $userAgent)) {
                return $browser;
            }
        }

        return 'Unknown Browser';
    }

}
