<?php
return [
    'app_id'            	=> env('WECHAT_MCH_APP_ID', '子商户的appid'),
    'token'             	=> env('WECHAT_MCH_TOKEN', '子商户的公众号token'),
    'encoding_aes_key'  	=> env('WECHAT_MCH_ENCODING_AES_KEY'),
    'secret'            	=> env('WECHAT_MCH_SECRET'),
    //哪些Url需要取得oauth_userinfo的权限
    'oauth_userinfo_paths'	=> [ 
        'order/list',
    ],
    'mch_id'				=> env('WECHAT_MCH_MCH_ID', '子商户的商户号'),
    'merchant_app_id'		=> env('WECHAT_MCH_MERCHANT_APP_ID', '服务商的appid'),
    'merchant_mch_id'		=> env('WECHAT_MCH_MERCHANT_MCH_ID', '服务商的商户号'),
    //微信商户平台(pay.weixin.qq.com)-->账户设置-->API安全-->密钥设置
    'merchant_payment_key'  => env('WECHAT_MCH_MERCHANT_PAYMENT_KEY', '商户payment key'),
    'merchant_sslcert'      => env('WECHAT_MCH_MERCHANT_PAYMENT_CERT_PATH'),
    'merchant_sslkey'       => env('WECHAT_MCH_MERCHANT_PAYMENT_KEY_PATH'),
    'split_sslcert'         => env('WECHAT_MCH_SPLIT_PAYMENT_CERT_PATH', '用于企业付款的支付证书'),
    'split_sslkey'          => env('WECHAT_MCH_SPLIT_PAYMENT_KEY_PATH', '用于企业付款的支付证书Key'),
    'split_payment_key'     => env('WECHAT_MCH_SPLIT_PAYMENT_KEY', '用于企业付款的payment key'),
    'split_app_id'          => env('WECHAT_MCH_SPLIT_APP_ID', '用于企业付款的app id')
];