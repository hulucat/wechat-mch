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
	protected $http;
    private $appId = null;
    private $secret = null;
	private $token = null;
	public function __construct(HttpClient $hc){
		$this->http = $hc;
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
            $body = $this->httpGet('https://api.weixin.qq.com/cgi-bin/token', [
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
        $rt = $this->httpPost($url, [
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

	protected function httpGet($url, Array $query){
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

    /**exception 'InvalidArgumentException' with message 'Passing in the "body" request option
     * as an array to send a POST request has been deprecated.
     * Please use the "form_params" request option to send a application/x-www-form-urlencoded request,
     * or a the "multipart" request option to send a multipart/form-data request.'
     * in /apps/jupiter/service/vendor/guzzlehttp/guzzle/src/Client.php:392
     * @param $url
     * @param $body
     * @return mixed
     */
    protected function httpPost($url, $body){
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
}
