<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DepositsUsers;
use App\Models\DepositServices;
use App\Models\PricesCategories;
use App\Models\DepositController;
use App\Models\ServicesController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StockHistoryController;
use App\Http\Requests\UpdateServicesControllerRequest;
use App\Models\CategoriesServicesController;
use App\Models\InvoiceDetails;
use App\Models\stockhistorypayments;
use App\Models\UnitOfMeasureController;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use stdClass;

class ServicesControllerController extends Controller
{
    public function index($enterprise_id)
    {
        $list=collect(ServicesController::where('enterprise_id','=',$enterprise_id)->orderby('name','asc')->get());
        $listdata=$list->map(function ($item,$key){
            return $this->show($item);
        });
        return $listdata;
    }
    
    /**
     * Group services by types and count them
     */
    public function servicesgroupedbytypes(Request $request){
        try {
            $servicesGroupedByType =collect(ServicesController::select('type',DB::raw('count(*) as count'))
            ->where('enterprise_id','=',$request['enterprise_id'])
            ->groupBy('type')
            ->get());
            $servicesGroupedByType->transform(function ($type){
                switch ($type['type']) {
                    case '1':
                        $type['name']="Articles";
                        break; 
                    case '2':
                        $type['name']="Services";
                        break;
                    case '3':
                        $type['name']="Accompagnements";
                        break;
                    
                    default:
                        # code...
                        break;
                }
                return $type;
            });

            return response()->json([
                'message'=>'success',
                'status'=>200,
                'error'=>null,
                'data'=>$servicesGroupedByType 
            ]); 
        } catch (Exception $th) {
            return response()->json([
                'message'=>'error',
                'status'=>500,
                'error'=>$th->getMessage(),
                'data'=>null
            ]);
        }
       
    }
     /**
     * services by types
     */
    public function servicesbytypes(Request $request){
        try {

            // return $request;
            $typesent=$request->query('typesent');
            $enterprise_id=$request->query('enterprise_id');
    
            $list=ServicesController::where('type',$typesent)
            ->where('enterprise_id',$enterprise_id)
            ->orderby('name')
            ->paginate(20);
            $list->appends($request->all());
            $list->getCollection()->transform(function ($service){
                return $this->show($service);
            });

            return $list; 
        } catch (Exception $th) {
            return response()->json([
                'message'=>'error',
                'status'=>500,
                'error'=>$th->getMessage(),
                'data'=>null
            ]);
        }
    }
    
    /**
     * financial summary by service
     */
    public function financialsummarybyservice(Request $request){
        if ($request['service_id']) {
            $service=ServicesController::find($request['service_id']);
            if ($service) {
                try {
                    
                    $stocktotal=StockHistoryController::select(DB::raw('count(id) as total_stock'),DB::raw('sum(quantity*price) as total_ca'))
                    ->where('service_id','=',$service['id'])
                    ->get()->first();
                    
                    $cash=StockHistoryController::select(DB::raw('sum(quantity*price) as total_cash'))
                    ->where('type','=','withdraw')
                    ->where('service_id','=',$service['id'])
                    ->where('type_approvement','=','cash')
                    ->get()->first();

                    $debts=StockHistoryController::select(DB::raw('sum(quantity*price) as total_debts'))
                            ->where('type','=','withdraw')
                            ->where('service_id','=',$service['id'])
                            ->where('type_approvement','=','credit')
                            ->get()->first();

                    $advances=stockhistorypayments::select(DB::raw('sum(amount) as total_advances'))
                    ->where('service_id','=',$service['id'])
                    ->get()->first();

                    $service['cash']=$cash['total_cash']?$cash['total_cash']:0;   
                    $service['debts']=$debts['total_debts']?$debts['total_debts']:0;   
                    $service['advances']=$advances['total_advances']?$advances['total_advances']:0;   
                    $service['sold']=$service['debts']-$service['advances'];   
                    $service['totalstockprovided']=$stocktotal['total_stock']?$stocktotal['total_stock']:0;   
                    $service['totalca']=$stocktotal['total_ca']?$stocktotal['total_ca']:0;

                    return response()->json([
                        'message'=>'success',
                        'status'=>200,
                        'error'=>null,
                        'data'=>$service
                    ]);  
                } catch (Exception $th) {
                    return response()->json([
                        'message'=>'error',
                        'status'=>500,
                        'error'=>$th->getMessage(),
                        'data'=>null
                    ]);
                }
            }else{
                return response()->json([
                    'message'=>'error',
                    'status'=>401,
                    'error'=>'not find',
                    'data'=>null
                ]);   
            }
        }else{
            return response()->json([
                'message'=>'error',
                'status'=>401,
                'error'=>'not sent',
                'data'=>null
            ]); 
        }
    }

