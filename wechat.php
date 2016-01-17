<?php
return [
    'app_id' => env('WECHAT_APPID', ''), // 必填
    'secret' => env('WECHAT_SECRET', ''), // 必填
    'mchid' => '',
    'key' => '',
    //下面两个如果光使用微信支付用不上
    'token' => env('WECHAT_TOKEN', ''),  // 必填
    'encoding_key' => env('WECHAT_ENCODING_KEY', ''), // 加密模式需要
];
