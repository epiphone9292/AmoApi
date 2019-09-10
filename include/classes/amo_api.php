<?php

class Amo_Api
{
    /**
     * логин для авторизации в системе, чаще всего email
     * @var string
     */
    private $login;

    /**
     * api-ключ, берётся в настройках аккаунта
     * @var string
     */
    private $api_key;

    /**
     * адрес аккаунта, указывается при создании, также можно посмотреть в адресной строке - перед '.amocrm.ru'
     * @var string
     */
    private $subdomen;

    /**
     * Для отправки запросов
     * @var Requester
     */
    private $requester;

    /**
     * лимит количества запросов в секунду
     * @var int
     */
    public static $limit_per_second = 5;

    /**
     * лимит количества создаваемых сущностей, если надо связать при создании - учитываем каждую связь как еще сущность
     * @var int
     */
    protected $limit_count = 250;

    public function __construct(string $login, string $api_key, string $subdomen)
    {
        $this->login = $login;
        $this->api_key = $api_key;
        $this->subdomen = $subdomen;
        $this->requester = new Requester($login, $api_key, $subdomen);
    }

    /**
     * Создаёт сущности по указанному количеству, если указан параметр $link_elements - связывает
     * @param int $count количество
     * @param string $type тип сущности leads|contacts|companies|customers
     * @param array|null можно указать id сущностей, которые надо привязать к контактам, привязка один к одному по порядку в массиве
     *
     * @return array id созданных сущностей
     */
    public function create_element(int $count, string $type, array $link_elements = null)
    {
        print_r("Началось создание {$type}.\n");

        if ($count > 10000) {
            die('Сбавь обороты, куда столько');
        }
        $method = "/api/v2/{$type}";

        $denominator = empty($link_elements) ? $this->limit_count : intval($this->limit_count / count($link_elements));
        $id = [];

        for ($i = $numerator = 0; $i < $count; $i++, $numerator++) {
            $data['add'][$i]['name'] = "{$type} {$count}";
            if ($type === 'customers') {
                $data['add'][$i]['next_date'] = time() + 60 * 60 * 24;
            }

            if ($type === 'contacts') {
                if (!empty($link_elements['leads'][$i])) {
                    $data['add'][$i]['leads_id'] = $link_elements['leads'][$i];
                }
                if (!empty($link_elements['companies'][$i])) {
                    $data['add'][$i]['company_id'] = $link_elements['companies'][$i];
                }
                if (!empty($link_elements['customers'][$i])) {
                    $data['add'][$i]['customers_id'] = $link_elements['customers'][$i];
                }
            }

            if ($i === $count - 1 || $numerator === $denominator) {
                $current_response = $this->requester->do_post_request($method, $data);
                foreach ($current_response['_embedded']['items'] as $new_element) {
                    $id[] = $new_element['id'];
                }

                print_r(sprintf("Создано %d %s.\n", $i + 1, $type));
                $numerator = 0;
            }
        }

        return $id;
    }

    /**
     * Метод возвращает информацию по доп полям аккаунта
     * @param string $type_element тип сущности, чьи доп. поля нужны
     *
     * @return array часть ответа от сервера, содержащая только информацию по доп полям
     */
    public function get_field_list(string $type_element = NULL)
    {
        $response_all_field = $this->requester->do_get_request('/api/v2/account?with=custom_fields');
        if ($type_element === NULL) {
            $list_field = $response_all_field['_embedded']['custom_fields'];
        } else{
            $list_field = $response_all_field['_embedded']['custom_fields'][$type_element];
        }

        return $list_field;
    }

    /**
     * Метод возвращает информацию по пользователям аккаунта
     * @return array ответ от сервера
     */
    public function get_user_list()
    {
        return $this->requester->do_get_request('/api/v2/account?with=users');
    }

    /**
     * Метод закрывает задачу по id
     * @param string $id_task id задачи
     * @return array ответ от сервера
     */
    public function close_tasks(string $id_task)
    {
        $data['update'][] = [
            'id' =>  $id_task,
            'updated_at' => time(),
            'is_completed' => true,
        ];
        $response = $this->requester->do_post_request("/api/v2/tasks", $data);
        return $response;
    }

    /**
     * Возвращает информацию по воронке - только id воронок или всё по воронкам и статусам
     * @param string 'id'|'all_info'
     * @return array
     */
    public function get_pipeline($action = 'id')
    {
        $response = $this->requester->do_get_request("/api/v2/pipelines");

        if ($action === 'id') {
            $result = [];
            foreach ($response['_embedded']['items'] as $id_pipe => $value) {
                $result[] = (int)$id_pipe;
            }
        } else {
            $result = $response;
        }
        return $result;
    }
}
