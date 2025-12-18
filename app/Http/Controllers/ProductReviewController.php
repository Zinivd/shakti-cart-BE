<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\AuthUser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductReviewController extends Controller
{

    private function validateToken(Request $request)
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token missing'
            ]);
        }

        $token = str_replace('Bearer ', '', $token);

        try {
            $decoded = json_decode(Crypt::decryptString($token), true);

            if (!isset($decoded['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format'
                ]);
            }

            $user = AuthUser::where('email', $decoded['email'])->first();

            if (!$user || $user->session_token !== $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ]);
            }

            return $user;

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token decryption failed'
            ]);
        }
    }






    // ---------------------------------------------------------
    // ADMIN VALIDATION
    // ---------------------------------------------------------
    private function validateAdmin(Request $request)
    {
        $user = $this->validateToken($request);

        if ($user instanceof \Illuminate\Http\JsonResponse)
            return $user;

        if ($user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privilege required.'
            ]);
        }

        return $user;
    }



    // ---------------------------------------------------------
    // REVIEW ID GENERATOR
    // ---------------------------------------------------------
    private function generateReviewId()
    {
        $latest = ProductReview::orderBy('id', 'desc')->first();
        $no = $latest ? (int) str_replace('RVW', '', $latest->review_id) + 1 : 1;
        return 'RVW' . $no;
    }

    // ---------------------------------------------------------
    // ADD / UPDATE REVIEW
    // ---------------------------------------------------------
    public function addOrUpdateReview(Request $request)
    {
        $user = $this->validateToken($request);
        if ($user instanceof \Illuminate\Http\JsonResponse)
            return $user;

        try {
            $request->validate([
                'product_id' => 'required|string|exists:products,product_id',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'rating' => 'required|integer|min:1|max:5'
            ]);

            $review = ProductReview::where('product_id', $request->product_id)
                ->where('user_id', $user->unique_id)
                ->first();

            if ($review) {
                $review->update([
                    'title' => $request->title,
                    'description' => $request->description,
                    'rating' => $request->rating
                ]);

                $message = 'Review updated successfully';
            } else {
                $review = ProductReview::create([
                    'review_id' => $this->generateReviewId(),
                    'product_id' => $request->product_id,
                    'user_id' => $user->unique_id,
                    'title' => $request->title,
                    'description' => $request->description,
                    'rating' => $request->rating
                ]);

                $message = 'Review added successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $review
            ]);

        } catch (Exception $e) {
            Log::error("Add/Update Review Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'=>$e->getMessage(),
                'message' => 'Failed to submit review'
            ], 500);
        }
    }


     public function addReviewByAdmin(Request $request)
    {
        $admin = $this->validateAdmin($request);
        if ($admin instanceof \Illuminate\Http\JsonResponse)
            return $admin;

        try {
            $request->validate([
                'product_id' => 'required|string|exists:products,product_id',
                'name' => 'required|string',
                'email' => 'required|email',
                'rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string',
                'description' => 'nullable|string'
            ]);

            $review = ProductReview::create([
                'review_id' => $this->generateReviewId(),
                'product_id' => $request->product_id,
                'user_id' => null,
                'is_admin' => true,
                'admin_name' => $request->name,
                'admin_email' => $request->email,
                'rating' => $request->rating,
                'title' => $request->title,
                'description' => $request->description
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Admin review added successfully',
                'data' => $review
            ]);

        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to add admin review'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // GET LOGGED-IN USER REVIEW FOR A PRODUCT
    // ---------------------------------------------------------
    public function getMyReview(Request $request, $product_id)
    {
        $user = $this->validateToken($request);
        if ($user instanceof \Illuminate\Http\JsonResponse)
            return $user;

        try {
            $review = ProductReview::where('product_id', $product_id)
                ->where('user_id', $user->unique_id)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $review
            ]);

        } catch (Exception $e) {
            Log::error("Get My Review Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch review'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // GET ALL REVIEWS BY PRODUCT ID (WITH FULL USER DATA)
    // ---------------------------------------------------------
    public function getAllReviewsByProduct(Request $request, $product_id)
    {
        try {
            $reviews = ProductReview::with('user')
                ->where('product_id', $product_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'product_id' => $product_id,
                'total_reviews' => $reviews->count(),
                'average_rating' => round($reviews->avg('rating'), 1),
                'data' => $reviews
            ]);

        } catch (Exception $e) {
            Log::error("Get All Reviews Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch reviews'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE REVIEW (USER ONLY)
    // ---------------------------------------------------------
    public function deleteReview(Request $request, $product_id)
    {
        $user = $this->validateToken($request);
        if ($user instanceof \Illuminate\Http\JsonResponse)
            return $user;

        try {
            $review = ProductReview::where('product_id', $product_id)
                ->where('user_id', $user->unique_id)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error("Delete Review Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review'
            ], 500);
        }
    }
}