    /**
     * Gettings periodic stock history by service
     */
    public function periodicstockhistory(Request $request){
        if (isset($request['criteria']) && !empty($request['criteria'])) {
            if (isset($request['service_id']) && !empty($request['service_id'])){
                try {
                    $service=ServicesController::find($request['service_id']);
                    if ($service) {
                        switch ($request['criteria']) {
                            case 'monthly':
                                Carbon::setLocale('fr');
                                $period=Carbon::now()->translatedFormat('F Y');
                                $startOfMonth = Carbon::now()->startOfMonth(); // Début du mois
                                $endOfMonth = Carbon::now()->endOfMonth();
                                break;
                            
                            default:
                                # code...
                                break;
                        }
    
                        $stockhistoryctrl= new StockHistoryControllerController();
                        $list=collect(StockHistoryController::where('service_id','=',$request['service_id'])
                        ->whereBetween('done_at',[$startOfMonth.' 00:00:00', $endOfMonth.' 23:59:59'])
                        ->get());
                        $list=$list->map(function($history) use($stockhistoryctrl){
                            return $stockhistoryctrl->show($history);
                        });

                        return response()->json([
                            'message'=>'success',
                            'status'=>200,
                            'error'=>null,
                            'data'=>$list,
                            'period'=>$period
                        ]);

                    }else{
                        return response()->json([
                            'message'=>'error',
                            'status'=>400,
                            'error'=>'service not fund',
                            'data'=>null
                        ]);
                    }
                } catch (Exception $th) {
                    return response()->json([
                        'message'=>'error',
                        'status'=>500,
                        'error'=>$th->getMessage(),
                        'data'=>null
                    ]); 
                }
               
            }else{
                return response()->json([
                    'message'=>'error',
                    'status'=>400,
                    'error'=>'no service sent',
                    'data'=>null
                ]); 
            }
           
        }else{
            return response()->json([
                'message'=>'error',
                'status'=>400,
                'error'=>'no criteria sent',
                'data'=>null
            ]); 
        }
    }
    
    /**
     * Gettings periodic stock history by service
     */
    public function periodicsell(Request $request){
        if (isset($request['criteria']) && !empty($request['criteria'])) {
            if (isset($request['service_id']) && !empty($request['service_id'])){
                try {
                    $service=ServicesController::find($request['service_id']);
                    if ($service) {
                        switch ($request['criteria']) {
                            case 'monthly':
                                Carbon::setLocale('fr');
                                $period=Carbon::now()->translatedFormat('F Y');
                                $startOfMonth = Carbon::now()->startOfMonth(); // Début du mois
                                $endOfMonth = Carbon::now()->endOfMonth();
                                break;
                            
                            default:
                                # code...
                                break;
                        }
    
                        $mouvements=InvoiceDetails::join('invoices as I','invoice_details.invoice_id','=','I.id')
                        ->whereBetween('I.date_operation',[$startOfMonth.' 00:00:00',$endOfMonth.' 23:59:59'])
                        ->where('invoice_details.service_id','=',$service['id'])
                        ->where('I.type_facture','<>','proforma')
                        ->get(['invoice_details.invoice_id','invoice_details.quantity','invoice_details.price','invoice_details.total','invoice_details.service_id','I.type_facture','I.date_operation','I.uuid']);
                      
                        return response()->json([
                            'message'=>'success',
                            'status'=>200,
                            'error'=>null,
                            'data'=>$mouvements,
                            'period'=>$period
                        ]);

                    }else{
                        return response()->json([
                            'message'=>'error',
                            'status'=>400,
                            'error'=>'service not fund',
                            'data'=>null
                        ]);
                    }
                } catch (Exception $th) {
                    return response()->json([
                        'message'=>'error',
                        'status'=>500,
                        'error'=>$th->getMessage(),
                        'data'=>null
                    ]); 
                }
               
            }else{
                return response()->json([
                    'message'=>'error',
                    'status'=>400,
                    'error'=>'no service sent',
                    'data'=>null
                ]); 
            }
           
        }else{
            return response()->json([
                'message'=>'error',
                'status'=>400,
                'error'=>'no criteria sent',
                'data'=>null
            ]); 
        }
    }

    public function subserviceslist($enterprise_id)
    {
        $list=collect(ServicesController::where('enterprise_id','=',$enterprise_id)->where('type','=',3)->orderby('name','asc')->get());
        $listdata=$list->map(function ($item,$key){
            return $this->show($item);
        });
        return $listdata;
    }

    /**
     * searching by name
     */
    public function search($enterprise_id){
         
        $list=ServicesController::where('enterprise_id', '=', $enterprise_id)->orderby('name','asc')->paginate(50);
        $list->getCollection()->transform(function ($item){
            return $this->show($item);
        });
       
        return $list;
    }
    
    /**
     * searching by categorie and deposit
     */
    public function searchbycategorieandeposit(Request $request){
    
        try {
            $list=collect(DepositServices::join('services_controllers as S','deposit_services.service_id','=','S.id')
            ->join('categories_services_controllers as C', 'S.category_id','=','C.id')
            ->where('S.category_id', '=', $request['category_id'])
            ->where('deposit_services.deposit_id', '=', $request['deposit_id'])
            ->limit(20)
            ->get('deposit_services.*'));
            $list->transform(function ($item){
                return $this->servicedetail($item);
            });
            return response()->json([
                "message"=>"success",
                "status"=>200,
                "error"=>null,
                "data"=>$list
            ]);
        } catch (Exception $th) {
             return response()->json([
                    "message"=>"error",
                    "status"=>500,
                    "error"=>$th->getMessage(),
                    "data"=>null
                ]);
        }

       
    }

