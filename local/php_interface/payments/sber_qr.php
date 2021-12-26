<?

namespace Payment;

use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;
use Citfact\SiteCore\Tools\DataAlteration;

class SberQR
{

    /**
     * Значение полученное при регистрации приложения
     */
    const CLIENT_ID = '*****';
    /**
     * Значение получаемое при регистрации приложение, которое
     * необходимо сразу же сохранить
     */
    const CLIENT_SECRET = '*****';
    /**
     * Авторизация пользователя
     */
    const URL_API_AUTHORIZATION = 'https://api.sberbank.ru/ru/prod/tokens/v2/oauth';
    /**
     * Создание заказа
     */
    const URL_API_ORDER_CREATE = 'https://api.sberbank.ru/ru/prod/order/v1/creation';
    /**
     * Получение статуса
     */
    const URL_API_ORDER_STATUS = 'https://api.sberbank.ru/ru/prod/order/v1/status';

    private $id_qr = '*****';

    /**
     * Скоупы для авторизации
     */
    const SCOPES = [
        'create' => 'https://api.sberbank.ru/order.create',
        'status' => 'https://api.sberbank.ru/order.status',
    ];

    /**
     * Отправка запроса
     * @param $url
     * @param $headers
     * @param $postFields
     * @param true|false $typeJson
     * @return mixed
     */
    private function sendCurl($url, $headers, $postFields, $typeJson = false)
    {
        $curl = curl_init();

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $typeJson ? json_encode($postFields) : http_build_query($postFields),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSLCERT => $_SERVER['DOCUMENT_ROOT'].'/path/client_cert.crt',
            CURLOPT_SSLKEY => $_SERVER['DOCUMENT_ROOT'].'/path/private.key',
        ];

        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $result = json_decode($response, true);
        curl_close($curl);

        return $result;
    }

    /**
     * @param $orderId
     * @return mixed
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ObjectException
     */
    public function createOrder($orderId)
    {
        $order = Order::load($orderId);
        $products = $order->getBasket()->getBasketItems();

        $items = [];

        foreach ($products as $item) {
            $element = $item->getFieldValues();
            $items[] = [
                'position_name' => $element['NAME'],
                'position_count' => (int)$element['QUANTITY'],
                'position_sum' => (int)($element['PRICE'] * 100),
                'position_description' => '',
            ];
        }

        $rquid = $this->getRandomString(32);

        $headers = [
            'accept: application/json',
            'authorization: Bearer '.$this->getToken(self::SCOPES['create']),
            'content-type: application/json',
            'x-ibm-client-id: '.self::CLIENT_ID,
            'x-Introspect-RqUID: '.$rquid,
        ];

        $date = new DateTime();
        $date = $date->format('Y-m-d').'T'.$date->format('h:i:s').'Z';

        $createOrder = new DateTime($order->getField("DATE_INSERT"));
        $createOrder = $createOrder->format('Y-m-d').'T'.$createOrder->format('h:i:s').'Z';

        $order = [
            'rq_uid' => $rquid,
            'rq_tm' => $date,
            'member_id' => (string)$order->getUserId(),
            'order_number' => (string)$order->getId(),
            'order_create_date' => $createOrder,
            'order_params_type' => $items,
            'id_qr' => $this->id_qr,
            'order_sum' => (int)($order->getPrice() * 100),
            'currency' => 'RUB',
            'description' => 'Номер заказа: '.$order->getId(),
        ];

        $response = $this->sendCurl(self::URL_API_ORDER_CREATE, $headers, $order, true);

        return $response;
    }

    /**
     * Проверка статуса оплаты в системе Сбера
     * @param $orderId
     * @return mixed
     */
    public function getOrderStatus($orderId)
    {
        $rq_uid = $this->getRandomString(32);

        $headers = [
            'accept: application/json',
            'authorization: Bearer '.$this->getToken(self::SCOPES['status']),
            'content-type: application/json',
            'x-ibm-client-id: '.self::CLIENT_ID,
            'x-Introspect-RqUID: '.$rq_uid,
        ];
        $date = new DateTime();
        $date = $date->format('Y-m-d').'T'.$date->format('h:i:s').'Z';
        $order = [
            'rq_uid' => $rq_uid,
            'rq_tm' => $date,
            'order_id' => $orderId,
        ];

        $response = $this->sendCurl(self::URL_API_ORDER_STATUS, $headers, $order, true);

        return $response;
    }

    /**
     * Получение авторизационного токена
     * @param string $scope
     * @return string
     */
    public function getToken(string $scope): string
    {
        $headers = [
            'accept: application/json',
            'authorization: '.$this->getAuthorizationHeader(),
            'content-type: application/x-www-form-urlencoded',
            'rquid: '.$this->getRandomString(32),
            'x-ibm-client-id: '.self::CLIENT_ID,
        ];

        $postFields = [
            'grant_type' => 'client_credentials',
            'scope' => $scope,
        ];

        $response = $this->sendCurl(self::URL_API_AUTHORIZATION, $headers, $postFields);

        return $response['access_token'];
    }

    /**
     * Кодировка ключей для авторизации
     * @return string
     */
    private function getAuthorizationHeader(): string
    {
        return 'Basic '.base64_encode(self::CLIENT_ID.':'.self::CLIENT_SECRET);
    }

    /**
     * @param int $length
     * @return string
     */
    public static function getRandomString(int $length = 16): string
    {
        $permitted_chars = 'abcdefABCDEF0123456789';
        $input_length = strlen($permitted_chars);
        $random_string = '';
        for ($i = 0; $i < $length; $i++) {
            $random_character = $permitted_chars[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }

        return $random_string;
    }

}