<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerController;
use App\Http\Requests\StoreCustomerControllerRequest;
use App\Http\Requests\UpdateCustomerControllerRequest;
use Illuminate\Support\Facades\DB;
use stdClass;

class CustomerControllerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($enterpriseid)
    {
        $list=collect(CustomerController::where('enterprise_id','=',$enterpriseid)->get());
        $listdata=$list->map(function ($item,$key){
            return $this->show($item);
        });
        return $listdata;
    }

    public function getcustomerbyId($customerId){
        return $this->show(CustomerController::find($customerId));
    }

     /**
     * searching stock histories by done paginated
     */
    public function searchingcustomersbypagination(Request $request){
        $searchTerm = $request->query('keyword', '');
        $enterpriseId = $request->query('enterprise_id', 0);  
        $actualuser=$this->getinfosuser($request->query('user_id'));
        if ($actualuser) {
                $list =CustomerController::where('enterprise_id', '=', $enterpriseId)
                ->where(function($query) use ($searchTerm) {
                    $query->where('customerName', 'LIKE', "%$searchTerm%")
                    ->orWhere('adress', 'LIKE', "%$searchTerm%")
                    ->orWhere('phone', 'LIKE', "%$searchTerm%")
                    ->orWhere('mail', 'LIKE', "%$searchTerm%")
                    ->orWhere('type', 'LIKE', "%$searchTerm%")
                    ->orWhere('uuid', 'LIKE', "%$searchTerm%");
                })
                ->select('customer_controllers.*')
                ->paginate(10)
                ->appends($request->query());

            $list->getCollection()->transform(function ($item){
                return $this->show($item);
            });
            return $list;
        }else{
            return response()->json([
                "status"=>400,
                "data"=>null,
                "message"=>"incorrect data"
            ],400);
        }
    }

    public function anonymous($enterpriseid){
        
        $customer=CustomerController::where('customerName','LIKE',"%anonyme%")->where('enterprise_id','=',$enterpriseid)->get()->first();
        if($customer){
            return $customer;
        }else{
            return CustomerController::where('enterprise_id','=',$enterpriseid)->get()->first();
        }
        
    }
    
    /**
     * search
     */
    
     public function search($enterpriseid){
    
        $list=CustomerController::where('enterprise_id','=',$enterpriseid)->paginate(40);
        $list->getCollection()->transform(function ($item){
            return $this->show($item);
        });
        return $list;
     }  
     
     public function searchwithstats($enterpriseid){
    
        // Clients
        $list = CustomerController::where('enterprise_id', '=', $enterpriseid)->paginate(40);
        $list->getCollection()->transform(function ($item) {
            return $this->show($item);
        });

        // ==========================
        //   FACTURES (Invoices)
        // ==========================
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $startOfWeek = now()->startOfWeek();
        $startOfLastWeek = now()->subWeek()->startOfWeek();
        $startOfMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonth()->startOfMonth();

        // Helper closure pour récupérer le montant correct (netToPay sinon total)
        $getAmount = function($q) {
            return $q->sum(DB::raw("CASE WHEN netToPay IS NOT NULL AND netToPay > 0 THEN netToPay ELSE total END"));
        };

        // ---- Today & Yesterday
        $invoicesTodayCash   = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'cash')->whereDate('date_operation', $today));
        $invoicesYesterdayCash = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'cash')->whereDate('date_operation', $yesterday));

        $invoicesTodayCredit = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'credit')->whereDate('date_operation', $today));
        $invoicesYesterdayCredit = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'credit')->whereDate('date_operation', $yesterday));

        // ---- Week & Last Week
        $invoicesWeekCash = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'cash')->whereBetween('date_operation', [$startOfWeek, now()]));
        $invoicesLastWeekCash = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'cash')->whereBetween('date_operation', [$startOfLastWeek, $startOfWeek]));

        $invoicesWeekCredit = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'credit')->whereBetween('date_operation', [$startOfWeek, now()]));
        $invoicesLastWeekCredit = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'credit')->whereBetween('date_operation', [$startOfLastWeek, $startOfWeek]));

        // ---- Month & Last Month
        $invoicesMonthCash = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'cash')->whereBetween('date_operation', [$startOfMonth, now()]));
        $invoicesLastMonthCash = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'cash')->whereBetween('date_operation', [$startOfLastMonth, $startOfMonth]));

        $invoicesMonthCredit = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'credit')->whereBetween('date_operation', [$startOfMonth, now()]));
        $invoicesLastMonthCredit = $getAmount(\App\Models\Invoices::where('enterprise_id', $enterpriseid)->where('type_facture', 'credit')->whereBetween('date_operation', [$startOfLastMonth, $startOfMonth]));

        // ==========================
        //   PAYMENTS (DebtPayments)
        // ==========================
        $paymentsToday     = $this->sumPayments($enterpriseid, ['date' => $today]);
        $paymentsYesterday = $this->sumPayments($enterpriseid, ['date' => $yesterday]);

        $paymentsWeek      = $this->sumPayments($enterpriseid, ['between' => [$startOfWeek, now()]]);
        $paymentsLastWeek  = $this->sumPayments($enterpriseid, ['between' => [$startOfLastWeek, $startOfWeek]]);

        $paymentsMonth     = $this->sumPayments($enterpriseid, ['between' => [$startOfMonth, now()]]);
        $paymentsLastMonth = $this->sumPayments($enterpriseid, ['between' => [$startOfLastMonth, $startOfMonth]]);


        // ==========================
        //   CALCUL DES ÉCARTS (%)
        // ==========================
        $percentChange = function($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 2);
        };

        $stats = [
            'invoices' => [
                'today' => [
                    'cash'   => $invoicesTodayCash,
                    'cash_change' => $percentChange($invoicesTodayCash, $invoicesYesterdayCash),
                    'credit' => $invoicesTodayCredit,
                    'credit_change' => $percentChange($invoicesTodayCredit, $invoicesYesterdayCredit),
                ],
                'week' => [
                    'cash'   => $invoicesWeekCash,
                    'cash_change' => $percentChange($invoicesWeekCash, $invoicesLastWeekCash),
                    'credit' => $invoicesWeekCredit,
                    'credit_change' => $percentChange($invoicesWeekCredit, $invoicesLastWeekCredit),
                ],
                'month' => [
                    'cash'   => $invoicesMonthCash,
                    'cash_change' => $percentChange($invoicesMonthCash, $invoicesLastMonthCash),
                    'credit' => $invoicesMonthCredit,
                    'credit_change' => $percentChange($invoicesMonthCredit, $invoicesLastMonthCredit),
                ],
            ],
            'payments' => [
                'today' => [
                    'total' => $paymentsToday,
                    'change' => $percentChange($paymentsToday, $paymentsYesterday),
                ],
                'week' => [
                    'total' => $paymentsWeek,
                    'change' => $percentChange($paymentsWeek, $paymentsLastWeek),
                ],
                'month' => [
                    'total' => $paymentsMonth,
                    'change' => $percentChange($paymentsMonth, $paymentsLastMonth),
                ],
            ]
        ];
        $pagination=$list->toArray();
        $pagination['stats']=$stats;
        // Retourne pagination + stats
        return response()->json($pagination);
     }

        private function sumPayments($enterpriseid, $dateRange)
        {
            $query = \App\Models\DebtPayments::join('debts', 'debts.id', '=', 'debt_payments.debt_id')
                ->join('customer_controllers', 'customer_controllers.id', '=', 'debts.customer_id')
                ->where('customer_controllers.enterprise_id', $enterpriseid);

            if (isset($dateRange['date'])) {
                $query->whereDate('debt_payments.done_at', $dateRange['date']);
            } elseif (isset($dateRange['between'])) {
                $query->whereBetween('debt_payments.done_at', $dateRange['between']);
            }

            return $query->sum('debt_payments.amount_payed');
}
     /**
      * Search by words
      */
      public function searchbywords(Request $request){
    
        $list=CustomerController::where('enterprise_id','=',$request['enterpriseid'])->where('customerName','LIKE',"%$request->word%")->orWhere('id','=',"$request->word")->orWhere('uuid','=',"$request->word")->limit(10)->get();

        return $list;
     }
    /**
     *Getting providers 
     */
    public function providers(){
        $list=collect(CustomerController::where('type','=','provider')->get());
        $listdata=$list->map(function ($item,$key){
            return $this->show($item);
        });
        return $listdata;
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
     * @param  \App\Http\Requests\StoreCustomerControllerRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCustomerControllerRequest $request)
    {
        $newcustomer= new stdClass;
        $ese=$this->getEse($request['created_by_id']);
        if (!$request['uuid']) {
            $request['uuid']=$this->getUuId('C','C');
        }

        if(!$request['type']){
            $request['type']="physique";
        }

        $request['sync_status']=true;
        $request['enterprise_id']=$ese->id;
        //if exists actual customer
        $ifexists=CustomerController::where('customerName',$request['customerName'])
                                    ->where('enterprise_id',$ese->id)->first();
        if ($ifexists) {
           return response()->json([
            "message"=>"duplicated",
            "data"=>null,
            "status"=>200
           ]);
        }

        $newcustomer=$this->show(CustomerController::create($request->all()));
        return $newcustomer;
    }

    /**
     * importing data or multiple insert
     */
    public function importation(Request $request){
        $data=[];
        if(count($request->data)>0){
            foreach ($request->data as $customer) {
                if ( $newCustomer=$this->store(new StoreCustomerControllerRequest($customer))) {
                    array_push($data,$newCustomer);
                }
            }
        }

        return $data;
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CustomerController  $customerController
     * @return \Illuminate\Http\Response
     */
   public function show(CustomerController $customerController)
    {
      return DB::table('customer_controllers')
        ->leftJoin('categories_customer_controllers as C', 'customer_controllers.category_id','=','C.id')
        ->leftJoin('point_of_sales as P', 'customer_controllers.pos_id','=','P.id')
        ->leftJoin('customer_controllers as C1', 'customer_controllers.employer','=','C1.id')
        ->where('customer_controllers.id', '=', $customerController->id)
        ->select([
            'customer_controllers.*',
            'C1.customerName as employer_name',
            'P.name as pos_name',
            'C.name as category_name',
            DB::raw("(
                SELECT SUM(
                    CASE 
                        WHEN invoices.netToPay IS NOT NULL AND invoices.netToPay > 0 
                        THEN invoices.netToPay 
                        ELSE invoices.total 
                    END
                )
                FROM invoices 
                WHERE invoices.customer_id = customer_controllers.id
            ) as turnover"),
            DB::raw("(
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM debts 
                        WHERE debts.customer_id = customer_controllers.id 
                          AND debts.sold > 0
                    ) 
                    THEN 1 ELSE 0 
                END
            ) as in_debt"),
            DB::raw("(
                SELECT COALESCE(SUM(debts.sold), 0)
                FROM debts 
                WHERE debts.customer_id = customer_controllers.id
            ) as total_debt")
        ])
        ->first();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CustomerController  $customerController
     * @return \Illuminate\Http\Response
     */
    public function edit(CustomerController $customerController)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCustomerControllerRequest  $request
     * @param  \App\Models\CustomerController  $customerController
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCustomerControllerRequest $request, CustomerController $customerController)
    {
       return $this->show(customerController::find($customerController->update($request->all())));
    }

    public function update2(Request $request,$id)
    {
        $customer=CustomerController::find($id);
        $customer->update($request->all());
        return $this->show($customer);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CustomerController  $customerController
     * @return \Illuminate\Http\Response
     */
    public function destroy(CustomerController $customerController)
    {
        return CustomerController::destroy($customerController);
    }
    
    public function delete($customer){
      
        $message="failed";
        $get=CustomerController::find($customer);
        if ($get->delete()) {
            $message="deleted";
        }

        return ['message'=>$message];
    }

    public function getbyuuid(Request $request){
        return CustomerController::where('uuid','=',$request['uuid'])->get()->first();
    }
   
}
