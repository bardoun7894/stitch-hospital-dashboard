<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileProfileController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get user profile
     * GET /api/mobile/profile
     */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->input('firebase_user')['uid'];

        try {
            $profile = $this->firebaseService->getUserProfile($userId);

            return response()->json([
                'success' => true,
                'data' => $profile
            ]);
        } catch (\Exception $e) {
            \Log::error('Get profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_profile')
            ], 404);
        }
    }

    /**
     * Update user profile
     * PUT /api/mobile/profile
     */
    public function update(Request $request): JsonResponse
    {
        $userId = $request->input('firebase_user')['uid'];

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'photo_url' => 'nullable|url',
            'locale' => 'nullable|in:ar,en',
        ]);

        try {
            $this->firebaseService->updateUserProfile($userId, $validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.profile_updated')
            ]);
        } catch (\Exception $e) {
            \Log::error('Update profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.profile_update_failed')
            ], 500);
        }
    }

    /**
     * Get user's family members
     * GET /api/mobile/profile/family
     */
    public function getFamilyMembers(Request $request): JsonResponse
    {
        $userId = $request->input('firebase_user')['uid'];

        try {
            $profile = $this->firebaseService->getUserProfile($userId);
            $familyMembers = $profile['family_members'] ?? [];

            return response()->json([
                'success' => true,
                'data' => $familyMembers
            ]);
        } catch (\Exception $e) {
            \Log::error('Get family members error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_family_members')
            ], 500);
        }
    }

    /**
     * Add family member
     * POST /api/mobile/profile/family
     */
    public function addFamilyMember(Request $request): JsonResponse
    {
        $userId = $request->input('firebase_user')['uid'];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|in:son,daughter,wife,husband,father,mother,other',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'national_id' => 'nullable|string|max:20',
        ]);

        try {
            $this->firebaseService->addFamilyMember($userId, $validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.family_member_added')
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Add family member error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_add_family_member')
            ], 500);
        }
    }

    /**
     * Delete family member
     * DELETE /api/mobile/profile/family/{memberId}
     */
    public function deleteFamilyMember(Request $request, string $memberId): JsonResponse
    {
        $userId = $request->input('firebase_user')['uid'];

        try {
            $this->firebaseService->deleteFamilyMember($userId, $memberId);

            return response()->json([
                'success' => true,
                'message' => __('messages.family_member_deleted')
            ]);
        } catch (\Exception $e) {
            \Log::error('Delete family member error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_delete_family_member')
            ], 500);
        }
    }
}
