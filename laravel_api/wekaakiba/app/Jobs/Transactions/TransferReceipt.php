<?php

namespace App\Mail\Transactions;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TransferReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject("Reçu de transaction")
                    ->view('emails.transactions.receipt')
                    ->with([
                        'amount' => $this->data['amount'],
                        'motif' => $this->data['motif'],
                        'date' => now()->format('d/m/Y H:i'),
                        'fees' => $this->data['fees'],
                        'source' => $this->data['source_account_id'],
                        'beneficiary' => $this->data['beneficiary_account_id'],
                        'ref' => $this->data['uuid'] ?? null,
                    ]);
    }
}