    /**
     * turning back articles for users
     */
    public function services_list(Request $request){ 
        $listdata=[];
        if($request['user_id']){
            $user=$this->getinfosuser($request['user_id']);
            $Ese=$this->getEse($request['user_id']);
             //if super_admin return all
            if($user['user_type']=='super_admin'){
                $deposits=DepositController::where('enterprise_id','=',$Ese['id'])->get();//deposits list
                foreach ($deposits as $key => $deposit) {
                    $servicesgotten=[];
                    # services
                    $services=DepositServices::where('deposit_id','=',$deposit['id'])->get();
                    foreach ($services as $key => $service) {
                        # details services
                        $funded=$this->servicedetail($service);
                        array_push($servicesgotten,$funded);
                    }
                    $depositdata=['deposit'=>$deposit,'services'=>$servicesgotten];
                    array_push($listdata,$depositdata);
                }

            }else{

                $deposits=DepositsUsers::join('deposit_controllers as D','deposits_users.deposit_id','=','D.id')->where('D.enterprise_id','=',$Ese['id'])->where('deposits_users.user_id','=',$user['id'])->get();//deposits list
                foreach ($deposits as $key => $deposit) {
                    $servicesgotten=[];
                    # services
                    $services=DepositServices::where('deposit_id','=',$deposit['id'])->get();
                    foreach ($services as $key => $service) {
                        # details services
                        $funded=$this->servicedetail($service);
                        array_push($servicesgotten,$funded);
                    }
                    $depositdata=['deposit'=>$deposit,'services'=>$servicesgotten];
                    array_push($listdata,$depositdata);
                }
            }
        }
        return $listdata;
      
    }    
    
    /**
     * turning back articles for users
     */
    public function services_list_paginated($userid){ 
        $listdata=[];
        if($userid){
            $user=$this->getinfosuser($userid);
            $Ese=$this->getEse($userid);
             //if super_admin return all
            if($user['user_type']=='super_admin'){
                $deposits=DepositController::where('enterprise_id','=',$Ese['id'])->get();//deposits list
                foreach ($deposits as $key => $deposit) {
                    $servicesgotten=[];
                    # services
                    $services=DepositServices::where('deposit_id','=',$deposit['id'])->paginate(50);
                    $services->getCollection()->transform(function ($service){
                        return $service=$this->servicedetail($service);
                    });
                    $depositdata=['deposit'=>$deposit,'data_services'=>$services];
                    array_push($listdata,$depositdata);
                }

            }else{

                $deposits=DepositsUsers::join('deposit_controllers as D','deposits_users.deposit_id','=','D.id')->where('D.enterprise_id','=',$Ese['id'])->where('deposits_users.user_id','=',$user['id'])->get();//deposits list
                foreach ($deposits as $key => $deposit) {
                    $servicesgotten=[];
                    # services
                    $services=DepositServices::where('deposit_id','=',$deposit['id'])->get()->paginate(50);
                    foreach ($services as $key => $service) {
                        # details services
                        $funded=$this->servicedetail($service);
                        array_push($servicesgotten,$funded);
                    }
                    $depositdata=['deposit'=>$deposit,'services'=>$servicesgotten];
                    array_push($listdata,$depositdata);
                }
            }
        }
        return $listdata;
      
    }

    public function articlesdeposit($deposit){
        $services=[];
        //getting services for each deposit
        $data=DepositServices::where('deposit_id','=',$deposit->id)->get();
        
            foreach ($data as $service) {
                $funded=$this->servicedetail($service);
                array_push($services,$funded); 
            }
                
        return $data=['deposit'=>$deposit,'services'=>$services] ;
    }   
    
