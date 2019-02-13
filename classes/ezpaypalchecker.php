<?php
//
// Definition of eZPaypalChecker class
//
// Created on: <18-Jul-2004 14:18:58 dl>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Paypal Payment Gateway
// SOFTWARE RELEASE: 1.0
// COPYRIGHT NOTICE: Copyright (C) 1999-2006 eZ systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*! \file ezpaypalchecker.php
*/

/*!
  \class eZPaypalChecker ezpaypalchecker.php
  \brief The class eZPaypalChecker implements
  functions to perform verification of the
  paypal's callback.
*/

class eZPaypalChecker extends eZPaymentCallbackChecker
{
    /**
     * @var eZPaymentLogger
     */
    public $logger;

    /**
     * @var eZINI
     */
    public $ini;

    public $callbackData;

    /**
     * @var eZPaymentObject
     */
    public $paymentObject;

    /**
     * @var eZOrder
     */
    public $order;

    function __construct($iniFile)
    {
        parent::__construct($iniFile);
        $this->eZPaymentCallbackChecker($iniFile);
        $this->logger = eZPaymentLogger::CreateForAdd('var/log/eZPaypalChecker.log');
    }

    function requestValidation()
    {
        $server = $this->ini->variable('ServerSettings', 'ServerName');
        $requestURI = $this->ini->variable('ServerSettings', 'RequestURI');
        $request = $this->buildRequestString();

        $url = $server . $requestURI;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'cURL/PHP');

        $response = curl_exec($ch);

        $this->logger->writeTimedString($response, 'Response is');

        if ($response && strcasecmp($response, 'VERIFIED') == 0) {
            return true;
        }

        $this->logger->writeTimedString('invalid response');
        return false;
    }

    function checkPaymentStatus()
    {
        if ($this->checkDataField('payment_status', 'Completed')) {
            return true;
        }

        $this->logger->writeTimedString('checkPaymentStatus failed');
        return false;
    }

    function buildRequestString()
    {
        $request = "cmd=_notify-validate";
        foreach ($this->callbackData as $key => $value) {
            $request .= "&$key=" . urlencode($value);
        }
        return $request;
    }

    function checkAmount($amount)
    {
        $orderAmount = $this->order->attribute('total_inc_vat');

        // To avoid floating errors, round the value down before checking.
        $shopINI = eZINI::instance('shop.ini');
        $precisionValue = (int)$shopINI->variable('MathSettings', 'RoundingPrecision');
        if (round($orderAmount, $precisionValue) === round($amount, $precisionValue)) {
            return true;
        }

        $this->logger->writeTimedString("Order amount ($orderAmount) and received amount ($amount) do not match.", 'checkAmount failed');
        return false;
    }

    function checkCurrency($currency)
    {
        //get the order currency
        $productCollection = $this->order->productCollection();
        $orderCurrency = $productCollection->attribute('currency_code');

        if ($orderCurrency == $currency) {
            return true;
        }

        $this->logger->writeTimedString("Order currency ($orderCurrency) and received currency ($currency).", 'checkCurrency failed');
        return false;
    }

    function approvePayment($continueWorkflow = true)
    {
        if ($this->paymentObject) {
            $this->paymentObject->approve();
            $this->paymentObject->store();

            //refetch the $this->order - it's an old object, just the order ID is good
            //Changing the status with an old version for $this->order will write old
            //values to the order
            $this->logger->writeTimedString('ReFetch Order ID: ' . $this->order->ID);

            /** @var eZOrder $order */
            $order = eZOrder::fetch($this->order->ID);
            $this->order = $order;

            // activate order if the customer does not return to the shop
            $this->order->activate();

            $orderStatus = (int)eZINI::instance('paypal.ini')->variable('Settings', 'OrderStatus');
            if ($orderStatus == 0) {
                $orderStatus = 1002;
            }
            $order->setStatus($orderStatus);

            if (eZOrderStatus::fetchByStatus($orderStatus)) {
                $this->order->setStatus($orderStatus);
            } else {
                $this->order->setStatus(eZOrderStatus::PROCESSING);
            }
            $this->order->store();

            $this->logger->writeTimedString('payment was approved');

            return ($continueWorkflow ? $this->continueWorkflow() : null);
        }

        $this->logger->writeTimedString("payment object is not set", 'approvePayment failed');
        return null;
    }

    function sendPOSTRequest($server, $port, $serverMethod, $request, $timeout = 30)
    {
        // only override, do nothing using Curl
    }

    function handleResponse($socket)
    {
        // only override, do nothing using Curl
    }
}
