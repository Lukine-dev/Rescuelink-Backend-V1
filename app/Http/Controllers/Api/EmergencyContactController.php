<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmergencyContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the user's emergency contacts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Regular users can only see their own contacts
        // Admin/SuperAdmin can see all contacts
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $contacts = EmergencyContact::with('user:id,first_name,last_name,email')
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);
        } else {
            $contacts = EmergencyContact::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);
        }

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }

    /**
     * Store a newly created emergency contact.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone_number' => 'required|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $data = $request->all();
        $data['user_id'] = $user->id;

        // If setting this as primary, unset primary from other contacts
        if ($request->input('is_primary', false)) {
            EmergencyContact::where('user_id', $user->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $contact = EmergencyContact::create($data);

        // Log the action
        AuditLogController::log(
            $user->id,
            'created',
            EmergencyContact::class,
            $contact->id,
            null,
            $contact->toArray(),
            'Emergency contact created for user: ' . $user->email
        );

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact created successfully',
            'data' => $contact
        ], 201);
    }

    /**
     * Display the specified emergency contact.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = auth()->user();
        $contact = EmergencyContact::with('user:id,first_name,last_name,email')->find($id);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency contact not found'
            ], 404);
        }

        // Check authorization
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $contact->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this contact'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $contact
        ]);
    }

    /**
     * Update the specified emergency contact.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $contact = EmergencyContact::find($id);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency contact not found'
            ], 404);
        }

        // Check authorization
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $contact->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this contact'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'relationship' => 'sometimes|required|string|max:100',
            'phone_number' => 'sometimes|required|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'is_primary' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $contact->toArray();
        $data = $request->all();

        // If setting this as primary, unset primary from other contacts
        if ($request->has('is_primary') && $request->input('is_primary')) {
            EmergencyContact::where('user_id', $contact->user_id)
                ->where('id', '!=', $contact->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $contact->update($data);

        // Log the action
        AuditLogController::log(
            $user->id,
            'updated',
            EmergencyContact::class,
            $contact->id,
            $oldData,
            $contact->toArray(),
            'Emergency contact updated'
        );

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact updated successfully',
            'data' => $contact
        ]);
    }

    /**
     * Remove the specified emergency contact.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $contact = EmergencyContact::find($id);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency contact not found'
            ], 404);
        }

        // Check authorization
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $contact->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this contact'
            ], 403);
        }

        $oldData = $contact->toArray();
        
        // If deleting a primary contact, set another contact as primary if exists
        if ($contact->is_primary) {
            $anotherContact = EmergencyContact::where('user_id', $contact->user_id)
                ->where('id', '!=', $contact->id)
                ->first();
            
            if ($anotherContact) {
                $anotherContact->update(['is_primary' => true]);
            }
        }

        $contact->delete();

        // Log the action
        AuditLogController::log(
            $user->id,
            'deleted',
            EmergencyContact::class,
            $id,
            $oldData,
            null,
            'Emergency contact deleted'
        );

        return response()->json([
            'success' => true,
            'message' => 'Emergency contact deleted successfully'
        ]);
    }

    /**
     * Set a contact as primary for the user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setPrimary($id)
    {
        $user = auth()->user();
        $contact = EmergencyContact::find($id);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency contact not found'
            ], 404);
        }

        // Check authorization
        if (!$user->isAdmin() && !$user->isSuperAdmin() && $contact->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this contact'
            ], 403);
        }

        // Unset primary from other contacts
        EmergencyContact::where('user_id', $contact->user_id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        // Set this contact as primary
        $contact->update(['is_primary' => true]);

        // Log the action
        AuditLogController::log(
            $user->id,
            'updated',
            EmergencyContact::class,
            $contact->id,
            ['is_primary' => false],
            ['is_primary' => true],
            'Set emergency contact as primary'
        );

        return response()->json([
            'success' => true,
            'message' => 'Contact set as primary successfully',
            'data' => $contact
        ]);
    }

    /**
     * Get the user's primary emergency contact.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPrimary()
    {
        $user = auth()->user();
        
        $primaryContact = EmergencyContact::where('user_id', $user->id)
            ->where('is_primary', true)
            ->first();

        if (!$primaryContact) {
            return response()->json([
                'success' => false,
                'message' => 'No primary emergency contact found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $primaryContact
        ]);
    }

    /**
     * Bulk update emergency contacts (replace all user's contacts).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contacts' => 'required|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.relationship' => 'required|string|max:100',
            'contacts.*.phone_number' => 'required|string|max:20',
            'contacts.*.alternate_phone' => 'nullable|string|max:20',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.notes' => 'nullable|string',
            'contacts.*.is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        // Delete existing contacts
        EmergencyContact::where('user_id', $user->id)->delete();
        
        // Create new contacts
        $contacts = [];
        foreach ($request->contacts as $contactData) {
            $contactData['user_id'] = $user->id;
            $contacts[] = EmergencyContact::create($contactData);
        }

        // Log the action
        AuditLogController::log(
            $user->id,
            'bulk_updated',
            EmergencyContact::class,
            null,
            null,
            ['count' => count($contacts)],
            'Bulk updated emergency contacts'
        );

        return response()->json([
            'success' => true,
            'message' => 'Emergency contacts updated successfully',
            'data' => $contacts
        ]);
    }
}