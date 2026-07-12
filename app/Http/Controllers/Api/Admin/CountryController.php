<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Country::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'code'       => ['required', 'string', 'size:2', 'unique:countries,code'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'flag_url'   => ['nullable', 'string', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $country = Country::create($data);

        return response()->json($country, 201);
    }

    public function show(Country $country): JsonResponse
    {
        return response()->json($country);
    }

    public function update(Request $request, Country $country): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'code'       => ['required', 'string', 'size:2', 'unique:countries,code,' . $country->id],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'flag_url'   => ['nullable', 'string', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $country->update($data);

        return response()->json($country);
    }

    public function destroy(Country $country): JsonResponse
    {
        $country->delete();

        return response()->json(['message' => 'Country deleted.']);
    }
}
