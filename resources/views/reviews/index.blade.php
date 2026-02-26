@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isSuperAdmin = RoleMiddleware::hasRole('super_admin');
@endphp

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Alerts --}}
    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-2xl text-sm border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">check_circle</span> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-2xl text-sm border border-red-100 dark:border-red-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">error</span> {{ session('error') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="bg-gradient-to-br from-amber-500/10 to-yellow-500/10 rounded-xl p-2 flex items-center justify-center border border-amber-500/10 shadow-sm">
                    <span class="material-symbols-outlined text-amber-500 dark:text-amber-400 text-2xl">star</span>
                </div>
                <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight">
                    {{ __('messages.reviews_ratings') }}
                </h1>
            </div>
            <p class="text-text-sub-light dark:text-text-sub-dark text-sm ltr:ml-14 rtl:mr-14">
                {{ __('messages.total_reviews') }}: {{ $totalReviews }}
            </p>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        {{-- Average Rating --}}
        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2 rounded-xl bg-amber-500/10">
                    <span class="material-symbols-outlined text-amber-500 text-xl">star</span>
                </div>
                <span class="text-sm font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.avg_rating') }}</span>
            </div>
            <div class="flex items-end gap-3">
                <span class="text-4xl font-black text-text-main-light dark:text-white">{{ $avgRating }}</span>
                <div class="flex items-center gap-0.5 mb-1.5">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= floor($avgRating))
                            <span class="material-symbols-outlined text-amber-400 text-lg filled">star</span>
                        @elseif($i - $avgRating < 1 && $i - $avgRating > 0)
                            <span class="material-symbols-outlined text-amber-400 text-lg">star_half</span>
                        @else
                            <span class="material-symbols-outlined text-gray-300 dark:text-gray-600 text-lg">star</span>
                        @endif
                    @endfor
                </div>
            </div>
        </div>

        {{-- Total Reviews --}}
        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2 rounded-xl bg-primary/10">
                    <span class="material-symbols-outlined text-primary text-xl">rate_review</span>
                </div>
                <span class="text-sm font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.total_reviews') }}</span>
            </div>
            <span class="text-4xl font-black text-text-main-light dark:text-white">{{ $totalReviews }}</span>
        </div>

        {{-- Rating Distribution --}}
        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="p-2 rounded-xl bg-indigo-500/10">
                    <span class="material-symbols-outlined text-indigo-500 text-xl">bar_chart</span>
                </div>
                <span class="text-sm font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.rating_distribution') }}</span>
            </div>
            <div class="space-y-1.5">
                @for($star = 5; $star >= 1; $star--)
                    @php
                        $count = $distribution[$star] ?? 0;
                        $percentage = $totalReviews > 0 ? round(($count / $totalReviews) * 100) : 0;
                    @endphp
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-3 text-text-sub-light dark:text-text-sub-dark font-bold">{{ $star }}</span>
                        <span class="material-symbols-outlined text-amber-400 text-xs filled">star</span>
                        <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div class="bg-amber-400 h-full rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                        </div>
                        <span class="w-8 text-end text-text-sub-light dark:text-text-sub-dark font-medium">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white dark:bg-surface-dark p-4 rounded-2xl border border-border-light dark:border-border-dark mb-6 shadow-sm">
        <form method="GET" action="{{ route('reviews.index') }}" class="flex flex-col sm:flex-row items-end gap-3 flex-wrap">
            <div class="flex-1 w-full sm:w-auto min-w-[150px]">
                <label for="doctor_id" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.doctor_name') }}</label>
                <select id="doctor_id" name="doctor_id"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
                    <option value="">{{ __('messages.all') }}</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor['id'] }}" {{ ($filters['doctor_id'] ?? '') == $doctor['id'] ? 'selected' : '' }}>
                            {{ $doctor['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 w-full sm:w-auto min-w-[150px]">
                <label for="clinic_id" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.clinic_name') }}</label>
                <select id="clinic_id" name="clinic_id"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
                    <option value="">{{ __('messages.all_clinics') }}</option>
                    @foreach($clinics as $clinic)
                        <option value="{{ $clinic['id'] }}" {{ ($filters['clinic_id'] ?? '') == $clinic['id'] ? 'selected' : '' }}>
                            {{ $clinic['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 w-full sm:w-auto min-w-[120px]">
                <label for="rating" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.rating') }}</label>
                <select id="rating" name="rating"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
                    <option value="">{{ __('messages.all') }}</option>
                    @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ ($filters['rating'] ?? '') == $i ? 'selected' : '' }}>{{ $i }} {{ __('messages.stars') }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex-1 w-full sm:w-auto min-w-[140px]">
                <label for="date_from" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.date_from') }}</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
            </div>
            <div class="flex-1 w-full sm:w-auto min-w-[140px]">
                <label for="date_to" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.date_to') }}</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
            </div>
            <div class="flex gap-2">
                <button type="submit"
                    class="bg-primary text-white px-5 py-2 rounded-xl text-sm font-medium shadow-md shadow-primary/20 hover:bg-primary-hover transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                    {{ __('messages.apply_filter') }}
                </button>
                <a href="{{ route('reviews.index') }}"
                    class="px-4 py-2 rounded-xl border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors text-text-sub-light dark:text-text-sub-dark">
                    {{ __('messages.all') }}
                </a>
            </div>
        </form>
    </div>

    {{-- Reviews List --}}
    @if(count($reviews) === 0)
        {{-- Empty State --}}
        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-16 text-center">
            <span class="material-symbols-outlined text-6xl text-border-light dark:text-border-dark mb-4 block">star_border</span>
            <p class="text-lg font-bold text-text-main-light dark:text-white mb-2">{{ __('messages.no_reviews') }}</p>
            <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_data') }}</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                @php
                    $rating = (int) ($review['rating'] ?? 0);
                    $isVisible = $review['is_visible'] ?? true;
                @endphp
                <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5 shadow-sm {{ !$isVisible ? 'opacity-50' : '' }} transition-opacity">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        {{-- Review Content --}}
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-base">person</span>
                                </div>
                                <div>
                                    <p class="font-bold text-text-main-light dark:text-white text-sm">{{ $review['patient_name'] ?? __('messages.unknown_user') }}</p>
                                    <div class="flex items-center gap-2 text-xs text-text-sub-light dark:text-text-sub-dark">
                                        <span class="material-symbols-outlined text-xs">stethoscope</span>
                                        <span>{{ $review['doctor_name'] ?? '' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Stars --}}
                            <div class="flex items-center gap-1 mb-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <span class="material-symbols-outlined text-base {{ $i <= $rating ? 'text-amber-400 filled' : 'text-gray-300 dark:text-gray-600' }}">star</span>
                                @endfor
                                <span class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark ltr:ml-1 rtl:mr-1">{{ $rating }}/5</span>
                            </div>

                            {{-- Comment --}}
                            @if(!empty($review['comment']))
                            <p class="text-sm text-text-main-light dark:text-text-main-dark leading-relaxed mt-2">{{ $review['comment'] }}</p>
                            @endif

                            {{-- Meta --}}
                            <div class="flex items-center gap-4 mt-3 text-[11px] text-text-sub-light dark:text-text-sub-dark">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[11px]">calendar_today</span>
                                    {{ \Carbon\Carbon::parse($review['created_at'] ?? '')->format('M d, Y') }}
                                </span>
                                @if(!$isVisible)
                                <span class="flex items-center gap-1 text-red-500">
                                    <span class="material-symbols-outlined text-[11px]">visibility_off</span>
                                    {{ __('messages.review_hidden') }}
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- Toggle Visibility (super_admin only) --}}
                        @if($isSuperAdmin)
                        <div class="flex-shrink-0">
                            <form method="POST" action="{{ route('reviews.toggle', $review['id']) }}">
                                @csrf
                                <input type="hidden" name="visible" value="{{ $isVisible ? '0' : '1' }}">
                                <button type="submit"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5
                                    {{ $isVisible
                                        ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 border border-red-200 dark:border-red-900/30'
                                        : 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-900/30'
                                    }}">
                                    <span class="material-symbols-outlined text-sm">{{ $isVisible ? 'visibility_off' : 'visibility' }}</span>
                                    {{ $isVisible ? __('messages.hide_review') : __('messages.show_review') }}
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
