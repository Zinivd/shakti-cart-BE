<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;

class ProductController extends Controller
{
    // 🔢 Auto-generate Product ID like PRD1, PRD2, etc.
    private function generateProductId()
    {
        $latest = Product::orderBy('id', 'desc')->first();
        $number = $latest ? (int) str_replace('PRD', '', $latest->product_id) + 1 : 1;
        return 'PRD' . $number;
    }

    // 🧩 Create Product API (Form Data)
    public function createProduct(Request $request)
    {
        // ✅ Custom validation messages
        $messages = [
            'images.*.image' => 'Each uploaded file must be an image.',
            'images.*.mimes' => 'Only JPEG, PNG, JPG, and WEBP image formats are allowed.',
            'images.*.max' => 'Each image must be smaller than 2 MB.',
        ];

        $validated = $request->validate([
            'product_name' => 'required|string',
            'brand' => 'nullable|string',
            'category_id' => 'required|exists:product_categories,category_id',
            'sub_category_id' => 'nullable|exists:product_subcategories,sub_category_id',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'size_unit' => 'nullable|string', // JSON string
            'actual_price' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'selling_price' => 'required|numeric',
            'product_list_type' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // 2MB per image
        ], $messages);

        $category = ProductCategory::where('category_id', $request->category_id)->first();
        $subCategory = ProductSubCategory::where('sub_category_id', $request->sub_category_id)->first();

        $imageUrls = [];

        // 📸 Handle Image Uploads (Flexible - single or multiple)
        if ($request->hasFile('images')) {
            $images = is_array($request->file('images'))
                ? $request->file('images')
                : [$request->file('images')];

            foreach ($images as $image) {
                try {
                    // Upload to S3
                    $path = Storage::disk('s3')->putFile('products', $image, 'public');
                    if ($path) {
                        $imageUrls[] = env('AWS_URL') . '/' . $path;
                    }
                } catch (\Exception $e) {
                    \Log::error('S3 Upload failed: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Image upload failed. Please try again later.',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }
        } else {
            \Log::info('No images found in request.');
        }

        // 🧾 Create Product Record
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
    }


    // 📋 Product List API
    public function getAllProducts()
    {
        $products = Product::with(['category', 'subcategory'])->get();

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'data' => $products
        ]);
    }
}
