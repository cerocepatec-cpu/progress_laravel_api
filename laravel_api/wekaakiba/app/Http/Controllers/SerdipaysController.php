<?php

namespace App\Http\Controllers;

use App\Models\serdipays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SerdipaysWebhookLog;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\wekaAccountsTransactions;
use App\Http\Requests\StoreserdipaysRequest;
use App\Http\Requests\UpdateserdipaysRequest;

class SerdipaysController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * get token for SERDI PAIE integration
     */
     public function getToken()
    {
        try {
            $lastconfig = serdipays::configFor("test");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }

        if (!$lastconfig->token_endpoint) {
            return $this->errorResponse("Token-endpoint non configuré", 400);
        }

        if (!$lastconfig->email) {
            return $this->errorResponse("Email non configuré", 400);
        }

        if (!$lastconfig->password) {
            return $this->errorResponse("Password non configuré", 400);
        }

        try {
            $response = Http::post($lastconfig->token_endpoint, [
                'email'    => $lastconfig->email,
                'password' => $lastconfig->password,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse("Impossible d'appeler le token-endpoint: " . $e->getMessage(), 400);
        }

        $data = $response->json();

        if (!$response->successful()) {
            return $this->errorResponse("Impossible de récupérer le token", 400);
        }

        // Sécuriser l'accès au token
        $token = $data['token']['access_token'] ?? null;

        if (!$token) {
            return $this->errorResponse("Le token n'a pas été retourné par l'API", 400);
        }

        // Mise à jour
        $lastconfig->update([
            'token' => $token
        ]);

        return $this->successResponse("success", $token);
    }

    /**
     * serdi pay feedback transactions
     */
   public function serditransactionsfeedback(Request $request)
    {
        DB::beginTransaction();

        try {
            // 1) Validation minimale du payload
            $callback = $request->all();

            if (!isset($callback['payment'])) {
                return response()->json([
                    "status"  => 400,
                    "message" => "Invalid callback payload",
                    "error"   => "Missing payment object"
                ], 400);
            }

            $payment = $callback['payment'];

            // 2) Chercher la transaction dans serdipays_webhook_logs
            $log = SerdipaysWebhookLog::where('sessionId', $payment['sessionId'])
                ->orWhere('transactionId', $payment['transactionId'])
                ->first();

            if (!$log) {
                return response()->json([
                    "status"  => 404,
                    "message" => "Callback received but transaction not found",
                    "data"    => $payment
                ], 404);
            }

            // 3) Mise à jour du statut du webhook log
            $log->update([
                'status'        => $payment['status'],     // success | failed
                'sessionStatus' => $payment['sessionStatus'],
            ]);

            // 4) Récupérer la transaction WEKA via wekatransactionId
            $wekaTx = wekaAccountsTransactions::where('id', $log->wekatransactionId)->first();

            if (!$wekaTx) {
                DB::commit();

                return response()->json([
                    "status"  => 404,
                    "error" => "Weka transaction not found",
                    "message" => "error",
                    "data"    => $payment
                ], 404);
            }

            // 5) Mise à jour du statut de la transaction WEKA
            // éviter les doubles déductions si un callback est reçu 2 fois
            if ($wekaTx->transaction_status === "validated" || $wekaTx->transaction_status === "pending") {
                $wekaTx->update([
                    'transaction_status' => $payment['status'] === "success" ? "validated" : "failed"
                ]);
            }

            // 6) Si succès → mettre à jour le solde du compte membre
            if ($payment['status'] === "success") {

                $memberAccount = $wekaTx->memberAccount;

                if ($memberAccount) {
                if ( $wekaTx->type=="withdraw") {
                    if ($memberAccount->sold >= $log->amount) {
                        $memberAccount->sold -= $log->amount;
                        $memberAccount->save();
                    }
                }  
                
                    if ( $wekaTx->type=="entry") {
                            $memberAccount->sold += $log->amount;
                            $memberAccount->save();
                    }

                    $transactionCtrl = new WekaAccountsTransactionsController();
                    event(new \App\Events\TransactionUpdateEvent(
                        $memberAccount->user_id,
                        $transactionCtrl->show($wekaTx)
                    ));

                    event(new \App\Events\MemberAccountUpdated(
                        $memberAccount->user_id,
                        $memberAccount
                    )); 
                }
            }

            DB::commit();

            return response()->json([
                "status"  => 200,
                "message" => "success",
                "data"    => ["log"=> $log]
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                "status"  => 500,
                "message" => "Internal error",
                "error"   => $e->getMessage()
            ], 500);
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
     * @param  \App\Http\Requests\StoreserdipaysRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreserdipaysRequest $request)
    {
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\serdipays  $serdipays
     * @return \Illuminate\Http\Response
     */
    public function show(serdipays $serdipays)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\serdipays  $serdipays
     * @return \Illuminate\Http\Response
     */
    public function edit(serdipays $serdipays)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateserdipaysRequest  $request
     * @param  \App\Models\serdipays  $serdipays
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateserdipaysRequest $request, serdipays $serdipays)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\serdipays  $serdipays
     * @return \Illuminate\Http\Response
     */
    public function destroy(serdipays $serdipays)
    {
        //
    }
}
