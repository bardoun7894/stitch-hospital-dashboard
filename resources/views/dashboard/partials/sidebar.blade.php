<aside
    class="w-64 bg-white dark:bg-[#1a222e] border-r rtl:border-r-0 rtl:border-l border-[#e5e7eb] dark:border-[#2d3748] flex flex-col h-full flex-shrink-0"
>
    <!-- Header -->
    <div class="p-6 flex items-center gap-3">
        <div class="bg-primary/10 rounded-lg p-2 flex items-center justify-center">
            <span class="material-symbols-outlined text-primary text-2xl">local_hospital</span>
        </div>
        <h1 class="text-xl font-bold tracking-tight text-[#111418] dark:text-white">{{ __('messages.med_admin') }}</h1>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-2 space-y-2 overflow-y-auto">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary' : 'text-[#637388] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#2d3748] hover:text-[#111418] dark:hover:text-white' }} font-medium group transition-colors">
            <span class="material-symbols-outlined {{ request()->routeIs('dashboard') ? 'filled' : '' }}">dashboard</span>
            <span>{{ __('messages.overview') }}</span>
        </a>

        <a href="{{ route('clinics.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg {{ request()->routeIs('clinics.*') ? 'bg-primary/10 text-primary' : 'text-[#637388] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#2d3748] hover:text-[#111418] dark:hover:text-white' }} font-medium transition-colors">
            <span class="material-symbols-outlined {{ request()->routeIs('clinics.*') ? 'filled' : '' }}">emergency_home</span>
            <span>{{ __('messages.clinic_management') }}</span>
        </a>

        <a href="{{ route('bookings.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg {{ request()->routeIs('bookings.*') ? 'bg-primary/10 text-primary' : 'text-[#637388] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#2d3748] hover:text-[#111418] dark:hover:text-white' }} font-medium transition-colors">
            <span class="material-symbols-outlined {{ request()->routeIs('bookings.*') ? 'filled' : '' }}">campaign</span>
            <span>{{ __('messages.queue_manager_nav') }}</span>
        </a>

        <a href="{{ route('doctors.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg {{ request()->routeIs('doctors.*') ? 'bg-primary/10 text-primary' : 'text-[#637388] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#2d3748] hover:text-[#111418] dark:hover:text-white' }} font-medium transition-colors">
            <span class="material-symbols-outlined {{ request()->routeIs('doctors.*') ? 'filled' : '' }}">stethoscope</span>
            <span>{{ __('messages.doctors_nav') }}</span>
        </a>

        <a href="{{ route('patients.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg {{ request()->routeIs('patients.*') ? 'bg-primary/10 text-primary' : 'text-[#637388] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#2d3748] hover:text-[#111418] dark:hover:text-white' }} font-medium transition-colors">
            <span class="material-symbols-outlined {{ request()->routeIs('patients.*') ? 'filled' : '' }}">group</span>
            <span>{{ __('messages.patients_nav') }}</span>
        </a>

         <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg {{ request()->routeIs('settings.*') ? 'bg-primary/10 text-primary' : 'text-[#637388] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#2d3748] hover:text-[#111418] dark:hover:text-white' }} font-medium transition-colors">
            <span class="material-symbols-outlined {{ request()->routeIs('settings.*') ? 'filled' : '' }}">settings</span>
            <span>{{ __('messages.settings_nav') }}</span>
        </a>
    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
        <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] cursor-pointer transition-colors">
            <div class="size-10 rounded-full bg-cover bg-center border border-gray-200" style="background-image: url('{{ $currentUser['avatar'] ?? 'https://ui-avatars.com/api/?name=Admin' }}');"></div>
            <div class="flex flex-col">
                <p class="text-sm font-semibold text-[#111418] dark:text-white">{{ $currentUser['name'] ?? __('messages.admin_user') }}</p>
                <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ $currentUser['role'] ?? __('messages.hospital_admin') }}</p>
            </div>
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
