<?php

namespace App\Support\Mail;

use App\Mail\MailConfigurationTestMail;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class MailTestSender
{
    public function __construct(
        private MailConfigurationInspector $inspector,
        private AuditLogger $audit,
    ) {}

    /**
     * Send a test message only to the administrator's own account email.
     *
     * @return array{ok: bool, local_only: bool, message_ar: string}
     */
    public function sendToSelf(User $administrator): array
    {
        if (! $administrator->can('settings.manage')) {
            throw new RuntimeException('غير مصرح.');
        }

        $email = (string) $administrator->email;
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('بريد حسابك غير صالح لإرسال الاختبار.');
        }

        $status = $this->inspector->status();

        try {
            Mail::to($email)->send(new MailConfigurationTestMail($administrator->name));
        } catch (Throwable $e) {
            $this->audit->record('mail.test_failed', $administrator, [
                'mailer' => $status->mailer,
                'transport' => $status->transport,
                'local_only' => $status->isLocalOnly,
                'recipient_masked' => $this->inspector->maskEmail($email),
                'error_class' => class_basename($e),
            ], $administrator);

            throw new RuntimeException(
                'تعذر إرسال رسالة الاختبار. تحقق من إعدادات المزود في بيئة النشر دون مشاركة الأسرار.',
                0,
                $e,
            );
        }

        $this->audit->record('mail.test_sent', $administrator, [
            'mailer' => $status->mailer,
            'transport' => $status->transport,
            'local_only' => $status->isLocalOnly,
            'recipient_masked' => $this->inspector->maskEmail($email),
        ], $administrator);

        if ($status->isLocalOnly) {
            return [
                'ok' => true,
                'local_only' => true,
                'message_ar' => $status->mailer === 'array'
                    ? 'تم قبول رسالة الاختبار محليًا فقط. لم يُرسل بريد إلى صندوق الوارد.'
                    : 'تم تسجيل رسالة الاختبار محليًا فقط. هذا لا يعني وصول الرسالة إلى صندوق الوارد.',
            ];
        }

        return [
            'ok' => true,
            'local_only' => false,
            'message_ar' => 'تم إرسال رسالة الاختبار إلى بريد حسابك. تحقق من صندوق الوارد (ومجلد الرسائل غير المرغوب فيها).',
        ];
    }
}
