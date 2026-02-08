@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('patients.index') }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-[#111418] dark:hover:text-white transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.add_new_patient') }}</h1>
    </div>

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">{{ __('messages.error_heading') }}</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-[#1a222e] border border-[#e5e7eb] dark:border-[#2d3748] rounded-xl shadow-sm p-6">
        <form action="{{ route('patients.store') }}" method="POST">
            @csrf
            
            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.full_name') }}</label>
                    <input type="text" name="name" id="name" required
                        class="w-full px-4 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#111821] text-[#111418] dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50"
                        placeholder="{{ __('messages.full_name_placeholder') }}">
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.phone_number') }}</label>
                    <input type="tel" name="phone" id="phone" required
                        class="w-full px-4 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#111821] text-[#111418] dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50"
                        placeholder="{{ __('messages.phone_placeholder') }}">
                </div>

                <!-- Email (Optional) -->
                <div>
                    <label for="email" class="block text-sm font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.email_optional') }}</label>
                    <input type="email" name="email" id="email"
                        class="w-full px-4 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#111821] text-[#111418] dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50"
                        placeholder="{{ __('messages.email_placeholder') }}">
                </div>

                <div>
                    <label for="national_id" class="block text-sm font-medium text-[#111418] dark:text-white mb-2">National ID</label>
                    <input type="text" name="national_id" id="national_id"
                        class="w-full px-4 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#111821] text-[#111418] dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50"
                        placeholder="National ID (optional)">
                </div>

                <div class="pt-4 flex justify-end gap-3">
                    <a href="{{ route('patients.index') }}"
                        class="px-6 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-[#637388] dark:text-[#9ca3af] hover:bg-gray-50 dark:hover:bg-[#202a37] font-medium transition-colors">
                        {{ __('messages.cancel') }}
                    </a>
                    <button type="submit"
                        class="px-6 py-2 rounded-lg bg-primary hover:bg-blue-700 text-white font-medium transition-colors shadow-sm">
                        {{ __('messages.create_patient') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
