<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;

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
                'category_name' => 'required|string|max:255'
            ]);

            $category = ProductCategory::create([
                'category_id' => $this->generateCategoryId(),
                'category_name' => $request->category_name
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
                'category_name' => 'required|string|max:255'
            ]);

            $category = ProductCategory::where('category_id', $request->category_id)->first();

            $category->update([
                'category_name' => $request->category_name
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
                'sub_category_name' => 'required|string|max:255'
            ]);

            $sub = ProductSubCategory::create([
                'sub_category_id' => $this->generateSubCategoryId(),
                'sub_category_name' => $request->sub_category_name,
                'category_id' => $request->category_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subcategory created successfully',
                'data' => $sub
            ]);

        } catch (Exception $e) {
            Log::error("Create SubCategory Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error creating subcategory'
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
                'sub_category_id' => 'required|string|exists:product_subcategories,sub_category_id',
                'sub_category_name' => 'required|string|max:255'
            ]);

            $sub = ProductSubCategory::where('sub_category_id', $request->sub_category_id)->first();

            $sub->update([
                'sub_category_name' => $request->sub_category_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subcategory updated successfully',
                'data' => $sub
            ]);

        } catch (Exception $e) {
            Log::error("Update SubCategory Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating subcategory'
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
                'message' => 'Error deleting subcategory'
            ], 500);
        }
    }
}
