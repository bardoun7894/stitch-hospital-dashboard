<header class="h-18 bg-surface-light dark:bg-surface-dark border-b border-border-light dark:border-border-dark flex items-center justify-between px-6 flex-shrink-0 z-20 sticky top-0 md:static transition-all duration-300">
    <div class="flex items-center gap-6">
        <!-- Mobile Sidebar Toggle -->
        <button 
            @click="sidebarOpen = !sidebarOpen" 
            class="md:hidden text-text-sub-light hover:text-primary transition-colors p-1"
        >
             <span class="material-symbols-outlined">menu</span>
        </button>

        <h2 class="text-xl font-bold text-text-main-light dark:text-text-main-dark hidden md:block tracking-tight text-white/90 drop-shadow-sm">{{ __('messages.global_dashboard') }}</h2>
        <div class="h-8 w-px bg-white/10 hidden md:block"></div>

        <!-- Status Chip -->
        <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-3 py-1.5 rounded-full border border-emerald-500/20 shadow-sm">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-bold tracking-wide uppercase">{{ __('messages.all_queues_running') }}</span>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <!-- Language Switcher -->
        <div class="flex items-center bg-gray-100 dark:bg-slate-800 rounded-full p-1 border border-border-light dark:border-border-dark shadow-inner">
            <a href="{{ route('switch-to-arabic') }}" class="px-3 py-1.5 rounded-full {{ app()->getLocale() === 'ar' ? 'bg-white text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-gray-400 hover:text-text-main-light dark:hover:text-white' }} text-[10px] font-bold transition-all uppercase">
                عربي
            </a>
            <a href="{{ route('switch-to-english') }}" class="px-3 py-1.5 rounded-full {{ app()->getLocale() === 'en' ? 'bg-white text-primary font-bold shadow-sm' : 'text-text-sub-light dark:text-gray-400 hover:text-text-main-light dark:hover:text-white' }} text-[10px] font-bold transition-all uppercase">
                ENG
            </a>
        </div>

        <div class="h-8 w-px bg-border-light dark:bg-border-dark mx-1"></div>

        <!-- Dark Mode Toggle -->
        <button
            x-data="{ dark: localStorage.getItem('theme') === 'dark' }"
            @click="dark = !dark; localStorage.setItem('theme', dark ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', dark)"
            class="p-2 rounded-full border border-border-light dark:border-border-dark bg-gray-50 dark:bg-white/5 hover:bg-white dark:hover:bg-white/10 transition-all shadow-sm group"
            :title="dark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        >
            <span x-show="!dark" class="material-symbols-outlined text-lg text-amber-500 group-hover:rotate-45 transition-transform duration-300">light_mode</span>
            <span x-show="dark" style="display:none" class="material-symbols-outlined text-lg text-indigo-300 group-hover:-rotate-12 transition-transform duration-300">dark_mode</span>
        </button>

        <div class="h-8 w-px bg-border-light dark:bg-border-dark mx-1"></div>

        <!-- Date Selector -->
        <div class="relative" x-data="{ selectedDate: '{{ now()->format('Y-m-d') }}' }">
            <button type="button" @click="$refs.dateInput.showPicker()" class="flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-white/5 border border-border-light dark:border-border-dark rounded-xl text-sm font-medium text-text-main-light dark:text-text-main-dark hover:bg-white dark:hover:bg-white/10 transition-all shadow-sm card-hover cursor-pointer">
                <span class="material-symbols-outlined text-lg text-primary">calendar_today</span>
                <span x-text="new Date(selectedDate + 'T00:00:00').toLocaleDateString(document.documentElement.lang === 'ar' ? 'ar-SA' : 'en-US', { month: 'short', day: 'numeric', year: 'numeric' })">{{ now()->format('M d, Y') }}</span>
                <span class="material-symbols-outlined text-lg text-text-sub-light opacity-70">expand_more</span>
            </button>
            <input type="date" x-ref="dateInput" x-model="selectedDate"
                   @change="if(selectedDate) { window.location.href = '/?date=' + selectedDate; }"
                   class="absolute inset-0 opacity-0 w-full h-full cursor-pointer" tabindex="-1">
        </div>

        <div class="h-8 w-px bg-border-light dark:bg-border-dark mx-1"></div>

        <!-- Notifications Bell with Dropdown -->
        <div x-data="notificationBell()" x-init="startPolling()" class="relative">
            <button @click="open = !open" class="relative p-2.5 text-text-sub-light hover:text-primary transition-all rounded-full hover:bg-gray-100 dark:hover:bg-white/10 active:scale-95">
                <span class="material-symbols-outlined" :class="{ 'animate-ring': hasNew }">notifications</span>
                <span x-show="count > 0" x-text="count > 9 ? '9+' : count" style="display:none" class="absolute top-0 right-0 min-w-[18px] h-[18px] flex items-center justify-center bg-rose-500 text-white text-[10px] font-bold rounded-full border-2 border-white dark:border-slate-800 shadow-sm animate-bounce"></span>
            </button>

            <!-- Dropdown -->
            <div x-show="open" @click.outside="open = false" style="display:none" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                 class="absolute right-0 rtl:right-auto rtl:left-0 mt-3 w-80 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-2xl shadow-2xl z-50 overflow-hidden ring-1 ring-black/5">
                <div class="px-4 py-3 border-b border-border-light dark:border-border-dark flex items-center justify-between bg-gray-50 dark:bg-white/5">
                    <h4 class="font-bold text-sm text-text-main-light dark:text-text-main-dark">{{ __('messages.pending_bookings') ?? 'Pending Bookings' }}</h4>
                    <span x-show="count > 0" style="display:none" class="bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm" x-text="count + ' {{ __('messages.new') }}'"></span>
                </div>
                <div class="max-h-64 overflow-y-auto divide-y divide-white/10 custom-scrollbar">
                    <template x-if="bookings.length === 0">
                        <div class="px-4 py-8 text-center text-text-sub-light text-sm flex flex-col items-center gap-2">
                            <div class="p-3 bg-white/5 rounded-full">
                                <span class="material-symbols-outlined text-3xl text-text-sub-light/50">notifications_off</span>
                            </div>
                            <p class="opacity-70">{{ __('messages.no_pending') ?? 'No pending bookings' }}</p>
                        </div>
                    </template>
                    <template x-for="booking in bookings" :key="booking.id">
                        <div class="px-4 py-3 hover:bg-white/20 dark:hover:bg-white/10 transition-colors cursor-pointer group" @click="goToBookings()">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 bg-indigo-50 dark:bg-indigo-500/20 text-primary p-2 rounded-full flex-shrink-0 group-hover:bg-primary group-hover:text-white transition-all shadow-sm">
                                    <span class="material-symbols-outlined text-sm">person_add</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-text-main-light dark:text-text-main-dark truncate" x-text="booking.patient_name"></p>
                                    <p class="text-xs text-text-sub-light truncate font-medium" x-text="booking.doctor_name"></p>
                                </div>
                                <span class="bg-amber-50 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase flex-shrink-0 shadow-sm border border-amber-100 dark:border-amber-500/30">{{ __('messages.new') }}</span>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="count > 0" style="display:none" class="px-4 py-3 border-t border-white/10 bg-white/30 dark:bg-white/5 transition-colors hover:bg-white/50 dark:hover:bg-white/10">
                    <a href="{{ route('bookings.index') }}" class="text-primary hover:text-indigo-600 dark:hover:text-indigo-300 text-sm font-bold flex items-center justify-center gap-1 transition-colors">
                        {{ __('messages.view_all_bookings') ?? 'View All Bookings' }}
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
