@extends('emails.layout')

@section('title', __('messages.email_booking_confirmed_subject', ['token' => $booking['token_number'] ?? '']))

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 24px; color: #1e293b; text-align: center;">
        {{ __('messages.email_booking_confirmed_subject', ['token' => $booking['token_number'] ?? '']) }}
    </h1>

    {{-- English --}}
    <p style="margin: 0 0 12px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        {{ __('messages.email_greeting') }},
    </p>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.7;">
        Your booking has been confirmed. Here are your details:
    </p>

    {{-- Booking Details Card --}}
    <div style="margin: 16px 0; padding: 20px; background-color: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b; width: 140px;">{{ __('messages.token_number') }}:</td>
                <td style="padding: 8px 0; font-size: 20px; color: #16a34a; font-weight: bold;">#{{ $booking['token_number'] ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b;">{{ __('messages.doctor_name') }}:</td>
                <td style="padding: 8px 0; font-size: 14px; color: #334155; font-weight: 600;">{{ $booking['doctor_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b;">{{ __('messages.clinic_name') }}:</td>
                <td style="padding: 8px 0; font-size: 14px; color: #334155;">{{ $booking['clinic_name'] ?? '-' }}</td>
            </tr>
            @if(!empty($booking['scheduled_date']))
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b;">{{ __('messages.booking_date') }}:</td>
                <td style="padding: 8px 0; font-size: 14px; color: #334155;">{{ $booking['scheduled_date'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Instructions --}}
    <p style="margin: 16px 0 8px 0; font-size: 14px; color: #475569; line-height: 1.6;">
        Please arrive on time and bring any relevant medical documents. Your queue number will be called when it is your turn.
    </p>

    {{-- Arabic --}}
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

    <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.7; direction: rtl; text-align: right;">
        تم تأكيد حجزكم. إليكم التفاصيل:
    </p>

    <div style="margin: 16px 0; padding: 20px; background-color: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0; direction: rtl; text-align: right;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" dir="rtl">
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b; width: 120px;">رقم الدور:</td>
                <td style="padding: 8px 0; font-size: 20px; color: #16a34a; font-weight: bold;">#{{ $booking['token_number'] ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b;">الطبيب:</td>
                <td style="padding: 8px 0; font-size: 14px; color: #334155; font-weight: 600;">{{ $booking['doctor_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-size: 14px; color: #64748b;">العيادة:</td>
                <td style="padding: 8px 0; font-size: 14px; color: #334155;">{{ $booking['clinic_name'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <p style="margin: 16px 0 0 0; font-size: 14px; color: #475569; line-height: 1.6; direction: rtl; text-align: right;">
        يرجى الحضور في الموعد المحدد وإحضار أي مستندات طبية ذات صلة. سيتم استدعاء رقم دورك عندما يحين دورك.
    </p>

    <p style="margin: 24px 0 0 0; font-size: 14px; color: #64748b; line-height: 1.6;">
        {{ __('messages.email_regards') }},<br>
        {{ config('app.name') }}
    </p>
@endsection