    /**
     * paginated articles for a deposit
     */
    public function articlesdepositpaginated($deposit_id){
        $services=[];
        $deposit= new stdClass;
        if (isset($deposit_id) && !empty($deposit_id)) {
            $deposit=DepositController::find($deposit_id);
            if ($deposit) {
                //getting services for deposit
                $services=DepositServices::where('deposit_id','=',$deposit->id)->paginate(40);
                $services->getCollection()->transform(function ($item){
                    return $item=$this->servicedetail($item);
                });
            }
        }
                  
        return $services ;
    }
    
    
    /**
     * paginated articles for a deposit
     */
    public function depositsandarticlespaginated($userid){
        $deposits=[];
        $services=[];
        $user=$this->getinfosuser($userid);
        $enterprise=$this->getEse($user['id']);
        if ($user['user_type']=='super_admin') {
            $deposits=DepositController::where('enterprise_id','=',$enterprise['id'])->get("deposit_controllers.id");
        } else {
            $deposits=DepositsUsers::join('deposit_controllers as D','deposits_users.deposit_id','=','D.id')->where('deposits_users.user_id','=',$userid)->get('D.id');
        }
        
        $deposits=$deposits->pluck('id')->toArray();
    
        if (count($deposits)>0) {
            //getting services for all deposits
            $services=DepositServices::whereIn('deposit_id',$deposits)->paginate(50);
            $services->getCollection()->transform(function ($item){
                return $item=$this->servicedetail($item);
            });
        }
                  
        return $services ;
    } 


    
    /**
     * searching data by word for a specific deposit
     */
    public function searchinarticlesdeposit(Request $request){
        if($request->word && !empty($request->word)){
            //getting services for the deposit
            if($request['type']=="stock"){
                $data=collect(
                    DepositServices::join('services_controllers as S', 'deposit_services.service_id','=','S.id')
                    ->where('deposit_id','=',$request['deposit_id'])
                    ->where('S.type','=','1')
                    ->where('S.name','LIKE',"%$request->word%")
                    ->limit(10)
                    ->get('deposit_services.*'));
            }else{
                $data=collect(
                    DepositServices::join('services_controllers as S', 'deposit_services.service_id','=','S.id')
                    ->where('deposit_id','=',$request['deposit_id'])
                    ->where('S.name','LIKE',"%$request->word%")
                    ->limit(10)
                    ->get('deposit_services.*'));
            }
       
          
            $data=$data->map(function ($item){
                return $this->servicedetail($item);
            });
        
            return $data;
        }else{
            return [];
        }
        
    }
    
    /**
     * searching data by word for a specific Ese
     */
    public function searchinarticlesbyname(Request $request){
        
        if($request->word && !empty($request->word)){
            if($request['type']=="stock"){
                $data=collect(
                    ServicesController::where('enterprise_id','=',$request['enterprise_id'])
                    ->where('name','LIKE',"%$request->word%")
                    ->where('type','=',"1")
                    ->limit(10)
                    ->get());
            }else{
                $data=collect(
                    ServicesController::where('enterprise_id','=',$request['enterprise_id'])
                    ->where('name','LIKE',"%$request->word%")
                    ->limit(10)
                    ->get());
            }
                
            $data=$data->map(function ($item){
                return $this->show($item);
            });
        
            return $data;
        }else{
            return [];
        }
        
    }    
    
    /**
     * searching data by word for a specific deposit
     */
    public function searchbycodebar(Request $request){
        
        if($request->word && !empty($request->word)){
            //getting services for the deposit
            if($request['type']=="stock"){
                $data=collect(
                    ServicesController::where('enterprise_id','=',$request['enterprise_id'])
                    ->where('codebar','=',$request->word)
                    ->where('type','=',"1")
                    ->limit(10)
                    ->get());
            }else{
                $data=collect(
                    ServicesController::where('enterprise_id','=',$request['enterprise_id'])
                    ->where('codebar','=',$request->word)
                    ->limit(10)
                    ->get());
            }
       
            $data=$data->map(function ($item){
                return $this->show($item);
            });
        
            return $data;
        }else{
            return [];
        }
        
    }
    
    /**
     * searching data by word for a specific deposit
     */
    public function searchinarticlesbybarcode(Request $request){
        $data= new stdClass;
        if($request->word && !empty($request->word)){
            //getting services for the deposit
            if($request['type']=="stock"){
                $data=DepositServices::join('services_controllers as S', 'deposit_services.service_id','=','S.id')
                ->where('deposit_id','=',$request['deposit_id'])
                ->where('S.codebar','=',"$request->word")
                ->where('S.type','=',"1")
                ->get('deposit_services.*')->first();
                if ($data) {
                    $data=$this->servicedetail($data);
                }
            }else{
                $data=DepositServices::join('services_controllers as S', 'deposit_services.service_id','=','S.id')
                ->where('deposit_id','=',$request['deposit_id'])
                ->where('S.codebar','=',"$request->word")
                ->get('deposit_services.*')->first();
                if ($data) {
                    $data=$this->servicedetail($data);
                }
            }
        }
        return $data;
    }

    /**
     * reset all services
     */
    public function resetallservices(Request $request){
        $counter=0;
        $message="";
        $services=ServicesController::where('enterprise_id','=',$request['enterprise_id'])->get();
        if(($services->count())>0){
            foreach ($services as $value) {
                //delete prices
                PricesCategories::where('service_id','=',$value['id'])->delete();
                DepositServices::where('service_id','=',$value['id'])->delete();
                StockHistoryController::where('service_id','=',$value['id'])->delete();
                InvoiceDetails::where('service_id','=',$value['id'])->delete();
                if ($value->delete()) {
                    $counter ++;
                    $message="deleted all";
                }else{
                    $message="few deleted";
                }
            }
        }else{
            $message="empty";
        }
        

        return response()->json([
            'deleted_counter' =>$counter,
            'all'=>$services->count(),
            'message'=>$message
        ]);
    }

