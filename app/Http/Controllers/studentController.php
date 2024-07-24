<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\stp_school;
use Exception;

class studentController extends Controller
{
    public function schoolList(Request $request)
    {
        try {
            $schoolList = stp_school::where('school_status', 1)
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->orWhere('institue_category', $request->category);
                })
                ->when($request->filled('country'), function ($query) use ($request) {
                    $query->orWhere('country_id', $request->country);
                })
                ->when($request->filled('location'), function ($query) use ($request) {
                    $query->orWhere('state_id', $request->location);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $query->where('school_name', 'like', '%' . $request->search . '%');
                })
                ->paginate(10)
                ->through(function ($school) {
                    return [
                        'id' => $school->id,
                        'name' => $school->school_name,
                        'category' => $school->institueCategory->core_metaName ?? null,
                        'logo' => $school->school_logo,
                        'country' => $school->country->country_name ?? null,
                        'state' => $school->state->state_name ?? null,
                        'city' => $school->city->city_name ?? null,
                        'description' => $school->school_shortDesc
                    ];
                });
            return response()->json($schoolList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Sever Error',
                'error' => $e
            ]);
        }
    }
}
