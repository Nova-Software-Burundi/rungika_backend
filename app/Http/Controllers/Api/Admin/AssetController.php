<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index()
    {
        return Asset::orderBy('code')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:10|unique:assets',
            'name' => 'required|string|max:255',
            'decimals' => 'integer|min:0|max:18',
            'enabled' => 'boolean',
        ]);

        return Asset::create($data);
    }

    public function show(Asset $asset)
    {
        return $asset;
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'code' => 'string|max:10|unique:assets,code,' . $asset->id,
            'name' => 'string|max:255',
            'decimals' => 'integer|min:0|max:18',
            'enabled' => 'boolean',
        ]);

        $asset->update($data);
        return $asset;
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->noContent();
    }
}
