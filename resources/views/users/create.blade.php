@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('users.index') }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-[#637388]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.create_user') }}</h1>
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
    <form action="{{ route('users.store') }}" method="POST" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.full_name') }} *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.full_name_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.email') }} *</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.email_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.password') }} *</label>
            <input type="password" name="password" required minlength="6" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.password_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.phone_number') }}</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.phone_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.role') }} *</label>
            <select name="role" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="">{{ __('messages.select') }}...</option>
                @foreach($roles as $role)
                <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>{{ __('messages.' . $role) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Hospital first, then clinic (filtered) --}}
        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.assign_hospital') }} *</label>
            <select name="hospital_id" id="hospital_id" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="">{{ __('messages.select') }}...</option>
                @foreach($hospitals as $hospital)
                <option value="{{ $hospital['id'] }}" {{ old('hospital_id', request('hospital_id')) === $hospital['id'] ? 'selected' : '' }}>{{ $hospital['name'] ?? $hospital['id'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.assign_clinic') }} *</label>
            <select name="clinic_id" id="clinic_id" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="">{{ __('messages.select_hospital_first') }}</option>
                @foreach($clinics as $clinic)
                <option value="{{ $clinic['id'] }}" data-hospital="{{ $clinic['hospital_id'] ?? '' }}" {{ old('clinic_id', request('clinic_id')) === $clinic['id'] ? 'selected' : '' }}>{{ $clinic['name'] ?? $clinic['id'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm transition-colors">
                {{ __('messages.create_user') }}
            </button>
            <a href="{{ route('users.index') }}" class="px-6 py-2.5 text-[#637388] hover:text-[#111418] dark:hover:text-white font-medium text-sm transition-colors">
                {{ __('messages.cancel') }}
            </a>
        </div>
    </form>
</div>

<script>
// Filter clinics dropdown based on selected hospital
const hospitalSelect = document.getElementById('hospital_id');
const clinicSelect = document.getElementById('clinic_id');
const allClinicOptions = Array.from(clinicSelect.querySelectorAll('option[data-hospital]'));
const defaultOption = clinicSelect.querySelector('option:first-child');

function filterClinics() {
    const selectedHospital = hospitalSelect.value;
    const currentClinic = clinicSelect.value;

    // Remove all options except default
    while (clinicSelect.options.length > 1) {
        clinicSelect.remove(1);
    }

    if (!selectedHospital) {
        defaultOption.textContent = '{{ __("messages.select_hospital_first") }}';
        return;
    }

    defaultOption.textContent = '{{ __("messages.select") }}...';

    // Add back matching clinics
    let found = false;
    allClinicOptions.forEach(opt => {
        if (opt.dataset.hospital === selectedHospital) {
            const newOpt = opt.cloneNode(true);
            if (newOpt.value === currentClinic) {
                newOpt.selected = true;
                found = true;
            }
            clinicSelect.appendChild(newOpt);
        }
    });

    // If current selection is not valid for new hospital, reset
    if (!found) {
        clinicSelect.value = '';
    }
}

hospitalSelect.addEventListener('change', filterClinics);

// Run on page load if hospital is pre-selected
if (hospitalSelect.value) {
    filterClinics();
}
</script>
@endsection
