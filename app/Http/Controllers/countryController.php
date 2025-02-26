<?php

namespace App\Http\Controllers;

use App\Models\stp_country;
use Illuminate\Http\Request;

class countryController extends Controller
{
    public function countryCode(Request $request)
    {
        try {
            $countryList = stp_country::get();
            $data = [];
            foreach ($countryList as $country) {
                $data[] = [
                    'id' => $country->id,
                    'name' => $country->country_name,
                    'country_code' => $country->country_code
                ];
            }
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }
    public function storeLocation(Request $request)
    {
        try {
            // Validate the incoming request data
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            // Here you can process the location data as needed
            // For example, save it to the database or perform other actions

            return response()->json([
                'success' => true,
                'message' => 'Location received successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ]);
        }
    }
}
