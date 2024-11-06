<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\stp_core_meta;
use App\Models\stp_enquiry;
use App\Http\Controllers\ServiceFunctionController;

class EnquiryController extends Controller
{
    protected $serviceFunctionController;

    public function __construct(ServiceFunctionController $serviceFunctionController)
    {
        $this->serviceFunctionController = $serviceFunctionController;
    }

    public function subjectList()
    {
        try {
            $subjectList = stp_core_meta::where('core_metaType', 'enquiry_subject_type')
                ->where('core_metaStatus', 1)
                ->get();



            return response()->json([
                'success' => true,
                'data' => $subjectList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    public function createEnquiry(Request $request)
    {
        try {
            $request->validate([
                'full_name' => 'required|string|max:255',
                'email' => 'required|email',
                'contact' => 'required|string|max:255',
                'subject' => 'required|integer',
                'message' => 'required|string|max:1000'

            ]);

            $findSubject = stp_core_meta::find($request->subject);
            $createEnquiry = stp_enquiry::create([
                'enquiry_name' => $request->full_name,
                'enquiry_email' => $request->email,
                'enquiry_phone' => $request->contact,
                'enquiry_subject' => $request->subject,
                'enquiry_message' => $request->message
            ]);

            $this->serviceFunctionController->sendEnquiryEmail($request->full_name, $request->email, $request->contact, $findSubject->core_metaName, $request->message);
            return response()->json([
                'success' => true,
                'message' => 'Enquiry created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function enquiryList(Request $request)
    {
        try {
            $request->validate([
                'subject' => 'integer'
            ]);

            $enquiryList = stp_enquiry::when($request->subject, function ($query, $subject) {
                return $query->where('enquiry_subject', $subject);
            })
                ->where('enquiry_status', 1)
                ->get()
                ->map(function ($enquiry) {
                    return [
                        'id' => $enquiry->id,
                        'enquiry_name' => $enquiry->enquiry_name,
                        'enquiry_email' => $enquiry->enquiry_email,
                        'enquiry_phone' => $enquiry->enquiry_phone,
                        'enquiry_subject' => $enquiry->subject->core_metaName,
                        'enquiry_message' => $enquiry->enquiry_message,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $enquiryList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
