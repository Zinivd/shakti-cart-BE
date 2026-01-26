<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductQuantity;
use App\Models\Transaction;
use Razorpay\Api\Api;
use Exception;

class RazorpayController extends Controller
{
    public function createOrder(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1'
            ]);

            $api = new \Razorpay\Api\Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            $order = $api->order->create([
                'receipt' => 'rcpt_' . time(),
                'amount' => $request->amount * 100, // paise
                'currency' => 'INR'
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $order['amount'],
                'currency' => $order['currency']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function checkout(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,order_id'
        ]);

        $order = Order::where('order_id', $request->order_id)->first();

        if ($order->payment_status !== 'PENDING') {
            return response()->json(['success' => false], 400);
        }

        $api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );

        $razorpayOrder = $api->order->create([
            'receipt' => $order->order_id,
            'amount' => $order->total_amount * 100,
            'currency' => 'INR'
        ]);

        Transaction::create([
            'order_id' => $order->order_id,
            'user_id' => $order->user_id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount' => $order->total_amount,
            'status' => 'CREATED'
        ]);

        return response()->json([
            'success' => true,
            'checkout' => [
                'key' => config('services.razorpay.key'),
                'order_id' => $razorpayOrder['id'],
                'amount' => $razorpayOrder['amount'],
                'currency' => 'INR',
                'name' => 'Shakthi Cart',
                'prefill' => [
                    'name' => $order->user_name,
                    'email' => $order->user_email,
                    'contact' => $order->user_phone
                ],
                'notes' => [
                    'order_id' => $order->order_id
                ]
            ]
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required'
        ]);

        try {
            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ]);

            $transaction = Transaction::where(
                'razorpay_order_id',
                $request->razorpay_order_id
            )->firstOrFail();

            $order = Order::where('order_id', $transaction->order_id)->first();

            foreach ($order->items as $item) {
                $stock = ProductQuantity::where([
                    'product_id' => $item->product_id,
                    'size' => $item->size
                ])->first();

                $stock->quantity -= $item->quantity;
                $stock->save();

                InventoryService::syncTotalQuantity($item->product_id);
            }

            $transaction->update([
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
                'status' => 'SUCCESS'
            ]);

            $order->update([
                'payment_status' => 'SUCCESS',
                'order_status' => 'CONFIRMED'
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => 'https://shakticart.com/payment-success?order_id=' . $order->order_id
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}