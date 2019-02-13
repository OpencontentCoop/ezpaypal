<?php
//
// Definition of eZPaypalGateway class
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

/*! \file ezpaypalgateway.php
*/

/*!
  \class eZPaypalGateway ezpaypalgateway.php
  \brief The class eZPaypalGateway implements
  functions to perform redirection to the PayPal
  payment server.
*/

define("EZ_PAYMENT_GATEWAY_TYPE_PAYPAL", "ezpaypal");

class eZPaypalGateway extends eZRedirectGateway
{
    function __construct()
    {
        $this->logger = eZPaymentLogger::CreateForAdd("var/log/eZPaypalType.log");
        $this->logger->writeTimedString('eZPaypalGateway::eZPaypalGateway()');
    }

    function createPaymentObject($processID, $orderID)
    {
        $this->logger->writeTimedString("createPaymentObject");

        return eZPaymentObject::createNew($processID, $orderID, 'Paypal');
    }

    function createRedirectionUrl($process)
    {
        $this->logger->writeTimedString("createRedirectionUrl");

        $paypalINI = eZINI::instance('paypal.ini');

        $paypalServer = $paypalINI->variable('ServerSettings', 'ServerName');
        $requestURI = $paypalINI->variable('ServerSettings', 'RequestURI');

        $processParams = $process->attribute('parameter_list');
        $orderID = $processParams['order_id'];

        $indexDir = eZSys::indexDir();
        $localHost = eZSys::serverURL();
        $localURI = eZSys::serverVariable('REQUEST_URI');

        /** @var eZOrder $order */
        $order = eZOrder::fetch($orderID);
        $amount = urlencode($order->attribute('total_inc_vat'));
        $currency = urlencode($order->currencyCode());

        $locale = eZLocale::instance();

        $countryCode = urlencode($locale->countryCode());

        $business = urlencode($this->getParameter($order, 'Business'));
        $maxDescLen = $this->getParameter($order, 'MaxDescriptionLength');
        $itemName = urlencode($this->createShortDescription($order, $maxDescLen));

        $accountInfo = $order->attribute('account_information');
        $first_name = urlencode($accountInfo['first_name']);
        $last_name = urlencode($accountInfo['last_name']);
        $street = urlencode($accountInfo['street2']);
        $zip = urlencode($accountInfo['zip']);
        $state = urlencode($accountInfo['state']);
        $place = urlencode($accountInfo['place']);
        $image_url = "$localHost" . urlencode($this->getParameter($order, 'LogoURI'));
        $background = urlencode($this->getParameter($order, 'BackgroundColor'));
        $pageStyle = urlencode($this->getParameter($order, 'PageStyle'));
        $noNote = urlencode($this->getParameter($order, 'NoNote'));
        $noteLabel = ($noNote == 1) ? '' : urlencode($this->getParameter($order, 'NoteLabel'));
        $noShipping = 1;

        $url = $paypalServer . $requestURI .
            "?cmd=_ext-enter" .
            "&redirect_cmd=_xclick" .
            "&business=$business" .
            "&item_name=$itemName" .
            "&custom=$orderID" .
            "&amount=$amount" .
            "&currency_code=$currency" .
            "&first_name=$first_name" .
            "&last_name=$last_name" .
            "&address1=$street" .
            "&zip=$zip" .
            "&state=$state" .
            "&city=$place" .
            "&image_url=$image_url" .
            "&cs=$background" .
            "&page_style=$pageStyle" .
            "&no_shipping=$noShipping" .
            "&cn=$noteLabel" .
            "&no_note=$noNote" .
            "&lc=$countryCode" .
            "&notify_url=$localHost" . $indexDir . "/paypal/notify_url/" .
            "&return=$localHost" . $indexDir . "/shop/checkout/" .
            "&cancel_return=$localHost" . $indexDir . "/shop/basket/";

        $this->logger->writeTimedString("business       = $business");
        $this->logger->writeTimedString("item_name      = $itemName");
        $this->logger->writeTimedString("custom         = $orderID");
        $this->logger->writeTimedString("no_shipping    = $noShipping");
        $this->logger->writeTimedString("localHost      = $localHost");
        $this->logger->writeTimedString("amount         = $amount");
        $this->logger->writeTimedString("currency_code  = $currency");
        $this->logger->writeTimedString("notify_url     = $localHost" . $indexDir . "/paypal/notify_url/");
        $this->logger->writeTimedString("return         = $localHost" . $indexDir . "/shop/checkout/");
        $this->logger->writeTimedString("cancel_return  = $localHost" . $indexDir . "/shop/basket/");

        return $url;
    }

    private function getParameter(eZOrder $order, $parameterKey)
    {
        $paypalINI = eZINI::instance('paypal.ini');
        $parameter = $paypalINI->variable('PaypalSettings', $parameterKey);

        if (class_exists('OCPaymentRecipient')) {
            $paymentRecipientAvailableParameters = eZINI::instance('payment_recipient.ini')->variable('ParametersSettings', 'AvailableParameters');
            if (isset($paymentRecipientAvailableParameters[$parameterKey])){
                foreach ($order->productItems() as $product){
                    /** @var eZContentObject $productObject */
                    $productObject = $product['item_object']->attribute('contentobject');
                    $productPaymentRecipient = eZPaymentRecipientType::getPaymentRecipientFromContentObject($productObject);
                    if ($productPaymentRecipient instanceof OCPaymentRecipient){
                        if ($productPaymentRecipient->hasParameter($parameterKey)){
                            $parameter = $productPaymentRecipient->getParameter($parameterKey);
                            break;
                        }
                    }
                }
            }
        }

        return $parameter;
    }
}

eZPaymentGatewayType::registerGateway(EZ_PAYMENT_GATEWAY_TYPE_PAYPAL, "ezpaypalgateway", "Paypal");

