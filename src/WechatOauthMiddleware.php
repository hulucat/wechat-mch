<?php

namespace Hulucat\WechatMch;

use Closure;
use Log;
/**
 * Class OAuthAuthenticate
 */
class WechatOauthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $wechat = app('WechatApi');
        if (!session('wechat_mch.oauth_user')) {
            if ($request->has('state') && $request->has('code')) {
                $oauthBasic = $wechat->getOauthBasic($request->input('code'));
                if($oauthBasic){
                    if(strpos($oauthBasic->scope, 'snsapi_userinfo')>=0){
                        $userInfo = $wechat->getOauthUserInfo($oauthBasic);
                        if($userInfo){
                            session(['wechat_mch.oauth_user' => $userInfo]);
                        }else{
                            Log::warning("WechatMch can not get snsapi_userinfo", [
                                'openid'    => $oauthBasic->openid,
                            ]);
                        }
                    }else{
                        session(['wechat_mch.oauth_user' => $oauthBasic]);
                    }
                    return redirect()->to($this->getTargetUrl($request));
                }
            }
            $scopes = 'snsapi_base';
            if(in_array($request->path(), config('wechat_mch.oauth_userinfo_paths'))){
                $scopes = 'snsapi_userinfo';
            }
            Log::debug("ready to redirect", [
                'fullUrl'       => $request->fullUrl(),
                'scopes'        => $scopes,
            ]);
            return redirect($wechat->getOauth2Redirect($request->fullUrl(), $scopes));
        }
        return $next($request);
    }

    /**
     * Build the target business url.
     *
     * @param Request $request
     *
     * @return string
     */
    public function getTargetUrl($request)
    {
        $queries = array_except($request->query(), ['code', 'state']);
        return $request->url().(empty($queries) ? '' : '?'.http_build_query($queries));
    }
}
