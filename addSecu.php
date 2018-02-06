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
 * ������ ������ �ؿ� ��� ������ �˻��ϱ� ���� �Լ�
 * @param string $start_dir ������
 * @param array $file_types Ư�� Ȯ���ڸ� ����
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
 * �˻��� ������ �迭�� ���� �̿��ϱ� ���� pathinfo�� ������� 
 * create_folder(������ �� ����), create_file(������ �� ����)�� ������
 * �־ �������� ���� �Լ�
 * @param array $files �˻��� ������ ���� �迭
 * @param string $target_dir ��ť�� �ڵ��� ���� ������ ������
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
 * �Ķ���ͷ� ���� ��θ� �������� ������ ������ �о�鿩 ��ť�� �ڵ��� �ʿ��� ������ ���� �ϱ� ���� �Լ�
 * @param array $file ������ �о���� ���
 * @return object (obj->files : ������� ����ִ�.)
 */
function add_secu_request_getParameter($file){
	// ������ �迭 ���·� �о� ���δ�.
	$file = @file($file);

	$result = new stdClass();

	$is_declared_secu = false;

	// ��ť�� �ڵ��� �ʿ����� �����ϱ� ���� �÷��� ��
	$is_need_declare_secu = false;
	
	// mpackage.share.SecureValidator �Ǵ� mpackage.share.* �� ����Ǿ� ������ �����ϱ� ���� �÷��� ��
	$is_declared_import_secu = false;

	// ù��°�� <%�� ���° ���ο� �ִ��� �����ϱ� ���� ����
	$first_declare_jsp = -1;

	// ���� ���߿� ���� �� <%@�� ���° ���ο� ��¢ �����ϱ� ���� ����
	$last_declare_jspAt = 0;

	// �˻��� ������ ��� �����ϱ� ���� ����
	$saved_variable_name = "";

	$count = count($file);

	// �� ���ξ� �˻��ϱ� ���� foreach��
	foreach($file as $kk => $vv){

		// ��� ������ ������ ���� ���� ���
		if($saved_variable_name != ""){
			// �����ݷ���(;)�� ���� ���
			if(!preg_match('/;/', $file[$kk])){
				// ���� ����
				continue;
			} else {
				// �����ݷ���(;)�� �߰�

				// ���������� ���� ���߾� �ֱ� ���ѿ� ������ ������
				preg_match('/^\s*/', $file[$kk], $space_matches);

				// �������ο� ��ť���ڵ��� ����
				$file[$kk] = $file[$kk] . $space_matches[0] . $saved_variable_name . " = " . SECURE_VALIDATOR_USE_FUNCTION . "(" . $saved_variable_name . ");" . EOL;

				// ��������� ���� �ʱ�ȭ
				$saved_variable_name = "";
			}
		}

		// ù��°�� <%�� �˻�
		if($first_declare_jsp == -1 && preg_match('/<%(?!@|\!|=|-)/', $vv)){
			$first_declare_jsp = $kk;
		}

		// mpackage.share.SecureValidator �Ǵ� mpackage.share.* �� �˻�
		if(preg_match(DECLARE_IMPORT_PATTERN, $vv)){
			$is_declared_import_secu = true;
		}

		// mpackage.share.SecureValidator �Ǵ� mpackage.share.* �� �˻�
		if(strpos($vv, DECLARE_SECURE_VALIDATOR)){
			$is_declared_secu = true;
		}

		// ���� ���߿� ���� �� <%@�� �˻�
		if(preg_match('/<%@/', $vv)){
			$last_declare_jspAt = $kk;
		}

		// ���� ��� request.getParameter, getRequestURI, getQueryString�� �˻�
		if (preg_match('/([��-�Ra-zA-Z0-9\_]+)\s*\=\s*.*request\.(getParameter\(\s*\"(.*)\"\s*\)|getRequestURI\(\s*\)|getQueryString\(\s*\))/', $vv, $matches)) {

			// �˻��� ���� ��
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

					// �ּ�(//)�� ��� ����
					if(preg_match('/(\/\/)\s*out\.print(ln)*\s*\(.*' . $variable_name . '.*\)\s*;/', $file[$j])) {
						$file[$j] = preg_replace('/(\/\/)\s*out\.print(ln)*\s*\(.*' . $variable_name . '.*\)\s*;/', '', $file[$j]);
						continue;
					}

					// out.print(${variable_name}) ����
					if(strpos($file[$j], '"')){

						if(preg_match('/\"\s*\+\s*' . $variable_name .'/', $file[$j])) {
							$file[$j] = preg_replace('/(\"\s*\+\s*)(' . $variable_name .')/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)', $file[$j]);
						} else {
							$file[$j] = preg_replace('/(\(\s*)(' . $variable_name .')(\s*\+)/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
						}
					} else {
						$file[$j] = preg_replace('/(out.print(?:ln)*\(\s*[^"]*)(' . $variable_name .')([^"]*\s*\))/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
					}

					// out.print(ln)�� <%= %>�� ��ȯ
					$file[$j] = preg_replace('/(?:out\.print(?:ln)*\s*\()(.+)([\+|\);])/', '%><%=$1%><%', $file[$j]);
				}
			}

			// ��ť�� �ڵ��� �ʿ��� �������� ��Ÿ���� ���Ͽ� �÷��� �� true�� ����
			$is_need_declare_secu = true;
		}
	}

	// SECURE_VALIDATOR�� ����Ʈ ��
	if($is_need_declare_secu && !$is_declared_import_secu){
		$file[0] = IMPORT_SECURE_VALIDATOR . EOL . $file[0];
	}

	// ��ť�� �ڵ��� �ʿ��� ����
	if($is_need_declare_secu){

		// DECLARE_SECURE_VALIDATOR �� ����Ǿ� ���� ������ �߰�
		if(!$is_declared_secu){
			// ù��° <% �� �ִ� ����
			if($first_declare_jsp != -1) {
				$pos = strrpos($file[$first_declare_jsp], "<%") + 2;
				$front_str = substr($file[$first_declare_jsp], 0, $pos);
				$rear_str = substr($file[$first_declare_jsp], $pos);

				// ù��° <% �� �ִ� ������ ���� �ٿ� SECURE_VALIDATOR�� ���� ��.
				$file[$first_declare_jsp] = $front_str . EOL . "\t" . DECLARE_SECURE_VALIDATOR . EOL;
				if(strlen(trim($rear_str)) > 1){
					$file[$first_declare_jsp] .= $rear_str;
				}
			// ��ť�� �ڵ��� �ʿ��� ���������� <%�� ���� ���
			} else {
				$file[$last_declare_jspAt] = $file[$last_declare_jspAt] . "<% " . DECLARE_SECURE_VALIDATOR . " %>" . EOL;
			}
		}
		// ��ť�� �ڵ��� ����� �ҽ��ڵ带 ����
		$result->files = $file;
	}
	return $result;
}
/**
 * �Ķ���ͷ� ���� ������ ����� ���� �Լ�
 * @param string $path ���
 */
