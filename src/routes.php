<?php
Route::group(['middleware' => ['web']], function () {

	//消息回调
	Route::any('wechat/home', 'Hulucat\WechatMch\WechatController@home');
});