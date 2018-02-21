<?php
/**
 * Liqpay Payment Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category        LiqPay
 * @package         liqpay/liqpay
 * @version         3.0
 * @author          Liqpay
 * @copyright       Copyright (c) 2014 Liqpay
 * @license         http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * EXTENSION INFORMATION
 *
 * LIQPAY API       https://www.liqpay.com/ru/doc
 *
 */
/**
 * Payment method liqpay process
 *
 * @author      Liqpay <support@liqpay.com>
 */
class LiqPay
{
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_RUB = 'RUB';
    const CURRENCY_RUR = 'RUR';
    private $_api_url = 'https://www.liqpay.com/api/';
    private $_checkout_url = 'https://www.liqpay.com/api/3/checkout';
    protected $_supportedCurrencies = array(
        self::CURRENCY_EUR,
        self::CURRENCY_USD,
        self::CURRENCY_UAH,
        self::CURRENCY_RUB,
        self::CURRENCY_RUR,
    );
    private $_public_key;
    private $_private_key;
    private $_server_response_code = null;
    /**
     * Constructor.
     *
     * @param string $public_key
     * @param string $private_key
     *
     * @throws InvalidArgumentException
     */
    public function __construct($public_key, $private_key)
    {
        if (empty($public_key)) {
            throw new InvalidArgumentException('public_key is empty');
        }
        if (empty($private_key)) {
            throw new InvalidArgumentException('private_key is empty');
        }
        $this->_public_key = $public_key;
        $this->_private_key = $private_key;
    }
    /**
     * Call API
     *
     * @param string $path
     * @param array $params
     *
     * @return string
     */
    public function api($path, $params = array())
    {
        if (!isset($params['version'])) {
            throw new InvalidArgumentException('version is null');
        }
        $url         = $this->_api_url . $path;
        $public_key  = $this->_public_key;
        $private_key = $this->_private_key;
        $data        = base64_encode(json_encode(array_merge(compact('public_key'), $params)));
        $signature   = base64_encode(sha1($private_key.$data.$private_key, 1));
        $postfields  = http_build_query(array(
           'data'  => $data,
           'signature' => $signature
        ));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $server_output = curl_exec($ch);
        $this->_server_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return json_decode($server_output);
    }
    /**
     * Return last api response http code
     * @return string|null
     */
    public function get_response_code()
    {
        return $this->_server_response_code;
    }
    /**
     * cnb_form
     *
     * @param array $params
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function cnb_form($params)
    {
        $language = 'ru';
        if (isset($params['language']) && $params['language'] == 'en') {
            $language = 'en';
        }
        $params    = $this->cnb_params($params);
        $data      = base64_encode(json_encode($params));
        $signature = $this->cnb_signature($params);
        
        return sprintf('
            <form method="POST" action="%s" accept-charset="utf-8">
                %s
                %s
                <button style="border: none !important; 
                display:inline-block !important;
                text-align: center !important;
                padding: 7px 20px !important;
                color: #fff !important; 
                font-size:16px !important; 
                font-weight: 600 !important; 
                font-family:OpenSans, sans-serif; 
                cursor: pointer !important; 
                border-radius: 2px !important;
                background: rgb(122,183,43) !important;">
                    <img src="https://static.liqpay.com/buttons/logo-small.png" name="btn_text"
                        style="margin-right: 7px !important; 
                        vertical-align: middle !important;"/>
                <span style="vertical-align:middle !important">Перейти до оплати</span>
                </button>
            </form>
            ',
            $this->_checkout_url,
            sprintf('<input type="hidden" name="%s" value="%s" />', 'data', $data),
            sprintf('<input type="hidden" name="%s" value="%s" />', 'signature', $signature),
            $language
        );
    }
    /**
     * cnb_signature
     *
     * @param array $params
     *
     * @return string
     */
    public function cnb_signature($params)
    {
        $params      = $this->cnb_params($params);
        $private_key = $this->_private_key;
        $json      = base64_encode(json_encode($params));
        $signature = $this->str_to_sign($private_key . $json . $private_key);
        return $signature;
    }
    /**
     * cnb_params
     *
     * @param array $params
     *
     * @return array $params
     */
    private function cnb_params($params)
    {
        $params['public_key'] = $this->_public_key;
        if (!isset($params['version'])) {
            throw new InvalidArgumentException('version is null');
        }
        if (!isset($params['amount'])) {
            throw new InvalidArgumentException('amount is null');
        }
        if (!isset($params['currency'])) {
            throw new InvalidArgumentException('currency is null');
        }
        if (!in_array($params['currency'], $this->_supportedCurrencies)) {
            throw new InvalidArgumentException('currency is not supported');
        }
        if ($params['currency'] == self::CURRENCY_RUR) {
            $params['currency'] = self::CURRENCY_RUB;
        }
        if (!isset($params['description'])) {
            throw new InvalidArgumentException('description is null');
        }
        return $params;
    }
    /**
     * str_to_sign
     *
     * @param string $str
     *
     * @return string
     */
    public function str_to_sign($str)
    {
        $signature = base64_encode(sha1($str, 1));
        return $signature;
    }
    //-----------------------------------------------------------------------------------------------
    public function LiqPayRequest(){

        $liqpay = new LiqPay('i54276112930', "R15mltUEp2UkL7pPI3FebTV10V0nsZ7w3DPoehz9");
            $html = $liqpay->cnb_form(array(
            'action'         => 'pay',
            'amount'         => Base::$aRequest['amount'],
            'currency'       => 'UAH',
            'description'    => 'Замовлення #'.Base::$aRequest['order_id'],
            'order_id'       => 'order_id_1',
            'order'          =>  Base::$aRequest['order_id'],
            'version'        => '3',
            ));
        Base::$tpl->assign('html',$html);
    }
    //------------------------------------------------------------------------------------------------
}