<?php
namespace Hulucat\WechatMch;

use GuzzleHttp\Client as HttpClient;
use Cache;
use Log;

class Article{
    public $title = null;
    public $description = null;
    public $url = null;
    public $picurl = null;

    public function __construct($title, $description, $url, $picurl){
        $this->title = $title;
        $this->description = $description;
        $this->url = $url;
        $this->picurl = $picurl;
    }
}

class WechatApi{
    private $appId = null;
    private $secret = null;
	private $token = null;
	public function __construct(){
        $this->token = config('wechat_mch.token');
        $this->appId = config('wechat_mch.app_id');
        $this->secret = config('wechat_mch.secret');
	}

    public function getAccessToken(){
        $cacheKey = 'WECHAT_MCH_ACCESS_TOKEN';
        $at = Cache::get($cacheKey);
        if($at){
            return $at;
        }else{
            $utils = new Utils();
            $body = $utils->httpGet('https://api.weixin.qq.com/cgi-bin/token', [
                'grant_type' => 'client_credential',
                'appid' => $this->appId,
                'secret'    => $this->secret,
            ]);
            $rt = json_decode($body);
            if(property_exists($rt, 'access_token')){
                $at = $rt->access_token;
                Cache::put($cacheKey, $at, 100);
                return $at;
            }else{
                return null;
            }
        }
    }

    /**生成jsapi config json字符串
     * @param array $jsApiList
     * @param bool $debug
     * @return mixed
     */
    public function getJsApiConfig($jsApiList=[], $debug=false){
        $utils = new Utils();
        $url = array_key_exists('HTTPS', $_SERVER)?'https://' : 'http://';
        $url .= $_SERVER['HTTP_HOST'];
        $url .= $_SERVER['REQUEST_URI'];
        $url .= '?'.$_SERVER['QUERY_STRING'];
        $nonceStr = $utils->getNonceStr();
        $timestamp = time();
        $ticket = $this->getJsApiTicket();
        Log::debug("WechatMch: making jsapi params", [
            'url'   => $url,
        ]);

        $dict = [
            'jsapi_ticket'  => $ticket,
            'nonceStr'      => $nonceStr,
            'timestamp'     => $timestamp,
            'url'           => $url
        ];
        $sign = '';
        foreach ($dict as $key=>$value){
            if($sign){
                $sign .= '&';
            }
            $sign .= "{$key}={$value}";
        }
        $sign = sha1($sign);
        $rt = [
            'debug'     => $debug,
            'timestamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'appId'     => config('wechat_mch.app_id'),
            'signature' => $sign,
            'jsApiList' => $jsApiList
        ];
        return json_encode($rt);
    }

    protected function getJsApiTicket(){
        $cacheKey = 'JS_API_TICKET';
        $ticket = Cache::get($cacheKey);
        if($ticket){
            return $ticket;
        }
        $utils = new Utils();
        $ac = $this->getAccessToken();
        $body = $utils->httpGet('https://api.weixin.qq.com/cgi-bin/ticket/getticket', [
            'access_token'  => $ac,
            'type'          => 'jsapi',
        ]);
        $rt = json_decode($body);
        if($rt['errcode']==0 && array_key_exists('ticket', $rt)){
            $ticket = $rt['ticket'];
            Cache::put($cacheKey, $ticket, 100);
        }
        return $ticket;
    }

    public function getOauth2Redirect($redirectUrl, $scope){
        return sprintf("%s?appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=STATE#wechat_redirect",
            'https://open.weixin.qq.com/connect/oauth2/authorize',
            $this->appId,
            urlencode($redirectUrl),
            $scope
        );
    }

    public function getOauthBasic($code){
        $utils = new Utils();
        $body = $utils->httpGet('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'grant_type'    => 'authorization_code',
            'appid'         => $this->appId,
            'secret'        => $this->secret,
            'code'          => $code,
        ]);
        $rt = json_decode($body);
        if(property_exists($rt, 'access_token')){
            return $rt;
        }else{
            return null;
        }
    }

    public function getOauthUserInfo($oauthBasic){
        $utils = new Utils();
        $body = $utils->httpGet('https://api.weixin.qq.com/sns/userinfo', [
            'access_token'          => $oauthBasic->access_token,
            'openid'                => $oauthBasic->openid,
            'lang'                  => 'zh_CN'
        ]);
        $rt = json_decode($body);
        if(property_exists($rt, 'nickname')){
            return $rt;
        }else{
            return null;
        }
    }

    public function getUserInfo($openid){
        $utils = new Utils();
        $body = $utils->httpGet('https://api.weixin.qq.com/cgi-bin/user/info', [
                'access_token' => $this->getAccessToken(),
                'openid' => $openid,
                'lang'   => 'zh_CN',
        ]);
        $rt = json_decode($body);
        if(property_exists($rt, 'subscribe')){
            if(property_exists($rt, 'nickname')){
                $rt->nickname = $this->encodeUserText($rt->nickname);
            }
            return $rt;
        }else{
            return null;
        }
    }

    /**
     * @param $postStr
     * @return array|null
     */
    public function parseMsg($postStr){
        Log::debug("WechatMch parse row string: $postStr");
        if(!$postStr){
            return null;
        }
        libxml_disable_entity_loader(true);
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $from = $postObj->FromUserName;
        $to = $postObj->ToUserName;
        $content = trim($postObj->Content);
        $type = $postObj->MsgType;
        $rt = ['content'=>$content, 'type'=>$type, 'from'=>$from, 'to'=>$to];
        if(property_exists($postObj, 'Event')){
            $rt['event'] = $postObj->Event;
        }
        if(property_exists($postObj, 'EventKey')){
            $rt['eventKey'] = $postObj->EventKey;
        }
        if(property_exists($postObj, 'Latitude')){
            $rt['lat'] = $postObj->Latitude;
            $rt['lng'] = $postObj->Longitude;
        }
        return $rt;
    }

    public function replyTextMsg($from, $to, $content){
        $textTpl = "<xml>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                    </xml>";
        return sprintf($textTpl, $from, $to, time(), 'text', $content);
    }

    public function checkSignature($signature, $timestamp, $nonce){
        $tmpArr = array($this->token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if($tmpStr == $signature){
            return true;
        }else{
            return false;
        }
    }

    public function newArticle($title, $description, $url, $picurl){
        return new Article($title, $description, $url, $picurl);
    }

    public function sendNews($to, $articles){
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=";
        $url .= $this->getAccessToken();
        Log::debug("WechatMch send news", [
            'to'        => $to,
            'articles'  => $articles,
        ]);
        $utils = new Utils();
        $rt = $utils->httpPost($url, [
            'touser'    => $to,
            'msgtype'   => 'news',
            'news'  => [
                'articles'  => $articles,
            ]
        ]);
        if($rt->getStatusCode()==200){
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return true;
            }
        }
        return false;
    }
}
