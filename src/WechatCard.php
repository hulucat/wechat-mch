<?php
namespace Hulucat\WechatMch;

use Cache;
use Log;

class WechatCard
{
    /**
     * @param $code 单张卡券的唯一标准
     * @param $cardId 卡券ID代表一类卡券。自定义code卡券必填
     * @param bool $checkConsume 是否校验code核销状态，填入true和false时的code异常状态返回数据不同。
     * @return null 参考微信文档
     */
    public function checkCode($code, $cardId=null, $checkConsume=true)
    {
        $wechatApi = app('WechatApi');
        $accessToken = $wechatApi->getAccessToken();
        $url = "https://api.weixin.qq.com/card/code/get?access_token=$accessToken";
        $utils = app('WechatUtils');
        $rt = $utils->httpPost($url, [
            'code'          => $code,
            'card_id'       => $cardId,
            'check_consume' => $checkConsume
        ]);
        if($rt->getStatusCode()==200){
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return $body;
            }else{
                Log::error("Error check code", [
                    'code'          => $code,
                    'cardId'        => $cardId,
                    'checkConsume'  => $checkConsume,
                    'errcode'       => $body->errcode,
                    'errmsg'        => $body->errmsg
                ]);
                return null;
            }
        }else{
            return null;
        }
    }

    /**核销Code接口
     * @param $code 需核销的Code码
     * @param null $cardId 卡券ID。创建卡券时use_custom_code填写true时必填。非自定义Code不必填写
     * @return null 参考微信文档
     */
    public function consume($code, $cardId=null)
    {
        $wechatApi = app('WechatApi');
        $accessToken = $wechatApi->getAccessToken();
        $url = "https://api.weixin.qq.com/card/code/consume?access_token=$accessToken";
        $utils = app('WechatUtils');
        $rt = $utils->httpPost($url, [
            'code'          => $code,
            'card_id'       => $cardId
        ]);
        if($rt->getStatusCode()==200){
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return $body;
            }else{
                Log::error("Error consume code", [
                    'code'      => $code,
                    'cardId'    => $cardId,
                    'errcode'   => $body->errcode,
                    'errmsg'    => $body->errmsg
                ]);
                return null;
            }
        }else{
            return null;
        }
    }

    /**Code解码接口
     * @param $encryptedCode 经过加密的Code码
     * @return null
     */
    public function decryptCode($encryptedCode)
    {
        $wechatApi = app('WechatApi');
        $accessToken = $wechatApi->getAccessToken();
        $url = "https://api.weixin.qq.com/card/code/decrypt?access_token=$accessToken";
        $utils = app('WechatUtils');
        $rt = $utils->httpPost($url, [
            'encrypt_code'   => $encryptedCode
        ]);
        if($rt->getStatusCode()==200){
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return $body;
            }else{
                Log::error("Error decrypt code", [
                    'errcode'   => $body->errcode,
                    'errmsg'    => $body->errmsg
                ]);
                return null;
            }
        }else{
            return null;
        }
    }

    /**获取卡券详情
    * @param $cardId 卡券ID代表一类卡券。
    */
    public function getCardDetail($cardId)
    {
        $wechatApi = app('WechatApi');
        $accessToken = $wechatApi->getAccessToken();
        $url = "https://api.weixin.qq.com/card/get?access_token=$accessToken";
        $utils = app('WechatUtils');
        $rt = $utils->httpPost($url, [
            'card_id'   => $cardId
        ]);
        if($rt->getStatusCode()==200){
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return $body;
            }else{
                Log::error("Error get card detail", [
                    'errcode'   => $body->errcode,
                    'errmsg'    => $body->errmsg
                ]);
                return null;
            }
        }else{
            return null;
        }
    }

    /**获取用户拥有的所有卡券
     * @param $openId user open id
     * @param null $cardId 卡券ID。不填写时默认查询当前appid下的卡券。
     * @return null 参考微信api返回
     */
    public function getCardList($openId, $cardId=null)
    {
        $wechatApi = app('WechatApi');
        $accessToken = $wechatApi->getAccessToken();
        $url = "https://api.weixin.qq.com/card/user/getcardlist?access_token=$accessToken";
        $utils = app('WechatUtils');
        $rt = $utils->httpPost($url, [
            'openid'    => $openId,
            'card_id'   => $cardId
        ]);
        if($rt->getStatusCode()==200){
            $body = json_decode(strval($rt->getBody()));
            if($body->errcode==0){
                return $body;
            }else{
                Log::error("Error get card list", [
                    'errcode'   => $body->errcode,
                    'errmsg'    => $body->errmsg
                ]);
                return null;
            }
        }else{
            return null;
        }
    }

}
