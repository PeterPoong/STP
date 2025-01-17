<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\stp_package;


class MarketingController extends Controller
{
    public function packageList(Request $request)
    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $packageList = stp_package::where('package_status', "!=", 0)->get();
            return response()->json([
                'success' => true,
                'data' => $packageList
            ]);


            return response()->json($packageList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
