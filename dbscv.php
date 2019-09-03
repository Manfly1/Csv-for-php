<?php
// З’єднання з бд
$mysqli = new mysqli("localhost", "root", "","Csv_task");
if (mysqli_connect_errno()) {
    printf("Помилка з'єднання з базою даних: %s\n", mysqli_connect_error());
    exit();
}
$mysqli->set_charset("utf8");
?>
 <h3>Завантажити файл *csv</h3>
<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post" enctype="multipart/form-data">

    <input type="file" name="csv"  />
    <input type="submit" name="submit" value="Надіслати" /></form>


<?php

$csv = Array();
$idCsv = Array();

if ( isset($_POST["submit"])) {
    // чи немає помилок
    if ($_FILES['csv']['error'] == 0) {

        // дані про  csv
        $name = $_FILES['csv']['name'];
        $ext = strtolower(end(explode('.', $_FILES['csv']['name'])));
        $type = $_FILES['csv']['type'];
        $tmpName = $_FILES['csv']['tmp_name'];


        // перевірка формату
        if ($ext === 'csv') {
            if (($handle = fopen($tmpName, 'r')) !== FALSE) {
                set_time_limit(0);

                //кількість рядків
                $row = 0;
                $idCsvCounter = 0;

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    //кількість стовпців
                    $col_count = count($data);

                    //Тільки якщо 6
                    if($col_count==6) {
                        // отримати дані з csv
                        $csv[$row]['uid'] = preg_replace('/\D/', '', $data[0]);;
                        $csv[$row]['firstName'] = $data[1];
                        $csv[$row]['lastName'] = $data[2];
                        $csv[$row]['birthDay'] = $data[3];
                        $csv[$row]['dateChange'] = $data[4];
                        $csv[$row]['description'] = $data[5];
                        $idCsv[$idCsvCounter] = $csv[$row]['uid'];
                        $idCsvCounter++;
                        // інкремент рядків
                        $row++;

                    }else{
                        echo "Помилка: строка номер №{$row} немає всіх даних";
                    }
                }
                echo "<br><h3>Логи щодо завантаження *CSV:</h3><br>";
                if($row>1) {

                    //перевірка на наявність у файлі
                    CheckFileDatabase($csv, $mysqli);

                    //робота із базою
                    CheckDataBase($csv, $mysqli);

                }else if($row==1){
                    CheckFileDatabase($csv, $mysqli);
                }

                fclose($handle);
            }
        }
    }

}


//Завантажити з бази наявні
$result = $mysqli->query("SELECT * FROM `info`");
if ($result->num_rows > 0) {
    echo "<br><h3><p>Дані з бази:</p></h3>";
    echo "<table cellspacing=\"2\" border=\"1\" cellpadding=\"5\" >
           <tr>
                <th>uid</th>
                <th>firstName</th>
                <th>lastName</th>
                <th>birthDay</th>
                <th>dateChange</th>
                <th>description</th>
           </tr>";

    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row["uid"]}</td>
                <td>{$row["firstName"]}</td>
                <td>{$row["lastName"]}</td>
                <td>{$row["birthDay"]}</td>
                <td>{$row["dateChange"]}</td>
                <td>{$row["description"]}</td>
              </tr>";
    }
    echo '</table>';
}



////////// Функції


// Перевірка чи існує строки із файлу в базі
function CheckDataBase($csvArray, $mysqli){
        $alterCounter = 0;
        $addCounter= 0;
        for($i=1; $i<count($csvArray);$i++ ){
            $result = $mysqli->query("SELECT * FROM `info` WHERE `uid`=".$csvArray[$i]['uid']."");
            $dbArr = $result->fetch_assoc();

            if(count($dbArr)>0){
                //Якщо є
                $d1 = new DateTime($csvArray[$i]["dateChange"]);
                $d2 = new DateTime($dbArr["dateChange"]);
                if($d1 > $d2) {
                    AlterTable($csvArray[$i], $mysqli, $dbArr);
                    $alterCounter++;
                }
           }else{
                // Якщо відстуня
               AddToTable($csvArray[$i], $mysqli);
                $addCounter++;

           }
        }
            if($alterCounter>0) echo "- Змінено рядків в базі даних: {$alterCounter}<br>";
            if($addCounter>0) echo "- Додано рядків в базу даних: {$addCounter}<br>";

    }

// Оновлення таблиці, якщо дата новіша в файлі
function AlterTable($csvArray, $mysqli, $dbArr){
    $resultQuery = $mysqli->query("UPDATE `info` SET 
						 	`firstName` = '" . $mysqli->real_escape_string($csvArray["firstName"]) . "' ,
						 	`lastName` = '" . $mysqli->real_escape_string($csvArray["lastName"]) . "' ,
						 	`birthDay`='" . $mysqli->real_escape_string($csvArray["birthDay"]) . "' ,
						 	`dateChange`='" . $mysqli->real_escape_string($csvArray["dateChange"]) . "' ,
						 	`description`= '" . $mysqli->real_escape_string($csvArray["description"]) . "'
						 	
						 	WHERE `uid`  = " . $csvArray["uid"] . "");
    }

// Додати то таблиці
function AddToTable($csvArray, $mysqli){
        $resultQuery = $mysqli->query("INSERT INTO `info`(`uid`, `firstName`, `lastName`, `birthDay`, `dateChange`, `description`) VALUES (
                            '" . $csvArray['uid'] . "',
                            '" . $csvArray['firstName'] . "',
                            '" . $csvArray['lastName'] . "',
                            '" . $csvArray['birthDay'] . "',
                            '" . $csvArray['dateChange'] . "',
                            '" . $csvArray['description'] . "' 
                            )");
    }

// Перевірити наявність в файлі
function CheckFileDatabase($csvArray, $mysqli){
        $result = $mysqli->query("SELECT * FROM `info`");

        // Для збирання ID
        $arrayDatabaseId = Array();
        $count = 0;

        $arrayCsvId = Array();

        while($row = $result->fetch_assoc()) {
            $arrayDatabaseId[$count] = (int)$row["uid"];
            $count++;
        }


        for($i=1; $i<count($csvArray); $i++){
            $arrayCsvId[$i-1] = (int)$csvArray[$i]["uid"];
        }

        // Виведення id, яких немає в файлі та переіндексація
        $deleteIDs = array_diff($arrayDatabaseId, $arrayCsvId);
        $deleteIDs = array_values($deleteIDs);

        // Видалення рядків по id
        DeleteInTable($deleteIDs,$mysqli);
        $countDeleteIDs = count($deleteIDs);
        if($countDeleteIDs>0) echo "- Видалено {$countDeleteIDs} рядків із бази, які не відповідають рядкам csv<br>";

    }

// Видалення із бази, згідно файлу
function DeleteInTable($deleteIDs, $mysqli){

    for($i = 0; $i<count($deleteIDs); $i++){
        $resultQuery = $mysqli->query("DELETE FROM `info` WHERE `uid` =".$deleteIDs[$i]."");
    }

 }