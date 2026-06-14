<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use Illuminate\Http\Request;

class SupportCategoryController extends Controller
{
    public function index()
    {
        return SupportCategory::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function all()
    {
        return SupportCategory::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:support_categories',
            'description' => 'nullable|string',
        ]);

        return SupportCategory::create($data);
    }

    public function update(Request $request, SupportCategory $supportCategory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:support_categories,name,' . $supportCategory->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supportCategory->update($data);

        return $supportCategory;
    }

    public function destroy(SupportCategory $supportCategory)
    {
        if ($supportCategory->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that has tickets.'
            ], 422);
        }

        $supportCategory->delete();

        return response()->noContent();
    }
}
