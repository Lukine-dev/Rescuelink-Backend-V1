<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // Remove the constructor since it's causing issues
    // public function __construct()
    // {
    //     $this->middleware('auth:api', ['except' => ['login', 'register']]);
    // }

    // ==============================
    // Register a new user
    // ==============================
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'ext_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'user_phone_number' => 'required|string|email|max:11|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'sometimes|in:user,responder,admin,superadmin'
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'ext_name' => $request->ext_name,
            'username' => $request->username,
            'email' => $request->email,
            'user_phone_number'=> $request->user_phone_number,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->respondWithToken($token);
    }

    // ==============================
    // Admin: Create user account
    // ==============================
    public function createUser(Request $request)
    {
        // Check if the authenticated user is an admin or superadmin
        $currentUser = auth()->user();
        
        if (!$currentUser || !in_array($currentUser->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Unauthorized. Only administrators can create user accounts.'
            ], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'ext_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'user_phone_number' => 'required|string|email|max:11|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'role' => 'required|in:user,responder,admin,superadmin',
            'send_welcome_email' => 'boolean',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'ext_name' => $request->ext_name,
            'username' => $request->username,
            'email' => $request->email,
            'user_phone_number'=> $request->user_phone_number,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'email_verified_at' => now(), // Auto-verify for admin-created accounts
        ]);

        // Optionally send welcome email
        if ($request->send_welcome_email) {
            // You would implement email sending logic here
            // Example: Mail::to($user->email)->send(new WelcomeEmail($user, $request->password));
        }

        return response()->json([
            'message' => 'User account created successfully',
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'ext_name' => $user->ext_name,
                'username' => $user->username,
                'email' => $user->email,
                'user_phone_number'=> $user->user_phone_number,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ]
        ], 201);
    }

    // ==============================
    // Reset password with confirmation
    // ==============================
    public function resetPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'error' => 'User not authenticated'
            ], 401);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'Current password is incorrect'
            ], 422);
        }

        // Check if new password is not the same as old password
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'error' => 'New password must be different from current password'
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Logout other devices if using JWT tokens (optional)
        // JWTAuth::invalidate();

        return response()->json([
            'message' => 'Password reset successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]
        ]);
    }

    // ==============================
    // Admin: Reset user password
    // ==============================
    public function adminResetUserPassword(Request $request, $userId)
    {
        // Check if the authenticated user is an admin or superadmin
        $currentUser = auth()->user();
        
        if (!$currentUser || !in_array($currentUser->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Unauthorized. Only administrators can reset user passwords.'
            ], 403);
        }

        $request->validate([
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'send_notification' => 'boolean',
        ]);

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Check if trying to reset own password
        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => 'Please use the regular password reset endpoint for your own account'
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Optionally send password reset notification
        if ($request->send_notification) {
            // You would implement email notification logic here
            // Example: Mail::to($user->email)->send(new PasswordChangedNotification());
        }

        // Invalidate existing tokens for the user (force re-login)
        // This would require additional logic with JWT blacklisting

        return response()->json([
            'message' => 'User password reset successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    // ==============================
    // Admin: Get all users (with pagination)
    // ==============================
    public function getAllUsers(Request $request)
    {
        // Check if the authenticated user is an admin or superadmin
        $currentUser = auth()->user();
        
        if (!$currentUser || !in_array($currentUser->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Unauthorized. Only administrators can view all users.'
            ], 403);
        }

        $perPage = $request->per_page ?? 15;
        $users = User::select(['id', 'first_name', 'middle_name', 'last_name', 'ext_name', 'username', 'email','user_phone_number', 'role', 'created_at', 'updated_at'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    // ==============================
    // Admin: Update user role
    // ==============================
    public function updateUserRole(Request $request, $userId)
    {
        // Check if the authenticated user is an admin or superadmin
        $currentUser = auth()->user();
        
        if (!$currentUser || !in_array($currentUser->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Unauthorized. Only administrators can update user roles.'
            ], 403);
        }

        $request->validate([
            'role' => 'required|in:user,responder,admin,superadmin',
        ]);

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Check if trying to update self (except superadmin can update their own role)
        if ($user->id === $currentUser->id && $currentUser->role !== 'superadmin') {
            return response()->json([
                'error' => 'Cannot modify your own role'
            ], 422);
        }

        // Superadmin specific: don't let non-superadmins modify superadmin roles
        if ($user->role === 'superadmin' && $currentUser->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmins can modify superadmin roles'
            ], 403);
        }

        $oldRole = $user->role;
        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $user->role,
            ]
        ]);
    }

    // ==============================
    // Admin: Delete user account
    // ==============================
    public function deleteUser($userId)
    {
        // Check if the authenticated user is an admin or superadmin
        $currentUser = auth()->user();
        
        if (!$currentUser || !in_array($currentUser->role, ['admin', 'superadmin'])) {
            return response()->json([
                'error' => 'Unauthorized. Only administrators can delete user accounts.'
            ], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Check if trying to delete self
        if ($user->id === $currentUser->id) {
            return response()->json([
                'error' => 'Cannot delete your own account'
            ], 422);
        }

        // Prevent deletion of superadmin accounts by non-superadmins
        if ($user->role === 'superadmin' && $currentUser->role !== 'superadmin') {
            return response()->json([
                'error' => 'Only superadmins can delete superadmin accounts'
            ], 403);
        }

        $user->delete();

        // Invalidate existing tokens for the deleted user
        // This would require additional logic with JWT blacklisting

        return response()->json([
            'message' => 'User account deleted successfully'
        ]);
    }

    // ==============================
    // Login with email
    // ==============================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    // ==============================
    // Get authenticated user info (with relationships)
    // ==============================
    public function profile()
    {
        $user = auth()->user();
        
        // Check if the relationships exist before loading to avoid errors
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'ext_name' => $user->ext_name,
            'username' => $user->username,
            'email' => $user->email,
            'user_phone_number' => $user->user_phone_number,
            'role' => $user->role,
        ];
        
        // Load relationships if they exist
        if (method_exists($user, 'emergencyContacts')) {
            $userData['emergency_contacts'] = $user->emergencyContacts;
        }
        
        if (method_exists($user, 'responder')) {
            $userData['responder'] = $user->responder;
        }
        
        return response()->json($userData);
    }

    // ==============================
    // Alternative: Get basic authenticated user info
    // ==============================
    public function me()
    {
        $user = auth()->user();
        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'ext_name' => $user->ext_name,
            'username' => $user->username,
            'email' => $user->email,
            'user_phone_number' => $user->user_phone_number,
            'role' => $user->role,
        ]);
    }

    // ==============================
    // Logout user
    // ==============================
    public function logout()
    {
        try {
            JWTAuth::logout();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Logged out successfully']);
        }
    }

    // ==============================
    // Refresh JWT token
    // ==============================
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired, login again'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to refresh token'], 401);
        }

        return $this->respondWithToken($token);
    }

    // ==============================
    // Check token validity
    // ==============================
    public function checkToken()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name' => $user->last_name,
                    'ext_name' => $user->ext_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'user_phone_number' => $user->user_phone_number,
                    'role' => $user->role,
                ],
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }

    // ==============================
    // Standard JWT response format
    // ==============================
    protected function respondWithToken($token)
    {
        $user = auth()->user();
        
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'ext_name' => $user->ext_name,
            'username' => $user->username,
            'email' => $user->email,
            'user_phone_number' => $user->user_phone_number,
            'role' => $user->role,
        ];
        
        // Load relationships if they exist
        if (method_exists($user, 'emergencyContacts')) {
            $userData['emergency_contacts'] = $user->emergencyContacts;
        }
        
        if (method_exists($user, 'responder')) {
            $userData['responder'] = $user->responder;
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $userData,
        ]);
    }
}