<?php use Camagru\Http\Http; ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= Http::escape($heading) ?></title></head>
<body style="margin:0;padding:0;background-color:#faf8f3;color:#2b332c;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;"><?= Http::escape($description) ?></div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#faf8f3;"><tr><td align="center" style="padding:36px 16px;">
<!--[if mso]><table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;">
    <tr><td style="padding:0 0 26px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
        <td width="40" height="40" align="center" bgcolor="#bd6045" style="border-radius:10px;color:#fff7e9;font-family:Georgia,serif;font-size:32px;line-height:40px;">c.</td>
        <td style="padding-left:12px;font-size:26px;font-weight:bold;letter-spacing:-1px;color:#2b332c;">camagru<span style="color:#bd6045;">.</span></td>
    </tr></table></td></tr>
    <tr><td style="border:1px solid #dedfd5;border-radius:12px;overflow:hidden;background-color:#fffdf8;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr><td bgcolor="#e9ecdf" style="padding:28px 28px 24px;border-radius:12px 12px 0 0;">
                <p style="margin:0 0 14px;font-size:10px;line-height:16px;letter-spacing:2px;color:#5c704e;">THE EVERYDAY, REMIXED.</p>
                <p aria-hidden="true" style="margin:0;color:#bd6045;font-family:Georgia,serif;font-size:54px;line-height:60px;">&#10035;</p>
                <p style="margin:14px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:25px;line-height:32px;">Ordinary moments.<br>A little more <em style="color:#b85e44;">playful.</em></p>
            </td></tr>
            <tr><td style="padding:32px 28px;">
                <p style="margin:0 0 16px;color:#9c7045;font-size:10px;line-height:16px;letter-spacing:1.6px;"><?= Http::escape($eyebrow) ?></p>
                <h1 style="margin:0 0 20px;font-family:Georgia,'Times New Roman',serif;font-weight:normal;font-size:34px;line-height:40px;letter-spacing:-1px;"><?= Http::escape($heading) ?></h1>
                <p style="margin:0 0 24px;color:#68705b;font-size:15px;line-height:25px;"><?= Http::escape($description) ?></p>
                <?php if ($quote !== null): ?><p style="margin:0 0 24px;padding:18px;border-left:3px solid #bd6045;background-color:#f3f2e9;color:#2b332c;font-size:14px;line-height:24px;overflow-wrap:anywhere;"><?= nl2br(Http::escape($quote)) ?></p><?php endif; ?>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" bgcolor="#bd6045" style="border-radius:6px;mso-padding-alt:15px 26px;">
                    <a href="<?= Http::escape($url) ?>" style="display:inline-block;padding:15px 26px;border:1px solid #bd6045;border-radius:6px;color:#fffaf0;font-size:14px;font-weight:bold;line-height:20px;text-decoration:none;text-align:center;"><?= Http::escape($action) ?> <span aria-hidden="true">&#8599;</span></a>
                </td></tr></table>
                <p style="margin:24px 0 0;color:#757b6f;font-size:12px;line-height:20px;"><?= Http::escape($note) ?></p>
                <p style="margin:20px 0 0;color:#757b6f;font-size:12px;line-height:20px;">Button not working? <a href="<?= Http::escape($url) ?>" style="color:#9c4f39;text-decoration:underline;">Open this page in your browser</a>.</p>
            </td></tr>
        </table>
    </td></tr>
    <tr><td align="center" style="padding:24px 12px 0;color:#757b6f;font-size:11px;line-height:19px;">Made for the joy of it.<br><span style="color:#9c7045;">Camagru &middot; Your little creative corner</span></td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table>
</body>
</html>
