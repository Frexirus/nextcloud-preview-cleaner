<?php
/*
https://github.com/Frexirus/nextcloud-preview-cleaner
*/

require_once __DIR__ . '/config/config.php'; //Подключение конфига некстклауда
//Подключение к базе данных
$pdo = new PDO('pgsql:host='.$CONFIG['dbhost'].';dbname='.$CONFIG['dbname'],$CONFIG['dbuser'],$CONFIG['dbpassword']);
//Поиск всех fileid изображений вне превью
$sql = "SELECT fileid FROM oc_filecache WHERE path NOT LIKE '%preview%' AND mimepart = 3";
foreach ($pdo->query($sql) as $row) {
	$column_fileid_users_images[]=$row['fileid'];
}
//Поиск всех parent изображений в превью
$sql = "SELECT parent FROM oc_filecache WHERE path LIKE '%preview%' AND mimepart = 3";
foreach ($pdo->query($sql) as $row) {
	$column_parent_preview_images[]=$row['parent'];
}
//Отбираем только уникальные parent
$column_parent_preview_images = array_values(array_unique($column_parent_preview_images));
//Поиск имён папок превью в соответствии с parent изображений превью
for ($i = 0; $i <= count($column_parent_preview_images)-1; $i++) {
	$sql = "SELECT name FROM oc_filecache WHERE fileid = $column_parent_preview_images[$i]";
	foreach ($pdo->query($sql) as $row) {
		$column_name_preview_images[]=$row['name'];
	}
}
//Сравнение списков, удаление из него используемых превью и сборка списка неиспользуемых превью
$unused_previews = array_values(array_diff($column_name_preview_images,$column_fileid_users_images));
//Собираем пути и паренты неиспользуемых превью
for ($i = 0; $i <= count($unused_previews)-1; $i++) {
	$sql = "SELECT fileid,path,parent FROM oc_filecache WHERE name = '$unused_previews[$i]' AND mimepart = 1";
	foreach ($pdo->query($sql) as $row) {
		$column_fileid_unused_rows[]=$row['fileid'];
		$column_path_unused_rows[]=$row['path'];
		$column_parent_unused_rows[]=$row['parent'];
	}
}
//Чистим мусор
foreach ($column_path_unused_rows as $i=>$row) {
	if ($row != "." and $row != "..") {
		$directory = $CONFIG['datadirectory']."/".$row;
		foreach (glob($directory."/*.*") as $filename) {
			unlink ($filename);
		}
		if (rmdir($directory)) {
			echo "REMOVED FOLDER -> ".$directory."<br />";
			$sql = "DELETE FROM oc_filecache WHERE parent = $column_parent_unused_rows[$i]";
			if ($pdo->query($sql)) {
				$sql = "DELETE FROM oc_filecache WHERE parent = $column_fileid_unused_rows[$i]";
				$pdo->query($sql);
			}
		}
		else
		{
			if (!is_dir($directory)) {$exists_dir = "<- FOLDER NOT FOUND, PLEASE RUN COMMAND \"occ files:scan-app-data\"";}
			echo "NOT REMOVED -> ".$directory."  ".$exists_dir."<br />";
		}
	}
}
//Если нечего удалять, то выводим сообщение об этом
if (count($column_path_unused_rows) == 0) echo "NOTHING TO DELETE...";
?>
