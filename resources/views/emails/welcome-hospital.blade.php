@extends('emails.layout')

@section('title', __('messages.email_welcome_subject'))

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 24px; color: #1e293b; text-align: center;">
        {{ __('messages.email_welcome_subject') }}
    </h1>

    {{-- English --}}
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        {{ __('messages.email_greeting') }},
    </p>
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        Thank you for registering <strong>{{ $hospitalData['name'] ?? $hospitalData['name_en'] ?? '' }}</strong> on our platform! Your registration has been received and is now under review by our team.
    </p>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        While we review your hospital, you can start setting up your clinics and doctors right away. Log in to your dashboard to get started:
    </p>

    {{-- Arabic --}}
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">

    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7; direction: rtl; text-align: right;">
        شكراً لتسجيل <strong>{{ $hospitalData['name'] ?? '' }}</strong> على منصتنا! تم استلام تسجيلكم وهو الآن قيد المراجعة من قبل فريقنا.
    </p>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.7; direction: rtl; text-align: right;">
        أثناء مراجعة المستشفى، يمكنكم البدء بإعداد العيادات والأطباء فوراً. سجّلوا الدخول للوحة التحكم للبدء:
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

    {{-- What's Next --}}
    <div style="margin: 0 0 16px 0; padding: 16px; background-color: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
        <p style="margin: 0 0 8px 0; font-size: 14px; color: #1e40af; font-weight: bold;">What's Next / الخطوات التالية:</p>
        <ul style="margin: 0; padding: 0 0 0 20px; font-size: 14px; color: #334155; line-height: 2;">
            <li>Add your clinics / أضف عياداتك</li>
            <li>Add doctors to each clinic / أضف الأطباء لكل عيادة</li>
            <li>Your hospital will be reviewed and activated / سيتم مراجعة المستشفى وتفعيله</li>
        </ul>
    </div>

    <p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.6;">
        {{ __('messages.email_regards') }},<br>
        {{ config('app.name') }}
    </p>
@endsection
