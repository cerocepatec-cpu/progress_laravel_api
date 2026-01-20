<?php

namespace App\Http\Controllers;

use App\Models\WekaWithdrawMethods;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWekaWithdrawMethodsRequest;
use App\Http\Requests\UpdateWekaWithdrawMethodsRequest;

class WekaWithdrawMethodsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
 public function index($user)
{
    $member = $this->getinfosuser($user);
    if (!$member) {
        return $this->errorResponse('member not found', 404);
    }

    $enterprise = $this->getEse($member->id);
    if (!$enterprise) {
        return $this->errorResponse('enterprise not found', 404);
    }

    $enterpriseId = $enterprise->id;

    try {
        // Validate the enterprise ID
        if (!is_numeric($enterpriseId)) {
            return $this->errorResponse('invalid enterprise ID', 400);
        }

        // Fetch the withdrawal methods for the given enterprise ID
        $methods = WekaWithdrawMethods::where('enterprise_id', $enterpriseId)
            ->where('status', 'enabled')
            ->get();

        // Check if methods exist
        if ($methods->isEmpty()) {
            return $this->errorResponse('no withdrawal methods found for this enterprise', 404);
        }

        // Filtrer selon les permissions du membre
        $filteredMethods = $methods->filter(function ($method) use ($member) {
            if ($method->method_name === 'agent' && $member->can_withdraw_by_agent) {
                return true;
            }
            if ($method->method_name === 'mobile_money' && $member->can_withdraw_on_mobile) {
                return true;
            }
            return false;
        })->values(); // Remettre les index à zéro

        // Vérifier s'il reste des méthodes après filtrage
        if ($filteredMethods->isEmpty()) {
            return $this->errorResponse('no available withdrawal methods for this member', 403);
        }

        return response()->json([
            "status" => 200,
            "message" => "success",
            "error" => null,
            "data" => $filteredMethods
        ]);
    } catch (\Exception $e) {
        return $this->errorResponse('an error occurred', 500);
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
     * @param  \App\Http\Requests\StoreWekaWithdrawMethodsRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWekaWithdrawMethodsRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WekaWithdrawMethods  $wekaWithdrawMethods
     * @return \Illuminate\Http\Response
     */
    public function show(WekaWithdrawMethods $wekaWithdrawMethods)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WekaWithdrawMethods  $wekaWithdrawMethods
     * @return \Illuminate\Http\Response
     */
    public function edit(WekaWithdrawMethods $wekaWithdrawMethods)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWekaWithdrawMethodsRequest  $request
     * @param  \App\Models\WekaWithdrawMethods  $wekaWithdrawMethods
     * @return \Illuminate\Http\Response
     */
    // public function update(UpdateWekaWithdrawMethodsRequest $request, WekaWithdrawMethods $wekaWithdrawMethods)
    // {
    //     //
    // }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WekaWithdrawMethods  $wekaWithdrawMethods
     * @return \Illuminate\Http\Response
     */
    public function destroy(WekaWithdrawMethods $wekaWithdrawMethods)
    {
        //
    }
}
