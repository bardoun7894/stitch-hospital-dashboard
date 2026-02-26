@extends('emails.layout')

@section('title', __('messages.email_hospital_rejected_subject'))

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 24px; color: #1e293b; text-align: center;">
        {{ __('messages.email_hospital_rejected_subject') }}
    </h1>

    {{-- English --}}
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        {{ __('messages.email_greeting') }},
    </p>
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        We regret to inform you that your hospital <strong>{{ $hospital['name'] ?? $hospital['name_en'] ?? '' }}</strong> registration was not approved at this time.
    </p>

    @if(!empty($reason))
    <div style="margin: 16px 0; padding: 16px; background-color: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;">
        <p style="margin: 0 0 4px 0; font-size: 13px; color: #991b1b; font-weight: bold;">
            {{ __('messages.rejection_reason') }}:
        </p>
        <p style="margin: 0; font-size: 14px; color: #991b1b; line-height: 1.6;">
            {{ $reason }}
        </p>
    </div>
    @endif

    <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        You can edit your hospital information and resubmit for review. Please log in to make the necessary changes.
    </p>

    {{-- Arabic --}}
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7; direction: rtl; text-align: right;">
        نأسف لإبلاغكم بأن تسجيل مستشفاكم <strong>{{ $hospital['name'] ?? '' }}</strong> لم تتم الموافقة عليه في الوقت الحالي.
    </p>

    @if(!empty($reason))
    <div style="margin: 16px 0; padding: 16px; background-color: #fef2f2; border-radius: 8px; border-right: 4px solid #ef4444; direction: rtl; text-align: right;">
        <p style="margin: 0 0 4px 0; font-size: 13px; color: #991b1b; font-weight: bold;">
            {{ __('messages.rejection_reason') }}:
        </p>
        <p style="margin: 0; font-size: 14px; color: #991b1b; line-height: 1.6;">
            {{ $reason }}
        </p>
    </div>
    @endif

    <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.7; direction: rtl; text-align: right;">
        يمكنكم تعديل معلومات المستشفى وإعادة التقديم للمراجعة. يرجى تسجيل الدخول لإجراء التعديلات اللازمة.
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
