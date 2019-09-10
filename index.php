<?php

require_once 'include/autoload.php';
require_once 'secret.php';

$stdin = fopen('php://stdin', 'r');
main_menu();

function main_menu(){
    do {
        popen('cls', 'w');
        print_r("\n
            Введите номер пункта меню:\n
            1. Создать n всех сущностей, связать с контактом\n
            2. Завершить указанную задачу по ID.\n
            3. Информация по воронкам.\n
            4. Создать n элементов указанной сущности\n
            0. Завершить работу.\n"
        );
        fscanf(STDIN, "%d\n", $number);
    } while ($number < 0 || $number > 4);

    switch ($number) {
        case 1:
            create_element_and_link_contact();
            break;
        case 2:
            complete_the_task();
            break;
        case 3:
            digital_pipeline();
            break;
        case 4:
            create_element();
            break;
        default:
            break;
    }
}

function create_element_and_link_contact()
{
    popen('cls', 'w');
    do {
        print_r("Введите n, где 0 < n < 10000: ");
        fscanf(STDIN, "%d\n", $count);
    } while ($count <= 0 || $count > 10000 );

    $amo_api = new Amo_Api(LOGIN, KEYAPI, SUBDOMAIN);

    $link['leads'] = $amo_api->create_element($count, 'leads');
    $link['companies'] = $amo_api->create_element($count, 'companies');
    $link['customers'] = $amo_api->create_element($count, 'customers');

    $contacts_id = $amo_api->create_element($count, 'contacts', $link);

    print_r("Создано по {$count} сущностей.\n\n");
    print_r("
        1. Вернуться в главное меню.\n\n
        Для завершения работы нажмите enter.\n"
    );
    fscanf(STDIN, "%d\n", $number);
    if ($number === 1) {
        main_menu();
    }
}

function complete_the_task()
{
    popen('cls', 'w');
    print_r("Введите id задачи: ");
    $id_task = trim(fgets(STDIN));
    $amo_api = new Amo_Api(LOGIN, KEYAPI, SUBDOMAIN);

    $response = $amo_api->close_tasks($id_task);
    $print = isset($response['_embedded']["items"][0]['id']) ? "Готово" : "Проверьте введенные данные";
    print_r("\n" . $print . "\n\n");

    print_r("
        1. Вернуться в главное меню.\n
        2. Попробовать снова\n\n
        Для завершения работы нажмите enter.\n"
    );
    fscanf(STDIN, "%d\n", $number);
    if ($number === 1) {
        main_menu();
    }  elseif ($number === 2) {
        complete_the_task();
    }
}

function digital_pipeline()
{
    do {
        popen('cls', 'w');
        print_r("
            Что вы хотите узнать?\n
            1. Количество воронок\n
            2. id воронок\n
            3. полная информация по воронкам и статусам\n
            0. в главное меню\n"
        );
        fscanf(STDIN, "%d\n", $n);
    } while ($n < 0 || $n > 3 );

    $amo_api = new Amo_Api(LOGIN, KEYAPI, SUBDOMAIN);

    switch ($n) {
        case 1:
            $result = 'Всего воронок: ' . count($amo_api->get_pipeline('id'));
            break;
        case 2:
            $result = 'Статусы: ' . implode(', ', $amo_api->get_pipeline('id'));
            break;
        default:
            $result = var_export($amo_api->get_pipeline('all_info'), true);
            break;
    }

    print_r("\n{$result}\n\n");
    print_r("
        1. Вернуться в главное меню.\n
        2. Попробовать снова\n\n
        Для завершения работы нажмите enter.\n"
    );
    fscanf(STDIN, "%d\n", $number);
    if ($number === 1) {
        main_menu();
    }  elseif ($number === 2) {
        digital_pipeline();
    }
}

function create_element()
{
    $map = [
        1 => 'contacts',
        2 => 'leads',
        3 => 'companies',
        4 => 'customers',
    ];
    do {
        popen('cls', 'w');
        print_r("
            Выберите номер сущности\n
            1. Контакт\n
            2. Компания\n
            3. Сделка\n
            4. Покупатель\n"
        );
        fscanf(STDIN, "%d\n", $element_type);
    } while ($element_type <= 0 || $element_type > 4 );
    print_r("Введите количество\n");
    fscanf(STDIN, "%d\n", $count);

    $amo_api = new Amo_Api(LOGIN, KEYAPI, SUBDOMAIN);
    $id_element = $amo_api->create_element($count, $map[$element_type]);

    print_r("Готово\n\n");
    print_r("
        1. Вернуться в главное меню.\n
        2. Попробовать снова\n\n
        Для завершения работы нажмите enter.\n"
    );
    fscanf(STDIN, "%d\n", $number);
    if ($number === 1) {
        main_menu();
    }  elseif ($number === 2) {
        create_element();
    }
}