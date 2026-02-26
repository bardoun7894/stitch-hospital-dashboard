@php
    use App\Http\Middleware\RoleMiddleware;
    $userRole = $currentUser['role'] ?? 'guest';
@endphp

<aside
    class="w-72 bg-surface-light dark:bg-surface-dark border-r rtl:border-r-0 rtl:border-l border-border-light dark:border-border-dark flex flex-col h-full flex-shrink-0 transition-all duration-300 z-30"
>
    <!-- Header -->
    <div class="h-18 flex items-center gap-3 px-6 border-b border-white/10 dark:border-white/5">
        @php
            $sidebarLogo = null;
            if ($userRole === 'hospital_manager' && !empty($currentUser['hospital_id'])) {
                try {
                    $sidebarHospital = app(\App\Services\FirebaseService::class)->getHospitalById($currentUser['hospital_id']);
                    $sidebarLogo = $sidebarHospital['logo_url'] ?? null;
                } catch (\Throwable $e) {}
            }
        @endphp
        @if($sidebarLogo)
        <img src="{{ $sidebarLogo }}" alt="" class="size-10 rounded-xl object-cover border border-primary/10 shadow-sm">
        @else
        <div class="bg-gradient-to-br from-primary/10 to-indigo-500/10 rounded-xl p-2 flex items-center justify-center border border-primary/10 shadow-sm">
            <span class="material-symbols-outlined text-primary text-2xl">local_hospital</span>
        </div>
        @endif
        <div>
            <h1 class="text-lg font-bold tracking-tight text-text-main-light dark:text-text-main-dark leading-tight">{{ __('messages.med_admin') }}</h1>
            <p class="text-[10px] text-text-sub-light uppercase tracking-wider font-semibold opacity-70">{{ __('messages.stitch_dashboard') }}</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto custom-scrollbar">

        {{-- Dashboard — all authenticated users --}}
        <a href="{{ route('dashboard') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('dashboard') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">dashboard</span>
            <span>{{ __('messages.overview') }}</span>
        </a>

        @if($userRole === 'doctor')
        {{-- ═══ DOCTOR-SPECIFIC SIDEBAR ═══ --}}

        {{-- My Schedule --}}
        @if(session('firebase_user_doctor_id'))
        <a href="{{ route('doctors.schedule', session('firebase_user_doctor_id')) }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('doctors.schedule') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('doctors.schedule') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">calendar_month</span>
            <span>{{ __('messages.my_schedule') }}</span>
        </a>
        @endif

        {{-- My Queue --}}
        <a href="{{ route('bookings.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('bookings.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('bookings.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">campaign</span>
            <span>{{ __('messages.my_queue') }}</span>
        </a>

        {{-- My Patients --}}
        <a href="{{ route('patients.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('patients.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('patients.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">group</span>
            <span>{{ __('messages.my_patients') }}</span>
        </a>

        {{-- Treatment Plans --}}
        <a href="{{ route('treatment-plans.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('treatment-plans.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('treatment-plans.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">clinical_notes</span>
            <span>{{ __('messages.treatment_plans_nav') }}</span>
        </a>

        {{-- Medications / Prescriptions --}}
        <a href="{{ route('medications.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('medications.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('medications.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">medication</span>
            <span>{{ __('messages.medications_nav') }}</span>
        </a>

        @else
        {{-- ═══ NON-DOCTOR SIDEBAR (admin/reception/hospital_manager) ═══ --}}

        {{-- Section Label --}}
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-text-sub-light/60 uppercase tracking-widest">{{ __('messages.management') }}</div>

        {{-- Hospital — hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['hospital_manager', 'super_admin']))
        <a href="{{ route('hospital.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('hospital.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('hospital.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">domain</span>
            <span>{{ __('messages.hospital_nav') }}</span>
        </a>
        @endif

        {{-- Clinics — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('clinics.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('clinics.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('clinics.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">emergency_home</span>
            <span>{{ __('messages.clinic_management') }}</span>
        </a>
        @endif

        {{-- Queue Manager — reception, clinic_admin, hospital_manager --}}
        @if(RoleMiddleware::hasAnyRole(['reception', 'clinic_admin', 'hospital_manager']))
        <a href="{{ route('bookings.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('bookings.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('bookings.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">campaign</span>
            <span>{{ __('messages.queue_manager_nav') }}</span>
        </a>
        @endif

        {{-- Section Label --}}
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-text-sub-light/60 uppercase tracking-widest">{{ __('messages.people') }}</div>

        {{-- Doctors — all staff --}}
        <a href="{{ route('doctors.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('doctors.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('doctors.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">stethoscope</span>
            <span>{{ __('messages.doctors_nav') }}</span>
        </a>

        {{-- Patients — reception, doctor, clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['reception', 'clinic_admin', 'hospital_manager']))
        <a href="{{ route('patients.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('patients.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('patients.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">group</span>
            <span>{{ __('messages.patients_nav') }}</span>
        </a>
        @endif

        {{-- Treatment Plans — doctor, clinic_admin, reception, hospital_manager --}}
        @if(RoleMiddleware::hasAnyRole(['doctor', 'clinic_admin', 'reception', 'hospital_manager']))
        <a href="{{ route('treatment-plans.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('treatment-plans.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('treatment-plans.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">clinical_notes</span>
            <span>{{ __('messages.treatment_plans_nav') }}</span>
        </a>
        @endif

        {{-- Medications / Prescriptions — doctor, clinic_admin, reception, hospital_manager --}}
        @if(RoleMiddleware::hasAnyRole(['doctor', 'clinic_admin', 'reception', 'hospital_manager']))
        <a href="{{ route('medications.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('medications.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('medications.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">medication</span>
            <span>{{ __('messages.medications_nav') }}</span>
        </a>
        @endif

        {{-- Users — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('users.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('users.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('users.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">manage_accounts</span>
            <span>{{ __('messages.users_nav') }}</span>
        </a>
        @endif

        {{-- Section Label --}}
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-text-sub-light/60 uppercase tracking-widest">{{ __('messages.system') }}</div>

        {{-- Reports — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('reports.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('reports.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('reports.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">bar_chart</span>
            <span>{{ __('messages.reports_nav') }}</span>
        </a>
        @endif

        {{-- Financial — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('financial.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('financial.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('financial.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">account_balance</span>
            <span>{{ __('messages.financial_nav') }}</span>
        </a>
        @endif

        {{-- Coupons — clinic_admin, hospital_manager --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('coupons.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('coupons.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('coupons.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">local_offer</span>
            <span>{{ __('messages.coupons') }}</span>
        </a>
        @endif

        {{-- Settings — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('settings.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('settings.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('settings.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">settings</span>
            <span>{{ __('messages.settings_nav') }}</span>
        </a>
        @endif

        {{-- Audit Log — super_admin only --}}
        @if(RoleMiddleware::hasAnyRole(['super_admin']))
        <a href="{{ route('audit.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('audit.*') ? 'bg-primary/10 text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all duration-200">
            <span class="material-symbols-outlined {{ request()->routeIs('audit.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform duration-200">history</span>
            <span>{{ __('messages.audit_log') }}</span>
        </a>
        @endif

        {{-- TV View — reception, clinic_admin --}}
        @if(RoleMiddleware::hasAnyRole(['reception', 'clinic_admin']))
        <a href="{{ route('tv.index') }}" target="_blank" class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-text-sub-light dark:text-text-sub-dark hover:bg-white/50 dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark font-medium transition-all duration-200">
            <span class="material-symbols-outlined group-hover:scale-110 transition-transform duration-200">tv</span>
            <span>{{ __('messages.tv_view_nav') }}</span>
        </a>
        @endif

        @endif {{-- end doctor/non-doctor check --}}

    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-border-light dark:border-border-dark bg-gray-50 dark:bg-white/5">
        <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-white dark:hover:bg-white/10 transition-colors cursor-pointer group">
            <div class="size-10 rounded-full border border-border-light dark:border-border-dark ring-2 ring-white/20 relative overflow-hidden bg-primary/10 flex items-center justify-center">
                <span class="text-primary font-bold text-sm uppercase">{{ mb_substr($currentUser['name'] ?? 'U', 0, 2) }}</span>
                @if(!empty($currentUser['avatar']))
                <img src="{{ $currentUser['avatar'] }}" alt="" class="absolute inset-0 size-full rounded-full object-cover" onerror="this.style.display='none'">
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-text-main-light dark:text-text-main-dark truncate">{{ $currentUser['name'] ?? __('messages.admin_user') }}</p>
                <p class="text-[10px] text-text-sub-light font-medium">{{ $currentUser['role_label'] ?? '' }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all" title="Logout">
                    <span class="material-symbols-outlined text-sm">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div
    x-show="sidebarOpen"
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-black bg-opacity-50 md:hidden"
    style="display: none;"
></div>
