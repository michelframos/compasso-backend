<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $assunto }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:8px;padding:32px;line-height:1.6;font-size:15px;">
                    <tr>
                        <td style="padding-bottom:16px;color:#71717a;font-size:13px;">{{ $escola }}</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:12px;font-size:18px;font-weight:bold;">{{ $assunto }}</td>
                    </tr>
                    <tr>
                        <td>{!! nl2br(e($mensagem)) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
