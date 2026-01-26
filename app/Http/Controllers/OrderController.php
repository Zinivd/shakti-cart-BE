<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductQuantity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use Razorpay\Api\Api;
use App\Models\AuthUser;
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
                'error' => $e->getMessage(),
                'message' => 'Invalid or expired token'
            ], 401);
        }
    }

    // ---------------------------------------------------------------------
    // 🛒 PLACE ORDER
    // ---------------------------------------------------------------------
    // public function placeOrder(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'user_id' => 'required|exists:auth_users,unique_id',
    //             'payment_mode' => 'required|string',
    //             'address' => 'required|array',
    //             'items' => 'required|array|min:1',
    //             'items.*.product_id' => 'required|exists:products,product_id',
    //             'items.*.size' => 'required|string',
    //             'items.*.quantity' => 'required|integer|min:1'
    //         ]);

    //         // 🔐 Auth check
    //         $decoded = $this->getUserFromToken($request);
    //         if ($decoded instanceof \Illuminate\Http\JsonResponse)
    //             return $decoded;

    //         // 👤 User (DB source of truth)
    //         $user = AuthUser::where('unique_id', $request->user_id)->first();

    //         // 🔒 Stock validation
    //         foreach ($request->items as $item) {
    //             $stock = ProductQuantity::where([
    //                 'product_id' => $item['product_id'],
    //                 'size' => $item['size']
    //             ])->first();

    //             if (!$stock || $stock->quantity < $item['quantity']) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => "Insufficient stock for {$item['product_id']} ({$item['size']})"
    //                 ], 400);
    //             }
    //         }

    //         // 🧮 Amount calculation (SERVER SIDE ONLY)
    //         $totalAmount = 0;
    //         foreach ($request->items as $item) {
    //             $product = Product::where('product_id', $item['product_id'])->first();
    //             $totalAmount += $product->selling_price * $item['quantity'];
    //         }

    //         // 🆔 Order ID
    //         $orderId = 'ORD' . (Order::max('id') + 1);

    //         // 🧾 Create Order
    //         Order::create([
    //             'order_id' => $orderId,
    //             'user_id' => $user->unique_id,
    //             'user_name' => $user->name,
    //             'user_email' => $user->email,
    //             'user_phone' => $user->phone,
    //             'payment_mode' => $request->payment_mode,
    //             'payment_status' => 'PENDING',
    //             'order_status' => 'CREATED',
    //             'total_amount' => $totalAmount,
    //             'address_building' => $request->address['building'],
    //             'address_line1' => $request->address['address_line1'],
    //             'address_line2' => $request->address['address_line2'] ?? null,
    //             'city' => $request->address['city'],
    //             'district' => $request->address['district'],
    //             'state' => $request->address['state'],
    //             'pincode' => $request->address['pincode'],
    //             'address_type' => $request->address['address_type']
    //         ]);

    //         // 🧾 Order Items
    //         foreach ($request->items as $item) {
    //             $product = Product::where('product_id', $item['product_id'])->first();

    //             OrderItem::create([
    //                 'order_id' => $orderId,
    //                 'product_id' => $item['product_id'],
    //                 'size' => $item['size'],
    //                 'quantity' => $item['quantity'],
    //                 'price' => $product->selling_price,
    //                 'total' => $product->selling_price * $item['quantity']
    //             ]);
    //         }

    //         // 💳 Razorpay Order
    //         $razorpay = new Api(
    //             config('services.razorpay.key'),
    //             config('services.razorpay.secret')
    //         );

    //         $razorpayOrder = $razorpay->order->create([
    //             'receipt' => $orderId,
    //             'amount' => $totalAmount * 100,
    //             'currency' => 'INR'
    //         ]);

    //         // 💾 Transaction (IMPORTANT)
    //         Transaction::create([
    //             'order_id' => $orderId,
    //             'user_id' => $user->unique_id,
    //             'razorpay_order_id' => $razorpayOrder['id'],
    //             'amount' => $totalAmount,
    //             'status' => 'CREATED',
    //             'gateway_response' => json_encode($razorpayOrder)
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order created, proceed to payment',
    //             'data' => [
    //                 'order_id' => $orderId,
    //                 'razorpay_order_id' => $razorpayOrder['id'],
    //                 'amount' => $totalAmount,
    //                 'currency' => 'INR',
    //                 'user' => [
    //                     'name' => $user->name,
    //                     'email' => $user->email,
    //                     'phone' => $user->phone
    //                 ]
    //             ]
    //         ]);

    //     } catch (Exception $e) {
    //         Log::error("PlaceOrder Error: " . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to place order',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function placeOrder(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:auth_users,unique_id',
                'payment_mode' => 'required|string|in:razorpay',
                'address' => 'required|array',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,product_id',
                'items.*.size' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1'
            ]);

            $decoded = $this->getUserFromToken($request);
            if ($decoded instanceof \Illuminate\Http\JsonResponse)
                return $decoded;

            $user = AuthUser::where('unique_id', $request->user_id)->firstOrFail();

            // 🔒 Stock check
            foreach ($request->items as $item) {
                $stock = ProductQuantity::where([
                    'product_id' => $item['product_id'],
                    'size' => $item['size']
                ])->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$item['product_id']} ({$item['size']})"
                    ], 400);
                }
            }

            // 🧮 Amount
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $product = Product::where('product_id', $item['product_id'])->first();
                $totalAmount += $product->selling_price * $item['quantity'];
            }

            // 🆔 SAFE Order ID
            $order = Order::create([
                'order_id' => 'ORD' . time(),
                'user_id' => $user->unique_id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_phone' => $user->phone,
                'payment_mode' => 'razorpay',
                'payment_status' => 'PENDING',
                'order_status' => 'CREATED',
                'total_amount' => $totalAmount,
                'address_building' => $request->address['building'],
                'address_line1' => $request->address['address_line1'],
                'address_line2' => $request->address['address_line2'] ?? null,
                'city' => $request->address['city'],
                'district' => $request->address['district'],
                'state' => $request->address['state'],
                'pincode' => $request->address['pincode'],
                'address_type' => $request->address['address_type']
            ]);

            foreach ($request->items as $item) {
                $product = Product::where('product_id', $item['product_id'])->first();

                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $item['product_id'],
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'price' => $product->selling_price,
                    'total' => $product->selling_price * $item['quantity']
                ]);
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->order_id,
                'amount' => $totalAmount
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order',
                'error' => $e->getMessage()
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
                'message' => 'Payment callback failed'
            ], 500);
        }
    }
    

     public function getInvoice($order_id)
    {
        try {
            $order = Order::with(['items.product'])
                ->where('order_id', $order_id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $transaction = Transaction::where('order_id', $order_id)->first();

            // 🧾 Build items
            $items = [];
            $subtotal = 0;

            foreach ($order->items as $item) {
                $items[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name ?? '',
                    'size' => $item->size,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'total_price' => $item->total
                ];

                $subtotal += $item->total;
            }

            return response()->json([
                'success' => true,
                'invoice' => [
                    'invoice_no' => 'INV-' . $order->order_id,
                    'order_id' => $order->order_id,
                    'order_date' => $order->created_at,
                    'order_status' => $order->order_status,
                    'payment_status' => $order->payment_status,

                    'customer' => [
                        'name' => $order->user_name,
                        'email' => $order->user_email,
                        'phone' => $order->user_phone
                    ],

                    'billing_address' => [
                        'building' => $order->address_building,
                        'address_line1' => $order->address_line1,
                        'address_line2' => $order->address_line2,
                        'city' => $order->city,
                        'district' => $order->district,
                        'state' => $order->state,
                        'pincode' => $order->pincode,
                        'address_type' => $order->address_type
                    ],

                    'items' => $items,

                    'amounts' => [
                        'subtotal' => $subtotal,
                        'tax' => 0,
                        'discount' => 0,
                        'grand_total' => $subtotal
                    ],

                    'payment' => [
                        'gateway' => 'razorpay',
                        'razorpay_order_id' => $transaction->razorpay_order_id ?? null,
                        'razorpay_payment_id' => $transaction->razorpay_payment_id ?? null,
                        'status' => $transaction->status ?? 'PENDING',
                        'paid_at' => $transaction->updated_at ?? null
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
