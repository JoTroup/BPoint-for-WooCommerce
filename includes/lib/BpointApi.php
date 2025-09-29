<?php

/**
 * Bpoint
 * @copyright   Copyright (c) Linkly 2024 (https://linkly.com.au/)
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class BpointApi
{

    /**
     * public and private variables
     *
     * @var string stores data for the class
     */
    private $_txnsUrl;
    private $_jsUrl;
    private $_headers;
    private $_proxyFlag;
    private $_proxyHost;
    private $_proxyPort;
    private $_action;
    private $_amount;
    private $_currency;
    private $_merchantReference;
    private $_crn1;
    private $_crn2;
    private $_crn3;
    private $_billerCode;
    private $_redirectionUrl;
    private $_webHookUrl;
    private $_subType;
    private $_type;
    private $_originalTxnNumber;
    private $_cardDetails;
    private $_testMode;
    private $_userAgent;
    private $_apiVersion = '5';

    public function setBaseURL($baseApiUrl, $proxyHost = "", $proxyPort = "")
    {
        $baseApiUrl = rtrim(trim($baseApiUrl),'/') . '/';
        $this->_txnsUrl = $baseApiUrl . 'v' . $this->_apiVersion;
        $this->_jsUrl = $baseApiUrl . 'clientscripts/api.js';
        if (($proxyHost == "") || ($proxyPort == "")) {
            $this->_proxyFlag = false;
        } else {
            $this->_proxyFlag = true;
            $this->_proxyHost = $proxyHost;
            $this->_proxyPort = $proxyPort;
        }
    }

    public function setCredentials($username, $password, $merchantNumber)
    {
        $encodedToken = base64_encode($username . "|" . $merchantNumber . ":" . $password);
        $authHeaderString = 'Authorization: Basic ' . $encodedToken;
        $this->_headers = array($authHeaderString, 'Content-Type: application/json; charset=utf-8');
    }

    public function getJsApiUrl()
    {
        return $this->_jsUrl;
    }

    public function setAction($action)
    {
        $this->_action = $action;
        return $this;
    }

    public function setAmount($amount)
    {
        $this->_amount = $amount;
        return $this;
    }

    public function setMerchantReference($merchantReference)
    {
        $this->_merchantReference = $merchantReference;
        return $this;
    }

    public function setCurrency($currency)
    {
        $this->_currency = $currency;
        return $this;
    }

    public function setCrn1($crn1)
    {
        $this->_crn1 = $crn1;
        return $this;
    }

    public function setCrn2($crn2)
    {
        $this->_crn2 = $crn2;
        return $this;
    }

    public function setCrn3($crn3)
    {
        $this->_crn3 = $crn3;
        return $this;
    }

    public function setBillerCode($billerCode)
    {
        $this->_billerCode = $billerCode;
        return $this;
    }

    public function setRedirectionUrl($redirectionUrl)
    {
        $this->_redirectionUrl = $redirectionUrl;
        return $this;
    }

    public function setWebHookUrl($webHookUrl)
    {
        $this->_webHookUrl = $webHookUrl;
        return $this;
    }

    public function setSubType($subType)
    {
        $this->_subType = $subType;
        return $this;
    }

    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    public function setCardDetails($cardNumber, $cVN, $expiryDate = "", $cardHolderName = "")
    {
        $cardDetails = new stdClass();
        $cardDetails->CardNumber = $cardNumber;
        $cardDetails->CVN = $cVN;
        if ($expiryDate) {
            $cardDetails->ExpiryDate = $expiryDate;
        }
        if ($cardHolderName) {
            $cardDetails->CardHolderName = $cardHolderName;
        }
        $this->_cardDetails = $cardDetails;
        return $this;
    }

    public function setTestMode($testMode)
    {
        $this->_testMode = $testMode;
        return $this;
    }

    public function setOriginalTxnNumber($originalTxnNumber)
    {
        $this->_originalTxnNumber = $originalTxnNumber;
        return $this;
    }

    public function setUserAgent($userAgent)
    {
        $this->_userAgent = $userAgent;
        return $this;
    }

    public function createAuthkey() 
    {
        $result = $this->post("/txns/authkeys", []);
        return $result;
    }

    public function attachTxnDetails($authkey)
    {
        $fields = array(
            "action" => $this->_action,
            "type" => $this->_type,
            "subType" => $this->_subType,
            "amount" => $this->_amount,
            "billerCode" => $this->_billerCode,
            "crn1" => $this->_crn1,
            "crn2" => $this->_crn2,
            "crn3" => $this->_crn3,
            "merchantReference" => $this->_merchantReference,
            "currency" => $this->_currency,
            "testMode" => $this->_testMode
        );
        $request = "/txns/authkeys/" . $authkey . "/txn-details";
        $result = $this->put($request, $fields);
        return $result;
    }

    public function processTransactionAuthkey($authkey)
    {
        $fields = array(
            "webhook" => array (
                "url" => $this->_webHookUrl,
                "version" => $this->_apiVersion
            )
        );
        $request = "/txns/authkeys/" . $authkey . "/process";
        $result = $this->post($request, $fields);
        return $result;
    }

    public function processTransaction()
    {
        $fields = array(
            "action" => $this->_action,
            "type" => $this->_type,
            "subType" => $this->_subType,
            "billerCode" => $this->_billerCode,
            "crn1" => $this->_crn1,
            "crn2" => $this->_crn2,
            "crn3" => $this->_crn3,
            "merchantReference" => $this->_merchantReference,
            "currency" => $this->_currency,
            "amount" => $this->_amount,
            "originalTxnNumber" => $this->_originalTxnNumber,
            "cardDetails" => $this->_cardDetails,
            "testMode" => $this->_testMode
        );
        $result = $this->post("/txns/", $fields);
        return $result;
    }

    public function error($body, $url, $json, $type)
    {
        global $error;
        if (isset($json)) {
            $results = json_decode($body, true);
            if (is_array($results) && isset($results[0])) {
                $results = $results[0];
                $results['type'] = $type;
                $results['url'] = $url;
                $results['payload'] = $json;
                $error = $results;
            } else {
                $error = ['type' => $type, 'url' => $url, 'payload' => $json, 'message' => 'Invalid response format'];
            }
        } else {
            $results = json_decode($body, true);
            if (is_array($results) && isset($results[0])) {
                $results = $results[0];
                $results['type'] = $type;
                $results['url'] = $url;
                $error = $results;
            } else {
                $error = ['type' => $type, 'url' => $url, 'message' => 'Invalid response format'];
            }
        }
    }

    /**
     * Performs a get request to the instantiated class
     *
     * Accepts the resource to perform the request on
     *
     * @param $resource string $resource a string to perform get on
     * @return results or var_dump error
     */
    protected function get($APIrequest)
    {
        $url = $this->_txnsUrl . $APIrequest;
        $curl = curl_init();
        if ($this->_proxyFlag == true) {
            curl_setopt_array($curl, array(
                CURLOPT_PROXY => $this->_proxyHost,
                CURLOPT_PROXYPORT => $this->_proxyPort,
            ));
        }
        if ($this->_userAgent) {
            curl_setopt_array($curl, array(
                CURLOPT_USERAGENT => $this->_userAgent
            ));
        }
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_POST => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $this->_headers
        ));
        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($curl);
        if ($http_status == 200 || $http_status == 400 || $http_status == 401) {
            $results = json_decode($response);
            return $results;
        } else {
            $this->error($body, $url, null, 'GET');
        }
    }

    /**
     * Performs a post request to the instantiated class
     *
     * Accepts the resource to perform the request on, and fields to be sent
     *
     * @param string $APIrequest a string to perform get on
     * @param array $fields an array to be sent in the request
     * @return results or var_dump error
     */
    protected function post($APIrequest, $fields)
    {
        global $error;
        $url = $this->_txnsUrl . $APIrequest;
        $json = json_encode($fields);
        $curl = curl_init();
        if ($this->_proxyFlag == true) {
            curl_setopt_array($curl, array(
                CURLOPT_PROXY => $this->_proxyHost,
                CURLOPT_PROXYPORT => $this->_proxyPort,
            ));
        }
        if ($this->_userAgent) {
            curl_setopt_array($curl, array(
                CURLOPT_USERAGENT => $this->_userAgent
            ));
        }
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $this->_headers
        ));
        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($curl);
        if ($http_status == 200 || $http_status == 201 || $http_status == 400 || $http_status == 401) {
            $results = json_decode($response);
            if ($http_status == 201 && $results == null) {
                return true;
            }
            return $results;
        } else {
            $this->error($body, $url, $json, 'POST');
        }
    }

    /**
     * Performs a put request to the instantiated class
     *
     * Accepts the resource to perform the request on, and fields to be sent
     *
     * @param string $APIrequest a string to perform get on
     * @param array $fields an array to be sent in the request
     * @return results or var_dump error
     */
    protected function put($APIrequest, $fields)
    {
        $url = $this->_txnsUrl . $APIrequest;
        $json = json_encode($fields);
        $curl = curl_init();

        if ($this->_proxyFlag == true) {
            curl_setopt_array($curl, array(
                CURLOPT_PROXY => $this->_proxyHost,
                CURLOPT_PROXYPORT => $this->_proxyPort,
            ));
        }

        if ($this->_userAgent) {
            curl_setopt_array($curl, array(
                CURLOPT_USERAGENT => $this->_userAgent
            ));
        }

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $this->_headers,
        ));

        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($curl);
        if ($http_status == 200 || $http_status == 201 || $http_status == 400 || $http_status == 401) {
            $results = json_decode($response);
            if ($http_status == 201 && $results == null) {
                return true;
            }
            return $results;
        } else {
            $this->error($body, $url, $json, 'PUT');
        }
    }

    /**
     * standardize amount based on currency
     * return string: AUD 50.56  |  JPY 51
     */
    public function standardizeAmount($amount, $currency)
    {
        $numberOfDigit = $this->getNumberOfDigitsAfterDecimal($currency);
        if ($numberOfDigit === null) {
            return null;
        }
        return round($amount, $numberOfDigit);
    }

    /**
     * standardlize amount from gateway
     * return number: 50.56 for AUD 5056  |  51 for JPY 51
     */
    public function standardizeAmountFromGateway($lowestDenominationAmount, $currency)
    {
        $numberOfDigit = $this->getNumberOfDigitsAfterDecimal($currency);
        if ($numberOfDigit === null) {
            return null;
        }
        return round($lowestDenominationAmount / pow(10, $numberOfDigit), $numberOfDigit);
    }

    /**
     * get lowest denomination amount
     * return number: 5056 for AUD 50.56  |  51 for JPY 51
     */
    public function getLowestDenominationAmount($amount, $currency)
    {
        $numberOfDigit = $this->getNumberOfDigitsAfterDecimal($currency);
        if ($numberOfDigit === null) {
            return null;
        }
        return round($amount * pow(10, $numberOfDigit));
    }

    /**
     * format amount based on currency
     * return string: "AUD 50.56"  |  "JPY 51"
     */
    public function formatAmountCurrency($amount, $currency)
    {
        $numberOfDigit = $this->getNumberOfDigitsAfterDecimal($currency);
        if ($numberOfDigit === null) {
            return null;
        }
        return $currency . ' ' . number_format(round($amount, $numberOfDigit), $numberOfDigit);
    }

    /**
     * get number of digits after decimal
     * return number: 2 for AUD, 0 for JPY
     */
    public function getNumberOfDigitsAfterDecimal($currency)
    {
        switch (strtoupper($currency)) {
            case 'BHD':
            case 'IQD':
            case 'JOD':
            case 'KWD':
            case 'LYD':
            case 'OMR':
            case 'TND':
                return 3;
            case 'AED':
            case 'AFN':
            case 'ALL':
            case 'AMD':
            case 'ANG':
            case 'AOA':
            case 'ARS':
            case 'AUD':
            case 'AWG':
            case 'AZN':
            case 'BAM':
            case 'BBD':
            case 'BDT':
            case 'BGN':
            case 'BMD':
            case 'BND':
            case 'BOB':
            case 'BRL':
            case 'BSD':
            case 'BTN':
            case 'BWP':
            case 'BZD':
            case 'CAD':
            case 'CDF':
            case 'CFA':
            case 'CFP':
            case 'CHF':
            case 'CNY':
            case 'COP':
            case 'CRC':
            case 'CUP':
            case 'CZK':
            case 'DKK':
            case 'DOP':
            case 'DZD':
            case 'ECS':
            case 'EGP':
            case 'ERN':
            case 'ETB':
            case 'EUR':
            case 'FJD':
            case 'FKP':
            case 'GBP':
            case 'GEL':
            case 'GGP':
            case 'GHS':
            case 'GIP':
            case 'GMD':
            case 'GWP':
            case 'GYD':
            case 'HKD':
            case 'HNL':
            case 'HRK':
            case 'HTG':
            case 'HUF':
            case 'IDR':
            case 'ILS':
            case 'INR':
            case 'IRR':
            case 'JMD':
            case 'KES':
            case 'KGS':
            case 'KHR':
            case 'KPW':
            case 'KYD':
            case 'KZT':
            case 'LAK':
            case 'LBP':
            case 'LKR':
            case 'LRD':
            case 'LSL':
            case 'LTL':
            case 'LVL':
            case 'MAD':
            case 'MDL':
            case 'MGF':
            case 'MKD':
            case 'MMK':
            case 'MNT':
            case 'MOP':
            case 'MRO':
            case 'MUR':
            case 'MVR':
            case 'MWK':
            case 'MXN':
            case 'MYR':
            case 'MZN':
            case 'NAD':
            case 'NGN':
            case 'NIO':
            case 'NOK':
            case 'NPR':
            case 'NZD':
            case 'PAB':
            case 'PEN':
            case 'PGK':
            case 'PHP':
            case 'PKR':
            case 'PLN':
            case 'QAR':
            case 'QTQ':
            case 'RON':
            case 'RSD':
            case 'RUB':
            case 'SAR':
            case 'SBD':
            case 'SCR':
            case 'SDG':
            case 'SEK':
            case 'SGD':
            case 'SHP':
            case 'SLL':
            case 'SOS':
            case 'SRD':
            case 'SSP':
            case 'STD':
            case 'SVC':
            case 'SYP':
            case 'SZL':
            case 'THB':
            case 'TJS':
            case 'TMT':
            case 'TOP':
            case 'TRY':
            case 'TTD':
            case 'TWD':
            case 'TZS':
            case 'UAH':
            case 'USD':
            case 'UYU':
            case 'UZS':
            case 'VEF':
            case 'WST':
            case 'XCD':
            case 'YER':
            case 'ZAR':
            case 'ZMW':
            case 'ZWD':
                return 2;
            case 'BIF':
            case 'BYR':
            case 'CLP':
            case 'CVE':
            case 'DJF':
            case 'GNF':
            case 'ISK':
            case 'JPY':
            case 'KMF':
            case 'KRW':
            case 'PYG':
            case 'RWF':
            case 'UGX':
            case 'VND':
            case 'VUV':
            case 'XAF':
            case 'XOF':
            case 'XPF':
                return 0;
            default:
                return null; //return null if currency code not found
        }
    }
}
