<?php

namespace Hulucat\WechatMch;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client as HttpClient;

class CorpServiceProvider extends ServiceProvider
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
        //
        include __DIR__.'/routes.php';
        $this->app->make('Hulucat\WechatMch\WechatController');
        $this->app->singleton('WechatApi', function($app){
        	return new WechatApi(new HttpClient());
        });
    }
}
