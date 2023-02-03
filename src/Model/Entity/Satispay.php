<?php
namespace Satispay\Model\Entity;

use Cake\Core\Configure;
use Cake\ORM\Entity;

class Satispay extends Entity {
    
    //$pid = payment_id
    public function receive($pid)
    {
        $authData = (object) Configure::read("Satispay");
        \SatispayGBusiness\Api::setPublicKey($authData->public_key);
        \SatispayGBusiness\Api::setPrivateKey($authData->private_key);
        \SatispayGBusiness\Api::setKeyId($authData->key_id);
        
        $payment = \SatispayGBusiness\Payment::get($pid);
        return $payment;
    }
}