<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.register_hospital') }} - {{ __('messages.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-slate-50 to-indigo-100 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950 min-h-screen font-sans antialiased flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative">
    
    <!-- Decorative Background Shapes -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-20 -left-20 w-96 h-96 bg-primary/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute top-1/4 right-0 w-72 h-72 bg-indigo-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-0 left-1/3 w-80 h-80 bg-blue-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 4s;"></div>
    </div>

    <div class="w-full max-w-2xl bg-white dark:bg-surface-dark rounded-3xl overflow-hidden relative z-10 animate-fade-in-up mx-auto border border-gray-200 dark:border-gray-700 shadow-xl">
        <div class="h-2 w-full bg-gradient-to-r from-primary to-indigo-400"></div>

        <div class="p-8 sm:p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto size-16 flex items-center justify-center rounded-2xl bg-gradient-to-br from-primary/10 to-indigo-500/10 text-primary mb-6 shadow-sm border border-primary/10">
                    <span class="material-symbols-outlined text-4xl">domain_add</span>
                </div>
                <h2 class="text-3xl font-bold text-text-main-light dark:text-white tracking-tight mb-2">
                    {{ __('messages.register_hospital') }}
                </h2>
                <p class="text-text-sub-light dark:text-text-sub-dark">
                    {{ __('messages.register_hospital_subtitle') }}
                </p>
            </div>

            <!-- Errors -->
            @if($errors->any())
            <div class="mb-6 bg-rose-50/50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-xl p-4 flex gap-3 items-start animate-pulse">
                <span class="material-symbols-outlined text-rose-500 text-sm mt-0.5">error</span>
                <ul class="text-sm text-rose-600 dark:text-rose-400 list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(session('error'))
            <div class="mb-6 bg-rose-50/50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-xl p-4 flex gap-3 items-start animate-pulse">
                <span class="material-symbols-outlined text-rose-500 text-sm mt-0.5">error</span>
                <p class="text-sm text-rose-600 dark:text-rose-400">{{ session('error') }}</p>
            </div>
            @endif

            <!-- Registration Form Wizard -->
            <div x-data="{ 
                step: 1, 
                steps: [
                    { id: 1, title: '{{ __('messages.hospital_info') }}', icon: 'domain' },
                    { id: 2, title: '{{ __('messages.contact_person') }}', icon: 'person' },
                    { id: 3, title: '{{ __('messages.account_password') }}', icon: 'lock' }
                ],
                nextStep() {
                    if (this.validateStep(this.step)) {
                        this.step++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },
                prevStep() {
                    this.step--;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                validateStep(currentStep) {
                    const container = document.getElementById('step-' + currentStep);
                    // Select all validate-able inputs
                    const inputs = Array.from(container.querySelectorAll('input[required]'));
                    
                    for (const input of inputs) {
                        if (!input.checkValidity()) {
                            input.reportValidity();
                            input.focus(); // Focus the first invalid field
                            return false; 
                        }
                    }
                    return true;
                },
                submitForm() {
                    if (this.validateStep(this.step)) {
                        document.getElementById('registration-form').submit();
                    }
                }
            }" class="w-full">
            
                <!-- Stepper Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between relative">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-gray-200 dark:bg-slate-700 rounded-lg -z-10"></div>
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-primary transition-all duration-300 rounded-lg -z-10" :style="'width: ' + ((step - 1) / (steps.length - 1) * 100) + '%'"></div>
                        
                        <template x-for="s in steps" :key="s.id">
                            <div class="flex flex-col items-center gap-2 cursor-pointer" @click="if(step > s.id) step = s.id">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-300"
                                     :class="step >= s.id ? 'bg-primary border-primary text-white shadow-lg shadow-primary/30' : 'bg-white dark:bg-slate-800 border-gray-300 dark:border-slate-600 text-gray-400 dark:text-gray-500'">
                                    <span class="material-symbols-outlined text-[20px]" x-text="s.icon"></span>
                                </div>
                                <span class="text-xs font-semibold hidden sm:block transition-colors duration-300" 
                                      :class="step >= s.id ? 'text-primary' : 'text-gray-400 dark:text-gray-500'" x-text="s.title"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <form id="registration-form" action="{{ route('hospital.register.submit') }}" method="POST" class="space-y-6" novalidate>
                    @csrf
    
                <!-- Step 1: Hospital Information -->
                <div id="step-1" x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="bg-gray-50 dark:bg-white/5 rounded-2xl p-6 border border-gray-200 dark:border-white/10">
                        <h3 class="text-lg font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl">domain</span>
                            {{ __('messages.hospital_info') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="group">
                                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.name') }} *</label>
                                <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.hospital_name_ar') }}">
                            </div>
                            <div class="group">
                                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.name_en') }}</label>
                                <input type="text" name="name_en" value="{{ old('name_en') }}" class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.hospital_name_en') }}">
                            </div>
                        </div>
                        <div class="mt-4 group">
                            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.address') }}</label>
                            <input type="text" name="address" value="{{ old('address') }}" class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.address_placeholder') }}">
                        </div>
                        <div class="mt-4 group">
                            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.phone') }}</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="+966-11-0000000" dir="ltr">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Contact Person -->
                <div id="step-2" x-show="step === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" style="display: none;">
                    <div class="bg-gray-50 dark:bg-white/5 rounded-2xl p-6 border border-gray-200 dark:border-white/10">
                        <h3 class="text-lg font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl">person</span>
                            {{ __('messages.contact_person') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="group">
                                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.contact_person') }} *</label>
                                <input type="text" name="contact_person" value="{{ old('contact_person') }}" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.contact_person_placeholder') }}">
                            </div>
                            <div class="group">
                                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.contact_phone') }}</label>
                                <input type="text" name="contact_phone" value="{{ old('contact_phone') }}" class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="+966-5-0000000" dir="ltr">
                            </div>
                        </div>
                        <div class="mt-4 group">
                            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.contact_email') }} *</label>
                            <input type="email" name="contact_email" value="{{ old('contact_email') }}" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.contact_email_placeholder') }}">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Account Password -->
                <div id="step-3" x-show="step === 3" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" style="display: none;">
                    <div class="bg-gray-50 dark:bg-white/5 rounded-2xl p-6 border border-gray-200 dark:border-white/10">
                        <h3 class="text-lg font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl">lock</span>
                            {{ __('messages.account_password') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="group">
                                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.password') }} *</label>
                                <input type="password" name="password" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.password_placeholder') }}">
                            </div>
                            <div class="group">
                                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">{{ __('messages.confirm_password') }} *</label>
                                <input type="password" name="password_confirmation" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all outline-none" placeholder="{{ __('messages.confirm_password_placeholder') }}">
                            </div>
                        </div>
                    </div>
    
                        <!-- Notice -->
                        <div class="mt-6 flex items-start gap-4 p-5 bg-amber-50/50 dark:bg-amber-900/10 border border-amber-200/50 dark:border-amber-900/30 rounded-xl">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 mt-0.5">info</span>
                            <p class="text-sm text-amber-800 dark:text-amber-200 leading-relaxed">{{ __('messages.hospital_submit_note') }}</p>
                        </div>
                    </div>
    
                    <!-- Navigation Buttons -->
                    <div class="flex items-center justify-between pt-4">
                        <button type="button" x-show="step > 1" style="display:none" @click="prevStep()" class="px-6 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 text-text-main-light dark:text-white font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                            {{ __('messages.back') ?? 'Back' }}
                        </button>
                        <div class="flex-1"></div>
                        <button type="button" x-show="step < 3" @click="nextStep()" class="px-6 py-2.5 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:bg-primary-hover transition-all active:scale-95">
                            {{ __('messages.next') ?? 'Next' }}
                        </button>
                        <button type="button" x-show="step === 3" style="display:none" @click="submitForm()" class="px-8 py-2.5 rounded-xl bg-gradient-to-r from-primary to-indigo-600 text-white font-bold shadow-lg shadow-primary/20 hover:from-primary-hover hover:to-indigo-700 transition-all active:scale-95 hover:-translate-y-0.5">
                            {{ __('messages.hospital_submit_registration') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="mt-8 pt-6 border-t border-gray-200/50 dark:border-slate-700/50 flex flex-col items-center gap-4">
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">
                    {{ __('messages.already_have_account') }}
                    <a href="{{ route('login') }}" class="font-bold text-primary hover:text-indigo-500 transition-colors hover:underline decoration-2 underline-offset-4">{{ __('messages.login') }}</a>
                </p>
                <div class="flex justify-center gap-3 p-1.5 bg-gray-100 dark:bg-slate-800 rounded-full border border-gray-200 dark:border-slate-700">
                    <a href="{{ route('switch-to-arabic') }}" class="text-xs font-medium px-4 py-1.5 rounded-full transition-all duration-300 {{ app()->getLocale() === 'ar' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-text-sub-light hover:text-text-main-light dark:text-text-sub-dark dark:hover:text-white' }}">العربية</a>
                    <a href="{{ route('switch-to-english') }}" class="text-xs font-medium px-4 py-1.5 rounded-full transition-all duration-300 {{ app()->getLocale() === 'en' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-text-sub-light hover:text-text-main-light dark:text-text-sub-dark dark:hover:text-white' }}">English</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
