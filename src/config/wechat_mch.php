<?php
return [
    'app_id'            	=> env('WECHAT_MCH_APP_ID'),
    'token'             	=> env('WECHAT_MCH_TOKEN'),
    'encoding_aes_key'  	=> env('WECHAT_MCH_ENCODING_AES_KEY'),
    'secret'            	=> env('WECHAT_MCH_SECRET'),
    'oauth_userinfo_paths'	=> [
        'order/list',
    ]
];