<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use App\Mail\HospitalApprovedMail;
use App\Mail\HospitalRejectedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HospitalController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index(Request $request)
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $statusFilter = $request->input('status', 'all');

            $cacheKey = 'hospital_index_' . md5("{$role}_{$hospitalId}");

            $hospitals = Cache::remember($cacheKey, 60, function () use ($role, $hospitalId) {
                if ($role === 'super_admin') {
                    $hospitals = $this->firebaseService->getHospitals();
                } elseif ($hospitalId) {
                    $hospital = $this->firebaseService->getHospitalById($hospitalId);
                    $hospitals = $hospital ? [$hospital] : [];
                } else {
                    $hospitals = [];
                }

                // Get clinic counts per hospital
                $allClinics = $this->firebaseService->getClinics();
                $clinicsByHospital = [];
                foreach ($allClinics as $clinic) {
                    $hid = $clinic['hospital_id'] ?? 'standalone';
                    $clinicsByHospital[$hid] = ($clinicsByHospital[$hid] ?? 0) + 1;
                }

                foreach ($hospitals as &$h) {
                    $h['clinic_count'] = $clinicsByHospital[$h['id']] ?? 0;
                }
                unset($h);

                return $hospitals;
            });

            // Count by status for tabs
            $statusCounts = ['all' => count($hospitals), 'waiting' => 0, 'pending' => 0, 'active' => 0, 'blocked' => 0, 'rejected' => 0, 'inactive' => 0];
            foreach ($hospitals as $h) {
                $s = $h['status'] ?? 'active';
                if (isset($statusCounts[$s])) {
                    $statusCounts[$s]++;
                }
            }

            // Filter by status
            if ($statusFilter !== 'all') {
                $hospitals = array_values(array_filter($hospitals, fn($h) => ($h['status'] ?? 'active') === $statusFilter));
            }

            return view('hospital.index', compact('hospitals', 'statusFilter', 'statusCounts'));
        } catch (\Throwable $e) {
            Log::error('Hospital index error: ' . $e->getMessage());
            $hospitals = [];
            $statusFilter = 'all';
            $statusCounts = ['all' => 0, 'waiting' => 0, 'pending' => 0, 'active' => 0, 'blocked' => 0, 'rejected' => 0, 'inactive' => 0];
            return view('hospital.index', compact('hospitals', 'statusFilter', 'statusCounts'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function create()
    {
        return view('hospital.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? '';

            // Super admin creates hospitals as active, others go to pending
            $validated['status'] = ($role === 'super_admin') ? 'active' : 'pending';
            $validated['submitted_by'] = $currentUser['id'] ?? null;

            $hospitalId = $this->firebaseService->createHospital($validated);

            if ($hospitalId) {
                $this->firebaseService->logActivity('hospital.created', [
                    'hospital_id' => $hospitalId,
                    'hospital_name' => $validated['name'],
                ]);

                // Create clinics & doctors submitted from the wizard
                $clinicsJson = $request->input('clinics_json', '[]');
                $doctorsJson = $request->input('doctors_json', '[]');
                $clinicsData = json_decode($clinicsJson, true) ?: [];
                $doctorsData = json_decode($doctorsJson, true) ?: [];

                $clinicIdMap = []; // maps clinic_index => created clinic_id
                foreach ($clinicsData as $index => $clinicInput) {
                    if (empty($clinicInput['name'])) continue;
                    $clinicId = $this->firebaseService->createClinic([
                        'name' => $clinicInput['name'],
                        'name_en' => $clinicInput['name_en'] ?? '',
                        'specialty' => $clinicInput['specialty'] ?? '',
                        'hospital_id' => $hospitalId,
                        'status' => 'active',
                    ]);
                    if ($clinicId) {
                        $clinicIdMap[$index] = $clinicId;
                    }
                }

                foreach ($doctorsData as $doctorInput) {
                    if (empty($doctorInput['name']) || empty($doctorInput['specialty'])) continue;
                    $clinicIndex = $doctorInput['clinic_index'] ?? -1;
                    $clinicId = $clinicIdMap[$clinicIndex] ?? '';
                    if (empty($clinicId)) continue;

                    $this->firebaseService->createDoctor([
                        'name' => $doctorInput['name'],
                        'name_en' => $doctorInput['name_en'] ?? '',
                        'specialty' => $doctorInput['specialty'],
                        'phone' => $doctorInput['phone'] ?? '',
                        'clinic_id' => $clinicId,
                        'hospital_id' => $hospitalId,
                        'status' => 'available',
                    ]);
                }

                if ($role === 'super_admin') {
                    return redirect()->route('hospital.show', $hospitalId)->with('success', __('messages.hospital_created'));
                }
                return redirect()->route('hospital.show', ['id' => $hospitalId, 'setup' => 1])
                    ->with('success', __('messages.hospital_submitted'));
            }

            return back()->with('error', __('messages.hospital_create_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Create hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.hospital_create_failed'))->withInput();
        }
    }

    public function show($id)
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';

            // Ownership check: hospital_manager can only see their hospital
            if ($role !== 'super_admin' && ($currentUser['hospital_id'] ?? null) !== $id) {
                abort(403);
            }

            $hospital = $this->firebaseService->getHospitalById($id);
            if (!$hospital) {
                return redirect()->route('hospital.index')->with('error', __('messages.hospital_not_found'));
            }

            // Get clinics for this hospital
            $clinics = $this->firebaseService->getClinicsForHospital($id);

            // Get doctors per clinic
            $hasDoctors = false;
            foreach ($clinics as &$clinic) {
                $doctors = $this->firebaseService->getDoctors($clinic['id']);
                $clinic['doctors'] = $doctors;
                $clinic['doctor_count'] = count($doctors);
                if ($clinic['doctor_count'] > 0) {
                    $hasDoctors = true;
                }
            }
            unset($clinic);

            $totalDoctors = array_sum(array_column($clinics, 'doctor_count'));

            // Setup progress
            $setupProgress = [
                'hospital_created' => true,
                'has_clinics' => count($clinics) > 0,
                'has_doctors' => $hasDoctors,
                'pending_approval' => ($hospital['status'] ?? '') === 'pending',
            ];

            $isSetupMode = request('setup') == 1;

            return view('hospital.show', compact('hospital', 'clinics', 'totalDoctors', 'setupProgress', 'isSetupMode'));
        } catch (\Throwable $e) {
            Log::error('Hospital show error: ' . $e->getMessage());
            return redirect()->route('hospital.index')->with('error', __('messages.hospital_not_found'));
        }
    }

    public function edit($id)
    {
        try {
            $hospital = $this->firebaseService->getHospitalById($id);
            if (!$hospital) {
                return redirect()->route('hospital.index')->with('error', __('messages.hospital_not_found'));
            }

            return view('hospital.edit', compact('hospital'));
        } catch (\Throwable $e) {
            Log::error('Hospital edit form error: ' . $e->getMessage());
            return redirect()->route('hospital.index')->with('error', __('messages.hospital_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $success = $this->firebaseService->updateHospital($id, $validated);

            if ($success) {
                $this->firebaseService->logActivity('hospital.updated', [
                    'hospital_id' => $id,
                    'hospital_name' => $validated['name'],
                ]);

                return redirect()->route('hospital.show', $id)->with('success', __('messages.hospital_updated'));
            }

            return back()->with('error', __('messages.hospital_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.hospital_update_failed'))->withInput();
        }
    }

    public function approve($id)
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        if (($currentUser['role'] ?? '') !== 'super_admin') {
            abort(403);
        }

        try {
            $success = $this->firebaseService->approveHospital($id, $currentUser['id'] ?? '');

            if ($success) {
                $this->firebaseService->logActivity('hospital.approved', [
                    'hospital_id' => $id,
                ]);

                // Send approval email notification
                $hospital = $this->firebaseService->getHospitalById($id);
                if ($hospital && !empty($hospital['contact_email'])) {
                    try {
                        Mail::to($hospital['contact_email'])->send(new HospitalApprovedMail($hospital));
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send approval email: ' . $e->getMessage());
                    }
                }

                return redirect()->route('hospital.show', $id)->with('success', __('messages.hospital_approved_success'));
            }

            return back()->with('error', __('messages.unknown_error'));
        } catch (\Throwable $e) {
            Log::error('Approve hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    public function reject(Request $request, $id)
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        if (($currentUser['role'] ?? '') !== 'super_admin') {
            abort(403);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $reason = $request->input('rejection_reason');
            $success = $this->firebaseService->rejectHospital($id, $currentUser['id'] ?? '', $reason);

            if ($success) {
                $this->firebaseService->logActivity('hospital.rejected', [
                    'hospital_id' => $id,
                    'reason' => $reason,
                ]);

                // Send rejection email notification
                $hospital = $this->firebaseService->getHospitalById($id);
                if ($hospital && !empty($hospital['contact_email'])) {
                    try {
                        Mail::to($hospital['contact_email'])->send(new HospitalRejectedMail($hospital, $reason));
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send rejection email: ' . $e->getMessage());
                    }
                }

                return redirect()->route('hospital.show', $id)->with('success', __('messages.hospital_rejected_success'));
            }

            return back()->with('error', __('messages.unknown_error'));
        } catch (\Throwable $e) {
            Log::error('Reject hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Change hospital status (super_admin only).
     * Supports: active, waiting, blocked, inactive, pending
     */
    public function changeStatus(Request $request, $id)
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        if (($currentUser['role'] ?? '') !== 'super_admin') {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:active,waiting,blocked,inactive,pending',
        ]);

        try {
            $newStatus = $request->input('status');
            $success = $this->firebaseService->updateHospital($id, ['status' => $newStatus]);

            if ($success) {
                Cache::flush();

                $this->firebaseService->logActivity('hospital.status_changed', [
                    'hospital_id' => $id,
                    'new_status' => $newStatus,
                ]);

                return redirect()->route('hospital.show', $id)->with('success', __('messages.hospital_status_changed'));
            }

            return back()->with('error', __('messages.unknown_error'));
        } catch (\Throwable $e) {
            Log::error('Change hospital status error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    public function resubmit($id)
    {
        try {
            $success = $this->firebaseService->resubmitHospital($id);

            if ($success) {
                return redirect()->route('hospital.show', $id)->with('success', __('messages.hospital_resubmitted'));
            }

            return back()->with('error', __('messages.unknown_error'));
        } catch (\Throwable $e) {
            Log::error('Resubmit hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    public function uploadLogo(Request $request, $id)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {
            $hospital = $this->firebaseService->getHospitalById($id);
            if (!$hospital) {
                return back()->with('error', __('messages.hospital_not_found'));
            }

            $file = $request->file('logo');
            $imageData = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();
            $dataUri = "data:{$mimeType};base64,{$imageData}";

            $success = $this->firebaseService->updateHospital($id, ['logo_url' => $dataUri]);

            if ($success) {
                $this->firebaseService->logActivity('hospital.updated', [
                    'hospital_id' => $id,
                    'hospital_name' => $hospital['name'] ?? '',
                    'action' => 'logo_uploaded',
                ]);

                return back()->with('success', __('messages.logo_uploaded'));
            }

            return back()->with('error', __('messages.logo_upload_failed'));
        } catch (\Throwable $e) {
            Log::error('Upload hospital logo error: ' . $e->getMessage());
            return back()->with('error', __('messages.logo_upload_failed'));
        }
    }

    public function destroy($id)
    {
        try {
            $success = $this->firebaseService->deleteHospital($id);

            if ($success) {
                $this->firebaseService->logActivity('hospital.deleted', [
                    'hospital_id' => $id,
                ]);
            }

            if (request()->expectsJson()) {
                return response()->json(['success' => $success]);
            }

            if ($success) {
                return redirect()->route('hospital.index')->with('success', __('messages.hospital_deleted'));
            }

            return back()->with('error', __('messages.hospital_delete_failed'));
        } catch (\Throwable $e) {
            Log::error('Delete hospital error: ' . $e->getMessage());
            if (request()->expectsJson()) {
                return response()->json(['success' => false], 500);
            }

            return back()->with('error', __('messages.hospital_delete_failed'));
        }
    }
}
