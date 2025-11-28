<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Models\AuthUser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductController extends Controller
{
    // ---------------------------------------------
    // 🔐 COMMON TOKEN VALIDATION (Same as your system)
    // ---------------------------------------------
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
                    'message' => 'Invalid token'
                ]);
            }

            $user = AuthUser::where('email', $decoded['email'])->first();

            if (!$user || $user->session_token !== $token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ]);
            }

            return $user; // valid user

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token decryption failed'
            ]);
        }
    }

    // ADMIN ONLY
    private function validateAdmin(Request $request)
    {
        $user = $this->validateToken($request);

        if ($user instanceof \Illuminate\Http\JsonResponse)
            return $user;

        if ($user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required'
            ]);
        }

        return $user;
    }


    // ---------------------------------------------
    // 🔢 GENERATE PRODUCT ID
    // ---------------------------------------------
    private function generateProductId()
    {
        $latest = Product::orderBy('id', 'desc')->first();
        $number = $latest ? ((int) str_replace('PRD', '', $latest->product_id) + 1) : 1;
        return "PRD" . $number;
    }


    // ---------------------------------------------
    // 🟩 CREATE PRODUCT
    // ---------------------------------------------
    public function createProduct(Request $request)
    {
        // Admin only
        $admin = $this->validateAdmin($request);
        if ($admin instanceof \Illuminate\Http\JsonResponse)
            return $admin;

        try {
            // Validation
            $request->validate([
                'product_name' => 'required|string',
                'brand' => 'nullable|string',
                'category_id' => 'required|exists:product_categories,category_id',
                'sub_category_id' => 'nullable|exists:product_subcategories,sub_category_id',
                'description' => 'nullable|string',
                'color' => 'nullable|string',
                'size_unit' => 'nullable|string',
                'actual_price' => 'required|numeric',
                'discount' => 'nullable|numeric',
                'selling_price' => 'required|numeric',
                'product_list_type' => 'nullable|string',
                'images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            ]);

            $category = ProductCategory::where('category_id', $request->category_id)->first();
            $subCategory = ProductSubCategory::where('sub_category_id', $request->sub_category_id)->first();

            // Upload images
            $imageUrls = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = Storage::disk('s3')->putFile('products', $image, 'public');
                    $imageUrls[] = env('AWS_URL') . "/" . $path;
                }
            }

            // Create Product
            $product = Product::create([
                'product_id' => $this->generateProductId(),
                'product_name' => $request->product_name,
                'brand' => $request->brand,
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
                'sub_category_id' => $subCategory->sub_category_id ?? null,
                'sub_category_name' => $subCategory->sub_category_name ?? null,
                'description' => $request->description,
                'color' => $request->color,
                'size_unit' => $request->size_unit ? json_decode($request->size_unit, true) : [],
                'actual_price' => $request->actual_price,
                'discount' => $request->discount ?? 0,
                'selling_price' => $request->selling_price,
                'product_list_type' => $request->product_list_type,
                'images' => $imageUrls,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully',
                'data' => $product
            ]);

        } catch (Exception $e) {
            Log::error("Create Product Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating product'
            ], 500);
        }
    }


    // ---------------------------------------------
    // 🟦 UPDATE PRODUCT
    // ---------------------------------------------
    public function updateProduct(Request $request)
    {
        // Admin only
        $admin = $this->validateAdmin($request);
        if ($admin instanceof \Illuminate\Http\JsonResponse)
            return $admin;

        try {
            $request->validate([
                'product_id' => 'required|string|exists:products,product_id',
                'product_name' => 'nullable|string',
                'brand' => 'nullable|string',
                'category_id' => 'nullable|exists:product_categories,category_id',
                'sub_category_id' => 'nullable|exists:product_subcategories,sub_category_id',
                'description' => 'nullable|string',
                'color' => 'nullable|string',
                'size_unit' => 'nullable|string',
                'actual_price' => 'nullable|numeric',
                'discount' => 'nullable|numeric',
                'selling_price' => 'nullable|numeric',
                'product_list_type' => 'nullable|string',
                'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
            ]);

            $product = Product::where('product_id', $request->product_id)->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // -------------------------------------
            // 🚀 IMAGE HANDLING (FORM-DATA)
            // -------------------------------------
            $imageUrls = $product->images;  // Keep existing images

            if ($request->hasFile('images')) {

                // Reset images only when new images passed
                $imageUrls = [];

                $images = $request->file('images');

                // Handle both single & array uploads
                if (!is_array($images)) {
                    $images = [$images];
                }

                foreach ($images as $image) {
                    $path = Storage::disk('s3')->putFile('products', $image, 'public');
                    $imageUrls[] = env('AWS_URL') . "/" . $path;
                }
            }

            // -------------------------------------
            // 🚀 UPDATE PRODUCT
            // -------------------------------------
            $product->update([
                'product_name' => $request->product_name ?? $product->product_name,
                'brand' => $request->brand ?? $product->brand,
                'category_id' => $request->category_id ?? $product->category_id,
                'sub_category_id' => $request->sub_category_id ?? $product->sub_category_id,
                'description' => $request->description ?? $product->description,
                'color' => $request->color ?? $product->color,
                'size_unit' => $request->size_unit
                    ? json_decode($request->size_unit, true)
                    : $product->size_unit,
                'actual_price' => $request->actual_price ?? $product->actual_price,
                'discount' => $request->discount ?? $product->discount,
                'selling_price' => $request->selling_price ?? $product->selling_price,
                'product_list_type' => $request->product_list_type ?? $product->product_list_type,
                'images' => $imageUrls,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);

        } catch (Exception $e) {
            Log::error("Update Product Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    // ---------------------------------------------
    // ❌ DELETE PRODUCT
    // ---------------------------------------------
    public function deleteProduct(Request $request)
    {
        // Admin only
        $admin = $this->validateAdmin($request);
        if ($admin instanceof \Illuminate\Http\JsonResponse)
            return $admin;

        try {
            $request->validate([
                'product_id' => 'required|string|exists:products,product_id'
            ]);

            $product = Product::where('product_id', $request->product_id)->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Optional: delete images from S3
            if (!empty($product->images)) {
                foreach ($product->images as $img) {
                    $path = str_replace(env('AWS_URL') . "/", "", $img);
                    Storage::disk('s3')->delete($path);
                }
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error("Delete Product Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product'
            ], 500);
        }
    }


    // ---------------------------------------------
    // 🟩 GET ALL PRODUCTS
    // ---------------------------------------------
    public function getAllProducts()
    {
        try {
            $products = Product::all();

            return response()->json([
                'success' => true,
                'count' => $products->count(),
                'data' => $products
            ]);

        } catch (Exception $e) {
            Log::error("GetAllProducts Error: " . $e->getMessage());
        }
    }


    // ---------------------------------------------
    // 🟦 GET PRODUCTS BY CATEGORY
    // ---------------------------------------------
    public function getProductsByCategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|string|exists:product_categories,category_id'
        ]);

        $products = Product::where('category_id', $request->category_id)->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }


    // ---------------------------------------------
    // 🟦 GET PRODUCTS BY SUBCATEGORY
    // ---------------------------------------------
    public function getProductsBySubCategory(Request $request)
    {
        $request->validate([
            'sub_category_id' => 'required|string|exists:product_subcategories,sub_category_id'
        ]);

        $products = Product::where('sub_category_id', $request->sub_category_id)->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }


    // ---------------------------------------------
    // 🎯 FILTER PRODUCTS
    // ---------------------------------------------
    public function getProductsFiltered(Request $request)
    {
        $request->validate([
            'category_id' => 'required|string|exists:product_categories,category_id',
            'sub_category_id' => 'nullable|string|exists:product_subcategories,sub_category_id',
        ]);

        $query = Product::where('category_id', $request->category_id);

        if ($request->sub_category_id) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'data' => $products
        ]);
    }
}
