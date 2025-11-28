<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderController extends Controller
{
    // ---------------------------------------------------------------------
    // 🔐 TOKEN VALIDATION (Reusable helper)
    // ---------------------------------------------------------------------
    private function getUserFromToken(Request $request)
    {
        try {
            $token = $request->header('Authorization');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization token missing'
                ], 401);
            }

            $token = str_replace('Bearer ', '', $token);

            $decoded = json_decode(Crypt::decryptString($token), true);

            if (!isset($decoded['unique_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token structure'
                ], 401);
            }

            return $decoded;

        } catch (Exception $e) {
            Log::error("Token Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }
    }

    // ---------------------------------------------------------------------
    // 🛒 PLACE ORDER
    // ---------------------------------------------------------------------
    public function placeOrder(Request $request)
    {
        try {
            $request->validate([
                'payment_mode' => 'required|string',
                'address' => 'required|array',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,product_id',
                'items.*.quantity' => 'required|integer|min:1'
            ]);

            // Token → User Info
            $decoded = $this->getUserFromToken($request);
            if ($decoded instanceof \Illuminate\Http\JsonResponse)
                return $decoded;

            $userId = $decoded['unique_id'];

            // Generate order ID
            $latest = Order::orderBy('id', 'desc')->first();
            $orderId = 'ORD' . ($latest ? $latest->id + 1 : 1);

            // Calculate total amount
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $product = Product::where('product_id', $item['product_id'])->first();
                $totalAmount += $product->selling_price * $item['quantity'];
            }

            // Create Order
            $order = Order::create([
                'order_id' => $orderId,
                'user_id' => $userId,
                'user_name' => $decoded['name'],
                'user_email' => $decoded['email'],
                'user_phone' => $decoded['phone'],

                // Address Info
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

            // Create Order Items
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

        } catch (Exception $e) {
            Log::error("PlaceOrder Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to place order'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 📦 UPDATE ORDER STATUS (Admin typically)
    // ---------------------------------------------------------------------
    public function updateOrderStatus(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,order_id',
                'status' => 'required|string'
            ]);

            $order = Order::where('order_id', $request->order_id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $order->order_status = $request->status;

            if ($request->status === 'Shipped') {
                $order->shipped_at = now();
            }

            if ($request->status === 'Delivered') {
                $order->delivered_at = now();
            }

            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated'
            ]);

        } catch (Exception $e) {
            Log::error("UpdateOrderStatus Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 📜 USER ORDER LIST (Token-based)
    // ---------------------------------------------------------------------
    public function orderList(Request $request)
    {
        try {
            $decoded = $this->getUserFromToken($request);
            if ($decoded instanceof \Illuminate\Http\JsonResponse)
                return $decoded;

            $orders = Order::where('user_id', $decoded['unique_id'])
                ->with('items.product')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);

        } catch (Exception $e) {
            Log::error("OrderList Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 📜 GET USER ORDERS BY ID (Admin)
    // ---------------------------------------------------------------------
    public function getUserOrders(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|string'
            ]);

            $orders = Order::where('user_id', $request->user_id)
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);

        } catch (Exception $e) {
            Log::error("GetUserOrders Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch orders'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 📦 GET ORDER BY ORDER ID
    // ---------------------------------------------------------------------
    public function getOrderByOrderId(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string'
            ]);

            $order = Order::where('order_id', $request->order_id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order details fetched successfully',
                'data' => $order
            ]);

        } catch (Exception $e) {
            Log::error("GetOrderById Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching order'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 💳 PAYMENT INITIATE
    // ---------------------------------------------------------------------
    public function initiatePayment(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,order_id'
            ]);

            return response()->json([
                "success" => true,
                "payment_url" => "https://your-payment-gateway/redirect"
            ]);

        } catch (Exception $e) {
            Log::error("InitiatePayment Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed'
            ], 500);
        }
    }

    // ---------------------------------------------------------------------
    // 💳 PAYMENT CALLBACK
    // ---------------------------------------------------------------------
    public function paymentCallback(Request $request)
    {
        try {
            $orderId = $request->merchantTransactionId;
            $paymentId = $request->transactionId;
            $status = $request->code;

            $order = Order::where('order_id', $orderId)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $order->payment_id = $paymentId;
            $order->payment_status = $status === 'PAYMENT_SUCCESS' ? 'SUCCESS' : 'FAILED';

            if ($order->payment_status === 'SUCCESS') {
                $order->order_status = 'CONFIRMED';
            }

            $order->save();

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            Log::error("PaymentCallback Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payment callback failed'
            ], 500);
        }
    }
}
