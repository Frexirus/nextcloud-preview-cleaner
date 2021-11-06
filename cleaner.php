<?php
/*
https://github.com/Frexirus/nextcloud-preview-cleaner
*/

require_once __DIR__ . '/config/config.php'; //Подключение конфига некстклауда

$pdo = new PDO('pgsql:host='.$CONFIG['dbhost'].';dbname='.$CONFIG['dbname'],$CONFIG['dbuser'],$CONFIG['dbpassword']);
//Список строк связанных с изображениями по столбцу fileid
$sql = "SELECT fileid FROM oc_filecache WHERE path NOT LIKE '%preview%' AND mimepart = 3";
foreach ($pdo->query($sql) as $row) {
	$column_fileid_users_images[]=$row['fileid'];
}
//Поиск парентов изображений в папке превью
$sql = "SELECT parent FROM oc_filecache WHERE path LIKE '%preview%' AND mimepart = 3";
foreach ($pdo->query($sql) as $row) {
	$column_name_preview_parents[]=$row['parent'];
}
$column_name_preview_parents = array_values(array_unique($column_name_preview_parents));
for ($i = 0; $i <= count($column_name_preview_parents)-1; $i++) {
	$sql = "SELECT name FROM oc_filecache WHERE fileid = $column_name_preview_parents[$i]";
	foreach ($pdo->query($sql) as $row) {
		$column_fileid_preview_images[]=$row['name'];
	}
}
//Сравнение списков и удаление из него используемых превью
$column_fileid_preview_unused_images = array_values(array_diff($column_fileid_preview_images,$column_fileid_users_images));

//Собираем пути и паренты неиспользуемого кэша
for ($i = 0; $i <= count($column_fileid_preview_unused_images)-1; $i++) {
	$sql = "SELECT fileid,path,parent FROM oc_filecache WHERE name = '$column_fileid_preview_unused_images[$i]' AND mimepart = 1";
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
?>
