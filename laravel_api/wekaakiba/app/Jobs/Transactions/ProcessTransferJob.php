<?php

namespace App\Jobs\Transactions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\Transactions\DebitAccountJob;
use App\Jobs\Transactions\CreditAccountJob;
use App\Jobs\Transactions\CreateTransactionRecordJob;
use App\Jobs\Transactions\SendReceiptEmailJob;

class ProcessTransferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transfer;

    public function __construct(array $transfer)
    {
        $this->transfer = $transfer;
    }

    public function handle()
    {
        DebitAccountJob::dispatch($this->transfer)->onQueue('transactions');
        CreditAccountJob::dispatch($this->transfer)->onQueue('transactions');
        CreateTransactionRecordJob::dispatch($this->transfer)->onQueue('transactions');
        SendReceiptEmailJob::dispatch($this->transfer)->onQueue('mail');
    }
}
