<!DOCTYPE html>
<html lang="{{ \App\Support\Localization\DocumentLocale::tag() }}" dir="{{ \App\Support\Localization\DocumentLocale::direction() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('auth.team_invitation_subject', ['workspace' => $workspaceName]) }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px;">
                    <tr>
                        <td style="padding:32px; font-family:Arial, sans-serif; color:#111827;">
                            <p style="margin:0 0 16px;">{{ __('auth.team_invitation_greeting', ['workspace' => $workspaceName, 'role' => $role]) }}</p>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:6px; background-color:#1d4ed8;">
                                        <a href="{{ $invitationUrl }}" style="display:inline-block; padding:12px 24px; font-family:Arial, sans-serif; color:#ffffff; text-decoration:none;">
                                            {{ __('auth.team_invitation_action') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:24px 0 0; font-size:12px; color:#6b7280;">{{ __('auth.team_invitation_expires', ['date' => $expiresAt->format('Y-m-d H:i')]) }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
