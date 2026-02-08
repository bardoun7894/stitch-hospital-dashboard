<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Session;
use App\Http\Middleware\RoleMiddleware;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Share current user data with sidebar and header views
        view()->composer(['dashboard.partials.sidebar', 'dashboard.partials.header'], function ($view) {
            $currentUser = RoleMiddleware::getCurrentUser();

            if ($currentUser) {
                $currentUser['avatar'] = 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['name'] ?? 'U') . '&background=4f46e5&color=fff';
                // Map role to display label
                $roleLabels = [
                    'super_admin' => __('messages.super_admin'),
                    'hospital_manager' => __('messages.hospital_manager'),
                    'clinic_admin' => __('messages.clinic_admin'),
                    'reception' => __('messages.reception'),
                    'doctor' => __('messages.doctor'),
                    'patient' => __('messages.patient'),
                ];
                $currentUser['role_label'] = $roleLabels[$currentUser['role'] ?? ''] ?? ($currentUser['role'] ?? '');
            } else {
                $currentUser = [
                    'name' => __('messages.unknown_user'),
                    'role' => 'guest',
                    'role_label' => '',
                    'clinic_id' => null,
                    'hospital_id' => null,
                    'avatar' => 'https://ui-avatars.com/api/?name=U&background=4f46e5&color=fff',
                ];
            }

            $view->with('currentUser', $currentUser);
        });
    }
}
