<?php


namespace App\Services;


use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    protected $partnerId;
    protected $username;
    protected $sKey;
    protected $authToken;
    protected $url;
    protected $data;
    protected $res;
    protected $expiration_time;

    public function __construct()
    {
        $this->partnerId = env('PARTNER_ID');
        $this->username = env('USERNAME');
        $this->sKey = env('S_KEY');

        $this->url = "https://preprod.shimotomo.com/transactionServices/REST/v1/";
        $this->data = [];
        $this->getToken();
    }

    public function getToken()
    {
        $data = "authentication.partnerId=" . $this->partnerId .
            "&merchant.username=" . $this->username .
            "&authentication.sKey=" . $this->sKey;

        $this->createRequest('authToken', $data);
        $res = json_decode($this->request());
        $this->authToken = $res->AuthToken;

        Storage::disk('local')->put('time.txt',  $res->timestamp);
        $this->expiration_time = $res->timestamp;

        return $this->authToken;
    }

    public function regenerateToken()
    {
        $data = "authentication.partnerId=" . $this->partnerId .
            "&authToken=" . $this->authToken;

        $this->createRequest('regenerateToken', $data);
        $res = json_decode($this->request());
        $this->authToken = $res->AuthToken;

        Storage::disk('local')->put('time.txt',  $res->timestamp);
        $this->expiration_time = $res->timestamp;

        return $this->authToken;
    }

    public function gerExpirationTokenTime()
    {
        return $this->expiration_time;
    }

    public function payment($res_type = 'raw')
    {
        try {
            $expiration_time = Storage::disk('local')->get('time.txt');
        } catch (FileNotFoundException $e) {
            $this->getToken();
            $expiration_time = $this->gerExpirationTokenTime();
        }

        if(Carbon::now()->timestamp >= strtotime($expiration_time))
            $this->regenerateToken();

        $data = $this->payment_data();
        $this->createRequest('payments', $data);

        $this->res = $this->request();

        if($res_type == 'raw')
            return $this->res;
        elseif($res_type == 'array')
            return json_decode($this->res);
        else return $this->res;
    }

    protected function createRequest($action, $data){
        $this->url .= $action;
        $this->data = $data;
    }

    protected function request()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('AuthToken : ' . $this->authToken));
        $responseData = curl_exec($ch);

        if(curl_errno($ch))
            return curl_error($ch);

        curl_close($ch);

        return $responseData;
    }

    protected function payment_data()
    {
        if(request('brand') == 'VISA')
            $transactionId = 'Rest Transaction 01';
        elseif (request('brand') == 'MC')
            $transactionId = 'Rest Transaction02';
        elseif (request('brand') == 'AMEX')
            $transactionId = 'Rest Transaction03';
        else $transactionId = '';

        $checksum = md5($this->partnerId . '|' . $this->sKey . '|' . $transactionId . '|' . request('amount'));

        return
            "authentication.memberId=" . $this->partnerId .
            "&authentication.checksum=" . $checksum .
            "&merchantTransactionId=" . $transactionId .
            "&amount=" . request('amount') .
            "&currency=" . request('currency') .
            "&card.number=" . request('number') .
            "&card.expiryMonth=" . request('month') .
            "&card.expiryYear=2022" . request('year') .
            "&card.cvv=" . request('cvv') .
            "&paymentBrand=" . request('brand') .
            "&paymentMode=CC" .
            "&paymentType=DB" .
            "&merchantRedirectUrl=https://www.merchantRedirectUrl.com";
    }
}
