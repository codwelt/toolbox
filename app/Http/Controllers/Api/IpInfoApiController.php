<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IpInfoApiController extends Controller
{
    public function show(Request $request)
    {
        $ip = $request->headers->get('x-forwarded-for') ? explode(',', $request->headers->get('x-forwarded-for'))[0] : $request->ip();
        $ip = trim($ip);

        $info = [
            'ip' => $ip,
            'is_ipv6' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'is_private' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false,
            'is_reserved' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false,
            'host' => @gethostbyaddr($ip) ?: null,
            'user_agent' => $request->userAgent(),
            'headers' => [
                'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
                'x_real_ip' => $request->headers->get('x-real-ip'),
                'accept_language' => $request->headers->get('accept-language'),
            ],
        ];

        return response()->json($info);
    }
}
