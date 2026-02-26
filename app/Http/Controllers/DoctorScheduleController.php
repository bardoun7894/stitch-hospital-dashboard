<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;

class DoctorScheduleController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function show($doctorId)
    {
        try {
            // Fetch single doctor directly instead of loading all doctors (N+1 fix)
            $doctor = $this->firebase->getDoctorById($doctorId);

            if (!$doctor) {
                return redirect()->route('doctors.index')->with('error', __('messages.doctor_not_found'));
            }

            // Get unavailability
            $unavailability = $this->firebase->getDoctorUnavailability($doctorId);

            return view('doctors.schedule', compact('doctor', 'unavailability'));
        } catch (\Throwable $e) {
            Log::error('Load doctor schedule error: ' . $e->getMessage());
            return redirect()->route('doctors.index')->with('error', __('messages.unknown_error'));
        }
    }

    public function update(Request $request, $doctorId)
    {
        // Ownership check: doctors can only edit their own schedule
        $currentUser = RoleMiddleware::getCurrentUser();
        if (($currentUser['role'] ?? '') === 'doctor' && ($currentUser['doctor_id'] ?? '') !== $doctorId) {
            abort(403);
        }

        $data = $request->validate([
            'slot_duration' => 'required|integer|min:5|max:60',
            'working_hours' => 'required|array',
            'duty_days' => 'nullable|array',
            'duty_month' => 'nullable|string',
        ]);

        try {
            // Transform working_hours for Firestore
            // New format per day: am_active, am_start, am_end, pm_active, pm_start, pm_end
            $transformedHours = [];
            foreach ($data['working_hours'] as $day => $hours) {
                $transformedHours[$day] = [
                    'am_active' => (bool)($hours['am_active'] ?? false),
                    'am_start' => $hours['am_start'] ?? '08:00',
                    'am_end' => $hours['am_end'] ?? '12:00',
                    'pm_active' => (bool)($hours['pm_active'] ?? false),
                    'pm_start' => $hours['pm_start'] ?? '16:00',
                    'pm_end' => $hours['pm_end'] ?? '21:00',
                    // Keep backward-compatible 'active' flag: true if either session is active
                    'active' => (bool)($hours['am_active'] ?? false) || (bool)($hours['pm_active'] ?? false),
                ];
            }

            // Build duty_days map keyed by month (e.g., "2026-02")
            $schedulePayload = [
                'working_hours' => $transformedHours,
                'slot_duration' => (int)$data['slot_duration'],
            ];

            if (!empty($data['duty_month'])) {
                // Merge with existing duty_days so we don't lose other months
                $doctor = $this->firebase->getDoctorById($doctorId);
                $existingDutyDays = $doctor['duty_days'] ?? [];

                $dutyDays = !empty($data['duty_days']) ? array_map('intval', $data['duty_days']) : [];
                $existingDutyDays[$data['duty_month']] = $dutyDays;

                $schedulePayload['duty_days'] = $existingDutyDays;
            }

            $this->firebase->updateDoctorSchedule($doctorId, $schedulePayload);

            return redirect()->back()->with('success', __('messages.schedule_updated'));
        } catch (\Throwable $e) {
            Log::error('Update doctor schedule error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('messages.settings_update_failed'));
        }
    }

    public function updateDutyDays(Request $request, $doctorId)
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        if (($currentUser['role'] ?? '') === 'doctor' && ($currentUser['doctor_id'] ?? '') !== $doctorId) {
            abort(403);
        }

        $data = $request->validate([
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'days' => 'present|array',
            'days.*' => 'integer|min:1|max:31',
        ]);

        try {
            $doctor = $this->firebase->getDoctorById($doctorId);
            $existingDutyDays = $doctor['duty_days'] ?? [];
            $existingDutyDays[$data['month']] = array_map('intval', $data['days']);

            $this->firebase->updateDutyDays($doctorId, $existingDutyDays);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Update duty days error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function addBlockout(Request $request, $doctorId)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string'
        ]);

        try {
            $this->firebase->addDoctorUnavailability($doctorId, [
                'start' => new \DateTime($data['start_date']),
                'end' => new \DateTime($data['end_date'] . ' 23:59:59'),
                'reason' => $data['reason']
            ]);

            return redirect()->back()->with('success', __('messages.blockout_added'));
        } catch (\Throwable $e) {
            Log::error('Add blockout error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('messages.unknown_error'));
        }
    }

    public function removeBlockout($doctorId, $unavailabilityId)
    {
        try {
            $this->firebase->removeDoctorUnavailability($doctorId, $unavailabilityId);
            return redirect()->back()->with('success', __('messages.blockout_removed'));
        } catch (\Throwable $e) {
            Log::error('Remove blockout error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('messages.unknown_error'));
        }
    }
}
