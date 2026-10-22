<?php

namespace App\Support\Mail;

/**
 * Reads Laravel mail config for admin display. Never returns raw passwords/secrets.
 */
class MailConfigurationInspector
{
    /** @var list<string> */
    private const LOCAL_MAILERS = ['log', 'array'];

    public function status(): MailConfigurationStatus
    {
        $mailer = (string) config('mail.default', 'log');
        $mailerConfig = (array) config("mail.mailers.{$mailer}", []);
        $transport = (string) ($mailerConfig['transport'] ?? $mailer);

        $fromAddress = (string) config('mail.from.address', '');
        $fromName = (string) config('mail.from.name', '');

        $isLocal = in_array($mailer, self::LOCAL_MAILERS, true)
            || in_array($transport, self::LOCAL_MAILERS, true);

        $rows = [
            ['label' => 'قناة الإرسال', 'value' => $this->mailerLabelAr($mailer)],
            ['label' => 'طريقة النقل', 'value' => $this->mailerLabelAr($transport)],
            ['label' => 'عنوان المرسل', 'value' => $this->maskEmail($fromAddress)],
            ['label' => 'اسم المرسل', 'value' => $fromName !== '' ? $fromName : '—'],
        ];

        if ($transport === 'smtp' || $mailer === 'smtp') {
            $host = (string) ($mailerConfig['host'] ?? '');
            $port = (string) ($mailerConfig['port'] ?? '');
            $username = $mailerConfig['username'] ?? null;
            $password = $mailerConfig['password'] ?? null;
            $scheme = (string) ($mailerConfig['scheme'] ?? '');

            $rows[] = ['label' => 'مضيف SMTP', 'value' => $host !== '' ? $host : '—'];
            $rows[] = ['label' => 'المنفذ', 'value' => $port !== '' ? $port : '—'];
            $rows[] = ['label' => 'التشفير / المخطط', 'value' => $scheme !== '' ? $scheme : '—'];
            $rows[] = [
                'label' => 'اسم المستخدم',
                'value' => filled($username) ? $this->maskSecret((string) $username) : 'غير معيّن',
                'sensitive' => true,
            ];
            $rows[] = [
                'label' => 'كلمة المرور',
                'value' => filled($password) ? '••••••••' : 'غير معيّن',
                'sensitive' => true,
            ];
        }

        if ($transport === 'log' || $mailer === 'log') {
            $channel = $mailerConfig['channel'] ?? null;
            $rows[] = [
                'label' => 'قناة السجل',
                'value' => filled($channel) ? (string) $channel : 'الافتراضية',
            ];
        }

        $configComplete = $this->configLooksComplete($mailer, $transport, $mailerConfig, $fromAddress);
        $mode = $isLocal ? 'local' : ($configComplete ? 'provider' : 'incomplete');

        return new MailConfigurationStatus(
            mailer: $mailer,
            transport: $transport,
            mode: $mode,
            modeLabelAr: match ($mode) {
                'local' => 'اختبار محلي فقط — لا يصل إلى صندوق الوارد',
                'provider' => 'مزود خارجي جاهز',
                default => 'إعداد غير مكتمل',
            },
            readinessLabelAr: match ($mode) {
                'local' => 'جاهز للاختبار المحلي — لن يُرسل بريد حقيقي',
                'provider' => 'الإعداد يبدو مكتملاً — التسليم يعتمد على مزود البريد',
                default => 'غير جاهز — يلزم إكمال إعداد البريد من بيئة النشر',
            },
            configLooksComplete: $configComplete,
            isLocalOnly: $isLocal,
            canAttemptProviderSend: ! $isLocal && $configComplete,
            rows: $rows,
            guidance: $this->guidance($mode, $mailer),
            fromAddressMasked: $this->maskEmail($fromAddress),
            fromName: $fromName,
        );
    }

    /**
     * @param  array<string, mixed>  $mailerConfig
     */
    private function configLooksComplete(string $mailer, string $transport, array $mailerConfig, string $fromAddress): bool
    {
        if ($fromAddress === '' || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (in_array($mailer, self::LOCAL_MAILERS, true) || in_array($transport, self::LOCAL_MAILERS, true)) {
            return true;
        }

        if ($transport === 'smtp' || $mailer === 'smtp') {
            if (! filled($mailerConfig['host'] ?? null)) {
                return false;
            }

            $username = $mailerConfig['username'] ?? null;
            $password = $mailerConfig['password'] ?? null;

            if (filled($username) && ! filled($password)) {
                return false;
            }

            return true;
        }

        // Other providers (ses, postmark, …): require from address; credentials live in env/SDK.
        return true;
    }

    /**
     * @return list<string>
     */
    private function guidance(string $mode, string $mailer): array
    {
        $lines = [
            'تُدار بيانات اعتماد البريد خارج التطبيق في بيئة النشر فقط — لا تُحفظ في قاعدة البيانات ولا تُعدَّل من هذه الواجهة.',
            'بعد تغيير إعدادات البريد في بيئة النشر، أعد تحميل تهيئة التطبيق إن لزم الأمر.',
        ];

        if ($mode === 'local') {
            $lines[] = $mailer === 'array'
                ? 'الوضع الحالي يخزّن الرسائل في الذاكرة للاختبار فقط ولا يُرسل بريدًا خارجيًا.'
                : 'الوضع الحالي يسجّل الرسائل محليًا في سجل التطبيق ولا يعني وصولها إلى صندوق الوارد.';
        }

        if ($mode === 'incomplete') {
            $lines[] = 'أكمل عنوان المرسل وإعدادات المضيف والاعتماد حسب مزود البريد في أسرار بيئة النشر.';
        }

        if ($mode === 'provider') {
            $lines[] = 'اختبار الإرسال من هذه الصفحة يذهب فقط إلى بريد حسابك الحالي. لا يُسمح بإدخال مستلم آخر.';
        }

        return $lines;
    }

    private function mailerLabelAr(string $value): string
    {
        return match ($value) {
            'log' => 'تسجيل محلي',
            'array' => 'ذاكرة اختبار',
            'smtp' => 'SMTP',
            'sendmail' => 'Sendmail',
            'ses', 'ses-v2' => 'Amazon SES',
            'postmark' => 'Postmark',
            'resend' => 'Resend',
            'mailgun' => 'Mailgun',
            default => $value !== '' ? $value : '—',
        };
    }

    public function maskEmail(string $email): string
    {
        if ($email === '' || ! str_contains($email, '@')) {
            return $email !== '' ? '••••' : '—';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localMasked = mb_substr($local, 0, 1).'•••';

        return $localMasked.'@'.$domain;
    }

    public function maskSecret(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 2) {
            return '••••';
        }

        return mb_substr($value, 0, 1).str_repeat('•', min(8, $len - 1));
    }
}
