<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;

class DoctorScheduleController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function show($doctorId)
    {
        $doctors = $this->firebase->getDoctors();
        // Find the specific doctor
        $doctor = null;
        foreach ($doctors as $d) {
            if ($d['id'] === $doctorId) {
                $doctor = $d;
                break;
            }
        }

        if (!$doctor) {
            return redirect()->route('doctors.index')->with('error', __('messages.doctor_not_found'));
        }

        // Get unavailability
        $unavailability = $this->firebase->getDoctorUnavailability($doctorId);

        return view('doctors.schedule', compact('doctor', 'unavailability'));
    }

    public function update(Request $request, $doctorId)
    {
        $data = $request->validate([
            'slot_duration' => 'required|integer|min:5|max:60',
            'working_hours' => 'required|array',
        ]);

        // Transform working_hours for Firestore (if needed) or keep as array
        // Expected format: 'monday' => ['start' => '09:00', 'end' => '17:00', 'active' => true]

        $this->firebase->updateDoctorSchedule($doctorId, [
            'working_hours' => $data['working_hours'],
            'slot_duration' => (int)$data['slot_duration']
        ]);

        return redirect()->back()->with('success', __('messages.schedule_updated'));
    }

    public function addBlockout(Request $request, $doctorId)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string'
        ]);

        $this->firebase->addDoctorUnavailability($doctorId, [
            'start' => new \DateTime($data['start_date']),
            'end' => new \DateTime($data['end_date'] . ' 23:59:59'),
            'reason' => $data['reason']
        ]);

        return redirect()->back()->with('success', __('messages.blockout_added'));
    }

    public function removeBlockout($doctorId, $unavailabilityId)
    {
        $this->firebase->removeDoctorUnavailability($doctorId, $unavailabilityId);
        return redirect()->back()->with('success', __('messages.blockout_removed'));
    }
}
