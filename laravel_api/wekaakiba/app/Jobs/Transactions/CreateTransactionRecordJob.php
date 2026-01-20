<?php

namespace App\Jobs\Transactions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\wekaAccountsTransactions;
use App\Models\User;

class CreateTransactionRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        // On récupère l'utilisateur car ta méthode dépend de userId
        $user = User::find($this->data['user_id']);

        // Enregistrer la transaction via la même structure que ton système actuel
        wekaAccountsTransactions::create([
            'amount'              => $this->data['amount'],
            'sold_before'         => $this->data['sold_before'],
            'sold_after'          => $this->data['sold_after'],
            'type'                => $this->data['type'], // ex: "send"
            'motif'               => $this->data['motif'],
            'user_id'             => $this->data['user_id'],
            'member_account_id'   => $this->data['source_account_id'],
            'member_id'           => $this->data['member_id'],
            'enterprise_id'       => $user ? $user->enterprise_id : null,
            'done_at'             => date('Y-m-d'),
            'account_id'          => $this->data['beneficiary_account_id'],
            'operation_done_by'   => $this->data['operation_done_by'] ?? 'system',
            'uuid'                => $this->data['uuid'], // on le calcule en amont !
            'fees'                => $this->data['fees'],
            'transaction_status'  => 'validated',
            'phone'               => $this->data['phone'] ?? null,
            'adresse'             => $this->data['adresse'] ?? null,
        ]);
    }
}