    /**
     * availables and unavailables services list
     */
    public function availablesunavailablesservices(Request $request){
        $unavailables=[];
        $availables=[];

        $availables=collect(ServicesController::where('status','=','available')->where('service_usage','=','location')->where('type','=',2)->where('enterprise_id','=',$request['enterprise_id'])->get());
        $availables= $availables->map(function ($service){
            return $this->show($service);
        });

        $unavailables=collect(ServicesController::where('status','=','unavailable')->where('service_usage','=','location')->where('enterprise_id','=',$request['enterprise_id'])->get());
        $unavailables=$unavailables->map(function ($service){
            return $this->show($service);
        });

        return response()->json([
            "message"=>"success",
            "status"=>200,
            "availables"=>$availables,
            "unavailables"=>$unavailables
        ]);
    }

    /**
     * getting detail for a service in deposit
     */
    public function servicedetail(DepositServices $servicesController)
    {
        $prices=PricesCategories::leftjoin('moneys as M','prices_categories.money_id','=','M.id')
        ->where('prices_categories.service_id','=',$servicesController['service_id'])
        ->get(['M.money_name','M.abreviation','prices_categories.*']);

        $service=ServicesController::leftjoin('categories_services_controllers as C', 'services_controllers.category_id','=','C.id')
        ->leftjoin('unit_of_measure_controllers as U','services_controllers.uom_id','=','U.id')
        ->leftjoin('deposit_services','services_controllers.id','=','deposit_services.service_id')
        ->where('deposit_services.service_id', '=', $servicesController['service_id'])
        ->where('deposit_services.deposit_id','=',$servicesController['deposit_id'])
        ->get(['deposit_services.available_qte','deposit_services.deposit_id','C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','services_controllers.*'])[0];
        
        return ['deposit_id'=>$servicesController['deposit_id'],'quantity'=>$service['available_qte'],'service'=>$service,'prices'=>$prices];
    }
    
    public function depositarticles($deposit_id){
        $services=[];
        $defaultmoney = $this->defaultmoney(DepositController::where('id','=',$deposit_id)->first()->enterprise_id);
        $services = [];
        //getting services for each deposit
        $data = DepositServices::where('deposit_id', '=', $deposit_id)->get();
        foreach ($data as $service) {
            $funded = $this->serviceDeposit(new Request(['deposit_id' => $deposit_id, 'service_id' => $service['service_id']]));
            $prices = PricesCategories::leftjoin('moneys as M', 'prices_categories.money_id', '=', 'M.id')
                ->where('prices_categories.service_id', $service['service_id'])
                ->get(['M.money_name', 'M.abreviation', 'prices_categories.*']);

            $funded['prices'] = $prices;
            if ($prices->count()) {
                foreach ($prices as $price) {
                    if ($price['principal'] == 1 && $defaultmoney->id == $price['money_id']) {
                        $funded['total'] = $price['price'] * $funded['available_qte'];
                    }
                }
            }


            array_push($services, $funded);
        }
                
        return $services;
    }

    public function serviceDeposit(Request $request)
    {
        return ServicesController::leftjoin('categories_services_controllers as C', 'services_controllers.category_id', '=', 'C.id')
            ->leftjoin('unit_of_measure_controllers as U', 'services_controllers.uom_id', '=', 'U.id')
            ->leftjoin('deposit_services', 'services_controllers.id', '=', 'deposit_services.service_id')
            ->where('deposit_services.service_id', '=', $request['service_id'])
            ->where('deposit_services.deposit_id', '=', $request['deposit_id'])
            ->get(['deposit_services.available_qte', 'C.name as category_name', 'U.name as uom_name', 'U.symbol as uom_symbol', 'services_controllers.*'])[0];
    }
    /**
     * Services to give to seller 
     */
    public function give_to_seller($user_id){
        $services=[];
        //check if the seller is affected to a deposit
        $result=DepositsUsers::where('user_id','=',$user_id)->get();
        foreach ($result as $depot) {
            //getting services for each deposit
            $data=DepositServices::leftjoin('services_controllers as S','deposit_services.service_id','=','S.id')
                ->leftjoin('categories_services_controllers as C', 'S.category_id','=','C.id')
                ->leftjoin('unit_of_measure_controllers as U','S.uom_id','=','U.id')
                ->leftjoin('prices_categories as PC','PC.service_id','=','S.id')
                ->leftjoin('moneys as M','PC.money_id','=','M.id')
                ->where('deposit_id','=',$depot['deposit_id'])
                ->get(['PC.label','PC.price','M.money_name','M.abreviation','deposit_services.available_qte','C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','deposit_services.deposit_id','S.*']);
            foreach ($data as $service) {
               
                array_push($services,$service); 
            }
                
        }

        return $services;
    }

