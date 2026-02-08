<header class="h-18 glass border-b border-border-light dark:border-border-dark flex items-center justify-between px-6 flex-shrink-0 z-20 sticky top-0 md:static transition-colors duration-300">
    <div class="flex items-center gap-6">
        <!-- Mobile Sidebar Toggle -->
        <button 
            @click="sidebarOpen = !sidebarOpen" 
            class="md:hidden text-text-sub-light hover:text-primary transition-colors p-1"
        >
             <span class="material-symbols-outlined">menu</span>
        </button>

        <h2 class="text-xl font-bold text-text-main-light dark:text-text-main-dark hidden md:block tracking-tight">{{ __('messages.global_dashboard') }}</h2>
        <div class="h-8 w-px bg-border-light dark:bg-border-dark hidden md:block"></div>

        <!-- Status Chip -->
        <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-3 py-1.5 rounded-full border border-emerald-200 dark:border-emerald-500/20 shadow-sm">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-semibold tracking-wide uppercase">{{ __('messages.all_queues_running') }}</span>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <!-- Language Switcher -->
        <div class="flex items-center bg-background-light dark:bg-surface-dark rounded-lg p-1 border border-border-light dark:border-border-dark shadow-sm">
            <a href="{{ route('switch-to-arabic') }}" class="px-3 py-1.5 rounded-md {{ app()->getLocale() === 'ar' ? 'bg-white dark:bg-white/10 text-primary font-bold shadow-sm' : 'text-text-sub-light hover:text-text-main-light dark:hover:text-text-main-dark' }} text-xs transition-all">
                عربي
            </a>
            <a href="{{ route('switch-to-english') }}" class="px-3 py-1.5 rounded-md {{ app()->getLocale() === 'en' ? 'bg-white dark:bg-white/10 text-primary font-bold shadow-sm' : 'text-text-sub-light hover:text-text-main-light dark:hover:text-text-main-dark' }} text-xs transition-all">
                ENG
            </a>
        </div>

        <div class="h-8 w-px bg-border-light dark:bg-border-dark mx-1"></div>

        <!-- Date Selector -->
        <button class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-lg text-sm font-medium text-text-main-light dark:text-text-main-dark hover:bg-background-light dark:hover:bg-white/5 transition-colors shadow-sm card-hover">
            <span class="material-symbols-outlined text-lg text-primary">calendar_today</span>
            <span>{{ now()->format('M d, Y') }}</span>
            <span class="material-symbols-outlined text-lg text-text-sub-light">expand_more</span>
        </button>

        <div class="h-8 w-px bg-border-light dark:bg-border-dark mx-1"></div>

        <!-- Notifications Bell with Dropdown -->
        <div x-data="notificationBell()" x-init="startPolling()" class="relative">
            <button @click="open = !open" class="relative p-2 text-text-sub-light hover:text-primary transition-colors rounded-full hover:bg-primary/5">
                <span class="material-symbols-outlined" :class="{ 'animate-ring': hasNew }">notifications</span>
                <span x-show="count > 0" x-text="count > 9 ? '9+' : count" class="absolute top-0 right-0 min-w-[18px] h-[18px] flex items-center justify-center bg-alert-red text-white text-[10px] font-bold rounded-full border-2 border-white dark:border-surface-dark shadow-sm"></span>
            </button>

            <!-- Dropdown -->
            <div x-show="open" @click.outside="open = false" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2"
                 class="absolute right-0 rtl:right-auto rtl:left-0 mt-3 w-80 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-xl shadow-2xl z-50 overflow-hidden ring-1 ring-black/5">
                <div class="px-4 py-3 border-b border-border-light dark:border-border-dark flex items-center justify-between bg-background-light/50 dark:bg-white/5 backdrop-blur-sm">
                    <h4 class="font-bold text-sm text-text-main-light dark:text-text-main-dark">{{ __('messages.pending_bookings') ?? 'Pending Bookings' }}</h4>
                    <span x-show="count > 0" class="bg-primary/10 text-primary text-xs font-bold px-2 py-0.5 rounded-full" x-text="count + ' new'"></span>
                </div>
                <div class="max-h-64 overflow-y-auto divide-y divide-border-light dark:divide-border-dark">
                    <template x-if="bookings.length === 0">
                        <div class="px-4 py-8 text-center text-text-sub-light text-sm flex flex-col items-center gap-2">
                            <span class="material-symbols-outlined text-3xl text-border-light dark:text-border-dark">notifications_off</span>
                            {{ __('messages.no_pending') ?? 'No pending bookings' }}
                        </div>
                    </template>
                    <template x-for="booking in bookings" :key="booking.id">
                        <div class="px-4 py-3 hover:bg-background-light dark:hover:bg-white/5 transition-colors cursor-pointer group" @click="goToBookings()">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 bg-primary/10 text-primary p-2 rounded-full flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                                    <span class="material-symbols-outlined text-sm">person_add</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text-main-light dark:text-text-main-dark truncate" x-text="booking.patient_name"></p>
                                    <p class="text-xs text-text-sub-light truncate" x-text="booking.doctor_name"></p>
                                </div>
                                <span class="bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase flex-shrink-0 shadow-sm border border-amber-100 dark:border-amber-900/30">New</span>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="count > 0" class="px-4 py-3 border-t border-border-light dark:border-border-dark bg-background-light dark:bg-white/5">
                    <a href="{{ route('bookings.index') }}" class="text-primary hover:text-primary-hover text-sm font-semibold flex items-center justify-center gap-1 transition-colors">
                        {{ __('messages.view_all_bookings') ?? 'View All Bookings' }}
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
