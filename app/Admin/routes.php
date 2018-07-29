<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('/', 'HomeController@index');
    $router->resource('users', 'UsersController');
    $router->resource('products', 'ProductsController');
    $router->get('orders', 'OrdersController@index')->name('admin.orders.index');
    //下面的这订单详情页是自己实现的，自己的模板和方法
    $router->get('orders/{order}', 'OrdersController@show')->name('admin.orders.show');
    //订单发货
    $router->post('orders/{order}/ship', 'OrdersController@ship')->name('admin.orders.ship');
    //拒绝退款
    $router->post('orders/{order}/refund', 'OrdersController@handleRefund')->name('admin.orders.handle_refund');
    //优惠券列表
    $router->get('coupon_codes', 'CouponCodesController@index');
});
