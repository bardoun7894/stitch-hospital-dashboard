<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f6f9; padding: 40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                    {{-- Header Bar --}}
                    <tr>
                        <td style="height: 6px; background: linear-gradient(to right, #6366f1, #818cf8);"></td>
                    </tr>

                    {{-- Logo / App Name --}}
                    <tr>
                        <td align="center" style="padding: 32px 32px 0 32px;">
                            {{-- Replace with actual logo image when available --}}
                            <h2 style="margin: 0; font-size: 22px; color: #6366f1; font-weight: bold; letter-spacing: 0.5px;">
                                {{ config('app.name', 'ClinicQu Dashboard') }}
                            </h2>
                        </td>
                    </tr>

                    {{-- Main Content --}}
                    <tr>
                        <td style="padding: 24px 32px 40px 32px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 32px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #94a3b8; text-align: center; line-height: 1.5;">
                                {{ config('app.name', 'ClinicQu Dashboard') }}
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #cbd5e1; text-align: center; line-height: 1.5;">
                                {{ __('messages.email_footer_automated') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
