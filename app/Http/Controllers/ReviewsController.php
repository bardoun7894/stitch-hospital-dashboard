<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;

class ReviewsController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Display the reviews list with filters.
     * Accessible by clinic_admin, hospital_manager, super_admin.
     */
    public function index(Request $request)
    {
        try {
            $filters = [];

            if ($request->filled('doctor_id')) {
                $filters['doctor_id'] = $request->input('doctor_id');
            }
            if ($request->filled('clinic_id')) {
                $filters['clinic_id'] = $request->input('clinic_id');
            }
            if ($request->filled('rating')) {
                $filters['rating'] = $request->input('rating');
            }
            if ($request->filled('date_from')) {
                $filters['date_from'] = $request->input('date_from');
            }
            if ($request->filled('date_to')) {
                $filters['date_to'] = $request->input('date_to');
            }

            $reviews = $this->firebase->getAllReviews($filters);
            $clinics = $this->firebase->getClinics();
            $doctors = $this->firebase->getDoctors();

            // Calculate summary stats
            $totalReviews = count($reviews);
            $avgRating = $totalReviews > 0
                ? round(array_sum(array_column($reviews, 'rating')) / $totalReviews, 1)
                : 0;

            // Rating distribution
            $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            foreach ($reviews as $review) {
                $r = (int) ($review['rating'] ?? 0);
                if ($r >= 1 && $r <= 5) {
                    $distribution[$r]++;
                }
            }

            return view('reviews.index', compact(
                'reviews',
                'clinics',
                'doctors',
                'totalReviews',
                'avgRating',
                'distribution',
                'filters'
            ));
        } catch (\Throwable $e) {
            Log::error('Reviews index error: ' . $e->getMessage());

            return view('reviews.index', [
                'reviews' => [],
                'clinics' => [],
                'doctors' => [],
                'totalReviews' => 0,
                'avgRating' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'filters' => [],
            ]);
        }
    }

    /**
     * Toggle review visibility.
     * Super_admin only.
     */
    public function toggleVisibility(Request $request, $id)
    {
        if (!RoleMiddleware::hasRole('super_admin')) {
            return redirect()->route('reviews.index')
                ->with('error', __('messages.unauthorized_access'));
        }

        try {
            $visible = $request->boolean('visible', true);
            $this->firebase->toggleReviewVisibility($id, $visible);

            $message = $visible
                ? __('messages.review_visible')
                : __('messages.review_hidden');

            return redirect()->route('reviews.index')
                ->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Toggle review visibility error: ' . $e->getMessage());
            return redirect()->route('reviews.index')
                ->with('error', __('messages.unknown_error'));
        }
    }
}