    /**
     * list of articles only for a specific user
     */
    public function myarticles($user_id){
        $services=[];
        //check if the seller is affected to a deposit
        $result=DepositsUsers::where('user_id','=',$user_id)->get();
        foreach ($result as $depot) {
            //getting services for each deposit
            $data=DepositServices::leftjoin('services_controllers as S','deposit_services.service_id','=','S.id')
                ->leftjoin('categories_services_controllers as C', 'S.category_id','=','C.id')
                ->leftjoin('unit_of_measure_controllers as U','S.uom_id','=','U.id')
                ->leftjoin('prices_categories as PC','PC.service_id','=','S.id')
                ->leftjoin('moneys as M','PC.money_id','=','M.id')
                ->where('deposit_id','=',$depot['deposit_id'])
                ->where('S.type','=','1')
                ->get(['PC.label','PC.price','M.money_name','M.abreviation','deposit_services.available_qte','C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','deposit_services.deposit_id','S.*']);
            foreach ($data as $service) {
                // $prices=PricesCategories::leftjoin('moneys as M','prices_categories.money_id','=','M.id')
                // ->where('prices_categories.service_id','=',$service->id)
                // ->get(['M.money_name','M.abreviation','prices_categories.*']);
                array_push($services,$service); 
            }
                
        }

        return $services;
    }



    public function depositall($deposit_id){

        $services=[];
        //getting services for each deposit
        $data=DepositServices::leftjoin('services_controllers as S','deposit_services.service_id','=','S.id')
            ->leftjoin('categories_services_controllers as C', 'S.category_id','=','C.id')
            ->leftjoin('unit_of_measure_controllers as U','S.uom_id','=','U.id')
            ->leftjoin('prices_categories as PC','PC.service_id','=','S.id')
            ->leftjoin('moneys as M','PC.money_id','=','M.id')
            ->where('deposit_id','=',$deposit_id)
            ->get(['PC.label','PC.price','M.money_name','M.abreviation','deposit_services.available_qte','C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','deposit_services.deposit_id','S.*']);
            foreach ($data as $service) {
                array_push($services,$service); 
            }
                
        return $services;
    }
    /**
     * services to sell (service and articles)
     */

     public function tosell(){

     }

    /**
     * adding from short cut (specially from seller)
     */
     public function showshortcut($service_id){

        return ServicesController::leftjoin('categories_services_controllers as C', 'S.category_id','=','C.id')
                ->leftjoin('unit_of_measure_controllers as U','S.uom_id','=','U.id')
                ->leftjoin('prices_categories as PC','PC.service_id','=','S.id')
                ->leftjoin('moneys as M','PC.money_id','=','M.id')
                ->where('services_controllers.id','=',$service_id)
                ->where('S.type','=','1')
                ->get(['PC.label','PC.price','M.money_name','M.abreviation','deposit_services.available_qte','C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','deposit_services.deposit_id','S.*']);
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
     * importation of data
     */
    // public function importation(Request $request){
    //     $data=[];
    //     if(count($request->data)>0){
    //         foreach ($request->data as $article) {
    //             if ( $newArticle=$this->store(new Request($article))) {
    //                 array_push($data,$newArticle);
    //             }
    //         }
    //     }

    //     return $data;
    // }

    public function importation(Request $request)
    {
        $data = [];

        if (count($request->data) > 0) {
           
            $chunks = array_chunk($request->data, 50);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $article) {
                    if ($newArticle = $this->store(new Request($article))) {
                        $data[] = $newArticle; // équivalent à array_push
                    }
                }
            }
        }

