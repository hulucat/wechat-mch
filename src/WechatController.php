<?php
namespace Hulucat\WechatMch;

use App\Http\Controllers\Controller;
use Hulucat\WechatMch\WechatApi;
use Illuminate\Http\Request;

class WechatController extends Controller
{
	public function home(Request $request, WechatApi $api)
	{
        if($api->checkSignature($request->input('signature'), $request->input('timestamp'),
            $request->input('nonce'))){
            $s = $request->input('echostr');
            Log::debug("Echostr: $s");
            Log::debug("Access token: ".$api->getAccessToken());
            return $s;
        }else{
            return 'ehh';
        }
	}

	public function oauth2(Request $request, CorpApi $corp){
		$code = $request->input('code');
		$back = $request->input('back');
		\Log::debug("code: $code");
		$uid = $corp->getUserId($code);
		\Log::debug("uid: $uid");
		$request->session()->set('corp_uid', $uid);
		header("Location: $back", true, 302);
	}
}