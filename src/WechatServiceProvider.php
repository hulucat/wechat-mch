<?php

namespace Hulucat\WechatMch;

use Illuminate\Support\ServiceProvider;


class WechatServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //publish config
        $this->publishes([
        		__DIR__.'/config/wechat_mch.php' => config_path('wechat_mch.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('WechatApi', function($app){
        	return new WechatApi();
        });
        $this->app->singleton('WechatPayment', function($app){
            return new WechatPayment();
        });
        $this->app->singleton('WechatCard', function($app){
            return new WechatCard();
        });
        $this->app->singleton('WechatUtils', function($app){
            return new Utils();
        });
    }
}
