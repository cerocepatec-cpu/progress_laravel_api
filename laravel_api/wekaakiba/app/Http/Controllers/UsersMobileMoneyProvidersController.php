<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MobileMoneyProviders;
use App\Models\UsersMobileMoneyProviders;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsersMobileMoneyProvidersController extends Controller
{
    public function store(Request $request){
            try {
                $validator=Validator::make($request->all(),[
                'user_id'=> 'required|integer',
                'mobile_money_provider_id'=> 'required|integer',
                'phone_number'=>['required', 'regex:/^(\+243|0)[0-9]{9}$/'],
                ]);

            if ($validator->fails()) {
                return $this->errorResponse('data sent not conform', 422);
            }else{
                $exists=$this->findUserAndProvider($request->user_id,$request->mobile_money_provider_id);
                if($exists){
                $exists->update([
                        'phone_number' =>$request->phone_number,
                        'status' =>'active',
                    ]);
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Mobile money provider updated successfully',
                        'data' =>$this->show($exists) 
                    ]);
                }else {
                    $new=UsersMobileMoneyProviders::create([
                        'user_id' => $request->user_id,
                        'mobile_money_provider_id' =>$request->mobile_money_provider_id,
                        'phone_number' => $request->phone_number,
                        'status' => 'active',
                    ]);
                    return response()->json([
                        'error' => null,
                        'status' => 200,
                        'message' => 'success',
                        'data' =>$this->show($new) 
                    ]);
                }
            }
        } catch (Exception $th) {
            return $this->errorResponse('An error occurred while processing your request', 500);
        }
       
    }

    public function show(UsersMobileMoneyProviders $usermobileprovider){
       return UsersMobileMoneyProviders::join('mobile_money_providers','users_mobile_money_providers.mobile_money_provider_id','=','mobile_money_providers.id')
       ->where('users_mobile_money_providers.id',$usermobileprovider->id)->get(['users_mobile_money_providers.*', 'mobile_money_providers.provider',
        'mobile_money_providers.country',
        'mobile_money_providers.name',
        'mobile_money_providers.metadata',])->first();
    }
}
