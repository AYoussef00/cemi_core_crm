<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور — '.config('app.name'))
            ->greeting('مرحبًا.')
            ->line('تلقّينا طلبًا لإعادة تعيين كلمة المرور المرتبطة بهذا البريد.')
            ->action('تعيين كلمة مرور جديدة', $this->resetUrl($notifiable))
            ->line('ينتهي صلاحية هذا الرابط خلال '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' دقيقة.')
            ->line('إذا لم تطلب إعادة التعيين، يمكنك تجاهل هذه الرسالة.')
            ->salutation('— '.config('app.name'));
    }
}
