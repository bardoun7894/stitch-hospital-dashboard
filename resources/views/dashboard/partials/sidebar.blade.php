@php
    use App\Http\Middleware\RoleMiddleware;
    $userRole = $currentUser['role'] ?? 'guest';
@endphp

<aside
    class="w-72 bg-surface-light dark:bg-surface-dark border-r rtl:border-r-0 rtl:border-l border-border-light dark:border-border-dark flex flex-col h-full flex-shrink-0 transition-all duration-300"
>
    <!-- Header -->
    <div class="h-18 flex items-center gap-3 px-6 border-b border-border-light dark:border-border-dark">
        <div class="bg-primary/10 rounded-xl p-2 flex items-center justify-center">
            <span class="material-symbols-outlined text-primary text-2xl">local_hospital</span>
        </div>
        <div>
            <h1 class="text-lg font-bold tracking-tight text-text-main-light dark:text-text-main-dark leading-tight">{{ __('messages.med_admin') }}</h1>
            <p class="text-[10px] text-text-sub-light uppercase tracking-wider font-semibold">Stitch Dashboard</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">

        {{-- Dashboard — all authenticated users --}}
        <a href="{{ route('dashboard') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('dashboard') ? 'filled' : '' }} group-hover:scale-110 transition-transform">dashboard</span>
            <span>{{ __('messages.overview') }}</span>
        </a>

        {{-- Section Label --}}
        <div class="px-3 mt-6 mb-2 text-xs font-semibold text-text-sub-light/70 uppercase tracking-widest">Management</div>

        {{-- Hospital — hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['hospital_manager', 'super_admin']))
        <a href="{{ route('hospital.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('hospital.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('hospital.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">domain</span>
            <span>{{ __('messages.hospital_nav') }}</span>
        </a>
        @endif

        {{-- Clinics — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('clinics.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('clinics.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('clinics.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">emergency_home</span>
            <span>{{ __('messages.clinic_management') }}</span>
        </a>
        @endif

        {{-- Queue Manager — reception, clinic_admin, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['reception', 'clinic_admin']))
        <a href="{{ route('bookings.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('bookings.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('bookings.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">campaign</span>
            <span>{{ __('messages.queue_manager_nav') }}</span>
        </a>
        @endif

        {{-- Section Label --}}
        <div class="px-3 mt-6 mb-2 text-xs font-semibold text-text-sub-light/70 uppercase tracking-widest">People</div>

        {{-- Doctors — all staff --}}
        <a href="{{ route('doctors.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('doctors.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('doctors.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">stethoscope</span>
            <span>{{ __('messages.doctors_nav') }}</span>
        </a>

        {{-- Patients — reception, doctor, clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['reception', 'doctor', 'clinic_admin', 'hospital_manager']))
        <a href="{{ route('patients.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('patients.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('patients.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">group</span>
            <span>{{ __('messages.patients_nav') }}</span>
        </a>
        @endif

        {{-- Treatment Plans — doctor, clinic_admin, reception --}}
        @if(RoleMiddleware::hasAnyRole(['doctor', 'clinic_admin', 'reception']))
        <a href="{{ route('treatment-plans.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('treatment-plans.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('treatment-plans.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">clinical_notes</span>
            <span>{{ __('messages.treatment_plans_nav') }}</span>
        </a>
        @endif

        {{-- Medications / Prescriptions — doctor, clinic_admin, reception --}}
        @if(RoleMiddleware::hasAnyRole(['doctor', 'clinic_admin', 'reception']))
        <a href="{{ route('medications.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('medications.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('medications.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">medication</span>
            <span>{{ __('messages.medications_nav') }}</span>
        </a>
        @endif

        {{-- Users — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('users.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('users.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('users.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">manage_accounts</span>
            <span>{{ __('messages.users_nav') }}</span>
        </a>
        @endif

        {{-- Section Label --}}
        <div class="px-3 mt-6 mb-2 text-xs font-semibold text-text-sub-light/70 uppercase tracking-widest">System</div>

        {{-- Reports — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('reports.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('reports.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('reports.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">bar_chart</span>
            <span>{{ __('messages.reports_nav') }}</span>
        </a>
        @endif

        {{-- Settings — clinic_admin, hospital_manager, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['clinic_admin', 'hospital_manager']))
        <a href="{{ route('settings.index') }}" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('settings.*') ? 'bg-primary/5 text-primary font-medium' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark' }} transition-all">
            <span class="material-symbols-outlined {{ request()->routeIs('settings.*') ? 'filled' : '' }} group-hover:scale-110 transition-transform">settings</span>
            <span>{{ __('messages.settings_nav') }}</span>
        </a>
        @endif

        {{-- TV View — reception, clinic_admin, super_admin --}}
        @if(RoleMiddleware::hasAnyRole(['reception', 'clinic_admin']))
        <a href="{{ route('tv.index') }}" target="_blank" class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5 hover:text-text-main-light dark:hover:text-text-main-dark font-medium transition-all">
            <span class="material-symbols-outlined group-hover:scale-110 transition-transform">tv</span>
            <span>{{ __('messages.tv_view_nav') }}</span>
        </a>
        @endif

    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-border-light dark:border-border-dark bg-background-light dark:bg-white/5">
        <div class="flex items-center gap-3 p-2 rounded-lg">
            <div class="size-10 rounded-full bg-cover bg-center border border-border-light dark:border-border-dark ring-2 ring-white dark:ring-surface-dark" style="background-image: url('{{ $currentUser['avatar'] ?? '' }}');"></div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-text-main-light dark:text-text-main-dark truncate">{{ $currentUser['name'] ?? __('messages.admin_user') }}</p>
                <p class="text-xs text-text-sub-light font-medium">{{ $currentUser['role_label'] ?? '' }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                @csrf
                <button type="submit" class="p-2 rounded-lg text-text-sub-light hover:text-alert-red hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Logout">
                    <span class="material-symbols-outlined text-lg">logout</span>
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
