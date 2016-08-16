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
            'Request: ' => $url,
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
}
