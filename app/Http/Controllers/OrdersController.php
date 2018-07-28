<?php

namespace App\Http\Controllers;

use App\Events\OrderReviewd;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\Request;
use App\Http\Requests\SendReviewRequest;
use App\Models\ProductSku;
use App\Models\UserAddress;
use App\Models\Order;
use App\Services\CartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\SystemException;
use App\Jobs\CloseOrder;
use App\Services\OrderService;
use App\Exceptions\InvalidRequestException;
class OrdersController extends Controller
{
    //下单
    public function store(OrderRequest $request, OrderService $orderService)
    {

        $user    = $request->user();
        $address = UserAddress::find($request->input('address_id'));
        return $orderService->store($user, $address, $request->input('remark'), $request->input('items'));
    }

    //订单列表页
    public function index()
    {
        $orders = Auth::user()->orders()->with(['items.productSku', 'items.product']) ->orderBy('created_at', 'desc')->paginate();
        return view('orders.index', ['orders' => $orders]);
    }

    //订单详情页面
    public function show(Order $order)
    {
        $this->authorize('own', $order);
        return view('orders.show', ['order'=>$order->load(['items.productSku', 'items.product'])]);
    }

    //用户确认收货
    public function received(Order $order, Request $request)
    {

        // 校验权限
        $this->authorize('own', $order);
        // 判断订单的发货状态是否为已发货
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('该订单还未发货');
        }
        $order->ship_status = Order::SHIP_STATUS_RECEIVED;
        $order->save();
        // 返回订单信息
        return $order;
    }

    //评价表单
    public function review(Order $order)
    {
        //校验权限
        $this->authorize('own', $order);
        //判断是否已经支付
         if (!$order->paid_at) {
             throw new InvalidRequestException('该订单未支付');
         }
         //使用load方法加载关联数据，避免N+1问题
        return view('orders.review', ['order'=>$order->load(['items.productSku', 'items.product'])]);
    }

    //发表评价
    public function sendReview(Order $order, SendReviewRequest $request)
    {
        //校验权限
        $this->authorize('own', $order);
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
        if ($order->reviewed) {
            throw new InvalidRequestException('该订单已评价，不可重复评价');
        }
        $reviews = $request->input('reviews');
        //开启事务
        \DB::transaction(function () use ($reviews, $order) {
            //遍历用户提交的评论数据
            foreach ($reviews as $review) {
                $orderItem = $order->items()->find($review['id']);
                //保存评分和评价
                $orderItem->update([
                    'rating' => $review['rating'],
                    'review' => $review['review'],
                    'reviewed_at'=> Carbon::now(),
                ]);
            }
            $order->update(['reviewed'=>true]);
            event(new OrderReviewd($order));
        });
        return redirect()->back();
    }


}