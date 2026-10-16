<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

/** Fail closed until the public mail/native-save contract has been requalified after vendor updates. */
final class PaymentIntegrationContract
{
    private const QUALIFIED = [
        'administrator/components/com_dpcalendar/src/Model/BookingModel.php' => '2b4da6d72935c5d6c002ccdb6d54086bb6ccf9d1da3aea4efc0e03d8c631ec37',
        'administrator/components/com_dpcalendar/src/Mail/MustacheMailTemplate.php' => '0073664fe5e9e1c442ccbab768978f466ed420b2da0199a5c976a4dd1b2c91c1',
        'administrator/components/com_dpcalendar/src/Mail/NotificationMailTrait.php' => 'feaeaf15a5ad5377f5e2c68f81f6f0c8c96133b511f6ed378ec532cec837bd22',
        'administrator/components/com_dpcalendar/src/Booking/Stages/SendNewBookingMail.php' => 'e0687b9ff412157dca3ad96c15669c0601768a4a64e6200d3843b779812b60ae',
        'administrator/components/com_dpcalendar/src/Booking/Stages/SendPaidBookingMail.php' => 'bc9155795fe06a8c5b95070c5a2ca1073c358af24dcab7bf305d5343ab48fe63',
        'administrator/components/com_dpcalendar/src/Booking/Stages/SendNotificationMail.php' => '67f7db0085bb57345045cd26e97ec1e34403948d63f2305f8fae05a50c52146d',
        'libraries/src/Mail/MailTemplate.php' => '4e3d93ae05f179845b57e4d8744983b89debb9079e0349a8f4963fb14442c85f',
        'libraries/src/Mail/Mail.php' => 'eb885f70ab22dce8915e2ddd4fdb5dc1050dfd4992f2de0f92a7819b79decb92',
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
