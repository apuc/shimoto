<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;


class PaymentsController extends Controller
{
    public function refill()
    {
        $ps = new PaymentService();

        $ps->payment(); // return result in json format; $ps->payment('array'); // return decoded result
    }
}
