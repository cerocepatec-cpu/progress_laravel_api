<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TransactionFee;

class TransactionFeeSeeder extends Seeder
{
    public function run(): void
    {
        $setby =1;
        $enterprise_id = 1;

        // === USD ===
        $usd = [
            ['min' => 0.1, 'max' => 10, 'withdraw' => 7.00, 'send' => 1.0],
            ['min' => 10.1, 'max' => 20, 'withdraw' => 5.00, 'send' => 0.9],
            ['min' => 20.01, 'max' => 50, 'withdraw' => 3.30, 'send' => 0.8],
            ['min' => 50.01, 'max' => 400, 'withdraw' => 2.75, 'send' => 0.7],
            ['min' => 401, 'max' => 2500, 'withdraw' => 1.50, 'send' => 0.5],
            ['min' => 2501, 'max' => null, 'withdraw' => 1.00, 'send' => 0.4],
        ];

        foreach ($usd as $row) {
            TransactionFee::create([
                'enterprise_id' => $enterprise_id,
                'money_id' => 2,
                'setby' => $setby,
                'min_amount' => $row['min'],
                'max_amount' => $row['max'],
                'withdraw_percent' => $row['withdraw'],
                'send_percent' => $row['send'],
            ]);
        }

        // === FRANC CONGOLAIS ===
        $fc = [
            ['min' => 100, 'max' => 30000, 'withdraw' => 7.00, 'send' => 1.0],
            ['min' => 30001, 'max' => 60000, 'withdraw' => 5.00, 'send' => 0.9],
            ['min' => 60001, 'max' => 150000, 'withdraw' => 3.30, 'send' => 0.8],
            ['min' => 150001, 'max' => 1200000, 'withdraw' => 2.75, 'send' => 0.7],
            ['min' => 1200001, 'max' => 7500000, 'withdraw' => 1.50, 'send' => 0.5],
            ['min' => 7500001, 'max' => null, 'withdraw' => 1.00, 'send' => 0.4],
        ];

        foreach ($fc as $row) {
            TransactionFee::create([
                'enterprise_id' => $enterprise_id,
                'money_id' => 1,
                'setby' => $setby,
                'min_amount' => $row['min'],
                'max_amount' => $row['max'],
                'withdraw_percent' => $row['withdraw'],
                'send_percent' => $row['send'],
            ]);
        }
    }
}
