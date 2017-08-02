<?php
/**
 * Created by PhpStorm.
 * User: nyinyilwin
 * Date: 8/2/17
 * Time: 2:15 PM
 */

namespace PhpJunior\Laravel2C2P\Api;

use PhpJunior\Laravel2C2P\Encryption\Encryption;

class PaymentGatewayApi
{
    private $config;
    /**
     * @var Encryption
     */
    private $encryption;

    /**
     * PaymentGatewayApi constructor.
     * @param $config
     * @param $encryption
     */
    public function __construct($config, $encryption)
    {
        $this->config = $config;
        $this->encryption = $encryption;
    }

    /**
     * @param array $input
     * @return string
     */
    public function paymentRequest(array $input)
    {
        $secretKey = $this->config->get('laravel-2c2p.secret_key');
        $merchantID = $this->config->get('laravel-2c2p.merchant_id');

        $input['amt'] = $this->amount($input['amt']);

        $stringToHash = $merchantID . $input['uniqueTransactionCode'] . $input['amt'];
        $xmlstring = '';

        foreach ($input as $key => $value) {
            $xmlstring .= '<'.$key.'>'.$value.'</'.$key.'>';
        }

        $hash = strtoupper(hash_hmac('sha1', $stringToHash ,$secretKey, false));

        $xml = '<PaymentRequest><version>8.0</version><merchantID>'.$merchantID.'</merchantID>';
        $xml .= $xmlstring;
        $xml .= '<hashValue>'.$hash.'</hashValue></PaymentRequest>';

        return base64_encode($xml);
    }

    /**
     * @param array $input
     * @return mixed|string
     */
    public function OneTwoThreeRequest(array $input)
    {
        $secretKey = $this->config->get('laravel-2c2p.123_api_secret_key');
        $merchantID = $this->config->get('laravel-2c2p.123_merchant_id');
        $currencyCode = $this->config->get('laravel-2c2p.123_currency_code');
        $countryCode = $this->config->get('laravel-2c2p.123_country_code');
        $agentCode = $this->config->get('laravel-2c2p.123_agent_code');
        $channelCode = $this->config->get('laravel-2c2p.123_channel_code');
        $merchantUrl = $this->config->get('laravel-2c2p.123_merchant_url');
        $apiCallUrl = $this->config->get('laravel-2c2p.123_api_call_url');

        $input['Amount'] = $this->amount($input['Amount']);

        if (array_has($input,'Discount'))
            $input['Discount'] = $this->amount($input['Discount']);

        if (array_has($input,'ServiceFee'))
            $input['ServiceFee'] = $this->amount($input['ServiceFee']);

        if (array_has($input,'ShippingFee'))
            $input['ShippingFee'] = $this->amount($input['ShippingFee']);

        $stringToHash = $merchantID . $input['InvoiceNo'] . $input['Amount'];
        $xmlstring = '';

        foreach (array_except($input, ['PaymentItems']) as $key => $value) {
            $xmlstring .= '<'.$key.'>'.$value.'</'.$key.'>';
        }

        $HashValue = urlencode(strtoupper(hash_hmac('sha1', $stringToHash ,$secretKey, false)));

        $xml = '<OneTwoThreeReq>';
        $xml .= '<version>1.1</version>';
        $xml .= '<MerchantID>'.$merchantID.'</MerchantID>';
        $xml .= '<CurrencyCode>'.$currencyCode.'</CurrencyCode>';
        $xml .= '<CountryCode>'.$countryCode.'</CountryCode>';

        $xml .= $xmlstring;

        if (array_has( $input,'PaymentItems')){
            $paymentItems = '<PaymentItems>';
            foreach ($input['PaymentItems'] as $paymentItem){
                $price = $this->amount($paymentItem['price']);
                $paymentItems .="<PaymentItem id=\"$paymentItem[id]\" name=\"$paymentItem[name]\" price=\"$price\" quantity=\"$paymentItem[quantity]\" />";
            }
            $paymentItems .= '</PaymentItems>';
            $xml .= $paymentItems;
        }

        $xml .= '<MerchantUrl>'.$merchantUrl.'</MerchantUrl>';
        $xml .= '<APICallUrl>'.$apiCallUrl.'</APICallUrl>';
        $xml .= '<AgentCode>'.$agentCode.'</AgentCode>';
        $xml .= '<ChannelCode>'.$channelCode.'</ChannelCode>';
        $xml .= '<HashValue>'.$HashValue.'</HashValue>';
        $xml .= '</OneTwoThreeReq>';

        return $this->encryption->pkcs7_123_encrypt($xml);
    }

    public function getData($text)
    {
        $response = $this->encryption->pkcs7_decrypt($text);
        $string = <<<XML
<?xml version='1.0'?>
$response
XML;
       return simplexml_load_string($string);
    }

    private function amount($amount)
    {
        $real_amount = sprintf("%.2f", $amount);
        $amount = str_replace('.', '', $real_amount);
        return str_pad($amount, 12, '0', STR_PAD_LEFT);
    }
}