<header class="h-16 bg-white dark:bg-[#1a222e] border-b border-[#e5e7eb] dark:border-[#2d3748] flex items-center justify-between px-6 flex-shrink-0 z-20 sticky top-0 md:static">
    <div class="flex items-center gap-6">
        <!-- Mobile Sidebar Toggle -->
        <button 
            @click="sidebarOpen = !sidebarOpen" 
            class="md:hidden text-[#637388] dark:text-[#9ca3af] hover:text-[#111418] dark:hover:text-white"
        >
             <span class="material-symbols-outlined">menu</span>
        </button>

        <h2 class="text-lg font-bold text-[#111418] dark:text-white hidden md:block">{{ __('messages.global_dashboard') }}</h2>
        <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 hidden md:block"></div>

        <!-- Status Chip -->
        <div class="flex items-center gap-2 bg-[#f0fdf4] dark:bg-[#064e3b] text-[#166534] dark:text-[#6ee7b7] px-3 py-1.5 rounded-full border border-[#bbf7d0] dark:border-[#065f46]">
            <span class="material-symbols-outlined text-sm filled">check_circle</span>
            <span class="text-sm font-semibold">{{ __('messages.all_queues_running') }}</span>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <!-- Language Switcher -->
        <div class="flex items-center gap-2 bg-background-light dark:bg-[#111821] rounded-lg p-1">
            <a href="{{ route('switch-to-arabic') }}" class="px-3 py-1.5 rounded {{ app()->getLocale() === 'ar' ? 'bg-white dark:bg-[#2d3748] text-primary font-semibold' : 'text-[#637388] dark:text-[#9ca3af] hover:text-[#111418] dark:hover:text-white' }} text-sm transition-colors">
                ع
            </a>
            <a href="{{ route('switch-to-english') }}" class="px-3 py-1.5 rounded {{ app()->getLocale() === 'en' ? 'bg-white dark:bg-[#2d3748] text-primary font-semibold' : 'text-[#637388] dark:text-[#9ca3af] hover:text-[#111418] dark:hover:text-white' }} text-sm transition-colors">
                EN
            </a>
        </div>

        <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>

        <!-- Date Selector -->
        <button class="flex items-center gap-2 px-3 py-2 bg-background-light dark:bg-[#111821] rounded-lg text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-200 dark:hover:bg-[#1f2937] transition-colors border border-transparent hover:border-gray-300 dark:hover:border-gray-600">
            <span class="material-symbols-outlined text-lg">calendar_today</span>
            <span>{{ now()->format('M d, Y') }}</span>
            <span class="material-symbols-outlined text-lg">expand_more</span>
        </button>

        <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>

        <!-- Notifications -->
        <button class="relative p-2 text-[#637388] dark:text-[#9ca3af] hover:text-primary dark:hover:text-primary transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-[#2d3748]">
            <span class="material-symbols-outlined">notifications</span>
            <span class="absolute top-1.5 right-2 rtl:right-auto rtl:left-2 size-2 bg-alert-red rounded-full border border-white dark:border-[#1a222e]"></span>
        </button>
    </div>
</header>
