<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;

class CouponsController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * List all coupons with stats.
     */
    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $currentUser['clinic_id'] ?? null;
            $role = $currentUser['role'] ?? 'patient';

            $filters = [];
            if ($role === 'clinic_admin' && $clinicId) {
                // Clinic admins see coupons for their clinic + global coupons
                // We fetch all and filter in view
            }

            $coupons = $this->firebase->getCoupons($filters);

            // Get clinics for display names
            $clinicsRaw = $this->firebase->getClinicsRaw();
            $clinicNames = [];
            foreach ($clinicsRaw as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $clinicNames[$doc->id()] = $data['name'] ?? $data['name_en'] ?? $doc->id();
            }

            return view('coupons.index', compact('coupons', 'clinicNames'));
        } catch (\Throwable $e) {
            Log::error('Coupons index error: ' . $e->getMessage());
            $coupons = [];
            $clinicNames = [];
            return view('coupons.index', compact('coupons', 'clinicNames'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Show create coupon form.
     */
    public function create()
    {
        try {
            $clinicsRaw = $this->firebase->getClinicsRaw();
            $clinics = [];
            foreach ($clinicsRaw as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();
                $clinics[] = $data;
            }

            return view('coupons.create', compact('clinics'));
        } catch (\Throwable $e) {
            Log::error('Coupons create form error: ' . $e->getMessage());
            $clinics = [];
            return view('coupons.create', compact('clinics'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Validate and store a new coupon.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after_or_equal:valid_from',
            'max_uses' => 'required|integer|min:0',
            'clinic_id' => 'nullable|string',
            'is_active' => 'nullable',
        ]);

        // Extra validation: percentage must be 1-100
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Percentage must be between 1 and 100'])->withInput();
        }

        try {
            $currentUser = RoleMiddleware::getCurrentUser();

            $validated['is_active'] = $request->has('is_active');
            $validated['created_by'] = $currentUser['uid'] ?? null;

            $this->firebase->createCoupon($validated);

            return redirect()->route('coupons.index')
                ->with('success', __('messages.coupon_created'));
        } catch (\Exception $e) {
            Log::error('Coupon store error: ' . $e->getMessage());
            $message = str_contains($e->getMessage(), 'already exists')
                ? __('messages.invalid_coupon') . ' - Code already exists'
                : __('messages.unknown_error');
            return back()->with('error', $message)->withInput();
        }
    }

    /**
     * Show edit coupon form.
     */
    public function edit(string $id)
    {
        try {
            $coupons = $this->firebase->getCoupons();
            $coupon = collect($coupons)->firstWhere('id', $id);

            if (!$coupon) {
                return redirect()->route('coupons.index')
                    ->with('error', __('messages.invalid_coupon'));
            }

            $clinicsRaw = $this->firebase->getClinicsRaw();
            $clinics = [];
            foreach ($clinicsRaw as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();
                $clinics[] = $data;
            }

            return view('coupons.edit', compact('coupon', 'clinics'));
        } catch (\Throwable $e) {
            Log::error('Coupon edit error: ' . $e->getMessage());
            return redirect()->route('coupons.index')
                ->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Update an existing coupon.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after_or_equal:valid_from',
            'max_uses' => 'required|integer|min:0',
            'clinic_id' => 'nullable|string',
            'is_active' => 'nullable',
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Percentage must be between 1 and 100'])->withInput();
        }

        try {
            $validated['is_active'] = $request->has('is_active');

            $success = $this->firebase->updateCoupon($id, $validated);

            if ($success) {
                return redirect()->route('coupons.index')
                    ->with('success', __('messages.coupon_updated'));
            }

            return back()->with('error', __('messages.unknown_error'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Coupon update error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'))->withInput();
        }
    }

    /**
     * Delete a coupon.
     */
    public function destroy(string $id)
    {
        try {
            $this->firebase->deleteCoupon($id);
            return redirect()->route('coupons.index')
                ->with('success', __('messages.coupon_deleted'));
        } catch (\Throwable $e) {
            Log::error('Coupon delete error: ' . $e->getMessage());
            return redirect()->route('coupons.index')
                ->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Toggle coupon active/inactive status.
     */
    public function toggle(string $id)
    {
        try {
            $coupons = $this->firebase->getCoupons();
            $coupon = collect($coupons)->firstWhere('id', $id);

            if (!$coupon) {
                return redirect()->route('coupons.index')
                    ->with('error', __('messages.invalid_coupon'));
            }

            $newStatus = !($coupon['is_active'] ?? false);
            $this->firebase->updateCoupon($id, ['is_active' => $newStatus]);

            $message = $newStatus
                ? __('messages.coupon_activated')
                : __('messages.coupon_deactivated');

            return redirect()->route('coupons.index')->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Coupon toggle error: ' . $e->getMessage());
            return redirect()->route('coupons.index')
                ->with('error', __('messages.unknown_error'));
        }
    }
}
