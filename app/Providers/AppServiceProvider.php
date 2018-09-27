<?php

namespace App\Providers;

use App\Exceptions\InvalidRequestException;
use App\Models\ProductSku;
use App\Models\User;
use App\Observer\ProductSkuObserver;
use App\Observer\UserObserver;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Monolog\Logger;
use Yansongda\Pay\Pay;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        ProductSku::observe(ProductSkuObserver::class);
        User::observe(UserObserver::class);
        if(!strtoupper(substr(PHP_OS,0,3) == 'WIN') && class_exists('\Horizon')) {
            \Horizon::auth(function ($request) {
                if(Admin::user() && Admin::user()->isAdministrator()){
                    return true;
                }
                throw new InvalidRequestException('老哥，这个就别看了吧');
                return false;
            });
        }

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 往服务容器中注入一个名为 alipay 的单例对象
        $this->app->singleton('alipay', function () {
            $config = config('pay.alipay');
            $config['notify_url'] = route($config['notify_url_route_name']);
            $config['return_url'] = route($config['return_url_route_name']);
            // 判断当前项目运行环境是否为线上环境
            if (app()->environment() !== 'production') {
                $config['mode']         = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个支付宝支付对象
            return Pay::alipay($config);
        });

        $this->app->singleton('wechat_pay', function () {
            $config = config('pay.wechat');
            $config['notify_url'] = route($config['notify_url_route_name']);
            if (app()->environment() !== 'production') {
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个微信支付对象
            return Pay::wechat($config);
        });
    }
}
