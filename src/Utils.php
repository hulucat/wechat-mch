<?php
namespace Hulucat\WechatMch;

use GuzzleHttp\Client as HttpClient;
use Cache;
use Log;

class Utils{
    protected $http;

    public function __construct(){
        $this->http = new HttpClient();
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    public function httpGet($url, Array $query){
        Log::debug("WechatMch get: ", [
            'Request: ' => $url,
            'Params: ' => $query,
        ]);
        $response = $this->http->request('GET', $url, ['query' => $query]);
        Log::debug('WechatMch http get:', [
            'Status'    => $response->getStatusCode(),
            'Reason'    => $response->getReasonPhrase(),
            'Headers'   => $response->getHeaders(),
            'Body'      => strval($response->getBody()),
        ]);
        return $response->getBody();
    }

    /**
     * @param $url
     * @param $body
     * @return mixed
     */
    public function httpPost($url, $body){
        $body = json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        Log::debug("WechatMch post: ", [
            'request: ' => $url,
            'body: ' => $body,
        ]);
        $response = $this->http->request('POST', $url, [
            'body'  => $body
        ]);
        Log::debug('WechatMch http post:', [
            'Status'    => $response->getStatusCode(),
            'Reason'    => $response->getReasonPhrase(),
            'Headers'   => $response->getHeaders(),
            'Body'      => strval($response->getBody()),
        ]);
        return $response;
    }

    public function httpPostMultipart($url, $multipart){
        Log::debug("WechatMch post multipart: ", [
            'request'     => $url,
            'multipart'   => json_encode($multipart),
        ]);
        $response = $this->http->request('POST', $url, [
            'multipart' => $multipart
        ]);
        Log::debug("WechatMch http post multipart: ", [
            'Status'    => $response->getStatusCode(),
            'Reason'    => $response->getReasonPhrase(),
            'Headers'   => $response->getHeaders(),
            'Body'      => strval($response->getBody()),
        ]);
        return $response;
    }

    /**
     * 对用户昵称、消息之类的文字进行编码，以便能够保存到数据库
     */
    public function encodeUserText($str){
        if(!is_string($str)){
            return $str;
        }
        if(!$str || $str=='undefined'){
            return '';
        }
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){
            return addslashes($str[0]);
        },$text); //将emoji的unicode留下，其他不动
        return json_decode($text);
    }

    /**
     * 对数据库取出的用户昵称、消息进行解码，以便显示
     */
    public function decodeUserText($str){
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i',function($str){
            return '\\';
        },$text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }

        /** 产生签名
     * @param $params
     * @param $key
     * @return mixed
     */
    public function sign($params){
        $dict = array();
        foreach ($params as $key=>$value){
            if($value!=null && $value!=''){
                $dict[$key] = $value;
            }
        }
        ksort($dict);
        $dict['key'] = config('wechat_corp.mch_payment_key');
        $str = urldecode(http_build_query($dict));
        return strtoupper(md5($str));
    }

    public function fromXml($xml){
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    public function toXml($dict)
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

    public function postXml($xml, $url, $useCert=false, $sslcert=null, $sslkey=null, $timeout=30)
    {
        Log::debug("WechatMch post xml to $url: \n".$xml);
        if($sslcert==null){
            $sslcert = config('wechat_mch.merchant_sslcert');
        }
        if($sslkey==null){
            $sslkey = config('wechat_mch.merchant_sslkey');
        }
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
            curl_setopt($ch,CURLOPT_SSLCERT, $sslcert);
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, $sslkey);
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
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            Log::error("Error post xml", [
                'url'   => $url,
                'xml'   => $xml,
                'errno' => $errno,
                'error' => $error
            ]);
            return null;
        }
    }    
}
