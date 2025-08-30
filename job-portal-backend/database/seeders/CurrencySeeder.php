<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run()
    {
        $currencies = [
            ['code'=> 'BTN', 'symbol'=> 'Nu.'],
            ['code' => 'USD', 'symbol' => '$'],
            ['code' => 'EUR', 'symbol' => '€'],
            ['code' => 'GBP', 'symbol' => '£'],
            ['code' => 'JPY', 'symbol' => '¥'],
            ['code' => 'AUD', 'symbol' => 'A$'],
            ['code' => 'CAD', 'symbol' => 'C$'],
            ['code' => 'INR', 'symbol' => '₹'],
            ['code' => 'CNY', 'symbol' => '¥'],
            ['code' => 'CHF', 'symbol' => 'CHF'],
            ['code' => 'NZD', 'symbol' => 'NZ$'],
            ['code' => 'ZAR', 'symbol' => 'R'],
            ['code' => 'SGD', 'symbol' => 'S$'],
            ['code' => 'HKD', 'symbol' => 'HK$'],
            ['code' => 'SEK', 'symbol' => 'kr'],
            ['code' => 'NOK', 'symbol' => 'kr'],
            ['code' => 'MXN', 'symbol' => '$'],
            ['code' => 'BRL', 'symbol' => 'R$'],
            ['code' => 'RUB', 'symbol' => '₽'],
            ['code' => 'KRW', 'symbol' => '₩'],
            ['code' => 'TRY', 'symbol' => '₺'],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
