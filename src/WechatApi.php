<?php
namespace Hulucat\WechatMch;
use Log;
use Cache;

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

    /**
    *
    */
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
        $utils = app('WechatUtils');
        $url = env("APP_URL");
        $url .= $_SERVER['REQUEST_URI'];
        if($_SERVER['QUERY_STRING']){
            $url .= '?'.$_SERVER['QUERY_STRING'];
        }
        $nonceStr = $utils->getNonceStr();
        $timestamp = time();
        $ticket = $this->getJsApiTicket();
        Log::debug("WechatMch: making jsapi params", [
            'jsapi_ticket'  => $ticket,
            'nonceStr'      => $nonceStr,
            'timestamp'     => $timestamp,
            'url'           => $url
        ]);
        $dict = [
            'jsapi_ticket'  => $ticket,
            'noncestr'      => $nonceStr,
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
        $debug = $debug?'true':'false';
        $rt = "{debug: $debug, timestamp: $timestamp, nonceStr: ";
        $rt .= '"'.$nonceStr.'"';
        if(config('wechat_mch.merchant_app_id')){
            $rt .= ',appId:"'.config('wechat_mch.merchant_app_id').'"';    
        }else{
            $rt .= ',appId:"'.config('wechat_mch.app_id').'"';    
        }
        
        $rt .= ',signature:"'.$sign.'"';
        $rt .= ',jsApiList:[';
        foreach ($jsApiList as $i=>$a){
            if($i>0){
                $rt .= ',';
            }
            $rt .= "'$a'";
        }
        $rt .= ']}';
        return $rt;
    }

    protected function getJsApiTicket(){
        $ac = $this->getAccessToken();
        $cacheKey = 'JS_API_TICKET';
        $ticket = Cache::get($cacheKey);
        if($ticket){
            //检查这个ticket对应的access token是不是过期了
            $oldAccessToken = Cache::get('WECHAT_MCH_'.md5($ticket));
            if($oldAccessToken == $ac){
                return $ticket;
            }
        }
        $utils = new Utils();
        $body = $utils->httpGet('https://api.weixin.qq.com/cgi-bin/ticket/getticket', [
            'access_token'  => $ac,
            'type'          => 'jsapi',
        ]);
        $rt = json_decode($body);
        if($rt->errcode==0 && property_exists($rt, 'ticket')){
            $ticket = $rt->ticket;
            Cache::put($cacheKey, $ticket, 100);
            Cache::put('WECHAT_MCH_'.md5($ticket), $ac);
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
                $rt->nickname = $utils->encodeUserText($rt->nickname);
            }
            return $rt;
        }else{
            return null;
        }
    }

    /**解析微信消息,字段内容见微信文档-消息管理
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
        $from = strval($postObj->FromUserName);
        $to = strval($postObj->ToUserName);
        $content = trim(strval($postObj->Content));
        $type = strval($postObj->MsgType);
        $rt = ['content'=>$content, 'type'=>$type, 'from'=>$from, 'to'=>$to];
        if(property_exists($postObj, 'Event')){
            $rt['event'] = strval($postObj->Event);
        }
        if(property_exists($postObj, 'EventKey')){
            $rt['eventKey'] = strval($postObj->EventKey);
        }
        if(property_exists($postObj, 'Latitude')){
            $rt['lat'] = floatval($postObj->Latitude);
            $rt['lng'] = floatval($postObj->Longitude);
        }
        return $rt;
    }

    /**上传素材
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1444738726&token=&lang=zh_CN
     * @param $type
     * @param $media
     */
    public function uploadMedia($type, $media){
        $multipart = [
            [
                'name'     => md5($media),
                'contents' => md5($media),
                'filename' => $media,
                'headers'  => [
                    'X-Foo' => 'this is an extra header to include'
                ]
            ]
        ];
        $utils = new Utils();
        $url = sprintf('https://api.weixin.qq.com/cgi-bin/media/upload?access_token=%s&type=%s',
            $this->getAccessToken(), $type);
        $response = $utils->httpPostMultipart($url, $multipart);
        if($response->getStatusCode()==200){
            return json_decode($response->getBody());
        }else{
            return null;
        }
    }

    public function replyNewsMsg($from, $to, $articles){
        $textTpl = "<xml>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <ArticleCount><![CDATA[%d]]></ArticleCount>
                        <Articles>%s</Articles>
                    </xml>";
        $articlesText = '';
        $articlesTpl = "
            <item>
                <Title><![CDATA[%s]]></Title>
                <Description><![CDATA[%s]]></Description>
                <PicUrl><![CDATA[%s]]></PicUrl>
                <Url><![CDATA[%s]]></Url>
            </item>
        ";
        foreach ($articles as $article){
            $articlesText .= sprintf($articlesTpl, $article->title, $article->description,
                $article->picurl, $article->url);
        }
        return sprintf($textTpl, $from, $to, time(), 'news', sizeof($articles), $articlesText);
    }

    public function replyImageMsg($from, $to, $imageUrl){
        $media = $this->uploadMedia('image', $imageUrl);
        if($media){
            //$media['type'], $media['media_id']
            $textTpl = "<xml>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Image>
                            <MediaId>
                                <![CDATA[%s]]>
                            </MediaId>
                        </Image>
                        <FuncFlag>0</FuncFlag>
                    </xml>";
            return sprintf($textTpl, $from, $to, time(), 'image', $media['media_id']);
        }
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

    /**发送客服消息
     * @param $to
     * @param $articles
     * @return bool
     */
    public function sendNews($to, $articles){
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=";
        $url .= $this->getAccessToken();
        $utils = app('WechatUtils');
        $rt = $utils->httpPost($url, [
            'touser'    => $to,
            'msgtype'   => 'news',
            'news'  => [
                'articles'  => $articles,
            ]
        ]);
        if($rt->getStatusCode()==200){
            Log::debug("WechatMch send news", [
                'to'        => $to,
                'articles'  => $articles,
                'result'    => strval($rt->getBody())
            ]);
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return true;
            }
        }else{
            Log::error("WechatMch send news failed", [
                'to'        => $to,
                'articles'  => $articles,
                'status'    => $rt->getStatusCode()
            ]);
        }
        return false;
    }

    /**
    * 企业付款
    * @param $params, array，包含以下字段：
    *   device_info 微信支付分配的终端设备号，非必填
    *   partner_trade_no 商户订单号
    *   openid 商户appid下，某用户的openid
    *   check_name 
    *       NO_CHECK：不校验真实姓名 
            FORCE_CHECK：强校验真实姓名（未实名认证的用户会校验失败，无法转账） 
            OPTION_CHECK：针对已实名认证的用户才校验真实姓名（未实名认证用户不校验，可以转账成功）
    *   re_user_name 收款用户真实姓名。如果check_name设置为FORCE_CHECK或OPTION_CHECK，则必填用户真实姓名
    *   amount 企业付款金额，单位为分
    *   desc 企业付款操作说明信息。必填。
    * @return 付款结果，参考微信文档付款结果 https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_2
    */
    public function transfer($params)
    {
        $utils = app('WechatUtils');
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        //sign
        $params['mch_appid'] = config('wechat_mch.split_app_id');
        $params['mchid'] = config('wechat_mch.split_mch_id');
        $params['nonce_str'] = $utils->getNonceStr();
        $params['spbill_create_ip'] = config('wechat_mch.local_ip');
        $params['sign'] = $utils->sign($params, config('wechat_mch.split_payment_key'));
        $xml = $utils->toXml($params);
        $resultXml = $utils->postXml($xml, $url, true, config('wechat_mch.split_sslcert'), 
            config('wechat_mch.split_sslkey'));
        $result = $utils->fromXml($resultXml);
        Log::debug("WechatMch transfer result: ".json_encode($result));
        return $result;
    }    
}