        return $data;
    }


    /**
     * Update all services
     */
    public function updateallservices(Request $request){
        $ese=$this->getEse($request['user_id']);
        if($request['criteria']==="set_tva"){
            try {
                $data=DB::update('update services_controllers set has_vat = ? where enterprise_id= ?',[$request['value'],$ese['id']]);
                return response()->json([
                    'message'=>'updated',
                    'data'=>$data
                ]);
            } catch (Exception $error) {
                return response()->json([
                    'message'=>'error',
                    'data'=>null
                ]);
            }
           
        }else{
            return response()->json([
                'message'=>'unknown',
                'data'=>null
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreServicesControllerRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!isset($request->name) || empty($request->name)) {
            return "empty name given";
        }

        DB::beginTransaction();

        try {
            // Vérifier si le service existe déjà
            $ifexists = ServicesController::where('name', $request->name)
                ->where('enterprise_id', $request->enterprise_id)
                ->first();

            if ($ifexists) {
                DB::rollBack();
                return null;
            }

            //test des categories
            if (!empty($request['category'])) {
                $normalizedCategory = strtolower($request['category']); // Convertit en minuscule

                $lookcategory = CategoriesServicesController::where('enterprise_id', $request->enterprise_id)
                    ->whereRaw('LOWER(name) = ?', [$normalizedCategory])
                    ->first();

                if ($lookcategory) {
                    $request['category_id'] = $lookcategory->id;
                } else {
                    $newcategory = CategoriesServicesController::create([
                        'parent_id'         => null,
                        'name' =>ucfirst(strtolower($request['category'])),
                        'user_id'           => $request['user_id'],
                        'description'       => null,
                        'type_conservation' => null,
                        'has_vat'           => null,
                        'enterprise_id'     => $request->enterprise_id
                    ]);
                    $request['category_id'] = $newcategory->id;
                }
            }
            
            //test unites des mesures
            if (!empty($request['uom'])) {
                $normalizedUom = strtolower($request['uom']); // Convertit en minuscule

                $lookUom =UnitOfMeasureController::where('enterprise_id', $request->enterprise_id)
                    ->whereRaw('LOWER(name) = ?', [$normalizedUom])
                    ->first();

                if ($lookUom) {
                    $request['uom_id'] = $lookUom->id;
                } else {
                    $newUom =UnitOfMeasureController::create([
                        'name' =>ucfirst(strtolower($request['uom'])),
                        'symbol'           =>ucfirst(strtolower($request['uom'])),
                        'enterprise_id'     => $request->enterprise_id
                    ]);
                    $request['uom_id'] = $newUom->id;
                }
            }

            // Création du service
            $new = ServicesController::create($request->all());

            // Création des prix
            if (isset($request->pricing)) {
                foreach ($request->pricing as $key => $pricing) {
                    $pricing['service_id'] = $new->id;

                    if (!isset($pricing['money_id']) || empty($pricing['money_id'])) {
                        $defaultmoney = $this->getdefaultmoney($this->getEse($request->user_id)['id']);
                        $pricing['money_id'] = $defaultmoney['id'];
                    }

                    if ($key === 0) {
                        $pricing['principal'] = 1;
                    }

                    PricesCategories::create($pricing);
                }
            }

            // Si utilisateur lié à un dépôt : dépôt initial
            if (!empty($request['user_id']) && floatval($request['available_qte'])>0) {
                $depositId=0;
                $isheaffected = DepositsUsers::where('user_id', $request->user_id)->first();
                if (!$isheaffected) {
                    $deposit=DepositController::where('enterprise_id',$request->enterprise_id)->first();
                    $depositId=$deposit->id;
                }else{
                    $depositId=$request->deposit_id;
                }

                if ($depositId>0) {
                    $stockqte = DepositServices::create([
                        'deposit_id'    =>$depositId,
                        'service_id'    => $new->id,
                        'available_qte' => $request->available_qte
                    ]);

                    if ($new->type == 1 && $stockqte) {
                        StockHistoryController::create([
                            'service_id'        => $new->id,
                            'user_id'           => $new->user_id,
                            'invoice_id'        => 0,
                            'quantity'          =>$stockqte->available_qte,
                            'price'             => 0,
                            'type'              => 'entry',
                            'type_approvement'  => 'cash',
                            'enterprise_id'     => $request->enterprise_id,
                            'motif'             => 'stock initial',
                            'note'             => 'stock initial',
                            'depot_id'          =>$depositId,
                            'done_at'          =>date('Y-m-d'),
                            'uuid'          =>$this->getUuId('C','SH'),
                        ]);
                    }
                }
            }

            // Affecter à tous les dépôts de type "group"
            $groupdeposits = DepositController::where('type', 'group')
                ->where('enterprise_id', $request->enterprise_id)
                ->get();

            foreach ($groupdeposits as $deposit) {
                $already = DepositServices::where('service_id', $new->id)
                    ->where('deposit_id', $deposit['id'])
                    ->exists();

                if (!$already) {
                    DepositServices::create([
                        'deposit_id'    => $deposit['id'],
                        'service_id'    => $new->id,
                        'available_qte' => 0
                    ]);
                }
            }

            DB::commit();
            return $this->show($new);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la création du service : " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     if(isset($request->name) && !empty($request->name)){
    //         //if exists service
    //         $ifexists=ServicesController::where('name',$request->name)
    //                     ->where('enterprise_id',$request->enterprise_id)->get()->first();
    //         if (($ifexists)) {
    //             return null;
    //             // abort(403, 'name duplicated');
    //         }
    //         $new=ServicesController::create($request->all());
    //         if(isset($request->pricing)){
    //             foreach ($request->pricing as $key=>$pricing) {
    //                 $pricing['service_id']=$new->id;
    //                 //check if there is money set
    //                 if(isset($pricing['money_id']) && !empty($pricing['money_id'])){
                       
    //                 }else{
    //                      //get default money
    //                      $defaultmoney=$this->getdefaultmoney($this->getEse($request->user_id)['id']);
    //                      $pricing['money_id']=$defaultmoney['id'];
    //                 }
    //                  //if no price already existed , make it principal
    //                 if($key===0){
    //                     $pricing['principal']=1;
    //                 }
    //                 PricesCategories::create($pricing);
    //             }
    //         }
    
    //         //if user is affected a deposit put the service in depositServices
    //         if(isset($request->user_id) && !empty($request->user_id)){
    //             //if it sets deposit_id
    //             if(isset($request->deposit_id) && !empty($request->deposit_id)){
    //                 $isheaffected=DepositsUsers::where('user_id','=',$request->user_id)->where('deposit_id','=',$request->deposit_id)->get()->first();
    //             }else{
    //                 $isheaffected=DepositsUsers::where('user_id','=',$request->user_id)->get()->first();
    //             }
                
    //             if($request->available_qte>0){
    //                 if(!$isheaffected) {
    //                     $isheaffected=DepositsUsers::leftjoin('deposit_controllers as D', 'deposits_users.deposit_id','=','D.id')->where('D.enterprise_id','=',$this->getEse($request->user_id)['id'])->get('deposits_users.*')->first();
    //                 }
    //                 if($isheaffected){
    //                     //insert the service in the actual deposit
    //                      $stockqte=DepositServices::create([
    //                         'deposit_id'=>$isheaffected['deposit_id'],
    //                         'service_id'=>$new->id,
    //                         'available_qte'=>$request->available_qte
    //                     ]);

    //                     if($new->type==1 && $stockqte){
    //                     //stock history
    //                     StockHistoryController::create([
    //                             'service_id'=>$new->id,
    //                             'user_id'=>$new->user_id,
    //                             'invoice_id'=>0,
    //                             'quantity'=>$request->available_qte,
    //                             'price'=>0,
    //                             'type'=>'entry',
    //                             'type_approvement'=>'cash',
    //                             'enterprise_id'=>$request->enterprise_id,
    //                             'motif'=>'stock initial',
    //                             'depot_id'=>$isheaffected[0]['deposit_id'],
    //                         ]);
    //                     }
    //                 }
    //             }  
    //         }
    //       //affect the service everywhere the deposit has group as type
    //       $groupdeposits=DepositController::where('type','=','group')->where('enterprise_id','=',$request->enterprise_id)->get();
    //       foreach ($groupdeposits as $key => $deposit) {
    //         //test if it's not affected
    //             if(count($ifnot=DepositServices::where('service_id','=',$new->id)->where('deposit_id','=',$deposit['id'])->get())<1){
    //             //insert the service in the actual deposit
    //             DepositServices::create([
    //                 'deposit_id'=>$deposit['id'],
    //                 'service_id'=>$new->id,
    //                 'available_qte'=>0
    //             ]);
    //         }
    //       }
    //         return $this->show($new);
    //     }else{
    //         return "empty name given";
    //     }
       
    // }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ServicesController  $servicesController
     * @return \Illuminate\Http\Response
     */
    public function show(ServicesController $servicesController)
    {
        $prices=PricesCategories::leftjoin('moneys as M','prices_categories.money_id','=','M.id')
        ->where('prices_categories.service_id','=',$servicesController->id)
        ->get(['M.money_name','M.abreviation','prices_categories.*']);

        $service=ServicesController::leftjoin('categories_services_controllers as C', 'services_controllers.category_id','=','C.id')
        ->leftjoin('unit_of_measure_controllers as U','services_controllers.uom_id','=','U.id')
        ->where('services_controllers.id', '=', $servicesController->id)
        ->get(['C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','services_controllers.*'])[0];  
        
        return ['service'=>$service,'prices'=>$prices];
    }

    /**
     * show detail without prices
     */
    public function detailwithoutprices(ServicesController $servicesController){
        return ServicesController::leftjoin('categories_services_controllers as C', 'services_controllers.category_id','=','C.id')
        ->leftjoin('unit_of_measure_controllers as U','services_controllers.uom_id','=','U.id')
        ->where('services_controllers.id', '=', $servicesController->id)
        ->get(['C.name as category_name','U.name as uom_name','U.symbol as uom_symbol','services_controllers.*'])->first();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ServicesController  $servicesController
     * @return \Illuminate\Http\Response
     */
    public function edit(ServicesController $servicesController)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateServicesControllerRequest  $request
     * @param  \App\Models\ServicesController  $servicesController
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateServicesControllerRequest $request, ServicesController $servicesController)
    {
        return $servicesController->update($request->all());
    }

    public function update2(Request $request,$id)
    {
        $service=ServicesController::find($id);
        $service->update($request->all());

        return $this->show($service);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ServicesController  $servicesController
     * @return \Illuminate\Http\Response
     */
    public function destroy(ServicesController $servicesController)
    {
        return ServicesController::destroy($servicesController);
    }
    
    public function destroy2($id)
    {
        $get=ServicesController::find($id);
        //delete all affectations on deposit
       
        $stockhistories=StockHistoryController::where('service_id','=',$id)->get();
        $invoicesdetails=InvoiceDetails::where('service_id','=',$id)->get();
        if ($stockhistories->count()<=0 &&  $invoicesdetails->count()<=0) {
            PricesCategories::where('service_id','=',$id)->delete();
            DepositServices::where('service_id','=',$id)->delete();
            StockHistoryController::where('service_id','=',$id)->delete();
            InvoiceDetails::where('service_id','=',$id)->delete();

            return $get->delete();
        }
       
        abort(401, 'non autorisé');
    }
}
