<?php

namespace App\Exports;

use App\Models\wekaAccountsTransactions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class TransactionsExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, ShouldQueue
{
    protected $request;
    protected $actualuser;
    protected int $index = 1;

    public function __construct(array $request, $actualuser)
    {
        $this->request = $request;
        $this->actualuser = $actualuser;
    }

     public function query()
    {
        $query = wekaAccountsTransactions::query()
            ->join('users as done_by', 'weka_accounts_transactions.user_id', '=', 'done_by.id') // caissier
            ->join('wekamemberaccounts as WA', 'weka_accounts_transactions.member_account_id', '=', 'WA.id')
            ->join('moneys as M', 'WA.money_id', '=', 'M.id')
            ->join('users as member_user', 'WA.user_id', '=', 'member_user.id') // membre
            ->leftJoin('accounts as A', 'weka_accounts_transactions.account_id', '=', 'A.id');

        // Filtres dynamiques
        if (!empty($this->request['moneys'])) {
            $query->whereIn('M.id', $this->request['moneys']);
        }

        if ($this->actualuser['user_type'] !== 'super_admin') {
            $query->where('weka_accounts_transactions.user_id', $this->actualuser->id);
        }

        if (!empty($this->request['members'])) {
            $query->whereIn('member_user.id', $this->request['members']);
        }

        if (!empty($this->request['cashiers'])) {
            $query->whereIn('done_by.id', $this->request['cashiers']);
        }

        if (!empty($this->request['enterprise_id'])) {
            $query->where('weka_accounts_transactions.enterprise_id', $this->request['enterprise_id']);
        }

        $from = $this->request['from'] ?? date('Y-m-d');
        $to   = $this->request['to']   ?? date('Y-m-d');

        $query->whereBetween('weka_accounts_transactions.done_at', [
            $from . ' 00:00:00',
            $to   . ' 23:59:59',
        ]);

        // Champs à retourner
        return $query->select([
            'member_user.user_name as member_user_name',
            'member_user.full_name as member_fullname',
            'member_user.uuid as member_uuid',
            'weka_accounts_transactions.*',
            'A.name as account_name',
            'WA.description as memberaccount_name',
            'M.abreviation as currency',
            'M.id as money_id',
            'done_by.id as done_by_id',
            'done_by.user_name as done_by_name',
            'done_by.full_name as done_by_fullname',
            'done_by.uuid as done_by_uuid',
        ]);
    }

   public function headings(): array
    {
        return [
            'Numéro',
            'ID',
            'Date',
            'Faite par',
            'UUID Caissier',
            'Membre',
            'Code Membre',
            'Type',
            'Montant',
            'Devise',
            'Compte',
            'Statut',
        ];
    }

    public function map($transaction): array
    {
        return [
            $this->index++,
            $transaction->uuid,
            $transaction->done_at,
            $transaction->done_by_fullname ?? $transaction->done_by_name ?? '',
            $transaction->done_by_uuid ?? $transaction->done_by_id ?? '',
            $transaction->member_fullname ?? '',
            $transaction->member_uuid ?? '',
            $transaction->type,
            $transaction->amount,
            $transaction->currency ?? '',
            $transaction->memberaccount_name ?? '',
            $transaction->transaction_status ?? '',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}

