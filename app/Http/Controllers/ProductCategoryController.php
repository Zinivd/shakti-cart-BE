<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    // 🔢 Generate Category ID like CTGRY1, CTGRY2, etc.
    private function generateCategoryId()
    {
        $latest = ProductCategory::orderBy('id', 'desc')->first();
        $number = $latest ? (int) str_replace('CTGRY', '', $latest->category_id) + 1 : 1;
        return 'CTGRY' . $number;
    }

    // 🔢 Generate SubCategory ID like SUBCTGRY1, SUBCTGRY2, etc.
    private function generateSubCategoryId()
    {
        $latest = ProductSubCategory::orderBy('id', 'desc')->first();
        $number = $latest ? (int) str_replace('SUBCTGRY', '', $latest->sub_category_id) + 1 : 1;
        return 'SUBCTGRY' . $number;
    }

    // 🧩 Create a main Category
    public function createCategory(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255'
        ]);

        $category = ProductCategory::create([
            'category_id' => $this->generateCategoryId(),
            'category_name' => $request->category_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ]);
    }

    // 🧩 Create a SubCategory under a main Category
    public function createSubCategory(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:product_categories,category_id',
            'sub_category_name' => 'required|string|max:255',
        ]);

        $subCategory = ProductSubCategory::create([
            'sub_category_id' => $this->generateSubCategoryId(),
            'sub_category_name' => $request->sub_category_name,
            'category_id' => $request->category_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory created successfully',
            'data' => $subCategory
        ]);
    }

    // 📦 Get all categories with their subcategories grouped
    public function getAllCategories()
    {
        $categories = ProductCategory::with('subcategories')->get();

        $response = $categories->map(function ($category) {
            return [
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
                'sub_categories' => $category->subcategories->map(function ($sub) {
                    return [
                        'sub_category_id' => $sub->sub_category_id,
                        'sub_category_name' => $sub->sub_category_name
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    }
}
