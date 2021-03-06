<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Application;
use Bitrix\Sale\Order;
use QRcode;
use Payment\SberQR;
use Helper\File;

class PageSberQR extends \CBitrixComponent
{

    private $dir = '/upload/sberqr/';

    public function executeComponent()
    {
        global $USER;

        $sber = new SberQR();
        $request = Application::getInstance()->getContext()->getRequest()->toArray();
        $order = Order::load($request['ID']);

        $idProp = '';
        $propertyCollection = $order->getPropertyCollection();
        $orderIdSber = $propertyCollection->getItemByOrderPropertyId($idProp);
        $payment = $order->getPaymentCollection()[0];
        $this->arResult['PAYED'] = $order->getField('PAYED');

        // Заказ не найден - отправим в карточку заказа
        if (empty($order)) {
            LocalRedirect('/account/orders-history/order_detail.php?ID=' . $request['ID']);
        }

        // Проверка статуса оплаты
        $orderStatus = $sber->getOrderStatus($orderIdSber->getValue())['status'];

        if ($orderStatus['error_code'] == '000000' && $orderStatus['order_operation_params'][0]['operation_type'] == 'PAY') {
            // Проводим оплату
            $payment->setPaid('Y');
            // Убираем внешний идентификатор
            if (count($orderIdSber->getValue()) > 0) {
                $orderIdSber->setValue('');
                $order->save();
                // Заказ оплачен - отправим в карточку заказа
                LocalRedirect('/account/orders-history/order_detail.php?ID=' . $request['ID']);
            }
        }

        // Оплата
        $this->arResult['ID'] = $request['ID'];
        $this->arResult['DATE'] = $order->getField('DATE_INSERT');
        $this->arResult['PRICE'] = $order->getField('PRICE');

        $orderSber = $sber->createOrder($request['ID'])['status'];

        if ($orderSber['order_state'] == 'CREATED') {
            $this->arResult['URL'] = $orderSber['order_form_url'];
        }

        // Создаём QR код
        QRcode::png(
            $code = $orderSber['order_form_url'],
            $outfile = sys_get_temp_dir() . '/tmp.png',
            $level = 'L',
            $size = '8px',
            $margin = '',
            $saveandprint = false
        );

        // Ссылка на файл с кодом для вывода в шаблоне
        $this->arResult['QR_CODE'] = File::getFileFullUrl(
            \CFile::SaveFile(
                \CFile::MakeFileArray($outfile),
                'sberqr'
            )
        );

        // Добавляет внешний идентификатор из системы Сбера
        $orderIdSber->setValue($orderSber['order_id']);

        // Удалим старые QR коды
        $this->deleteOldQrCodes();

        if (empty($this->arParams['CHECK_STATUS'])) {
            $this->IncludeComponentTemplate();
        }

        $order->save();

    }

    // Удаляем старые коды из папки /upload/sberqr/
    public static function deleteOldQrCodes()
    {
        return (new File())->clear($this->dir);
    }

}
