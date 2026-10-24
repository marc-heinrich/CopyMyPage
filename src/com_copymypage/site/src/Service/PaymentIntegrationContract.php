<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

/** Fail closed until the public mail/native-save contract has been requalified after vendor updates. */
final class PaymentIntegrationContract
{
    private const QUALIFIED = [
        'administrator/components/com_dpcalendar/src/Model/BookingModel.php' => '2b4da6d72935c5d6c002ccdb6d54086bb6ccf9d1da3aea4efc0e03d8c631ec37',
        'administrator/components/com_dpcalendar/src/Mail/MustacheMailTemplate.php' => 'eac17e1dbd03e66c94b29306ded2d48304dc0f1e24482219402e910f42dc68f2',
        'administrator/components/com_dpcalendar/src/Mail/NotificationMailTrait.php' => '2e316e60e91799b5fa4568dc8cbb7c8c6271c3210560f77eb140843d667d6292',
        'administrator/components/com_dpcalendar/src/Booking/Stages/SendNewBookingMail.php' => 'e0687b9ff412157dca3ad96c15669c0601768a4a64e6200d3843b779812b60ae',
        'administrator/components/com_dpcalendar/src/Booking/Stages/SendPaidBookingMail.php' => 'bc9155795fe06a8c5b95070c5a2ca1073c358af24dcab7bf305d5343ab48fe63',
        'administrator/components/com_dpcalendar/src/Booking/Stages/SendNotificationMail.php' => '247da621fabb08456b5e46acc6e01abfd0ea3476022a868d41d2298945d22214',
        'libraries/src/Mail/MailTemplate.php' => '18c3c48c4536987f814637de373a683d0332a7c1307b6f7d88bbd6153fb94e2b',
        'libraries/src/Mail/Mail.php' => '089b380754fd1909501772ff8a3619502137ea87babe4bc7b117cdc787a5c7e9',
        'libraries/vendor/phpmailer/phpmailer/src/PHPMailer.php' => '451486c23f7f82e822ddb0667237714d2ce265920c9ed9cf1128b4c5fc46b657',
    ];

    public static function assertQualified(): void
    {
        foreach (self::QUALIFIED as $path => $expected) {
            $file = JPATH_ROOT . '/' . $path;
            if (!is_file($file) || !hash_equals($expected, hash_file('sha256', $file))) {
                throw new \RuntimeException('CopyMyPage payment/mail integration must be requalified after the native update.');
            }
        }
    }
}
