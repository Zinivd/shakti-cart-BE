<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function placeOrder(Request $request)
    {
        $request->validate([
            'payment_mode' => 'required|string',
            'address' => 'required|array',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        // TOKEN → USER
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            $decoded = json_decode(\Crypt::decryptString($token), true);

            $userId = $decoded['unique_id'];
            $userName = $decoded['name'];
            $userEmail = $decoded['email'];
            $userPhone = $decoded['phone'];

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        // NEW ORDER ID
        $latest = Order::orderBy('id', 'desc')->first();
        $orderId = 'ORD' . ($latest ? $latest->id + 1 : 1);

        // CALCULATE TOTAL
        $totalAmount = 0;
        foreach ($request->items as $item) {
            $product = Product::where('product_id', $item['product_id'])->first();
            $totalAmount += $product->selling_price * $item['quantity'];
        }

        // INSERT ORDER
        $order = Order::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'user_phone' => $userPhone,

            'address_building' => $request->address['building'],
            'address_line1' => $request->address['address_line1'],
            'address_line2' => $request->address['address_line2'] ?? null,
            'city' => $request->address['city'],
            'district' => $request->address['district'],
            'state' => $request->address['state'],
            'pincode' => $request->address['pincode'],
            'landmark' => $request->address['landmark'] ?? null,
            'address_type' => $request->address['address_type'],

            'payment_mode' => $request->payment_mode,
            'payment_status' => 'PENDING',
            'order_status' => 'PLACED',
            'total_amount' => $totalAmount
        ]);

        // INSERT ITEMS
        foreach ($request->items as $item) {
            $product = Product::where('product_id', $item['product_id'])->first();

            OrderItem::create([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $product->selling_price,
                'total' => $product->selling_price * $item['quantity']
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $orderId,
            'total_amount' => $totalAmount
        ]);
    }

    public function updateOrderStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,order_id',
            'status' => 'required|string'
        ]);

        $order = Order::where('order_id', $request->order_id)->first();

        $order->order_status = $request->status;

        if ($request->status == 'Shipped')
            $order->shipped_at = now();
        if ($request->status == 'Delivered')
            $order->delivered_at = now();

        $order->save();

        return response()->json(['success' => true, 'message' => 'Order status updated']);
    }


    public function orderList(Request $request)
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            $decoded = json_decode(\Crypt::decryptString($token), true);
            $userId = $decoded['unique_id'];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        $orders = Order::where('user_id', $userId)
            ->with('items.product')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }


    public function getUserOrders(Request $request)
    {
        $user_id = $request->query('user_id');

        if (!$user_id) {
            return response()->json([
                'success' => false,
                'message' => 'user_id is required'
            ]);
        }

        $orders = Order::where('user_id', $user_id)

            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }


    public function getOrderByOrderId(Request $request)
    {
        // Validate Query Param
        $request->validate([
            'order_id' => 'required|string'
        ]);

        // Fetch Order
        $order = Order::where('order_id', $request->order_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order details fetched successfully',
            'data' => $order
        ]);
    }


    public function initiatePayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,order_id'
        ]);

        $order = Order::where('order_id', $request->order_id)->first();

        return response()->json([
            "success" => true,
            "payment_url" => "https://your-payment-gateway/redirect"
        ]);
    }


    public function paymentCallback(Request $request)
    {
        $orderId = $request->merchantTransactionId;
        $paymentId = $request->transactionId;
        $status = $request->code;

        $order = Order::where('order_id', $orderId)->first();

        $order->payment_id = $paymentId;
        $order->payment_status = $status === 'PAYMENT_SUCCESS' ? 'SUCCESS' : 'FAILED';

        if ($order->payment_status === 'SUCCESS') {
            $order->order_status = 'CONFIRMED';
        }

        $order->save();

        return response()->json(['success' => true]);
    }


}
