<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Storage;

class ProductCategoryController extends Controller
{
    // ---------------------------------------------------------
    // TOKEN VALIDATION (Same as before)
    // ---------------------------------------------------------
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
    // ID GENERATORS
    // ---------------------------------------------------------
    private function generateCategoryId()
    {
        $latest = ProductCategory::orderBy('id', 'desc')->first();
        $no = $latest ? (int) str_replace('CTGRY', '', $latest->category_id) + 1 : 1;
        return 'CTGRY' . $no;
    }

    private function generateSubCategoryId()
    {
        $latest = ProductSubCategory::orderBy('id', 'desc')->first();
        $no = $latest ? (int) str_replace('SUBCTGRY', '', $latest->sub_category_id) + 1 : 1;
        return 'SUBCTGRY' . $no;
    }

    // ---------------------------------------------------------
    // CREATE CATEGORY
    // ---------------------------------------------------------
    public function createCategory(Request $request)
    {
        if ($admin = $this->validateAdmin($request)) {
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;
        }

        try {
            $request->validate([
                'category_name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            ]);

            $imageUrl = null;

            if ($request->hasFile('image')) {
                $path = Storage::disk('s3')->putFile(
                    'category',
                    $request->file('image'),
                    'public'
                );

                $imageUrl = env('AWS_URL') . '/' . $path;
            }

            $category = ProductCategory::create([
                'category_id' => $this->generateCategoryId(),
                'category_name' => $request->category_name,
                'image' => $imageUrl, // ONE image
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ]);

        } catch (Exception $e) {
            Log::error("Create Category Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error creating category'
            ], 500);
        }
    }


    // ---------------------------------------------------------
    // UPDATE CATEGORY
    // ---------------------------------------------------------
    public function updateCategory(Request $request)
    {
        if ($admin = $this->validateAdmin($request)) {
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;
        }

        try {
            $request->validate([
                'category_id' => 'required|string|exists:product_categories,category_id',
                'category_name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            ]);

            $category = ProductCategory::where('category_id', $request->category_id)->first();

            $imageUrl = $category->image;

            if ($request->hasFile('image')) {
                $path = Storage::disk('s3')->putFile(
                    'category',
                    $request->file('image'),
                    'public'
                );

                $imageUrl = env('AWS_URL') . '/' . $path;
            }

            $category->update([
                'category_name' => $request->category_name,
                'image' => $imageUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category
            ]);

        } catch (Exception $e) {
            Log::error("Update Category Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error updating category'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE CATEGORY
    // ---------------------------------------------------------
    public function deleteCategory(Request $request)
    {
        if ($admin = $this->validateAdmin($request)) {
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;
        }

        try {
            $request->validate([
                'category_id' => 'required|string|exists:product_categories,category_id'
            ]);

            $category = ProductCategory::where('category_id', $request->category_id)->first();

            // Delete category AND its subcategories
            ProductSubCategory::where('category_id', $request->category_id)->delete();
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error("Delete Category Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error deleting category'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // CREATE SUBCATEGORY
    // ---------------------------------------------------------
    public function createSubCategory(Request $request)
    {
        if ($admin = $this->validateAdmin($request)) {
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;
        }

        try {
            $request->validate([
                'category_id' => 'required|exists:product_categories,category_id',
                'subcategories' => 'required|array|min:1',
                'subcategories.*.sub_category_name' => 'required|string|max:255'
            ]);

            $created = [];

            foreach ($request->subcategories as $item) {
                $created[] = ProductSubCategory::create([
                    'sub_category_id' => $this->generateSubCategoryId(),
                    'sub_category_name' => $item['sub_category_name'],
                    'category_id' => $request->category_id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subcategories created successfully',
                'data' => $created
            ]);

        } catch (Exception $e) {
            Log::error("Create SubCategory Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error creating subcategories'
            ], 500);
        }
    }
    // ---------------------------------------------------------
    // UPDATE SUBCATEGORY
    // ---------------------------------------------------------
    public function updateSubCategory(Request $request)
    {
        if ($admin = $this->validateAdmin($request)) {
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;
        }

        try {
            $request->validate([
                'category_id' => 'required|exists:product_categories,category_id',
                'subcategories' => 'required|array|min:1',
                'subcategories.*.sub_category_id' => 'required|exists:product_subcategories,sub_category_id',
                'subcategories.*.sub_category_name' => 'required|string|max:255',
            ]);

            $updated = [];

            foreach ($request->subcategories as $item) {
                $sub = ProductSubCategory::where('sub_category_id', $item['sub_category_id'])
                    ->where('category_id', $request->category_id)
                    ->first();

                if ($sub) {
                    $sub->update([
                        'sub_category_name' => $item['sub_category_name']
                    ]);

                    $updated[] = $sub;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Subcategories updated successfully',
                'data' => $updated
            ]);

        } catch (Exception $e) {
            Log::error("Update SubCategory Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error updating subcategories'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE SUBCATEGORY
    // ---------------------------------------------------------
    public function deleteSubCategory(Request $request)
    {
        if ($admin = $this->validateAdmin($request)) {
            if ($admin instanceof \Illuminate\Http\JsonResponse)
                return $admin;
        }

        try {
            $request->validate([
                'sub_category_id' => 'required|string|exists:product_subcategories,sub_category_id'
            ]);

            $sub = ProductSubCategory::where('sub_category_id', $request->sub_category_id)->first();
            $sub->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subcategory deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error("Delete SubCategory Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error deleting subcategory'
            ], 500);
        }
    }


    public function getAllCategories(Request $request)
    {
        if ($user = $this->validateToken($request)) {
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;
        }

        try {
            $categories = ProductCategory::with('subcategories')->get();

            return response()->json([
                'success' => true,
                'count' => $categories->count(),
                'data' => $categories
            ]);

        } catch (Exception $e) {
            Log::error("GetAllCategories Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Unable to fetch categories'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // 📌 2) GET ALL SUBCATEGORIES
    // ---------------------------------------------------------
    public function getAllSubCategories(Request $request)
    {
        if ($user = $this->validateToken($request)) {
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;
        }

        try {
            $subs = ProductSubCategory::all();

            return response()->json([
                'success' => true,
                'count' => $subs->count(),
                'data' => $subs
            ]);

        } catch (Exception $e) {
            Log::error("GetAllSubCategories Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Unable to fetch subcategories'
            ], 500);
        }
    }

    // ---------------------------------------------------------
    // 📌 3) GET SUBCATEGORIES BY CATEGORY ID
    // ---------------------------------------------------------
    public function getSubCategoriesByCategory(Request $request)
    {
        if ($user = $this->validateToken($request)) {
            if ($user instanceof \Illuminate\Http\JsonResponse)
                return $user;
        }

        try {
            $request->validate([
                'category_id' => 'required|string|exists:product_categories,category_id'
            ]);

            $subs = ProductSubCategory::where('category_id', $request->category_id)->get();

            return response()->json([
                'success' => true,
                'category_id' => $request->category_id,
                'count' => $subs->count(),
                'data' => $subs
            ]);

        } catch (Exception $e) {
            Log::error("GetSubCategoriesByCategory Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Unable to fetch subcategories'
            ], 500);
        }
    }
}
