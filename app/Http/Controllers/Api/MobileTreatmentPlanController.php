<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MobileTreatmentPlanController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Check if the current user has an active treatment plan with a doctor.
     * GET /api/mobile/treatment-plans/check?doctor_id=X
     */
    public function check(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $doctorId = $request->query('doctor_id');

        if (!$doctorId) {
            return response()->json([
                'success' => false,
                'message' => 'doctor_id is required',
            ], 400);
        }

        try {
            $hasActivePlan = $this->firebaseService->hasActiveTreatmentPlan($doctorId, $userId);
            $plan = $hasActivePlan
                ? $this->firebaseService->getActiveTreatmentPlan($doctorId, $userId)
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'has_active_plan' => $hasActivePlan,
                    'plan' => $plan,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Treatment plan check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check treatment plan',
            ], 500);
        }
    }

    /**
     * Get all active treatment plans for the current user.
     * GET /api/mobile/treatment-plans
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        try {
            $firestore = $this->firebaseService->getFirestore();
            if (!$firestore) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $snapshot = $firestore->collection('treatment_plans')
                ->where('patient_id', '=', $userId)
                ->where('status', '=', 'active')
                ->documents();

            $plans = [];
            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                // Format timestamps
                if (isset($data['created_at']) && $data['created_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['created_at'] = $data['created_at']->get()->format('c');
                }
                if (isset($data['updated_at']) && $data['updated_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['updated_at'] = $data['updated_at']->get()->format('c');
                }

                $plans[] = $data;
            }

            return response()->json([
                'success' => true,
                'data' => $plans,
            ]);
        } catch (\Throwable $e) {
            Log::error('Get treatment plans error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve treatment plans',
            ], 500);
        }
    }

    private function resolveUserId(Request $request): ?string
    {
        $uid = data_get($request->input('firebase_user'), 'uid');
        return is_string($uid) && $uid !== '' ? $uid : null;
    }
}
