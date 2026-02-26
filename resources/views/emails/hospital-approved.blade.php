@extends('emails.layout')

@section('title', __('messages.email_hospital_approved_subject'))

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 24px; color: #1e293b; text-align: center;">
        {{ __('messages.email_hospital_approved_subject') }}
    </h1>

    {{-- English --}}
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        {{ __('messages.email_greeting') }},
    </p>
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        Congratulations! Your hospital <strong>{{ $hospital['name'] ?? $hospital['name_en'] ?? '' }}</strong> has been approved and is now active on our platform. You can now log in and start managing your clinics, doctors, and bookings.
    </p>

    {{-- Arabic --}}
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7; direction: rtl; text-align: right;">
        تهانينا! تمت الموافقة على مستشفاكم <strong>{{ $hospital['name'] ?? '' }}</strong> وأصبح نشطاً على منصتنا. يمكنكم الآن تسجيل الدخول والبدء بإدارة العيادات والأطباء والحجوزات.
    </p>

    {{-- CTA Button --}}
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" style="padding: 24px 0;">
                <a href="{{ route('login') }}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(to right, #6366f1, #4f46e5); color: #ffffff; text-decoration: none; border-radius: 12px; font-size: 15px; font-weight: bold; letter-spacing: 0.3px;">
                    {{ __('messages.email_login_link') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.6;">
        {{ __('messages.email_regards') }},<br>
        {{ config('app.name') }}
    </p>
@endsection
