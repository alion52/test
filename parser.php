<?php

declare(strict_types=1);

require 'vendor/autoload.php';


use PhpOffice\PhpSpreadsheet\IOFactory;

const HOST = 'localhost';
const PORT = 3306;
const DATABASE = 'excel_db';
const USER_NAME = 'root';
const PASSWORD = '';


function saveToDatabase($article, $price, $name, $quantity): bool
{
    $db = new mysqli(HOST, USER_NAME, PASSWORD, DATABASE, PORT);

    $stmt = $db->prepare("INSERT INTO products (article, price, name, quantity) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdss", $article, $price, $name, $quantity);

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

if (isset($_POST['parse'])) {
    
    $column_article = 0;
    $column_name = 1;
    $column_price = 2;  
    $column_quantity = 3;   
    $price_min = isset($_POST['price_min']) ? (float)$_POST['price_min'] : 0;
    $price_max = isset($_POST['price_max']) ? (float)$_POST['price_max'] : PHP_INT_MAX;
    $article_min = (int)($_POST['article_min'] ?? 0);
    $article_max = (int)($_POST['article_max'] ?? 999999999);
    $contain_header = isset($_POST['contain_header']);

    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            foreach ($data as $rowIndex => $row) {
                // Пропуск заголовка
                if ($rowIndex === 0 && $contain_header) {
                    continue;
                }

                echo "Строка $rowIndex: ";
                print_r(implode(' ', $row));
                echo "<br>";

                // Проверка наличия индексов
                if (array_key_exists($column_article, $row) &&
                    array_key_exists($column_price, $row) &&
                    array_key_exists($column_name, $row)&&
                    array_key_exists($column_quantity, $row)) {
                    $article = $row[$column_article];
                    $price = $row[$column_price] !== null ? str_replace(',', '.', $row[$column_price]) : 0; 
                    $name = $row[$column_name];
                    $quantity = (int)$row[$column_quantity];

                    // Преобразавоние цены в float
                    $price = (float)$price;

                    if (!empty($article) && $price >= $price_min && $price <= $price_max) {
                        if ((!empty($article_min) && $article >= $article_min) || empty($article_min)) {
                            if ((!empty($article_max) && $article <= $article_max) || empty($article_max)) {
                                saveToDatabase($article, $price, $name, $quantity);
                            }
                        }
                    }
                } else {
                    echo 'Ошибка: один или несколько столбцов не найдены в строке.';
                }
            }

            echo "Данные успешно сохранены в базу данных.";
        } catch (Exception $e) {
            echo 'Ошибка обработки файла: ' . $e->getMessage();
        }
    } else {
        echo 'Ошибка загрузки файла или файл не был загружен.';
    }
}