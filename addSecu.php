<?php

define("SECURE_VALIDATOR_VARIABLE", "secu");
define("SECURE_VALIDATOR_FUNCTION", "makeSecureChar");
define("SECURE_VALIDATOR_USE_FUNCTION", SECURE_VALIDATOR_VARIABLE . "." .SECURE_VALIDATOR_FUNCTION);
define("DECLARE_SECURE_VALIDATOR", "SecureValidator " . SECURE_VALIDATOR_VARIABLE . " = new SecureValidator();");
define("DECLARE_IMPORT_PATTERN", "/(mpackage\.share\.SecureValidator|mpackage\.share\.\*)/");
define("IMPORT_SECURE_VALIDATOR", "<%@page import=\"mpackage.share.SecureValidator\"%>");
define("REPLACEMENT", SECURE_VALIDATOR_USE_FUNCTION . "($1$2$3)$4");
define("EOL", "\n");

/**
 * Does the provided string end with a specific substring? Case sensitive.
 * @param string $str string to search
 * @param string $sub substring to look for in $str
 * @return boolean true iff $str ends with $sub.
 * @todo rename to ends_with for consistency
 */
function endsWith( $str, $sub ) {
   return ( substr( $str, 0 - strlen( $sub ) ) === $sub );
}
/**
 * ÁöÁ¤ÇÑ Æú´õÀÇ ¹Ø¿¡ ¸ğµç ÆÄÀÏÀ» °Ë»öÇÏ±â À§ÇÑ ÇÔ¼ö
 * @param string $start_dir Æú´õ¸í
 * @param array $file_types Æ¯Á¤ È®ÀåÀÚ¸¦ ÁöÁ¤
 * @return array 
 */
function dir_recursive($start_dir, $file_types = array()) {
	$files = array();
	$start_dir = str_replace("\\", "/", $start_dir);    // canonicalize

	if (is_dir($start_dir)) {
		$fh = opendir($start_dir);

		while (($file = readdir($fh)) !== false) {
			if (strcmp($file, '.')==0 || strcmp($file, '..')==0) continue;

			$filepath = $start_dir . '/' . $file;
			if ( is_dir($filepath) ) {
				$files = array_merge($files, dir_recursive($filepath, $file_types));
			} else {
				if (count($file_types) == 0) {
					array_push($files, $filepath);
				} else {
					foreach ($file_types as $file_type) {
						if (endsWith($file, $file_type)) {
							array_push($files, $filepath);
						}
					}
				}
			}
		}
		closedir($fh);
	} else {
		$files = false;
	}
	return $files;
}
/**
 * °Ë»öÇÑ ÆÄÀÏÀÇ ¹è¿­À» Á»´õ ÀÌ¿ëÇÏ±â ½±°Ô pathinfoÀÇ °á°ú°ª°ú 
 * create_folder(¸¸µé¾î¾ß ÇÒ Æú´õ), create_file(¸¸µé¾î¾ß ÇÒ ÆÄÀÏ)ÀÇ Á¤º¸¸¦
 * ³Ö¾î¼­ °¡Á®¿À±â À§ÇÑ ÇÔ¼ö
 * @param array $files °Ë»öÇÑ ÆÄÀÏÀÇ ¹®ÀÚ ¹è¿­
 * @param string $target_dir ½ÃÅ¥¾î ÄÚµùÀ» À§ÇØ ÁöÁ¤ÇÑ Æú´õ¸í
 * @return array
 */
function change_pathinfo($files, $target_dir){
	$result = array();
	foreach($files as $v){
		$pathinfo = pathinfo($v);
		$pathinfo['base'] = $v;
		$dirname = $pathinfo['dirname'];
		$explode_target_dir = explode($target_dir, $dirname);
		$pathinfo['create_folder'] = $explode_target_dir[1];
		$pathinfo['create_file'] = $explode_target_dir[1] . "/" . $pathinfo['basename'];
		$result[] = $pathinfo;
	}
	return $result;
}
/**
 * ÆÄ¶ó¹ÌÅÍ·Î ¹ŞÀº °æ·Î¸¦ ¹ÙÅÁÀ¸·Î ÆÄÀÏÀÇ ³»¿ëÀ» ÀĞ¾îµé¿© ½ÃÅ¥¾î ÄÚµù¿¡ ÇÊ¿äÇÑ ³»¿ëÀ» Àû¿ë ÇÏ±â À§ÇÑ ÇÔ¼ö
 * @param array $file ÆÄÀÏÀ» ÀĞ¾îµéÀÏ °æ·Î
 * @return object (obj->files : °á°ú°ªÀÌ µé¾îÀÖ´Ù.)
 */
