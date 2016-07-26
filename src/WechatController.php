<?php
namespace Hulucat\WechatMch;

use App\Http\Controllers\Controller;
use Hulucat\WechatMch\WechatApi;
use Illuminate\Http\Request;
use Log;

class WechatController extends Controller
{
	public function home(Request $request, WechatApi $api)
	{
        if($api->checkSignature($request->input('signature'), $request->input('timestamp'),
            $request->input('nonce'))){
            $dict = $api->parseMsg($GLOBALS["HTTP_RAW_POST_DATA"]);
            Log::debug("WechatMch received message", $dict);
            if($dict){
                switch ($dict['type']){
                    case 'text':
                        $reply = $api->replyTextMsg(
                            $dict['to'],
                            $dict['from'],
                            '欢迎您关注!'
                        );
                        Log::debug("Reply: $reply");
                        return $reply;
                    case 'image':
                        return $api->replyTextMsg(
                            $dict['to'],
                            $dict['from'],
                            '照片好漂亮!'
                        );
                    default:
                        return $api->replyTextMsg(
                            $dict['to'],
                            $dict['from'],
                            '嘎哈?!'
                        );
                }
            }
            return $request->input('echostr');
        }else{
            return 'ehh';
        }
	}

	public function oauth2(Request $request, CorpApi $corp){
		$code = $request->input('code');
		$back = $request->input('back');
		Log::debug("code: $code");
		$uid = $corp->getUserId($code);
		Log::debug("uid: $uid");
		$request->session()->set('corp_uid', $uid);
		header("Location: $back", true, 302);
	}
}