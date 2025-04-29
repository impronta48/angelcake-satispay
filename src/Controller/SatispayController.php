<?php

declare(strict_types=1);

namespace Satispay\Controller;

use Satispay\Controller\AppController;
use Cake\Core\Configure;

class SatispayController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authentication->allowUnauthenticated(['pay']);
    }

    //Generates the redirect to go to the satispay payment page
    public function pay($amount, $user_id, $order_id=null, $thank_you = null) {
        
        $this->autoRender = false;

        //Massimoi - 21/1/25 - non ho capito perchè non posso prendere la variabile da Configure, funziona solo con json
        //$authData = (object) Configure::read("Satispay");
        $p = CONFIG . conf_path() . '/satispay-authentication.json';
        $authData = json_decode(file_get_contents($p));
        
        if (empty($authData->sandbox)) {
            $authData->sandbox = false;
        } 
        \SatispayGBusiness\Api::setSandbox($authData->sandbox);

        \SatispayGBusiness\Api::setPublicKey($authData->public_key);
        \SatispayGBusiness\Api::setPrivateKey($authData->private_key);
        \SatispayGBusiness\Api::setKeyId($authData->key_id);
        
        $u = $this->request->scheme() . '://' . $this->request->host() . '/' . $thank_you;
        // $u = str_replace('*', '/', $thank_you);
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
        $respData = [
            'success' => true,
            'data' => [
                'redirect_url' => $redirect_url,
                'callback_url' => $receive_url
            ]
        ];
        // $success = true;
        // $data = $receive_url;
        // $this->set(compact('success'));
        // $this->set(compact('data'));
        // $this->viewBuilder()->setOption('serialize', ['success', 'data']);


        return $this->response
                ->withType('json')
                ->withStringBody(json_encode($respData));
    }


}
