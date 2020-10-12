//<?php
/**
 * Payment ExpressPay_Erip
 *
 * Express-Pay: ERIP payments processing
 *
 * @category    plugin
 * @version     0.0.1
 * @author      OOO "TriIncom"
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender
 * @internal    @properties &title=Название;text;Экспресс Платежи: ЕРИП &isTest=Использовать тестовый режим;list;Нет==0||Да==1; &serviceId=Номер услуги;text; &token=Токен;text; &useSignature=Использовать секретное слово для подписи счетов;list;Нет==0||Да==1; &secretWord=Секретное слово;text; &notifUrl=Адрес для получения уведомлений;text;https://домен вашего сайта/commerce/expresspay_erip/payment-process &useSignatureForNotif=Использовать цифровую подпись для уведомлений;list;Нет==0||Да==1; &secretWordForNotif=Секретное слово для уведомлений;text; &showQrCode=Показывать Qr-код;list;Нет==0||Да==1; &pathToErip=Путь по ветке ЕРИП;text; &isNameEdit=Разрешено изменять ФИО;list;Нет==0||Да==1;&isAmountEdit=Разрешено изменять сумму;list;Нет==0||Да==1;&isAddressEdit=Разрешено изменять адрес;list;Нет==0||Да==1;&smsNotif=Отсылать уведомления плательщикам по SMS;list;Нет==0||Да==1;&emailNotif=Отсылать уведомления плательщикам по электронной почте;list;Нет==0||Да==1;0
 * @internal    @modx_category Commerce
 * @internal    @disabled 0
 * @internal    @installset base
*/

if (empty($modx->commerce) && !defined('COMMERCE_INITIALIZED')) {
    return;
}

$isSelectedPayment = !empty($order['fields']['payment_method']) && $order['fields']['payment_method'] == 'expresspay_erip';
$commerce = ci()->commerce;
$lang = $commerce->getUserLanguage('expresspay_erip');

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\ExpresspayEripPayment($modx, $params);

        if (empty($params['title'])) {
            $params['title'] = $lang['expresspay_erip.caption'];
        }

        $commerce->registerPayment('expresspay_erip', $params['title'], $class);
        break;
    }

    case 'OnBeforeOrderSending': {
        if ($isSelectedPayment) {
            $FL->setPlaceholder('extra', $FL->getPlaceholder('extra', '') . $commerce->loadProcessor()->populateOrderPaymentLink());
        }

        break;
    }
}
