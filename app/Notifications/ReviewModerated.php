<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A28 · Письмо автору: отзыв опубликован / отклонён / банк ответил. Отправляется, если включено в настройках кабинета. */
class ReviewModerated extends Notification
{
    public function __construct(public Review $review, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bank = $this->review->bank->displayName();
        $mail = (new MailMessage)->greeting('Здравствуйте, '.$notifiable->name.'!');

        return match ($this->event) {
            'approved' => $mail->subject('Ваш отзыв о «'.$bank.'» опубликован')
                ->line('Отзыв «'.$this->review->title.'» прошёл модерацию и учитывается в народном рейтинге.')
                ->action('Открыть отзыв', route('banks.show', $this->review->bank).'#review-'.$this->review->id),
            'rejected' => $mail->subject('Отзыв о «'.$bank.'» не опубликован')
                ->line('Причина: '.$this->review->reject_reason)
                ->line('Исправьте отзыв на странице банка — он снова уйдёт на модерацию.')
                ->action('Мои отзывы', route('account.reviews')),
            default => $mail->subject('Банк ответил на ваш отзыв о «'.$bank.'»')
                ->line(($this->review->reply_author ?: 'Представитель банка').': '.str($this->review->reply)->limit(400))
                ->action('Прочитать ответ', route('banks.show', $this->review->bank).'#review-'.$this->review->id),
        };
    }
}