function add_secu_request_getParameter($file){
	// ÆÄÀÏÀ» ¹è¿­ ÇüÅÂ·Î ÀĞ¾î µéÀÎ´Ù.
	$file = @file($file);

	$result = new stdClass();

	$is_declared_secu = false;

	// ½ÃÅ¥¾î ÄÚµùÀÌ ÇÊ¿äÇÑÁö ÀúÀåÇÏ±â À§ÇÑ ÇÃ·¡±× °ª
	$is_need_declare_secu = false;
	
	// mpackage.share.SecureValidator ¶Ç´Â mpackage.share.* °¡ ¼±¾ğµÇ¾î ÀÖÁö¸¦ ÀúÀåÇÏ±â À§ÇÑ ÇÃ·¡±× °ª
	$is_declared_import_secu = false;

	// Ã¹¹øÂ°ÀÇ <%°¡ ¸î¹øÂ° ¶óÀÎ¿¡ ÀÖ´ÂÁö ÀúÀåÇÏ±â À§ÇÑ º¯¼ö
	$first_declare_jsp = -1;

	// °¡Àå ³ªÁß¿¡ ¼±¾ğ ÇÑ <%@°¡ ¸î¹øÂ° ¶óÀÎ¿¡ ÀÖÂ¢ ÀúÀåÇÏ±â À§ÇÑ º¯¼ö
	$last_declare_jspAt = 0;

	// °Ë»öÇÑ º¯¼ö¸¦ Àá½Ã ÀúÀåÇÏ±â À§ÇÑ º¯¼ö
	$saved_variable_name = "";

	$count = count($file);

	// ÇÑ ¶óÀÎ¾¿ °Ë»öÇÏ±â À§ÇÑ foreach¹®
	foreach($file as $kk => $vv){

		// Àá½Ã ÀúÀåÇÑ º¯¼ö¿¡ °ªÀÌ ÀÖÀ» °æ¿ì
		if($saved_variable_name != ""){
			// ¼¼¹ÌÄİ·ĞÀÌ(;)ÀÌ ¾øÀ» °æ¿ì
			if(!preg_match('/;/', $file[$kk])){
				// ´ÙÀ½ ¶óÀÎ
				continue;
			} else {
				// ¼¼¹ÌÄİ·ĞÀÌ(;)À» ¹ß°ß

				// ´ÙÀ½¶óÀÎÀÇ ÁÙÀ» ¸ÂÃß¾î ÁÖ±â À§ÇÑ¿© °ø¹éÀ» °¡Á®¿È
				preg_match('/^\s*/', $file[$kk], $space_matches);

				// ´ÙÀ½¶óÀÎ¿¡ ½ÃÅ¥¾îÄÚµùÀ» Àû¿ë
				$file[$kk] = $file[$kk] . $space_matches[0] . $saved_variable_name . " = " . SECURE_VALIDATOR_USE_FUNCTION . "(" . $saved_variable_name . ");" . EOL;

				// Àá½ÃÀúÀåÇÑ °ªÀ» ÃÊ±âÈ­
				$saved_variable_name = "";
			}
		}

		// Ã¹¹øÂ°ÀÇ <%À» °Ë»ö
		if($first_declare_jsp == -1 && preg_match('/<%(?!@|\!|=|-)/', $vv)){
			$first_declare_jsp = $kk;
		}

		// mpackage.share.SecureValidator ¶Ç´Â mpackage.share.* ¸¦ °Ë»ö
		if(preg_match(DECLARE_IMPORT_PATTERN, $vv)){
			$is_declared_import_secu = true;
		}

		// mpackage.share.SecureValidator ¶Ç´Â mpackage.share.* ¸¦ °Ë»ö
		if(strpos($vv, DECLARE_SECURE_VALIDATOR)){
			$is_declared_secu = true;
		}

		// °¡Àå ³ªÁß¿¡ ¼±¾ğ ÇÑ <%@¸¦ °Ë»ö
		if(preg_match('/<%@/', $vv)){
			$last_declare_jspAt = $kk;
		}

		// º¯¼ö ¸í°ú request.getParameter, getRequestURI, getQueryString¸¦ °Ë»ö
		if (preg_match('/([°¡-ÆRa-zA-Z0-9\_]+)\s*\=\s*.*request\.(getParameter\(\s*\"(.*)\"\s*\)|getRequestURI\(\s*\)|getQueryString\(\s*\))/', $vv, $matches)) {

			// °Ë»öµÈ º¯¼ö ¸í
			$variable_name = $matches[1];

			for ($j=$kk+1; $j<$count; $j++) {
				
				if(strpos($file[$j], SECURE_VALIDATOR_USE_FUNCTION . '(' . $variable_name . ')')) continue;

				if(preg_match('/(\/\/)*\s*System\.out\.print(ln)\(.*' . $variable_name . '.*\)\s*;/', $file[$j])) {
					$file[$j] = preg_replace('/(\/\/)*\s*System\.out\.print(ln)\(.*' . $variable_name . '.*\)\s*;/', '', $file[$j]);
					continue;
				}

				if(preg_match('/\<\%\s*=\s*' . $variable_name . '\s*\%\>/', $file[$j])) {
					$file[$j] = preg_replace('/(\<\%\s*=\s*)(' . $variable_name . ')(\s*\%\>)/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
				}
				if(preg_match('/out.print\s*[^;]*' . $variable_name . '[)+;\s]+/', $file[$j])) {

					// ÁÖ¼®(//)ÀÎ °æ¿ì »èÁ¦
					if(preg_match('/(\/\/)\s*out\.print(ln)*\s*\(.*' . $variable_name . '.*\)\s*;/', $file[$j])) {
						$file[$j] = preg_replace('/(\/\/)\s*out\.print(ln)*\s*\(.*' . $variable_name . '.*\)\s*;/', '', $file[$j]);
						continue;
					}

					// out.print(${variable_name}) ÇüÅÂ
					if(strpos($file[$j], '"')){

						if(preg_match('/\"\s*\+\s*' . $variable_name .'/', $file[$j])) {
							$file[$j] = preg_replace('/(\"\s*\+\s*)(' . $variable_name .')/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)', $file[$j]);
						} else {
							$file[$j] = preg_replace('/(\(\s*)(' . $variable_name .')(\s*\+)/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
						}
					} else {
						$file[$j] = preg_replace('/(out.print(?:ln)*\(\s*[^"]*)(' . $variable_name .')([^"]*\s*\))/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
					}

					// out.print(ln)À» <%= %>·Î º¯È¯
					$file[$j] = preg_replace('/(?:out\.print(?:ln)*\s*\()(.+)([\+|\);])/', '%><%=$1%><%', $file[$j]);
				}
			}

			// ½ÃÅ¥¾î ÄÚµùÀÌ ÇÊ¿äÇÑ ÆÄÀÏÀÓÀ» ³ªÅ¸³»±â À§ÇÏ¿© ÇÃ·¡±× °ª true·Î º¯°æ
			$is_need_declare_secu = true;
		}
	}

	// SECURE_VALIDATOR¸¦ ÀÓÆ÷Æ® ÇÔ
	if($is_need_declare_secu && !$is_declared_import_secu){
		$file[0] = IMPORT_SECURE_VALIDATOR . EOL . $file[0];
	}

	// ½ÃÅ¥¾î ÄÚµùÀÌ ÇÊ¿äÇÑ ÆÄÀÏ
	if($is_need_declare_secu){

		// DECLARE_SECURE_VALIDATOR °¡ ¼±¾ğµÇ¾î ÀÖÁö ¾ÊÀ¸¸é Ãß°¡
		if(!$is_declared_secu){
			// Ã¹¹øÂ° <% °¡ ÀÖ´Â ¶óÀÎ
			if($first_declare_jsp != -1) {
				$pos = strrpos($file[$first_declare_jsp], "<%") + 2;
				$front_str = substr($file[$first_declare_jsp], 0, $pos);
				$rear_str = substr($file[$first_declare_jsp], $pos);

				// Ã¹¹øÂ° <% °¡ ÀÖ´Â ¶óÀÎÀÇ ´ÙÀ½ ÁÙ¿¡ SECURE_VALIDATOR¸¦ ¼±¾ğ ÇÔ.
				$file[$first_declare_jsp] = $front_str . EOL . "\t" . DECLARE_SECURE_VALIDATOR . EOL;
				if(strlen(trim($rear_str)) > 1){
					$file[$first_declare_jsp] .= $rear_str;
				}
			// ½ÃÅ¥¾î ÄÚµùÀÌ ÇÊ¿äÇÑ ÆÄÀÏÀÌÁö¸¸ <%°¡ ¾ø´Â °æ¿ì
			} else {
				$file[$last_declare_jspAt] = $file[$last_declare_jspAt] . "<% " . DECLARE_SECURE_VALIDATOR . " %>" . EOL;
			}
		}
		// ½ÃÅ¥¾î ÄÚµùÀÌ Àû¿ëµÈ ¼Ò½ºÄÚµå¸¦ ÀúÀå
		$result->files = $file;
	}
	return $result;
}
/**
 * ÆÄ¶ó¹ÌÅÍ·Î ¹ŞÀº Æú´õ¸¦ ¸¸µé±â À§ÇÑ ÇÔ¼ö
 * @param string $path °æ·Î
 */
function create_folder($path){
	$dir = BASE_PATH . $path;
	if(!is_dir($dir)){
		@mkdir($dir, 0777, true);
	}
}
/**
 * ÆÄ¶ó¹ÌÅÍ·Î ¹ŞÀº °ªÀ» ÆÄÀÏ·Î ³»º¸³»±â À§ÇÑ ÇÔ¼ö
 * @param object <br>
 * object->create_file (ÆÄÀÏÀÇ °æ·Î)
 * object->files (ÆÄÀÏÀÇ ³»¿ë)
 */
function flush_output($output){
	$fp = fopen($output->create_file, 'w');
	foreach($output->files as $v){
		@fwrite($fp, $v);
	}
	@fclose($fp);
}

// backup¸ğµåÀÎÁö ¾Æ´ÑÁö ÀúÀåÇÏ±â À§ÇÑ ÇÃ·¡±× º¯¼ö
$is_backup_mode = true;

// ÆÄ¶ó¹ÌÅÍ °ªÀÌ ÇÏ³ªµµ ¾ø´Â °æ¿ì ¿¡·¯¸¦ ³¿
if ($_SERVER["argc"] == 1) {
	echo "¿¡·¯ : ´ë»óÆú´õ°¡ ¾ø½À´Ï´Ù." . EOL;
	echo "example : resources\php.exe addSecu.php C:\ui" . EOL;
	exit(1);
}

// µÎ¹øÂ°ÀÇ ÆÄ¶ó¹ÌÅÍ °ªÀÌ 1ÀÎ °æ¿ì output ¸ğµå
// µÎ¹øÂ°ÀÇ ÆÄ¶ó¹ÌÅÍ °ªÀÌ 1ÀÌ ¾Æ´Ñ °æ¿ì backup ¸ğµå
if (isset($_SERVER["argv"][2]) && $_SERVER["argv"][2] == "1"){
	define("BASE_PATH", "output");
	echo "´ë»óÆú´õÀÇ ÆÄÀÏ¿¡ ´ëÇÏ¿© ½ÃÅ¥¾î ÄÚµùÀ» Àû¿ëÇÏÁö ¾ÊÀ¸¸ç " . BASE_PATH . "¹Ø¿¡ ½ÃÅ¥¾î ÄÚµùÀ» Àû¿ëÇÑ ÄÚµå¸¦ ÀúÀåÇÕ´Ï´Ù." . EOL;
	$is_backup_mode = false;
} else {
	define("BASE_PATH", "backup");
	echo "´ë»óÆú´õÀÇ ÆÄÀÏ¿¡ ´ëÇÏ¿© ½ÃÅ¥¾î ÄÚµùÀ» Àû¿ëÇÏ°í " . BASE_PATH . "¹Ø¿¡ º¯°æÀüÀÇ ÆÄÀÏÀ» ¹é¾÷ÇÕ´Ï´Ù." . EOL;
}

// Ã¹¹øÂ° ÆÄ¶ó¹ÌÅÍ °ªÀ» ÀúÀå
$target_dir = $_SERVER["argv"][1];

// À©µµ¿ìÀÇ ¿ª½½·¯½¬¸¦ ½½·¯½¬ °ªÀ¸·Î º¯°æ
$target_dir = str_replace("\\", "/", $target_dir);

// ´ë»ó ÆÄÀÏÀ» ¹è¿­°ªÀ¸·Î °¡Á®¿È
$target_files = dir_recursive($target_dir);

// ÆÄ¶ó¹ÌÅÍ °ªÀÌ Æú´õ°¡ ¾Æ´Ï°í ÆÄÀÏÀÏ °æ¿ìÀÇ Ã³¸®
if(!$target_files){
	// ÆÄÀÏ °æ·Î¸¦ ¹è¿­¿¡ °ªÀ» ³ÖÀ½
	$target_files[] = $target_dir;

	// ÆÄÀÏ¸íÀ» Á¦¿ÜÇÑ °ª¸¸ ÃßÃâÇØ¼­ $target_dir¿¡ ³ÖÀ½
	$explode_slash_target_dir = explode("/", $target_dir);
	$target_file = array_pop($explode_slash_target_dir);
	$target_dir = implode("/", $explode_slash_target_dir);
}

// ´ë»ó ÆÄÀÏÀ» »ç¿ëÇÏ±â ½±°Ô pathinfo°ªÀ» ³Ö¾î¼­ °¡Á®¿È
$pathinfo_files = change_pathinfo($target_files, $target_dir);

// BASE_PATH Æú´õ°¡ ¾ø´Â °æ¿ì »ı¼ºÇÔ
if(!is_dir(BASE_PATH)){
	@mkdir(BASE_PATH, 0777);
}

// ´ë»ó ÆÄÀÏÀÇ °¹¼ö¸¦ °¡Á®¿È
$count = count($pathinfo_files);

foreach($pathinfo_files as $k => $v){
	// ´ë»ó ÆÄÀÏÀÇ Ç® °æ·Î
	$base = $v["base"];

	// ¸¸µé¾î¾ß ÇÒ Æú´õ¸¦ Á¤º¸¸¦ °¡Á®¿Í ¾øÀ¸¸é »ı¼º ÇÔ
	$create_folder = $v["create_folder"];
	create_folder($create_folder);
	
	// ½ÃÅ¥¾î ÄÚµùÀ» Àû¿ëÇÑ °á°ú °ªÀ» °¡Á®¿È
	$output = add_secu_request_getParameter($base);

	// °á°ú °ªÀÌ ¾øÀ» °æ¿ì continue ÇÔ
	if(!isset($output->files)) continue;

	// ¸¸µé¾î¾ß ÇÒ ÆÄÀÏÀ» °¡Á®¿È
	$create_file = $v["create_file"];

	// ¸ğµå¿¡ µû¶ó¼­ Ã³¸®¸¦ ´Ş¸® ÇÔ
	if($is_backup_mode){
		//file backup
		@copy($base, BASE_PATH . $create_file);
		$output->create_file = $base;
	} else {
		$output->create_file = BASE_PATH . $create_file;
	}

	// ÆÄÀÏÀ» ³»º¸³¿
	flush_output($output);

	// ÁøÇàµÈ ÆÛ¼¾Æ®¸¦ °è»ê
	$percent = floor((intval($k)/$count)*100);

	// ¿Ï·áµÈ ÆÄÀÏ¸í (ÁøÇàµÈ ÆÛ¼¾Æ®)¸¦ Ãâ·Â
	echo $output->create_file . " done. ($percent% ÁøÇà)". EOL;
}

// ´ë»óÆú´õ¸¦ Å½»ö±â¿¡¼­ ¿­±â
exec("start $target_dir");

// BASE_PATH¸¦ Å½»ö±â¿¡¼­ ¿­±â
exec("start " . BASE_PATH);

?>