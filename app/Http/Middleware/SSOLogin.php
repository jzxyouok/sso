<?php

namespace App\Http\Middleware;

use App\Models\TokenRecord;
use App\Models\UserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;

class SSOLogin
{

    protected function redirect(Request $request, $code)
    {
        return view('index.redirect', [
            'redirect_uri' => $request->get('redirect_uri', ''),
            'code' => $code,
        ]);
    }

    protected function checkSession(Request $request)
    {
        if ($request->session()->get('code')
            && $openid = $request->session()->get('openid')) {
            $code = md5(uniqid('lingyin-code'));
            Cache::put("lingyin:openid:{$code}", $openid, 3600);
            $this->redirect($request, $code);
        }
    }

    protected function checkCookie(Request $request)
    {
        $token = Cookie::get('lingyin-token');
        $ttl = Cookie::get('lingyin-ttl', '');
        $sign = Cookie::get('lingyin-sign', '');
        if ($token && $sign == md5($token . $ttl . $request->userAgent() . $request->ip())) {

            $record = (new TokenRecord())->getExpireRecordByToken($token);

            if ($record && $record->openid && $userInfo = (new UserInfo())->getUserInfoByOpenid($record->openid)) {

                $code = md5(uniqid('lingyin-code'));
                Cache::put("lingyin:openid:{$code}", $record->openid, 3600);

                $request->session()->put('code', $userInfo->code);
                $request->session()->put('code', $record->openid);

                $this->redirect($request, $code);
            }
        }
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $this->checkSession($request);

        $this->checkCookie($request);

        return $next($request);
    }
}
