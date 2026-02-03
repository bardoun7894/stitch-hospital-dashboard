@extends('layouts.app')

@section('title', 'حمولة الأطباء - Doctor Load')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">حمولة الأطباء (Doctor Load)</h1>
            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">{{ date('Y-m-d') }}</span>
        </div>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('messages.doctor_header') }}</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('messages.bookings_header') }}</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('messages.arrived') }}</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">{{ __('messages.attendance_percent_header') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($report as $doc)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $doc['name'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                            {{ $doc['booked'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-center font-bold">
                            {{ $doc['arrived'] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            @php
                                $percent = $doc['booked'] > 0 ? round(($doc['arrived'] / $doc['booked']) * 100) : 0;
                            @endphp
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $percent > 70 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $percent }}%
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-500 italic">
                            {{ __('messages.no_doctor_data') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-6 bg-indigo-50 rounded-xl border border-indigo-100">
                <h3 class="text-lg font-bold text-indigo-900 mb-2">{{ __('messages.distribution_notes') }}</h3>
                <p class="text-sm text-indigo-700">{{ __('messages.distribution_description') }}</p>
            </div>

            <div class="p-6 bg-green-50 rounded-xl border border-green-100">
                <h3 class="text-lg font-bold text-green-900 mb-2">{{ __('messages.compliance_analysis') }}</h3>
                <p class="text-sm text-green-700">{{ __('messages.compliance_description') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
