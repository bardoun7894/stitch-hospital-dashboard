@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('doctors.index') }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-[#637388]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.add_doctor') }}</h1>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('doctors.store') }}" method="POST" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name') }} *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.name') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name_en') }}</label>
            <input type="text" name="name_en" value="{{ old('name_en') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.name_en') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.specialty') }} *</label>
            <input type="text" name="specialty" value="{{ old('specialty') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.specialty') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.assign_clinic') }}</label>
            <select name="clinic_id" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
                <option value="">{{ __('messages.no_clinic_standalone') }}</option>
                @foreach($clinics as $clinic)
                <option value="{{ $clinic['id'] }}" {{ old('clinic_id', request('clinic_id')) === $clinic['id'] ? 'selected' : '' }}>{{ $clinic['name'] ?? $clinic['id'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.phone_number') }}</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.phone_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.consultation_fee') }}</label>
            <input type="number" name="consultation_fee" value="{{ old('consultation_fee') }}" min="0" step="0.01" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="0.00">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.status') }} *</label>
            <select name="status" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
                <option value="available" {{ old('status', 'available') === 'available' ? 'selected' : '' }}>{{ __('messages.available') }}</option>
                <option value="busy" {{ old('status') === 'busy' ? 'selected' : '' }}>{{ __('messages.busy') }}</option>
                <option value="off" {{ old('status') === 'off' ? 'selected' : '' }}>{{ __('messages.off_duty') }}</option>
            </select>
        </div>

        {{-- Professional Info Section --}}
        <div class="pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
            <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-base">school</span>
                {{ __('messages.professional_info') }}
            </h3>

            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.bio') }}</label>
                    <textarea name="bio" rows="3" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.bio') }}">{{ old('bio') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.bio_en') }}</label>
                    <textarea name="bio_en" rows="3" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.bio_en') }}">{{ old('bio_en') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.education') }}</label>
                    <input type="text" name="education" value="{{ old('education') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="e.g. MD, King Saud University">
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.years_experience') }}</label>
                    <input type="number" name="years_experience" value="{{ old('years_experience') }}" min="0" max="80" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="0">
                </div>
            </div>
        </div>

        {{-- Certifications Section --}}
        <div class="pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]" x-data="{ certifications: {{ json_encode(old('certifications', [])) }}, newCert: '' }">
            <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-base">workspace_premium</span>
                {{ __('messages.certifications') }}
            </h3>

            <div class="flex gap-2 mb-3">
                <input type="text" x-model="newCert" @keydown.enter.prevent="if(newCert.trim()) { certifications.push(newCert.trim()); newCert = ''; }" class="flex-1 px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.add_certification') }}">
                <button type="button" @click="if(newCert.trim()) { certifications.push(newCert.trim()); newCert = ''; }" class="px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">add</span>
                    {{ __('messages.add_certification') }}
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <template x-for="(cert, index) in certifications" :key="index">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-xs font-medium">
                        <span x-text="cert"></span>
                        <input type="hidden" name="certifications[]" :value="cert">
                        <button type="button" @click="certifications.splice(index, 1)" class="hover:text-red-500 transition-colors">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </span>
                </template>
            </div>
        </div>

        {{-- Languages Section --}}
        <div class="pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]" x-data="{ languages: {{ json_encode(old('languages', [])) }} }">
            <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-base">translate</span>
                {{ __('messages.languages_spoken') }}
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach(['Arabic', 'English', 'French', 'Urdu', 'Hindi', 'Filipino'] as $lang)
                <label class="flex items-center gap-2 p-3 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors cursor-pointer" :class="languages.includes('{{ $lang }}') ? 'bg-primary/5 border-primary/30' : ''">
                    <input type="checkbox" name="languages[]" value="{{ $lang }}" :checked="languages.includes('{{ $lang }}')" @change="$event.target.checked ? languages.push('{{ $lang }}') : languages.splice(languages.indexOf('{{ $lang }}'), 1)" class="rounded border-[#e5e7eb] text-primary focus:ring-primary/30">
                    <span class="text-sm text-[#111418] dark:text-white">{{ $lang }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Doctor Login Credentials Section --}}
        <div class="pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]" x-data="{ generateCredentials: {{ old('generate_credentials') ? 'true' : 'false' }} }">
            <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-base">key</span>
                {{ __('messages.doctor_login_credentials') }}
            </h3>

            <label class="flex items-center gap-2 mb-4 cursor-pointer">
                <input type="checkbox" name="generate_credentials" value="1" x-model="generateCredentials" class="rounded border-[#e5e7eb] text-primary focus:ring-primary/30">
                <span class="text-sm text-[#111418] dark:text-white font-medium">{{ __('messages.generate_login_credentials') }}</span>
            </label>

            <div x-show="generateCredentials" x-collapse style="display:none" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.admin_email') }} *</label>
                    <input type="email" name="login_email" value="{{ old('login_email') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="doctor@clinic.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.password') }} *</label>
                    <input type="password" name="login_password" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.password_placeholder') }}">
                </div>

                {{-- Accessible Clinics (multi-select) --}}
                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.accessible_clinics') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($clinics as $clinic)
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors cursor-pointer text-sm">
                            <input type="checkbox" name="assigned_clinic_ids[]" value="{{ $clinic['id'] }}" {{ in_array($clinic['id'], old('assigned_clinic_ids', [])) ? 'checked' : '' }} class="rounded border-[#e5e7eb] text-primary focus:ring-primary/30">
                            <span class="text-[#111418] dark:text-white">{{ $clinic['name'] ?? $clinic['id'] }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm transition-colors">
                {{ __('messages.add_doctor') }}
            </button>
            <a href="{{ route('doctors.index') }}" class="px-6 py-2.5 text-[#637388] hover:text-[#111418] dark:hover:text-white font-medium text-sm transition-colors">
                {{ __('messages.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
