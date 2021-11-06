<?php
//Не забудте установить на данный скрипт привилегии выполнения от имени вебсервера
//Добавление задачи -> crontab -e -u www-data
//*  */12  *  *  * php -f /var/www/nextcloud/cleaner.php > /dev/null 2>&1
//*  */12  *  *  * php -f /var/www/nextcloud/occ files:scan-app-data > /dev/null 2>&1

$key = 'TqLHu47GZVGIFor4'; //Ключ входа

//if($key != $_GET['key']){echo "OOPS"; exit();}

require_once __DIR__ . '/config/config.php'; //Подключение конфига некстклауда

$pdo = new PDO('pgsql:host='.$CONFIG['dbhost'].';dbname='.$CONFIG['dbname'],$CONFIG['dbuser'],$CONFIG['dbpassword']);
//Список всех строк по столбцу fileid
$sql = "SELECT fileid FROM oc_filecache";
foreach ($pdo->query($sql) as $row) {
	$column_fileid[]=$row['fileid'];
}
//Отфильтрованный список по частичному совпадению path и mimepart (3 <- images)
$sql = "SELECT name FROM oc_filecache WHERE path LIKE '%preview%' AND mimepart = 3";
foreach ($pdo->query($sql) as $row) {
	$column_name_pm[]=$row['name'];
}
//Собираем список парентов кэша
for ($i = 0; $i <= count($column_name_pm)-1; $i++) {
	$sql = "SELECT parent FROM oc_filecache WHERE name = '$column_name_pm[$i]'";
	foreach ($pdo->query($sql) as $row) {
		$column_parent[]=$row['parent'];
	}
}
//Создаём список имён папок кэша картинок
for ($i = 0; $i <= count($column_parent)-1; $i++) {
	$sql = "SELECT path,name FROM oc_filecache WHERE fileid = '$column_parent[$i]' AND mimepart = 1";
	foreach ($pdo->query($sql) as $row) {
		$column_name_folders[]=$row['name'];
	}
}
$unused_dirs = array_values(array_unique(array_diff($column_name_folders, $column_fileid)));
//Собираем пути неиспользуемого кэша
for ($i = 0; $i <= count($unused_dirs)-1; $i++) {
	$sql = "SELECT path FROM oc_filecache WHERE name = '$unused_dirs[$i]' AND mimepart = 1";
	foreach ($pdo->query($sql) as $row) {
		$column_path_folders[]=$row['path'];
	}
}
//Удаляем список ненужного
foreach ($column_path_folders as $row) {
	if ($row != "." and $row != "..") {
		$directory = $CONFIG['datadirectory']."/".$row;
		foreach (glob($directory."/*.*") as $filename) {
			unlink ($filename);
		}
		if (rmdir($directory)) {
			echo "REMOVED -> ".$directory."<br />";
		}
		else
		{
			if (!is_dir($directory)) {$exists_dir = "<- FOLDER NOT FOUND, PLEASE RUN COMMAND \"occ files:scan-app-data\"";}
			echo "NOT REMOVED -> ".$directory."  ".$exists_dir."<br />";
		}
	}
}
?>
