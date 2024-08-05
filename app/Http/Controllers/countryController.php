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
}
