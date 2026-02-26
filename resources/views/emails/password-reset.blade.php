<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.reset_password') }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f6f9; padding: 40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                    <!-- Header Bar -->
                    <tr>
                        <td style="height: 6px; background: linear-gradient(to right, #6366f1, #818cf8);"></td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 32px;">
                            <h1 style="margin: 0 0 8px 0; font-size: 24px; color: #1e293b; text-align: center;">
                                {{ __('messages.reset_password') }}
                            </h1>
                            <p style="margin: 0 0 24px 0; font-size: 14px; color: #64748b; text-align: center;">
                                {{ __('messages.app_name') }}
                            </p>

                            <!-- English -->
                            <p style="margin: 0 0 8px 0; font-size: 15px; color: #334155; line-height: 1.6;">
                                You are receiving this email because we received a password reset request for your account. Click the button below to reset your password:
                            </p>

                            <!-- Arabic -->
                            <p style="margin: 0 0 24px 0; font-size: 15px; color: #334155; line-height: 1.6; direction: rtl; text-align: right;">
                                لقد تلقيت هذا البريد الإلكتروني لأننا تلقينا طلب إعادة تعيين كلمة المرور لحسابك. انقر على الزر أدناه لإعادة تعيين كلمة المرور:
                            </p>

                            <!-- Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding: 8px 0 24px 0;">
                                        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(to right, #6366f1, #4f46e5); color: #ffffff; text-decoration: none; border-radius: 12px; font-size: 15px; font-weight: bold; letter-spacing: 0.3px;">
                                            {{ __('messages.reset_password') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Expiry Notice -->
                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                                This password reset link will expire in 60 minutes. If you did not request a password reset, no further action is required.
                            </p>
                            <p style="margin: 0 0 16px 0; font-size: 13px; color: #94a3b8; line-height: 1.5; direction: rtl; text-align: right;">
                                ستنتهي صلاحية رابط إعادة تعيين كلمة المرور خلال 60 دقيقة. إذا لم تطلب إعادة تعيين كلمة المرور، فلا يلزم اتخاذ أي إجراء آخر.
                            </p>

                            <!-- Fallback URL -->
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
                            <p style="margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.5; word-break: break-all;">
                                If you are having trouble clicking the button, copy and paste this URL into your browser:<br>
                                <a href="{{ $resetUrl }}" style="color: #6366f1;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
