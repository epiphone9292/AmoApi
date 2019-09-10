<?php

class Requester
{
    /**
     * начало url для запросов
     * @var string
     */
    private $start_url;

    /**
     * данные для авторизации в системе
     * @var array
     */
    private $user;

    const ERROR_AUTH = [
        101 => 'Возникает в случае запроса к несуществующему аккаунту (субдомену)',
        110 => 'Неправильный логин или пароль',
        111 => 'Возникает после нескольких неудачных попыток авторизации. В этом случае нужно авторизоваться в аккаунте через браузер, введя код капчи',
        112 => 'Возникает, когда пользователь выключен в настройках аккаунта “Пользователи и права” или не состоит в аккаунте',
        113 => 'Доступ к данному аккаунту запрещён с Вашего IP адреса. Возникает, когда в настройках безопасности аккаунта включена фильтрация доступа к API по “белому списку IP адресов”',
        401 => 'На сервере нет данных аккаунта. Нужно сделать запрос на другой сервер по переданному IP'
    ];
    const ERROR_EVENTS = [
        218 => 'Добавление событий: пустой массив',
        221 => 'Список событий: требуется тип',
        222 => 'Добавление/Обновление событий: пустой запрос',
        223 => 'Добавление/Обновление событий: неверный запрашиваемый метод (GET вместо POST)',
        224 => 'Обновление событий: пустой массив',
        225 => 'Обновление событий: события не найдены',
        226 => 'Добавление событий: элемент события данной сущности не найден',
        244 => 'Добавление событий: недостаточно прав для добавления события'
    ];
    const ERROR_CODES = [
        301 => 'Moved permanently',
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not found',
        500 => 'Internal server error',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
    ];

    /**
     * коды CURLINFO_HTTP_CODE, которые игнорим
     * 401 тут, так как авторизация проверяется только при запросе на авторизацию,
     * если 401 приходит в обычный запрос - возможно истекла сессия, проверяем попыткой авторизации и если ок - повторяем
     * @var array
     */
    const NOT_BAD_CURL_CODE = [200, 204, 401];

    public function __construct(string $login, string $api_key, string $subdomen)
    {
        $this->start_url = "https://{$subdomen}.amocrm.ru";
        $this->user = [
            'USER_LOGIN' => $login,
            'USER_HASH'=> $api_key
        ];
    }

    /**
     * Метод для отправки пост-запросов
     * @param string $method метод api
     * @param array $data данные для отправки
     * @return array
     */
    public function do_post_request(string $method, array $data)
    {
        try {
            if (empty($data)) {
                throw new Exception('Пустое тело запроса');
            }
        } catch (Exception $exception) {
            die('Проблема с запросом: ' . $exception->getMessage());
        }
        usleep(intval(1000000 / Amo_Api::$limit_per_second));

        $options = [
            CURLOPT_URL => $this->start_url . $method,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options + $this->get_default_options());
        $response = curl_exec($curl);
        $response = json_decode($response, true);
        $code_response = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->check_curl_code($code_response);

        if (isset($response['response']['error_code'], self::ERROR_AUTH[(int)$response['response']['error_code']])) {
            $response = $this->autorize($method, $data);
        }
        $this->check_response($response);

        return $response;
    }

    /**
     * Метод для авторизации
     * Вызывается в том случае, если при попытке выполнить очередной запрос вернулась ошибка "не авторизован"
     * @param string $method метод api, который не получилось выполнить из-за авторизации
     * @param array $data данные для отправки
     * @return array
     */
    private function autorize(string $method, $data = null)
    {
        usleep(intval(1000000 / Amo_Api::$limit_per_second));

        $options = [
            CURLOPT_URL => $this->start_url . '/private/api/auth.php?type=json',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($this->user),
            CURLOPT_HTTPHEADER =>array('Content-Type: application/json')
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options + $this->get_default_options());
        $response = curl_exec($curl);
        $response = json_decode($response, true)['response'];

        $code_response = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->check_curl_code($code_response);
        try {
            if (!$response['auth']) {
                $response['error_code'] = (int)$response['error_code'];
                throw new Exception($response['error'], $response['error_code']);
            }
        } catch (Exception $exception) {
            die('Проблема с запросом: ' . $exception->getMessage() . PHP_EOL . 'Код ошибки: ' . $exception->getCode());
        }

        return $data ? $this->do_post_request($method, $data) : $this->do_get_request($method);
    }

    /**
     * Метод для отправки гет-запросов
     * @param string $method метод api
     * @return array
     */
    public function do_get_request(string $method)
    {
        usleep(intval(1000000 / Amo_Api::$limit_per_second));

        $options = [
            CURLOPT_URL => $this->start_url . $method,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options + $this->get_default_options());
        $response = curl_exec($curl);
        $response = json_decode($response, true);

        $code_response = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->check_curl_code($code_response);

        if (isset($response['response']['error_code'], self::ERROR_AUTH[(int)$response['response']['error_code']])) {
            $response = $this->autorize($method);
        }
        $this->check_response($response);

        return $response;
    }

    /**
     * Проверка кода ответа curl
     * @param string $code_response код ответа
     * @return void
     */
    private function check_curl_code($code_response)
    {
        try {
            if (!in_array($code_response, self::NOT_BAD_CURL_CODE)) {
                $error_text = isset(self::ERROR_CODES[$code_response]) ? self::ERROR_CODES[$code_response] : 'Undescribed error';
                throw new Exception($error_text, $code_response);
            }
        } catch (Exception $exception) {
            die('Проблема с запросом: ' . $exception->getMessage() . PHP_EOL . 'Код ошибки: ' . $exception->getCode());
        }
    }

    /**
     * Проверка ответа amoCRM
     * @param string $code_response код ответа
     * @return void
     */
    private function check_response($response)
    {
        try {
            if (!empty($response['_embedded']['errors'])) {
                $text_error = '';
                foreach ($response['_embedded']['errors'] as $command => $items) {
                    $text_error .= $command . ': ';
                    foreach ($items as $element_id => $text) {
                        $text_error .= sprintf('%s - %s ', $element_id, $text);
                    }
                }
                throw new Exception($text_error);
            }
            if (isset($response['status'], self::ERROR_EVENTS[$response['status']])) {
                throw new Exception(self::ERROR_EVENTS[$response['status']], $response['status']);
            }
            if (!empty($response['response']['error'])) {
                throw new Exception($response['response']['error']);
            }
        } catch (Exception $exception) {
            die('Проблема с запросом: ' . $exception->getMessage());
        }
    }

    /**
     * default опции для curl
     * @return array
     */
    private function get_default_options()
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $_SERVER['DOCUMENT_ROOT'].'cookie.txt',
            CURLOPT_COOKIEJAR => $_SERVER['DOCUMENT_ROOT'].'cookie.txt',
            CURLOPT_USERAGENT => 'amoCRM-API-client/1.0',
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ];
    }
}
