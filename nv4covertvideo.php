<?php
/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 31/05/2010, 00:36
 */
define ( 'NV_SYSTEM', true );

// Xac dinh thu muc goc cua site
define ( 'NV_ROOTDIR', pathinfo ( str_replace ( DIRECTORY_SEPARATOR, '/', __file__ ), PATHINFO_DIRNAME ) );
// Ket noi den mainfile.php nam o thu muc goc.
$realpath_mainfile = $set_active_op = '';

require NV_ROOTDIR . '/includes/mainfile.php';

if ($sys_info ['allowed_set_time_limit']) {
	set_time_limit ( 1200 );
}
if (NV_CLIENT_IP != '127.0.0.1') {
	die ( NV_CLIENT_IP );
}

try {
	global $db2;
	$db2 = new PDO ( "pgsql:dbname=3cms_binhphuoc;host=localhost", 'postgres', 'root' );
	$db2->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	$db2->setAttribute ( PDO::ATTR_CASE, PDO::CASE_LOWER );
	$db2->setAttribute ( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
	$db2->setAttribute ( PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8 );
	// $title = iconv( "UTF-8", "ISO-8859-1", $row['name'] );
	// if( empty( $title ) )
	// {
	// $title = iconv( "UTF-8", "Windows-1252", $row['name'] );
	// //}
} catch ( Exception $e ) {
	trigger_error ( 'Sorry! Could not connect to data sql server', 256 );
}
echo ('Kết nối xong CSDL');
echo '<br>';

global $module_data, $lang_im, $lang_fix;
$module_data = 'videoclips';
$lang_im = 'vi';
$lang_fix = $db_config['prefix'] . '_' . $lang_im;
$url_video = 'tin-video-23541.htm';
$id_domain = 57610;

$result = $db->query('SHOW TABLE STATUS LIKE ' . $db->quote($lang_fix . '\_' . $module_data . '\_%'));
while ($item = $result->fetch()) {
	$db->query('TRUNCATE ' . $item['name']);
}
;
//Thêm cat

$result_cat = $db2->query('SELECT catalogueid FROM article, article_catalogue WHERE article.articleid = article_catalogue.articleid  AND article_catalogue.organizationid = '.$db2->quote($id_domain).' AND urlvideo !='.$db2->quote('').' GROUP BY catalogueid')->fetchColumn();
if(!empty($result_cat)) {
	$_catid = $result_cat;
}
else {
	die('không có chuyên mục video');
}

$result_catalog = $db2->query('SELECT catalogueid, name_vn FROM catalogue WHERE catalogueid ='.$db2->quote($_catid))->fetch();

$sql = "INSERT INTO " . $lang_fix . "_" . $module_data . "_topic ( parentid, title, alias, description, weight, img, status, keywords) VALUES
			( :parentid, :title, :alias, :description, :weight, '', :status, '')";

$data_insert = array ();
$data_insert ['parentid'] = 0;
$data_insert ['title'] = $result_catalog['name_vn'];
$data_insert ['alias'] = change_alias($result_catalog['name_vn']);
$data_insert ['description'] = '';
$data_insert ['weight'] = 1;
$data_insert ['status'] = 1;
$catid = $db->insert_id ( $sql, 'id', $data_insert );

//Thêm videoclip
$sql = $db2->query('SELECT article.articleid, createdatetime, title_vn, summarycontent_vn, viewsummary_vn,content_vn,viewcontent_vn,startdatetime, enddatetime,imagename,viewable,showndate,counter FROM article, article_catalogue
WHERE article.articleid = article_catalogue.articleid AND catalogueid = '.$db2->quote($_catid).' AND article_catalogue.organizationid =' .$db2->quote($id_domain));
while($row_video = $sql->fetch()) {
	$addtime = intval($row_video['createdatetime'] / 1000);
	$hometext = (empty($row_video['summarycontent_vn'])) ? $row_video['viewsummary_vn'] : $row_video['summarycontent_vn'];
	$internalpath = '';
	if (preg_match ( "/file:(.*),/", $row_video['viewcontent_vn'], $match )) {
		$internalpath = str_replace("'", '', $match[1]);
	}
	$sql_video = 'INSERT INTO ' . $lang_fix . '_' . $module_data . '_clip
				(tid, title, alias, hometext, bodytext, keywords, img, internalpath, comm, status, addtime) VALUES
				 (
				 ' . intval ( $catid ) . ',
				 :title,
				 :alias,
				 :hometext,
				 :bodytext,
				 :keywords,
				 :img,
				 :internalpath,
				 :comm,
				 :status,
				 :addtime )';
	$hometext = nv_get_plaintext ( $hometext );
	$hometext = trim ( $hometext, '&nbsp;' );
	$hometext = trim ($hometext);
	$data_insert = array ();
	$data_insert ['title'] = $row_video['title_vn'];
	$data_insert ['alias'] = change_alias ( $row_video['title_vn'] );
	$count = $db->query ( 'SELECT COUNT(*) FROM ' . $lang_fix . '_' . $module_data . '_clip WHERE title = ' . $db->quote ( $row_video['title_vn'] ) )->fetchColumn ();
	if ($count > 0) {
		++$count;
		$data_insert ['alias'] = $data_insert ['alias'] . '-' . $count;
	}

	$data_insert ['hometext'] = $hometext;
	$data_insert ['bodytext'] = $row_video['content_vn'];
	$data_insert ['keywords'] = '';
	$data_insert ['img'] = $row_video['imagename'];
	$data_insert ['internalpath'] = $internalpath;
	$data_insert ['comm'] = 1;
	$data_insert ['status'] = $row_video['viewable'];
	$data_insert ['addtime'] = $addtime;

	try {
		$stmt = $db->prepare ( $sql_video );
		foreach ( array_keys ( $data_insert ) as $key ) {
			$stmt->bindParam ( ':' . $key, $data_insert [$key], PDO::PARAM_STR, strlen ( $data_insert [$key] ) );
		}
		$stmt->execute ();
		$id = $db->lastInsertId ();
	} catch ( PDOException $e ) {
		echo '<pre>';
		print_r ( $e );
		echo '</pre>';
		die ();
	}
	if($id>0) {
		$stmt = $db->prepare ( 'INSERT INTO ' . $lang_fix . '_' . $module_data . '_hit (`cid`, `view`, `liked`, `unlike`, `broken`) VALUES
					(' . $id . ',
					' . $row_video['counter'] . ',
					0,
					0,
					0
					 )' );
		$stmt->execute ();
	}
}
print_r('<h3>Thực hiện xong</h3>');
function nv_get_plaintext($string, $keep_image = false, $keep_link = false)
{
	// Get image tags
	if ($keep_image) {
		if (preg_match_all("/\<img[^\>]*src=\"([^\"]*)\"[^\>]*\>/is", $string, $match)) {
			foreach ($match[0] as $key => $_m) {
				$textimg = '';
				if (strpos($match[1][$key], 'data:image/png;base64') === false) {
					$textimg = " " . $match[1][$key];
				}
				if (preg_match_all("/\<img[^\>]*alt=\"([^\"]+)\"[^\>]*\>/is", $_m, $m_alt)) {
					$textimg .= " " . $m_alt[1][0];
				}
				$string = str_replace($_m, $textimg, $string);
			}
		}
	}

	// Get link tags
	if ($keep_link) {
		if (preg_match_all("/\<a[^\>]*href=\"([^\"]+)\"[^\>]*\>(.*)\<\/a\>/isU", $string, $match)) {
			foreach ($match[0] as $key => $_m) {
				$string = str_replace($_m, $match[1][$key] . " " . $match[2][$key], $string);
			}
		}
	}

	$string = str_replace(' ', ' ', strip_tags($string));
	return preg_replace('/[ ]+/', ' ', $string);
}