function create_folder($path){
	$dir = BASE_PATH . $path;
	if(!is_dir($dir)){
		@mkdir($dir, 0777, true);
	}
}
/**
 * �Ķ���ͷ� ���� ���� ���Ϸ� �������� ���� �Լ�
 * @param object <br>
 * object->create_file (������ ���)
 * object->files (������ ����)
 */
function flush_output($output){
	$fp = fopen($output->create_file, 'w');
	foreach($output->files as $v){
		@fwrite($fp, $v);
	}
	@fclose($fp);
}

// backup������� �ƴ��� �����ϱ� ���� �÷��� ����
$is_backup_mode = true;

// �Ķ���� ���� �ϳ��� ���� ��� ������ ��
if ($_SERVER["argc"] == 1) {
	echo "���� : ��������� �����ϴ�." . EOL;
	echo "example : resources\php.exe addSecu.php C:\ui" . EOL;
	exit(1);
}

// �ι�°�� �Ķ���� ���� 1�� ��� output ���
// �ι�°�� �Ķ���� ���� 1�� �ƴ� ��� backup ���
if (isset($_SERVER["argv"][2]) && $_SERVER["argv"][2] == "1"){
	define("BASE_PATH", "output");
	echo "��������� ���Ͽ� ���Ͽ� ��ť�� �ڵ��� �������� ������ " . BASE_PATH . "�ؿ� ��ť�� �ڵ��� ������ �ڵ带 �����մϴ�." . EOL;
	$is_backup_mode = false;
} else {
	define("BASE_PATH", "backup");
	echo "��������� ���Ͽ� ���Ͽ� ��ť�� �ڵ��� �����ϰ� " . BASE_PATH . "�ؿ� �������� ������ ����մϴ�." . EOL;
}

// ù��° �Ķ���� ���� ����
$target_dir = $_SERVER["argv"][1];

// �������� ���������� ������ ������ ����
$target_dir = str_replace("\\", "/", $target_dir);

// ��� ������ �迭������ ������
$target_files = dir_recursive($target_dir);

// �Ķ���� ���� ������ �ƴϰ� ������ ����� ó��
if(!$target_files){
	// ���� ��θ� �迭�� ���� ����
	$target_files[] = $target_dir;

	// ���ϸ��� ������ ���� �����ؼ� $target_dir�� ����
	$explode_slash_target_dir = explode("/", $target_dir);
	$target_file = array_pop($explode_slash_target_dir);
	$target_dir = implode("/", $explode_slash_target_dir);
}

// ��� ������ ����ϱ� ���� pathinfo���� �־ ������
$pathinfo_files = change_pathinfo($target_files, $target_dir);

// BASE_PATH ������ ���� ��� ������
if(!is_dir(BASE_PATH)){
	@mkdir(BASE_PATH, 0777);
}

// ��� ������ ������ ������
$count = count($pathinfo_files);

foreach($pathinfo_files as $k => $v){
	// ��� ������ Ǯ ���
	$base = $v["base"];

	// ������ �� ������ ������ ������ ������ ���� ��
	$create_folder = $v["create_folder"];
	create_folder($create_folder);
	
	// ��ť�� �ڵ��� ������ ��� ���� ������
	$output = add_secu_request_getParameter($base);

	// ��� ���� ���� ��� continue ��
	if(!isset($output->files)) continue;

	// ������ �� ������ ������
	$create_file = $v["create_file"];

	// ��忡 ���� ó���� �޸� ��
	if($is_backup_mode){
		//file backup
		@copy($base, BASE_PATH . $create_file);
		$output->create_file = $base;
	} else {
		$output->create_file = BASE_PATH . $create_file;
	}

	// ������ ������
	flush_output($output);

	// ����� �ۼ�Ʈ�� ���
	$percent = floor((intval($k)/$count)*100);

	// �Ϸ�� ���ϸ� (����� �ۼ�Ʈ)�� ���
	echo $output->create_file . " done. ($percent% ����)". EOL;
}

// ��������� Ž���⿡�� ����
exec("start $target_dir");

// BASE_PATH�� Ž���⿡�� ����
exec("start " . BASE_PATH);

?>