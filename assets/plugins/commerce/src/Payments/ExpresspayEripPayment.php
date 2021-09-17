<?php

namespace Commerce\Payments;

class ExpresspayEripPayment extends Payment implements \Commerce\Interfaces\Payment
{
    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('expresspay_erip');
    }

    public function getMarkup()
    {
        $out = [];

        if ($this->getSetting('isTest')) {
            $out[] = $this->lang['expresspay_erip.test_mode'];
        }

        if (empty($this->getSetting('serviceId'))) {
            $out[] = $this->lang['expresspay_erip.error_empty_serviceId'];
        }

        if (empty($this->getSetting('token'))) {
            $out[] = $this->lang['expresspay_erip.error_empty_token'];
        }

        $out = implode('<br>', $out);

        if (!empty($out)) {
            $out = '<span class="error" style="color: red;">' . $out . '</span>';
        }

        return $out;
    }

    public function getPaymentLink()
    {
        $debug = !empty($this->getSetting('debug'));

        $processor = $this->modx->commerce->loadProcessor();
        $order     = $processor->getOrder();
        $fields    = $order['fields'];
        $currency  = ci()->currency->getCurrency($order['currency']);
        $payment   = $this->createPayment($order['id'], ci()->currency->convertToDefault($order['amount'], $currency['code']));

        if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
            $receipt['email'] = $order['email'];
        }
        if (!empty($order['phone'])) {
            $receipt['phone'] = substr(preg_replace('/[^\d]+/', '', $order['phone']), 0, 15);
        }
        $receipt['tax_system_code'] = $this->getSetting('tax_system_code');

        $fio = explode(" ", $order['name']);

        $request = array(
            "ServiceId"          => $this->getSetting('serviceId'),
            "AccountNo"          => $order['id'],
            "Amount"             => number_format($order['amount'], 2, ',', ''),
            "Currency"           => 933,
            'ReturnType'         => "json",
            'ReturnUrl'          => '',
            'FailUrl'            => '',
            'Expiration'         => '',
            "Info"               => 'Оплата заказа на сайте ' . $this->modx->getConfig('site_name'),
            "Surname"            => $fio[0],
            "FirstName"          => $fio[1],
            "Patronymic"         => $fio[2],
            "City"               => '',
            "Street"             => '',
            "House"              => '',
            "Building"           => '',
            "Apartment"          => '',
            'ReturnUrl'          => '-',
            'FailUrl'            => '-',
            "IsNameEditable"     => $this->getSetting('isNameEdit'),
            "IsAddressEditable"  => $this->getSetting('isAddressEdit'),
            "IsAmountEditable"   => $this->getSetting('isAmountEdit'),
            "EmailNotification"  => $this->getSetting('emailNotif') ? $receipt['email'] : '',
            "SmsPhone"           => $this->getSetting('smsNotif') ? $receipt['phone'] : '',
        );

        $secretWord = $this->getSetting('useSignature') ? $this->getSetting('secretWord') : '';

        $request['Signature'] = $this->compute_signature($request, $secretWord);

        $response = $this->sendRequestPOST($request);

        $response = json_decode($response, true);

        if (isset($response['Errors'])) {
            $this->log_info('Response', print_r($response, 1));
            $output_error =
                '<br />
            <h3>Ваш номер заказа: ##ORDER_ID##</h3>
            <p>При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</p>
            <input type="button" value="Продолжить" onClick=\'location.href="##HOME_URL##"\'>';

            $output_error = str_replace('##ORDER_ID##', $order['id'],  $output_error);

            $output_error = str_replace('##HOME_URL##', $this->modx->getConfig('site_url'),  $output_error);

            echo $output_error;
        } else {
            $output =
                '<table style="width: 100%;text-align: left;">
            <tbody>
                    <tr>
                        <td valign="top" style="text-align:left;">
                        <h3>Ваш номер заказа: ##ORDER_ID##</h3>
                            Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).
                            <br />
                            <br />1. Для этого в перечне услуг ЕРИП перейдите в раздел:  <b>##ERIP_PATH##</b> <br />
                            <br />2. В поле <b>"Номер заказа"</b>введите <b>##ORDER_ID##</b> и нажмите "Продолжить" <br />
                            <br />3. Укажите сумму для оплаты <b>##AMOUNT##</b><br />
                            <br />4. Совершить платеж.<br />
                        </td>
                            <td style="text-align: center;padding: 70px 20px 0 0;vertical-align: middle">
								##OR_CODE##
								<p><b>##OR_CODE_DESCRIPTION##</b></p>
								</td>
						</tr>
				</tbody>
            </table>
            <br />
            <input type="button" value="Продолжить" onClick=\'location.href="##HOME_URL##"\'>';

            $output = str_replace('##ORDER_ID##', $order['id'],  $output);
            $output = str_replace('##ERIP_PATH##', $this->getSetting('pathToErip'),  $output);
            $output = str_replace('##AMOUNT##', number_format($order['amount'], 2, ',', ''),  $output);
            $output = str_replace('##HOME_URL##', $this->modx->getConfig('site_url'),  $output);

            if ($this->getSetting('showQrCode')) {
                $qr_code = $this->getQrCode($response['ExpressPayInvoiceNo']);
                $output = str_replace('##OR_CODE##', '<img src="data:image/jpeg;base64,' . $qr_code . '"  width="200" height="200"/>',  $output);
                $output = str_replace('##OR_CODE_DESCRIPTION##', 'Отсканируйте QR-код для оплаты',  $output);
            } else {
                $output = str_replace('##OR_CODE##', '',  $output);
                $output = str_replace('##OR_CODE_DESCRIPTION##', '',  $output);
            }

            echo $output;
        }
    }

    public function handleCallback()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            print "OK!";
        }

        $processor = $this->modx->commerce->loadProcessor();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Получение данных
            $json = $_POST['Data'];
            $signature = $_POST['Signature'];

            // Преобразуем из JSON в Array
            $data = json_decode($json, true);

            $id = $data['AccountNo'];

            if ($this->getSetting('useSignatureForNotif')) {

                $secretWord = $this->getSetting('secretWordForNotif');

                if ($signature == $this->computeSignature($json, $secretWord)) {
                    if ($data['CmdType'] == '3' && $data['Status'] == '3' || $data['Status'] == '6') {
                        $processor->changeStatus($id, 3); // Изменение статуса заказа на оплачен
                        header("HTTP/1.0 200 OK");
                        print $status = 'OK | payment received'; //Все успешно
                    } elseif ($data['CmdType'] == '3' && $data['Status'] == '5') {
                        $processor->changeStatus($id, 5); // Изменение статуса заказа на отменён
                        header("HTTP/1.0 200 OK");
                        print $status = 'OK | payment received'; //Все успешно
                    }
                } else {
                    header("HTTP/1.0 400 Bad Request");
                    print $status = 'FAILED | wrong notify signature  '; //Ошибка в параметрах
                }
            }
            if ($data['CmdType'] == '3' && $data['Status'] == '3' || $data['Status'] == '6') {
                $processor->changeStatus($id, 3); // Изменение статуса заказа на оплачен
                header("HTTP/1.0 200 OK");
                print $status = 'OK | payment received'; //Все успешно
            } elseif ($data['CmdType'] == '3' && $data['Status'] == '5') {
                $processor->changeStatus($id, 5); // Изменение статуса заказа на отменён
                header("HTTP/1.0 200 OK");
                print $status = 'OK | payment cancel'; //Все успешно
            } else {
                header("HTTP/1.0 200 Bad Request");
                print $status = 'FAILED | ID заказа неизвестен';
            }
        }

        return true;
    }

    // Проверка электронной подписи
    protected function computeSignature($json, $secretWord)
    {
        $hash = NULL;

        $secretWord = trim($secretWord);

        if (empty($secretWord))
            $hash = strtoupper(hash_hmac('sha1', $json, ""));
        else
            $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
        return $hash;
    }

    //Получение Qr-кода
    protected function getQrCode($ExpressPayInvoiceNo)
    {
        $request_params_for_qr = array(
            "Token" => $this->getSetting("token"),
            "InvoiceId" => $ExpressPayInvoiceNo,
            'ViewType' => 'base64'
        );

        $secretWord = $this->getSetting('useSignature') ? $this->getSetting('secretWord') : '';

        $request_params_for_qr["Signature"] = $this->compute_signature($request_params_for_qr, $secretWord, 'get_qr_code');

        $request_params_for_qr  = http_build_query($request_params_for_qr);
        $response_qr = $this->sendRequestGET('https://api.express-pay.by/v1/qrcode/getqrcode/?' . $request_params_for_qr);
        $response_qr = json_decode($response_qr);
        $qr_code = $response_qr->QrCodeBody;
        return $qr_code;
    }

    // Отправка POST запроса
    protected function sendRequestPOST($params)
    {
        $url = $this->getSetting('isTest') ? "https://sandbox-api.express-pay.by/v1/web_invoices" : "https://api.express-pay.by/v1/web_invoices";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    // Отправка GET запроса
    protected function sendRequestGET($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    //Вычисление цифровой подписи
    protected function compute_signature($request_params, $secret_word, $method = 'add_invoice')
    {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            'add_invoice' => array(
                "serviceid",
                "accountno",
                "amount",
                "currency",
                "expiration",
                "info",
                "surname",
                "firstname",
                "patronymic",
                "city",
                "street",
                "house",
                "building",
                "apartment",
                "isnameeditable",
                "isaddresseditable",
                "isamounteditable",
                "emailnotification",
                "smsphone",
                "returntype",
                "returnurl",
                "failurl"
            ),
            'get_qr_code' => array(
                "invoiceid",
                "viewtype",
                "imagewidth",
                "imageheight"
            ),
            'add_invoice_return' => array(
                "accountno",
                "invoiceno"
            )
        );

        $result = $this->getSetting('token');

        foreach ($api_method[$method] as $item)
            $result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

        return $hash;
    }

    private function log_info($name, $message)
    {
        $this->log($name, "INFO", $message);
    }

    private function log($name, $type, $message)
    {
        $log_url = dirname(__FILE__) . '/log';

        if (!file_exists($log_url)) {
            $is_created = mkdir($log_url, 0777);

            if (!$is_created)
                return;
        }

        $log_url .= '/express-pay-erip' . date('Y.m.d') . '.log';

        file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - " . date("Y-m-d H:i:s") . "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
    }
}
