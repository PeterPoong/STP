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
                'enquiry_message' => $request->message,
                'enquiry_status' => 2
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
                        'enquiry_status' => $enquiry->enquiry_status
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

    public function enquiryListAdmin(Request $request)
    {
        try {
            // Get the per_page value from the request, default to 10 if not provided or empty
            $perPage = $request->filled('per_page') && $request->per_page !== ""
                ? ($request->per_page === 'All' ? stp_enquiry::count() : (int)$request->per_page)
                : 10;

            $request->validate([
                'subject' => 'integer'
            ]);

            $enquiryList = stp_enquiry::when($request->subject, function ($query, $subject) {
                return $query->where('enquiry_subject', $subject);
            })
                ->whereIn('enquiry_status', [1, 2])
                ->paginate($perPage)
                ->through(function ($enquiry) {
                    return [
                        'id' => $enquiry->id,
                        'enquiry_name' => $enquiry->enquiry_name,
                        'enquiry_email' => $enquiry->enquiry_email,
                        'enquiry_phone' => $enquiry->enquiry_phone,
                        'enquiry_subject' => $enquiry->subject->core_metaName ?? null,
                        'enquiry_message' => $enquiry->enquiry_message,
                        'enquiry_status' => $enquiry->enquiry_status,
                    ];
                });

            return response()->json($enquiryList);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function enquiryDetail(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);

            $enquiry = stp_enquiry::find($request->id);

            if (!$enquiry) {
                return response()->json([
                    'success' => false,
                    'message' => "Enquiry not found"
                ]);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $enquiry->id,
                    'name' => $enquiry->enquiry_name,
                    'email' => $enquiry->enquiry_email,
                    'phone' => $enquiry->enquiry_phone,
                    'subject' => $enquiry->subject->core_metaName ?? null,
                    'message' => $enquiry->enquiry_message,
                    'status' => $enquiry->enquiry_status,
                    'messageContent'=> $enquiry->enquiry_reply_message ?? ''
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'errors' => $e->getMessage()
            ]);
        }
    }

    public function replyEnquiry(Request $request)
    {
        $request->validate([
            'enquiryId' => 'required|integer',
            'subject' => 'required|string|max:255',
            'email' => 'required|email',
            'messageContent' => 'required|string|max:1000',

        ]);
        $findEnquiry = stp_enquiry::find($request->enquiryId);

        $this->serviceFunctionController->replyEnquiryEmail($request->subject, $request->email, $request->messageContent);
        $findEnquiry->update(
            [
                'enquiry_status' => 1,
                'enquiry_reply_message' => $request->messageContent,
            ]
        );

        return 'reply successfully';
    }
}
