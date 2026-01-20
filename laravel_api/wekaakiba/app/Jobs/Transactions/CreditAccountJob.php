<?php

namespace App\Jobs\Transactions;

use Illuminate\Bus\Queueable;
use App\Models\wekamemberaccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class CreditAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        DB::transaction(function () {
            $beneficiary = wekamemberaccounts::lockForUpdate()
                ->find($this->data['beneficiary_id']);

            $beneficiary->sold += $this->data['amount'];
            $beneficiary->save();
        });
    }
}
