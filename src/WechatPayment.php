<?php
namespace Hulucat\WechatMch;

use Cache;
use Log;

class WechatPayment {

    /**统一下单接口
     * @param $params array[string: string], key和value对应微信文档中参数列表; 包含:
     * device_info(可选)
     * body
     * detail(可选)
     * attach(可选)
     * out_trade_no
     * fee_type(可选)
     * total_fee
     * spbill_create_ip
     * time_start(可选)
     * time_expire(可选)
     * goods_tag(可选)
     * notify_url
     * product_id
     * limit_pay(可选)
     * openid
     * sub_openid(openid, sub_openid二者必填其一)
     * @return prepay_id or null
     */
	public function prepare($params){
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $params['appid'] = config('wechat_mch.merchant_app_id');
        $params['mch_id'] = config('wechat_mch.merchant_mch_id');
        $params['sub_appid'] = config('wechat_mch.app_id');
        $params['sub_mch_id'] = config('wechat_mch.mch_id');
        $utils = new Utils();
        $params['nonce_str'] = $utils->getNonceStr();
        $params['trade_type'] = 'JSAPI';
        $params['sign'] = $this->sign($params);
        $xml = $this->toXml($params);
        $result = $this->fromXml($this->postXml($xml, $url));
        Log::debug("WechatMch unifiedorder result: ".json_encode($result));
        if($result['return_code']=='SUCCESS'){
            if($result['result_code']=='SUCCESS'){
                $time = time();
                $nonceStr = $utils->getNonceStr();
                $package = 'prepay_id='.$result['prepay_id'];
                $paySign = $this->sign([
                    'appId'     => config('wechat_mch.merchant_app_id'),
                    'timeStamp' => $time,
                    'nonceStr'  => $nonceStr,
                    'package'   => $package,
                    'signType'  => 'MD5'
                ]);
                $rt = [
                    'package'   => $package,
                    'timestamp' => $time,
                    'nonceStr'  => $nonceStr,
                    'signType'  => 'MD5',
                    'paySign'   => $paySign
                ];
                return $rt;
            }else{
                Log::error("Error prepare pay", [
                    'result_code'   => $result['result_code'],
                    'err_code'      => $result['err_code'],
                    'err_code_des'  => $result['err_code_des'],
                ]);
                return null;
            }
        }else{
            Log::error("Error prepare pay", [
                'return_code'   => $result['return_code'],
                'return_msg'    => $result['return_msg']
            ]);
            return null;
        }
	}

    /**处理微信支付回调
     * @param $callback 用户自定义处理函数, 形式如function callback($notify), $notify是微信返回的参数数组
     * @return null
     */
    public function handleNotify($callback){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $data = $this->fromXml($xml);
        Log::info("WechatMch payment notify: ".json_encode($data));
        $rt = null;
        if($data['return_code']=='SUCCESS'){
            $dict = array();
            foreach ($data as $key=>$value){
                if($key!='sign') {
                    $dict[$key] = $value;
                }
            }
            $sign = $this->sign($dict);
            if($data['sign'] != $sign){
                Log::error("WechatMch payment notify: sign not match", [
                    "input"     => $data['sign'],
                    "should_be" => $sign,
                ]);
                $rt =
                    `<xml>
                        <return_code><![CDATA[FAIL]]></return_code>
                        <return_msg><![CDATA[INVALID SIGN]]></return_msg>
                    </xml>`;
            }else{
                call_user_func($callback, $data);
                $rt =
                    `<xml>
                        <return_code><![CDATA[SUCCESS]]></return_code>
                        <return_msg><![CDATA[OK]]></return_msg>
                    </xml>`;
            }
        }else{
            $rt =
                `<xml>
                    <return_code><![CDATA[FAIL]]></return_code>
                    <return_msg><![CDATA[PARDEN]]></return_msg>
                </xml>`;
        }
        Log::info("WechatMch payment notify output: \n{$rt}");
        return $rt;
    }

    /**退款
     * @param $params [string:string],对应微信文档中的参数,需要传递的参数包括:
     * transaction_id 微信订单号
     * out_trade_no 商户订单号 商户系统内部的订单号, transaction_id、out_trade_no二选一，
     *              如果同时存在优先级：transaction_id> out_trade_no
     * out_refund_no 商户退款单号
     * total_fee
     * refund_fee
     * refund_fee_type 可选
     * op_user_id 可选,操作员帐号, 默认为商户号
     * @return array [string:string],对应微信文档中的结果参数
     */
    public function refund($params){
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        $params['appid'] = config('wechat_mch.merchant_app_id');
        $params['mch_id'] = config('wechat_mch.merchant_mch_id');
        $params['sub_appid'] = config('wechat_mch.app_id');
        $params['sub_mch_id'] = config('wechat_mch.mch_id');
        if(!array_key_exists('op_user_id', $params)){
            $params['op_user_id'] = config('wechat_mch.merchant_mch_id');
        }
        $utils = new Utils();
        $params['nonce_str'] = $utils->getNonceStr();
        $params['sign'] = $this->sign($params);
        $xml = $this->toXml($params);
        $result = $this->fromXml($this->postXml($xml, $url));
        if(!$result || $result['return_code']!='SUCCESS'){
            Log::error("WechatMch refund fail", json_encode($result));
            return $result;
        }else{
            return null;
        }
    }

    /** 产生签名
     * @param $params
     * @param $key
     * @return mixed
     */
    private function sign($params){
        $dict = array();
        foreach ($params as $key=>$value){
            if($value!=null && $value!=''){
                $dict[$key] = $value;
            }
        }
        ksort($dict);
        $dict['key'] = config('wechat_mch.merchant_payment_key');
        $str = urldecode(http_build_query($dict));
        return strtoupper(md5($str));
    }

    private function fromXml($xml){
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    private function toXml($dict)
    {
        $xml = '<xml>';
        foreach ($dict as $key => $val) {
            if (is_numeric($val)){
                $xml .= "<{$key}>{$val}</{$key}>";
            }else{
                $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    private function postXml($xml, $url, $useCert=false, $timeout=30)
    {
        Log::debug("WechatMch post xml to $url: \n".$xml);
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if($useCert == true){
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, config('wechat_mch.merchant_sslcert'));
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, 'wechat_mch.merchant_sslkey');
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            Log::debug("WechatMch post xml result: \n".$data);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            Log::error("Error post xml", [
                'url'   => $url,
                'xml'   => $xml,
                'error' => $error,
            ]);
            return null;
        }
    }

}