@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isSuperAdmin = RoleMiddleware::hasRole('super_admin');
    $status = $hospital['status'] ?? 'active';
@endphp

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('hospital.show', $hospital['id']) }}" class="p-2 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors">
            <span class="material-symbols-outlined text-text-sub-light dark:text-text-sub-dark">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-text-main-light dark:text-white">{{ __('messages.edit_hospital') }}</h1>
            <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ $hospital['name'] ?? '' }}</p>
        </div>
    </div>

    {{-- Rejection Reason Banner --}}
    @if($status === 'rejected' && !empty($hospital['rejection_reason']))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-2xl text-sm border border-red-100 dark:border-red-900/30">
        <div class="flex items-center gap-2 mb-2">
            <span class="material-symbols-outlined text-lg">cancel</span>
            <span class="font-bold">{{ __('messages.hospital_rejected') }}</span>
        </div>
        <p class="mb-3">{{ __('messages.rejection_reason') }}: {{ $hospital['rejection_reason'] }}</p>
        <form action="{{ route('hospital.resubmit', $hospital['id']) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="bg-primary text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-primary-hover transition-colors">
                {{ __('messages.resubmit_for_review') }}
            </button>
        </form>
    </div>
    @endif

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
    @if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-2xl text-sm border border-red-100 dark:border-red-900/30">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('hospital.update', $hospital['id']) }}" method="POST" class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.name') }} *</label>
                <input type="text" name="name" value="{{ old('name', $hospital['name'] ?? '') }}" required class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
            </div>
            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.name_en') }}</label>
                <input type="text" name="name_en" value="{{ old('name_en', $hospital['name_en'] ?? '') }}" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.address') }}</label>
            <input type="text" name="address" value="{{ old('address', $hospital['address'] ?? '') }}" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
        </div>

        <div>
            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.phone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $hospital['phone'] ?? '') }}" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" dir="ltr">
        </div>

        <div class="border-t border-border-light dark:border-border-dark pt-5">
            <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-3">{{ __('messages.location') }}</h3>
            <x-map-picker
                :latitude="old('latitude', $hospital['location']['lat'] ?? $hospital['location']['latitude'] ?? '')"
                :longitude="old('longitude', $hospital['location']['lng'] ?? $hospital['location']['longitude'] ?? '')"
            />
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-primary-hover transition-colors shadow-md shadow-primary/20">{{ __('messages.update') }}</button>
            <a href="{{ route('hospital.show', $hospital['id']) }}" class="px-6 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors">{{ __('messages.cancel') }}</a>
        </div>
    </form>

    {{-- Hospital Logo Upload --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 mt-6">
        <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base">photo_camera</span>
            {{ __('messages.hospital_logo') }}
        </h3>

        <div class="flex items-center gap-6">
            {{-- Logo Preview --}}
            <div class="shrink-0">
                @if(!empty($hospital['logo_url']))
                <img src="{{ $hospital['logo_url'] }}" alt="{{ $hospital['name'] ?? '' }}" class="size-24 rounded-xl object-cover border-2 border-border-light dark:border-border-dark">
                @else
                <div class="size-24 rounded-xl bg-primary/10 text-primary flex items-center justify-center border-2 border-border-light dark:border-border-dark">
                    <span class="material-symbols-outlined text-3xl">domain</span>
                </div>
                @endif
            </div>

            {{-- Upload Form --}}
            <form action="{{ route('hospital.upload-logo', $hospital['id']) }}" method="POST" enctype="multipart/form-data" class="flex-1">
                @csrf
                <div class="space-y-3">
                    <input type="file" name="logo" accept="image/jpeg,image/png" class="block w-full text-sm text-text-sub-light dark:text-text-sub-dark file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary/10 file:text-primary hover:file:bg-primary/20 file:cursor-pointer file:transition-colors">
                    <p class="text-xs text-text-sub-light dark:text-text-sub-dark">{{ __('messages.logo_requirements') }}</p>
                    <button type="submit" class="px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-primary-hover font-bold text-sm transition-colors shadow-md shadow-primary/20 inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">upload</span>
                        {{ !empty($hospital['logo_url']) ? __('messages.change_logo') : __('messages.upload_logo') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 mt-6">
        <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4">{{ __('messages.quick_actions') }}</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('clinics.create', ['hospital_id' => $hospital['id']]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium text-text-main-light dark:text-white hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-base text-primary">add</span>
                {{ __('messages.add_clinic_to_hospital') }}
            </a>
            <a href="{{ route('users.create', ['hospital_id' => $hospital['id']]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium text-text-main-light dark:text-white hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-base text-primary">person_add</span>
                {{ __('messages.add_staff_to_hospital') }}
            </a>
            <a href="{{ route('hospital.show', $hospital['id']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium text-text-main-light dark:text-white hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-base text-primary">visibility</span>
                {{ __('messages.view_hospital') }}
            </a>
        </div>
    </div>
</div>
@endsection
