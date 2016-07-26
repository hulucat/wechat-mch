<?php
Route::group(['middleware' => ['web']], function () {

	//消息回调
	Route::get('wechat/home', 'Hulucat\WechatMch\WechatController@home');
});