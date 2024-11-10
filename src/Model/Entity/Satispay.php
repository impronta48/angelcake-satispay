<?php
namespace Satispay\Model\Entity;

use Cake\Core\Configure;
use Cake\ORM\Entity;

class Satispay extends Entity {
    
    //$pid = payment_id
    public function receive($pid)
    {
        $authData = json_decode(file_get_contents(CONFIG. '/satispay-authentication.json'));

        \SatispayGBusiness\Api::setPublicKey($authData->public_key);
        \SatispayGBusiness\Api::setPrivateKey($authData->private_key);
        \SatispayGBusiness\Api::setKeyId($authData->key_id);
         
        $payment = \SatispayGBusiness\Payment::get($pid);
        return $payment;
    }
}