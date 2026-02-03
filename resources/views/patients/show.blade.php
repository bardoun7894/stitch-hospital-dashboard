<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Patient Medical Record Detail View</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#741ce9",
                        "primary-dark": "#5e15be",
                        "background-light": "#f7f6f8",
                        "background-dark": "#181121",
                        "surface-light": "#ffffff",
                        "surface-dark": "#231a2e",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .dark ::-webkit-scrollbar-thumb { background: #4b5563; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#141118] dark:text-gray-100 font-display min-h-screen flex flex-col">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-surface-light dark:bg-surface-dark border-b border-[#f2f0f4] dark:border-gray-800 shadow-sm">
        <div class="px-6 md:px-10 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4 text-[#141118] dark:text-white">
                <div class="text-primary size-8 flex items-center justify-center bg-primary/10 rounded-lg">
                    <span class="material-symbols-outlined text-2xl">local_hospital</span>
                </div>
                <h2 class="text-lg font-bold leading-tight tracking-[-0.015em]">MediRecord Pro</h2>
            </div>
            <div class="hidden md:flex flex-1 justify-end gap-6 items-center">
                <!-- Search -->
                <label class="flex items-center w-full max-w-xs h-10 bg-[#f2f0f4] dark:bg-gray-800 rounded-lg overflow-hidden group focus-within:ring-2 ring-primary/50 transition-all">
                    <div class="pl-3 text-[#736388] dark:text-gray-400 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">search</span>
                    </div>
                    <input class="w-full bg-transparent border-none text-[#141118] dark:text-white placeholder:text-[#736388] dark:placeholder:text-gray-500 text-sm px-2 focus:ring-0" placeholder="{{ __('messages.search_placeholder_general') }}"/>
                </label>
                <!-- Actions -->
                <div class="flex gap-2">
                    <button class="flex items-center justify-center size-10 rounded-lg bg-[#f2f0f4] dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-[#141118] dark:text-white transition-colors relative">
                        <span class="material-symbols-outlined text-[20px]">notifications</span>
                        <span class="absolute top-2 right-2 size-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-800"></span>
                    </button>
                    <button class="flex items-center justify-center size-10 rounded-lg bg-[#f2f0f4] dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-[#141118] dark:text-white transition-colors">
                        <span class="material-symbols-outlined text-[20px]">settings</span>
                    </button>
                </div>
                <!-- User Profile -->
                <div class="flex items-center gap-3 pl-4 border-l border-gray-100 dark:border-gray-700">
                    <div class="text-right hidden lg:block">
                        <p class="text-sm font-bold text-[#141118] dark:text-white">Dr. Sarah Wilson</p>
                        <p class="text-xs text-[#736388] dark:text-gray-400">Cardiology Dept.</p>
                    </div>
                    <div class="bg-center bg-no-repeat bg-cover rounded-full size-10 border-2 border-white dark:border-gray-700 shadow-sm" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuDAYEvvzDEWGbmOJIjHXLJ7L2Aw9fFS9Q4J8o_DWxYMGdcDxQ8Ji5zAyA5hRlypIhiSL_xhAT5bUf9fj6yNbXgG0Iza90b29_cwQQM6DaaeXBeHo6FRxBojfkavdQwyxyWPvABepiuhP8bHx7HhkBP4Yp81KFaqyx4OCabgkegvRu0qF2CYJrEqfsTQDaUrQnJTwMd7kZVbpAFZ-TCfvFlv1x9gRkeisDBX5pnTpZjxfGAWuWTihy3P5LVJp2jzR_2ZCjbMOZQvUYQ");'></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="flex-1 flex flex-col w-full max-w-[1440px] mx-auto px-4 md:px-8 py-6 gap-6">
        <!-- Patient Profile Header & Actions -->
        <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm p-6 border border-gray-100 dark:border-gray-800">
            <div class="flex flex-col md:flex-row gap-6 justify-between items-start md:items-center">
                <div class="flex flex-col sm:flex-row gap-5 items-start sm:items-center w-full md:w-auto">
                    <div class="relative group">
                        <div class="bg-center bg-no-repeat bg-cover rounded-full size-24 border-4 border-[#f7f6f8] dark:border-gray-800 shadow-inner" style='background-image: url("{{ $patient['avatar'] }}");'></div>
                        <div class="absolute bottom-1 right-1 bg-green-500 size-4 rounded-full border-2 border-white dark:border-gray-800" title="{{ __('messages.patient_checked_in') }}"></div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-bold text-[#141118] dark:text-white">{{ $patient['name'] }}</h1>
                            <span class="bg-primary/10 text-primary dark:text-purple-300 text-xs font-bold px-2 py-0.5 rounded-md border border-primary/20">{{ __('messages.mrn_label') }} {{ $patient['id'] }}</span>
                            <button class="text-gray-400 hover:text-primary transition-colors" title="Toggle Privacy Mode">
                                <span class="material-symbols-outlined text-[20px]">visibility</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-[#736388] dark:text-gray-400 flex-wrap">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">{{ $patient['gender'] === 'Female' ? 'female' : 'male' }}</span> {{ $patient['gender'] }}, {{ $patient['age'] }} {{ __('messages.age_unit') }}</span>
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span>{{ __('messages.dob_label') }} {{ $patient['dob'] }}</span>
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-red-500">water_drop</span> Blood Type: <span class="font-bold text-[#141118] dark:text-white">{{ $patient['blood_type'] }}</span></span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-100 dark:border-yellow-900/50 max-w-fit">
                            <span class="material-symbols-outlined text-yellow-600 dark:text-yellow-500 text-[18px]">emergency</span>
                            <span class="text-xs font-medium text-yellow-800 dark:text-yellow-200">Emergency: {{ $patient['emergency_contact']['name'] }} ({{ $patient['emergency_contact']['relation'] }}) • {{ $patient['emergency_contact']['phone'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex w-full md:w-auto gap-3">
                    <button class="flex-1 md:flex-none h-10 px-4 items-center justify-center gap-2 rounded-lg bg-[#f2f0f4] dark:bg-gray-800 text-[#141118] dark:text-white hover:bg-gray-200 dark:hover:bg-gray-700 font-bold text-sm transition-colors border border-transparent">
                        <span class="material-symbols-outlined text-[18px]">edit_note</span>
                        <span>Add Note</span>
                    </button>
                    <button class="flex-1 md:flex-none h-10 px-4 items-center justify-center gap-2 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-sm transition-colors shadow-lg shadow-primary/20">
                        <span class="material-symbols-outlined text-[18px]">ios_share</span>
                        <span>Export Record</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700 bg-transparent">
            <nav aria-label="Tabs" class="-mb-px flex space-x-8 overflow-x-auto no-scrollbar">
                <a class="border-primary text-primary dark:text-primary whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm flex items-center gap-2" href="#">
                    <span class="material-symbols-outlined text-[20px]">dashboard</span> Overview
                </a>
                <a class="border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2" href="#">
                    <span class="material-symbols-outlined text-[20px]">history</span> History
                </a>
                <a class="border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2" href="#">
                    <span class="material-symbols-outlined text-[20px]">biotech</span> Lab Results
                </a>
                <a class="border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2" href="#">
                    <span class="material-symbols-outlined text-[20px]">pill</span> Prescriptions
                </a>
                <a class="border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2" href="#">
                    <span class="material-symbols-outlined text-[20px]">description</span> Documents
                </a>
            </nav>
        </div>

        <!-- Vitals Summary Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- BP -->
            <div class="bg-surface-light dark:bg-surface-dark p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[#736388] dark:text-gray-400 uppercase tracking-wider">Blood Pressure</p>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-bold text-[#141118] dark:text-white">{{ $patient['vitals']['bp']['value'] }}</span>
                        <span class="text-xs font-medium text-gray-500">{{ $patient['vitals']['bp']['unit'] }}</span>
                    </div>
                </div>
                <div class="size-10 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <span class="material-symbols-outlined">monitor_heart</span>
                </div>
            </div>
            <!-- HR -->
            <div class="bg-surface-light dark:bg-surface-dark p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[#736388] dark:text-gray-400 uppercase tracking-wider">Heart Rate</p>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-bold text-[#141118] dark:text-white">{{ $patient['vitals']['hr']['value'] }}</span>
                        <span class="text-xs font-medium text-gray-500">{{ $patient['vitals']['hr']['unit'] }}</span>
                    </div>
                </div>
                <div class="size-10 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-600 dark:text-red-400">
                    <span class="material-symbols-outlined">ecg_heart</span>
                </div>
            </div>
            <!-- Weight -->
            <div class="bg-surface-light dark:bg-surface-dark p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[#736388] dark:text-gray-400 uppercase tracking-wider">Weight</p>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-bold text-[#141118] dark:text-white">{{ $patient['vitals']['weight']['value'] }}</span>
                        <span class="text-xs font-medium text-gray-500">{{ $patient['vitals']['weight']['unit'] }}</span>
                    </div>
                </div>
                <div class="size-10 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center text-green-600 dark:text-green-400">
                    <span class="material-symbols-outlined">monitor_weight</span>
                </div>
            </div>
            <!-- Temp -->
            <div class="bg-surface-light dark:bg-surface-dark p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[#736388] dark:text-gray-400 uppercase tracking-wider">Temperature</p>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-2xl font-bold text-[#141118] dark:text-white">{{ $patient['vitals']['temp']['value'] }}</span>
                        <span class="text-xs font-medium text-gray-500">{{ $patient['vitals']['temp']['unit'] }}</span>
                    </div>
                </div>
                <div class="size-10 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center text-orange-600 dark:text-orange-400">
                    <span class="material-symbols-outlined">device_thermostat</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 flex flex-col gap-6">
                <!-- Lab Results Card -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/30">
                        <h3 class="font-bold text-lg text-[#141118] dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">science</span> Recent Lab Results
                        </h3>
                        <a class="text-sm text-primary font-semibold hover:underline" href="#">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/30">
                                    <th class="px-6 py-3 font-semibold uppercase tracking-wider">Test Name</th>
                                    <th class="px-6 py-3 font-semibold uppercase tracking-wider text-right">Result</th>
                                    <th class="px-6 py-3 font-semibold uppercase tracking-wider text-right">Reference</th>
                                    <th class="px-6 py-3 font-semibold uppercase tracking-wider text-center">Trend (6mo)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                                @foreach($patient['lab_results'] as $lab)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors {{ $lab['status'] === 'critical' ? 'bg-red-50/10' : '' }}">
                                    <td class="px-6 py-4 font-medium text-[#141118] dark:text-white flex items-center gap-2">
                                        {{ $lab['name'] }}
                                        @if($lab['status'] === 'critical')
                                            <span class="size-2 rounded-full bg-red-500" title="Abnormal"></span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-{{ $lab['color'] }}-600">{{ $lab['result'] }}</td>
                                    <td class="px-6 py-4 text-right text-gray-500">{{ $lab['reference'] }}</td>
                                    <td class="px-6 py-4">
                                        <div class="w-24 h-8 mx-auto flex items-end gap-0.5">
                                            @php
                                                $barHeights = match($lab['trend']) {
                                                    'down' => ['80%', '70%', '75%', '60%', '40%'],
                                                    'stable' => ['50%', '55%', '52%', '58%', '55%'],
                                                    'up' => ['40%', '50%', '60%', '75%', '90%'],
                                                };
                                                $baseColor = $lab['status'] === 'critical' ? 'red' : 'primary';
                                                $opacities = ['20', '30', '40', '60', ''];
                                            @endphp
                                            @foreach($barHeights as $index => $height)
                                                <div class="w-full bg-{{ $baseColor }}{{ $opacities[$index] ? '/' . $opacities[$index] : '' }} rounded-sm" style="height: {{ $height }};"></div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Patient History Timeline -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-lg text-[#141118] dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">history_edu</span> Medical History Timeline
                        </h3>
                    </div>
                    <div class="relative pl-4 border-l-2 border-gray-200 dark:border-gray-700 space-y-8">
                        @foreach($patient['history'] as $event)
                        <div class="relative pl-6">
                            <div class="absolute -left-[9px] top-1 size-4 rounded-full bg-surface-light dark:bg-surface-dark border-2 border-{{ $event['color'] === 'primary' ? 'primary' : $event['color'] . '-500' }}"></div>
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1">
                                <div>
                                    <h4 class="text-base font-bold text-[#141118] dark:text-white">{{ $event['title'] }}</h4>
                                    <p class="text-sm text-[#736388] dark:text-gray-400 mt-1">{{ $event['notes'] }}</p>
                                    @if($event['doctor'])
                                    <div class="flex items-center gap-2 mt-2 text-xs font-medium text-gray-500">
                                        <span class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">{{ $event['doctor'] }}</span>
                                        @if($event['dept'])
                                        <span class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">{{ $event['dept'] }}</span>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                                <span class="text-xs font-semibold text-gray-500 bg-gray-50 dark:bg-gray-800 px-2 py-1 rounded border border-gray-100 dark:border-gray-700 whitespace-nowrap">{{ $event['date'] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-6 text-center">
                        <button class="text-sm text-primary font-bold hover:underline">Load older history...</button>
                    </div>
                </div>
            </div>

            <!-- Right Column (Sidebar) -->
            <div class="flex flex-col gap-6">
                <!-- Allergies Card -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-red-100 dark:border-red-900/50 overflow-hidden">
                    <div class="px-5 py-3 bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-900/30 flex justify-between items-center">
                        <h3 class="font-bold text-base text-red-800 dark:text-red-300 flex items-center gap-2">
                            <span class="material-symbols-outlined">warning</span> Allergies
                        </h3>
                        <span class="bg-red-200 dark:bg-red-800 text-red-800 dark:text-red-100 text-[10px] font-bold px-2 py-0.5 rounded-full">CRITICAL</span>
                    </div>
                    <div class="p-4 flex flex-col gap-3">
                        @foreach($patient['allergies'] as $allergy)
                            @if($allergy['severity'] === 'critical')
                            <div class="flex items-center justify-between p-3 bg-red-50/50 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/20">
                                <div>
                                    <p class="font-bold text-sm text-[#141118] dark:text-white">{{ $allergy['name'] }}</p>
                                    <p class="text-xs text-red-600 dark:text-red-400">{{ $allergy['reaction'] }}</p>
                                </div>
                                <span class="material-symbols-outlined text-red-500">block</span>
                            </div>
                            @else
                            <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
                                <div>
                                    <p class="font-bold text-sm text-[#141118] dark:text-white">{{ $allergy['name'] }}</p>
                                    <p class="text-xs text-[#736388] dark:text-gray-400">{{ $allergy['reaction'] }}</p>
                                </div>
                                <span class="material-symbols-outlined text-gray-400">info</span>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <!-- Chronic Conditions -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="font-bold text-lg text-[#141118] dark:text-white">Chronic Conditions</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($patient['conditions'] as $condition)
                        @php
                            $icon = match($condition['name']) {
                                'Hypertension' => 'water_drop',
                                'Type 2 Diabetes' => 'glucose',
                                'Osteoarthritis' => 'skeleton',
                                default => 'healing'
                            };
                            $dotColor = match($condition['state']) {
                                'managed' => 'green-500',
                                'monitoring' => 'yellow-500',
                                'stable' => 'green-500',
                                default => 'gray-500'
                            };
                        @endphp
                        <div class="p-4 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer">
                            <div class="size-10 rounded-lg bg-{{ $condition['color'] }}-50 dark:bg-{{ $condition['color'] }}-900/20 text-{{ $condition['color'] }}-600 dark:text-{{ $condition['color'] }}-400 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined">{{ $icon }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-[#141118] dark:text-white truncate">{{ $condition['name'] }}</p>
                                <p class="text-xs text-[#736388] dark:text-gray-400">{{ $condition['status'] }}</p>
                            </div>
                            <span class="size-2 rounded-full bg-{{ $dotColor }}" title="{{ ucfirst($condition['state']) }}"></span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Documents -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-[#141118] dark:text-white">Documents</h3>
                        <button class="text-xs font-bold text-primary bg-primary/10 px-2 py-1 rounded hover:bg-primary/20 transition-colors">Upload</button>
                    </div>
                    <div class="p-2 space-y-1">
                        @foreach($patient['documents'] as $doc)
                        <a class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group" href="#">
                            <div class="size-8 flex items-center justify-center bg-gray-100 dark:bg-gray-800 text-gray-500 group-hover:text-primary rounded">
                                <span class="material-symbols-outlined text-[20px]">{{ $doc['type'] === 'image' ? 'image' : 'description' }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#141118] dark:text-white truncate">{{ $doc['name'] }}</p>
                                <p class="text-xs text-gray-400">{{ $doc['meta'] }}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
