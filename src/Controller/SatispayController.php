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
        $this->Authentication->allowUnauthenticated(['pay']);
    }

    /**
     * Inizializza le credenziali Satispay API dalla configurazione.
     */
    private function initApi(): void
    {
        $p = CONFIG . conf_path() . '/satispay-authentication.json';
        $authData = json_decode(file_get_contents($p));

        if (empty($authData->sandbox)) {
            $authData->sandbox = false;
        }
        \SatispayGBusiness\Api::setSandbox($authData->sandbox);
        \SatispayGBusiness\Api::setPublicKey($authData->public_key);
        \SatispayGBusiness\Api::setPrivateKey($authData->private_key);
        \SatispayGBusiness\Api::setKeyId($authData->key_id);
    }

    //Generates the redirect to go to the satispay payment page
    public function pay($amount, $user_id, $order_id = null)
    {

        $this->autoRender = false;

        $this->initApi();

        $thank_you = $this->request->getQuery('thank_you', '');
        $receive_url = $this->request->scheme() . '://' . $this->request->host() . '/' . $thank_you . '?payment_id={uuid}';

        $payment = \SatispayGBusiness\Payment::create([
            "flow" => "MATCH_CODE",
            "amount_unit" => $amount * 100,
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

    /**
     * Lista i pagamenti Satispay con filtri opzionali.
     * Parametri query supportati: starting_after, ending_before, limit, status, flow.
     */
    public function index()
    {
        $this->autoRender = false;

        $this->initApi();

        // Parametri supportati dall'API Satispay: https://developers.satispay.com/reference/get-list-of-payments
        // NOTA: l'endpoint è scoped per KeyID — restituisce solo i pagamenti delle credenziali usate.
        // 'flow' non è un parametro reale dell'API; i pagamenti ecommerce e fisici si distinguono
        // unicamente per KeyID (credenziali diverse = pagamenti distinti).
        $allowedFilters = ['starting_after', 'starting_after_timestamp', 'limit', 'status'];
        $query = array_intersect_key($this->request->getQueryParams(), array_flip($allowedFilters));

        $payments = \SatispayGBusiness\Payment::all($query);

        return $this->response
            ->withType('json')
            ->withStringBody(json_encode([
                'success' => true,
                'data' => $payments,
            ]));
    }

    /**
     * Storna (annulla) un pagamento Satispay già completato e aggiorna il Payin nel DB.
     *
     * @param string $payment_id L'ID del pagamento Satispay da stornare.
     */
    public function storno(string $payment_id = null)
    {
        $this->autoRender = false;

        if (empty($payment_id)) {
            return $this->response
                ->withType('json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'payment_id mancante']));
        }

        $this->initApi();

        // Prima ottieni il pagamento originale per conoscerne stato e importo
        $original = \SatispayGBusiness\Payment::get($payment_id);

        if (empty($original) || empty($original->id)) {
            return $this->response
                ->withType('json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Pagamento non trovato su Satispay']));
        }

        $status = $original->status ?? '';

        if ($status === 'ACCEPTED') {
            // Pagamento già completato → va rimborsato creando un payment REFUND
            // (CANCEL non funziona su pagamenti ACCEPTED: "Unable to fulfill request")
            $result = \SatispayGBusiness\Payment::create([
                'flow'               => 'REFUND',
                'amount_unit'        => $original->amount_unit,
                'currency'           => 'EUR',
                'parent_payment_uid' => $payment_id,
            ]);
        } elseif (in_array($status, ['PENDING', 'AUTHORIZED'])) {
            // Pagamento non ancora accettato → si può cancellare
            $result = \SatispayGBusiness\Payment::update($payment_id, ['action' => 'CANCEL']);
        } else {
            // Già annullato o stato non gestito
            return $this->response
                ->withType('json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => "Impossibile stornare un pagamento in stato '{$status}'",
                ]));
        }

        // Aggiorna il Payin corrispondente nel DB (cerca per transaction_id)
        try {
            $payinsTable = $this->fetchTable('Contrasporto.Payins');
            $payin = $payinsTable->find()
                ->where(['transaction_id' => $payment_id])
                ->first();

            if ($payin) {
                $payin->cancellata = true;
                $payin->fase_pagamento = 'cancelled';
                $payinsTable->save($payin);
            }
        } catch (\Exception $e) {
            // Il Payin potrebbe non esistere se lo storno riguarda un flusso iscrizioni
            \Cake\Log\Log::warning('Satispay storno: impossibile aggiornare il Payin - ' . $e->getMessage());
        }

        return $this->response
            ->withType('json')
            ->withStringBody(json_encode([
                'success' => true,
                'data' => $result,
            ]));
    }
}
