@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('doctors.index') }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-[#637388]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.edit_doctor') }}</h1>
            <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ $doctor['name'] ?? '' }}</p>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-lg text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">check_circle</span> {{ session('success') }}
    </div>
    @endif

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
    <form action="{{ route('doctors.update', $doctor['id']) }}" method="POST" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name') }} *</label>
            <input type="text" name="name" value="{{ old('name', $doctor['name'] ?? '') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name_en') }}</label>
            <input type="text" name="name_en" value="{{ old('name_en', $doctor['name_en'] ?? '') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.specialty') }} *</label>
            <input type="text" name="specialty" value="{{ old('specialty', $doctor['specialty'] ?? '') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.assign_clinic') }} *</label>
            <select name="clinic_id" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
                <option value="">{{ __('messages.select') }}...</option>
                @foreach($clinics as $clinic)
                <option value="{{ $clinic['id'] }}" {{ old('clinic_id', $doctor['clinic_id'] ?? '') === $clinic['id'] ? 'selected' : '' }}>{{ $clinic['name'] ?? $clinic['id'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.phone_number') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $doctor['phone'] ?? '') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.consultation_fee') }}</label>
            <input type="number" name="consultation_fee" value="{{ old('consultation_fee', $doctor['consultation_fee'] ?? '') }}" min="0" step="0.01" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="0.00">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.status') }} *</label>
            <select name="status" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white">
                <option value="available" {{ old('status', $doctor['status'] ?? '') === 'available' ? 'selected' : '' }}>{{ __('messages.available') }}</option>
                <option value="busy" {{ old('status', $doctor['status'] ?? '') === 'busy' ? 'selected' : '' }}>{{ __('messages.busy') }}</option>
                <option value="off" {{ old('status', $doctor['status'] ?? '') === 'off' ? 'selected' : '' }}>{{ __('messages.off_duty') }}</option>
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
                    <textarea name="bio" rows="3" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.bio') }}">{{ old('bio', $doctor['bio'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.bio_en') }}</label>
                    <textarea name="bio_en" rows="3" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="{{ __('messages.bio_en') }}">{{ old('bio_en', $doctor['bio_en'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.education') }}</label>
                    <input type="text" name="education" value="{{ old('education', $doctor['education'] ?? '') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="e.g. MD, King Saud University">
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.years_experience') }}</label>
                    <input type="number" name="years_experience" value="{{ old('years_experience', $doctor['years_experience'] ?? '') }}" min="0" max="80" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary text-[#111418] dark:text-white" placeholder="0">
                </div>
            </div>
        </div>

        {{-- Certifications Section --}}
        <div class="pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]" x-data="{ certifications: {{ json_encode(old('certifications', $doctor['certifications'] ?? [])) }}, newCert: '' }">
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
        @php $doctorLanguages = old('languages', $doctor['languages'] ?? []); @endphp
        <div class="pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]" x-data="{ languages: {{ json_encode($doctorLanguages) }} }">
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

        <div class="flex items-center gap-3 pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm transition-colors">
                {{ __('messages.save_changes') }}
            </button>
            <a href="{{ route('doctors.index') }}" class="px-6 py-2.5 text-[#637388] hover:text-[#111418] dark:hover:text-white font-medium text-sm transition-colors">
                {{ __('messages.cancel') }}
            </a>
        </div>
    </form>

    {{-- Profile Photo Upload --}}
    <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 mt-6">
        <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-base">photo_camera</span>
            {{ __('messages.photo') }}
        </h3>

        <div class="flex items-center gap-6">
            {{-- Photo Preview --}}
            <div class="shrink-0">
                @if(!empty($doctor['photo_url']))
                <img src="{{ $doctor['photo_url'] }}" alt="{{ $doctor['name'] ?? '' }}" class="size-24 rounded-full object-cover border-2 border-[#e5e7eb] dark:border-[#2d3748]">
                @else
                <div class="size-24 rounded-full bg-primary/10 text-primary flex items-center justify-center border-2 border-[#e5e7eb] dark:border-[#2d3748]">
                    <span class="material-symbols-outlined text-3xl">person</span>
                </div>
                @endif
            </div>

            {{-- Upload Form --}}
            <form action="{{ route('doctors.upload-photo', $doctor['id']) }}" method="POST" enctype="multipart/form-data" class="flex-1">
                @csrf
                <div class="space-y-3">
                    <input type="file" name="photo" accept="image/jpeg,image/png" class="block w-full text-sm text-[#637388] file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary/10 file:text-primary hover:file:bg-primary/20 file:cursor-pointer file:transition-colors">
                    <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ __('messages.photo_requirements') }}</p>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm transition-colors inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">upload</span>
                        {{ !empty($doctor['photo_url']) ? __('messages.change_photo') : __('messages.upload_photo') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Quick Actions --}}
    @if(!empty($doctor['clinic_id']))
    <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 mt-6">
        <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4">{{ __('messages.quick_actions') }}</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('clinics.edit', $doctor['clinic_id']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                <span class="material-symbols-outlined text-base text-primary">arrow_back</span>
                {{ __('messages.back_to_clinic') }}
            </a>
            <a href="{{ route('doctors.create', ['clinic_id' => $doctor['clinic_id']]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                <span class="material-symbols-outlined text-base text-primary">person_add</span>
                {{ __('messages.add_another_doctor') }}
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
