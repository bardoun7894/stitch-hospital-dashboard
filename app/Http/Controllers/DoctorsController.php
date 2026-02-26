<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DoctorsController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $currentUser['clinic_id'] ?? null;
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $role = $currentUser['role'] ?? 'patient';

            $cacheKey = 'doctors_index_' . md5("{$role}_{$clinicId}_{$hospitalId}");

            $data = Cache::remember($cacheKey, 45, function () use ($role, $clinicId, $hospitalId) {
                if ($role === 'super_admin') {
                    $doctors = $this->firebase->getDoctors();
                } elseif ($role === 'hospital_manager' && $hospitalId) {
                    $doctors = $this->firebase->getDoctorsForHospital($hospitalId);
                } elseif ($clinicId) {
                    $doctors = $this->firebase->getDoctors($clinicId);
                } else {
                    $doctors = $this->firebase->getDoctors();
                }

                // Build clinic name lookup
                $clinics = $this->firebase->getClinics();
                $clinicNames = [];
                foreach ($clinics as $clinic) {
                    $clinicNames[$clinic['id']] = $clinic['name'] ?? $clinic['id'];
                }

                return ['doctors' => $doctors, 'clinicNames' => $clinicNames];
            });

            $doctors = $data['doctors'];
            $clinicNames = $data['clinicNames'];

            // Enrich doctors with clinic names
            foreach ($doctors as &$doctor) {
                $cid = $doctor['clinic_id'] ?? $doctor['clinic'] ?? '';
                $doctor['clinic'] = $clinicNames[$cid] ?? $cid;
            }
            unset($doctor);

            return view('doctors.index', compact('doctors'));
        } catch (\Throwable $e) {
            Log::error('Doctors index error: ' . $e->getMessage());
            $doctors = [];
            return view('doctors.index', compact('doctors'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function create()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';

            if ($role === 'super_admin') {
                $clinics = $this->firebase->getClinics();
            } elseif ($currentUser['hospital_id'] ?? null) {
                $clinics = $this->firebase->getClinicsForHospital($currentUser['hospital_id']);
            } elseif ($currentUser['clinic_id'] ?? null) {
                $clinic = $this->firebase->getClinic($currentUser['clinic_id']);
                $clinics = $clinic ? [$clinic] : [];
            } else {
                $clinics = $this->firebase->getClinics();
            }

            return view('doctors.create', compact('clinics'));
        } catch (\Throwable $e) {
            Log::error('Doctors create form error: ' . $e->getMessage());
            $clinics = [];
            return view('doctors.create', compact('clinics'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'specialty' => 'required|string|max:255',
            'clinic_id' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,busy,off',
            'bio' => 'nullable|string|max:2000',
            'bio_en' => 'nullable|string|max:2000',
            'education' => 'nullable|string|max:500',
            'years_experience' => 'nullable|integer|min:0|max:80',
            'certifications' => 'nullable|array',
            'certifications.*' => 'string|max:255',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:100',
            'generate_credentials' => 'nullable',
            'login_email' => 'nullable|required_if:generate_credentials,1|email|max:255',
            'login_password' => 'nullable|required_if:generate_credentials,1|string|min:6',
            'assigned_clinic_ids' => 'nullable|array',
            'assigned_clinic_ids.*' => 'string',
        ]);

        // Ownership check: ensure the clinic belongs to the user's hospital
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        if (!empty($validated['clinic_id'])) {
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                $clinic = $this->firebase->getClinic($validated['clinic_id']);
                if (!$clinic || ($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    return back()->with('error', 'This clinic does not belong to your hospital.')->withInput();
                }
            } elseif (($role === 'clinic_admin' || $role === 'reception') && ($currentUser['clinic_id'] ?? null)) {
                $validated['clinic_id'] = $currentUser['clinic_id'];
            }
        }

        try {
            $id = $this->firebase->createDoctor($validated);

            if ($id) {
                $this->firebase->logActivity('doctor.created', [
                    'doctor_id' => $id,
                    'doctor_name' => $validated['name'],
                    'specialty' => $validated['specialty'],
                ]);

                // Generate login credentials if requested
                if ($request->boolean('generate_credentials') && !empty($validated['login_email']) && !empty($validated['login_password'])) {
                    // Determine hospital_id from the clinic or from current user
                    $hospitalId = $currentUser['hospital_id'] ?? null;
                    if (!$hospitalId && !empty($validated['clinic_id'])) {
                        $doctorClinic = $this->firebase->getClinic($validated['clinic_id']);
                        $hospitalId = $doctorClinic['hospital_id'] ?? null;
                    }

                    $userId = $this->firebase->createStaffUser([
                        'name' => $validated['name'],
                        'email' => $validated['login_email'],
                        'password' => $validated['login_password'],
                        'role' => 'doctor',
                        'clinic_id' => $validated['clinic_id'] ?? null,
                        'hospital_id' => $hospitalId,
                        'phone' => $validated['phone'] ?? '',
                    ]);

                    if ($userId) {
                        // Link user_id to doctor record
                        $updateData = ['user_id' => $userId];
                        if (!empty($validated['assigned_clinic_ids'])) {
                            $updateData['assigned_clinic_ids'] = $validated['assigned_clinic_ids'];
                        }
                        $this->firebase->updateDoctor($id, $updateData);

                        $this->firebase->logActivity('doctor.credentials_created', [
                            'doctor_id' => $id,
                            'user_id' => $userId,
                            'email' => $validated['login_email'],
                        ]);
                    }
                }

                // If in setup mode, redirect back to hospital show page
                if ($request->input('setup') == 1 && !empty($validated['clinic_id'])) {
                    $clinic = $this->firebase->getClinic($validated['clinic_id']);
                    if ($clinic && !empty($clinic['hospital_id'])) {
                        return redirect()->route('hospital.show', ['id' => $clinic['hospital_id'], 'setup' => 1])
                            ->with('success', __('messages.doctor_created'));
                    }
                }
                return redirect()->route('doctors.edit', $id)->with('success', __('messages.doctor_created'));
            }

            return back()->with('error', __('messages.doctor_creation_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Create doctor error: ' . $e->getMessage());
            return back()->with('error', __('messages.doctor_creation_failed'))->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $doctor = $this->firebase->getDoctorById($id);
            if (!$doctor) {
                return redirect()->route('doctors.index')->with('error', __('messages.doctor_not_found'));
            }

            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';

            // Ownership check: hospital_manager can only edit doctors in their hospital's clinics
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                $doctorClinic = $this->firebase->getClinic($doctor['clinic_id'] ?? $doctor['clinic'] ?? '');
                if (!$doctorClinic || ($doctorClinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    abort(403);
                }
                $clinics = $this->firebase->getClinicsForHospital($currentUser['hospital_id']);
            } elseif (($role === 'clinic_admin' || $role === 'reception') && ($currentUser['clinic_id'] ?? null)) {
                $doctorClinicId = $doctor['clinic_id'] ?? $doctor['clinic'] ?? '';
                if ($doctorClinicId !== $currentUser['clinic_id']) {
                    abort(403);
                }
                $clinic = $this->firebase->getClinic($currentUser['clinic_id']);
                $clinics = $clinic ? [$clinic] : [];
            } elseif ($role === 'super_admin') {
                $clinics = $this->firebase->getClinics();
            } else {
                $clinics = $this->firebase->getClinics();
            }

            return view('doctors.edit', compact('doctor', 'clinics'));
        } catch (\Throwable $e) {
            Log::error('Doctors edit form error: ' . $e->getMessage());
            return redirect()->route('doctors.index')->with('error', __('messages.doctor_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        // Ownership check: ensure doctor belongs to user's hospital/clinic
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $doctor = $this->firebase->getDoctorById($id);
            if ($doctor) {
                $doctorClinic = $this->firebase->getClinic($doctor['clinic_id'] ?? $doctor['clinic'] ?? '');
                if (!$doctorClinic || ($doctorClinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    abort(403);
                }
            }
        } elseif (($role === 'clinic_admin' || $role === 'reception') && ($currentUser['clinic_id'] ?? null)) {
            $doctor = $this->firebase->getDoctorById($id);
            if ($doctor && ($doctor['clinic_id'] ?? $doctor['clinic'] ?? '') !== $currentUser['clinic_id']) {
                abort(403);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'specialty' => 'required|string|max:255',
            'clinic_id' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,busy,off',
            'bio' => 'nullable|string|max:2000',
            'bio_en' => 'nullable|string|max:2000',
            'education' => 'nullable|string|max:500',
            'years_experience' => 'nullable|integer|min:0|max:80',
            'certifications' => 'nullable|array',
            'certifications.*' => 'string|max:255',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:100',
        ]);

        // Prevent reassigning doctor to a clinic outside user's hospital
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $targetClinic = $this->firebase->getClinic($validated['clinic_id']);
            if (!$targetClinic || ($targetClinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                return back()->with('error', 'This clinic does not belong to your hospital.')->withInput();
            }
        } elseif (($role === 'clinic_admin' || $role === 'reception') && ($currentUser['clinic_id'] ?? null)) {
            $validated['clinic_id'] = $currentUser['clinic_id'];
        }

        try {
            $success = $this->firebase->updateDoctor($id, $validated);

            if ($success) {
                $this->firebase->logActivity('doctor.updated', [
                    'doctor_id' => $id,
                    'doctor_name' => $validated['name'],
                    'specialty' => $validated['specialty'],
                ]);

                return redirect()->route('doctors.index')->with('success', __('messages.doctor_updated'));
            }

            return back()->with('error', __('messages.doctor_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update doctor error: ' . $e->getMessage());
            return back()->with('error', __('messages.doctor_update_failed'))->withInput();
        }
    }

    public function uploadPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {
            $doctor = $this->firebase->getDoctorById($id);
            if (!$doctor) {
                return back()->with('error', __('messages.doctor_not_found'));
            }

            $file = $request->file('photo');
            $imageData = base64_encode(file_get_contents($file->getRealPath()));
            $mimeType = $file->getMimeType();
            $dataUri = "data:{$mimeType};base64,{$imageData}";

            $success = $this->firebase->updateDoctor($id, ['photo_url' => $dataUri]);

            if ($success) {
                $this->firebase->logActivity('doctor.updated', [
                    'doctor_id' => $id,
                    'doctor_name' => $doctor['name'] ?? '',
                    'action' => 'photo_uploaded',
                ]);

                return back()->with('success', __('messages.photo_uploaded'));
            }

            return back()->with('error', __('messages.photo_upload_failed'));
        } catch (\Throwable $e) {
            Log::error('Upload doctor photo error: ' . $e->getMessage());
            return back()->with('error', __('messages.photo_upload_failed'));
        }
    }

    public function destroy($id)
    {
        try {
            // Ownership check: ensure doctor belongs to user's hospital/clinic
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                $doctor = $this->firebase->getDoctorById($id);
                if ($doctor) {
                    $doctorClinic = $this->firebase->getClinic($doctor['clinic_id'] ?? $doctor['clinic'] ?? '');
                    if (!$doctorClinic || ($doctorClinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                        return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
                    }
                }
            } elseif (($role === 'clinic_admin' || $role === 'reception') && ($currentUser['clinic_id'] ?? null)) {
                $doctor = $this->firebase->getDoctorById($id);
                if ($doctor && ($doctor['clinic_id'] ?? $doctor['clinic'] ?? '') !== $currentUser['clinic_id']) {
                    return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
                }
            }

            $success = $this->firebase->deleteDoctor($id);

            if ($success) {
                $this->firebase->logActivity('doctor.deleted', [
                    'doctor_id' => $id,
                ]);

                return response()->json(['success' => true, 'message' => __('messages.doctor_deleted')]);
            }

            return response()->json(['success' => false, 'error' => __('messages.doctor_delete_failed')], 500);
        } catch (\Throwable $e) {
            Log::error('Delete doctor error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => __('messages.doctor_delete_failed')], 500);
        }
    }
}
