<?php

declare(strict_types=1);

namespace Satispay\Controller;

use Satispay\Controller\AppController;
use Cake\Core\Configure;
use Cake\Routing\Router;

class SatispayController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['pay']);
    }

    //Generates the redirect to go to the satispay payment page
    public function pay($amount, $user_id, $order_id=null, $thank_you = null) {
        \SatispayGBusiness\Api::setSandbox(true);
        $authData = (object) Configure::read("Satispay");
        \SatispayGBusiness\Api::setPublicKey($authData->public_key);
        \SatispayGBusiness\Api::setPrivateKey($authData->private_key);
        \SatispayGBusiness\Api::setKeyId($authData->key_id);
        
        $u = str_replace('*', '/', $thank_you);
        $receive_url = "$u?payment_id={uuid}";

        $payment = \SatispayGBusiness\Payment::create([
            "flow" => "MATCH_CODE",
            "amount_unit" => $amount,
            "currency" => "EUR",            
            "callback_url" => "$receive_url",
            "metadata" => [
                "order_id" => $order_id,
                "user" => $user_id,                
            ]
        ]);

        $redirect_url = $payment->redirect_url;
        $this->redirect("$redirect_url?redirect_url=$receive_url");
    }


}
