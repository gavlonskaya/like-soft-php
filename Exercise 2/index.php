<?php
/**
 * Скрипт для извлечения ссылок из раздела "события на долговом рынке" на сайте www.bills.ru.
 */
// Подключение к базе данных
$servername = "localhost"; // Имя сервера базы данных
$username = "billsuser"; // Имя пользователя базы данных
$password = "billsuser"; // Пароль пользователя базы данных
$dbname = "bills"; // Имя базы данных

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

// Загрузка страницы www.bills.ru
$url = "https://www.bills.ru/";
$opts = array('http'=>array('header' => "User-Agent:MyAgent/1.0\r\n"));
$context = stream_context_create($opts);
$html = file_get_contents($url, false, $context);

if (!empty($html)) {
    echo "Ответ содержит данные.\n";
} else {
    echo "Ответ не содержит данных.\n";
}

// Создание объекта DOM для парсинга HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

// Поиск блока "события на долговом рынке"
$eventsBlock = $dom->getElementById("bizon_api_news_list");

$eventDates = [];
$eventTitles = [];
$eventLinks = [];

if ($eventsBlock) {
    // Извлечение данных из раздела "события на долговом рынке"
    $eventItems = $eventsBlock->getElementsByTagName("a");

    foreach ($eventItems as $eventItem) {
        $eventLink = $eventItem->getAttribute("href");
        $eventLinks[] = $eventLink;

        $eventDateNode = $eventItem->getElementsByTagName("span")->item(0);
        $eventTitle = $eventItem->nodeValue;

        if ($eventDateNode) {
            $eventDate = $eventDateNode->nodeValue;
            $eventDates[] = $eventDate;
        } else {
            $eventDates[] = "";
        }

        $eventTitles[] = $eventTitle;
    }

    // Сохранение данных в базу данных (создайте таблицу, если она ещё не существует)
    for ($i = 0; $i < count($eventDates); $i++) {
        $date = date("Y-m-d H:i:s", strtotime($eventDates[$i]));
        $title = $eventTitles[$i];
        $url = $eventLinks[$i];

        // Проверка, существует ли уже URL в базе данных
        $checkSql = "SELECT * FROM bills_ru_events WHERE url = '$url'";
        $result = $conn->query($checkSql);

        if ($result->num_rows == 0) {
            // URL не существует, вставляем его в базу данных
            $insertSql = "INSERT INTO bills_ru_events (date, title, url) VALUES ('$date', '$title', '$url')";
            if ($conn->query($insertSql) === false) {
                echo "Ошибка при выполнении запроса: " . $conn->error;
            }
        } else {
            echo "URL уже существует, пропускаем: $url\n";
        }
    }
} else {
    echo "Блок 'события на долговом рынке' не найден.";
}

// Закрытие соединения с базой данных
$conn->close();
