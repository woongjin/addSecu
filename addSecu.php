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
 * 지정한 폴더의 밑에 모든 파일을 검색하기 위한 함수
 * @param string $start_dir 폴더명
 * @param array $file_types 특정 확장자를 지정
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
 * 검색한 파일의 배열을 좀더 이용하기 쉽게 pathinfo의 결과값과 
 * create_folder(만들어야 할 폴더), create_file(만들어야 할 파일)의 정보를
 * 넣어서 가져오기 위한 함수
 * @param array $files 검색한 파일의 문자 배열
 * @param string $target_dir 시큐어 코딩을 위해 지정한 폴더명
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
 * 파라미터로 받은 경로를 바탕으로 파일의 내용을 읽어들여 시큐어 코딩에 필요한 내용을 적용 하기 위한 함수
 * @param array $file 파일을 읽어들일 경로
 * @return object (obj->files : 결과값이 들어있다.)
 */
function add_secu_request_getParameter($file){
	// 파일을 배열 형태로 읽어 들인다.
	$file = @file($file);
	$result = new stdClass();
	
	// 시큐어 코딩이 필요한지 저장하기 위한 플래그 값
	$is_need_declare_secu = false;
	
	// mpackage.share.SecureValidator 또는 mpackage.share.* 가 선언되어 있지를 저장하기 위한 플래그 값
	$is_declare_import_secu = false;

	// 첫번째의 <%가 몇번째 라인에 있는지 저장하기 위한 변수
	$first_declare_jsp = -1;

	// 가장 나중에 선언 한 <%@가 몇번째 라인에 있짖 저장하기 위한 변수
	$last_declare_jspAt = 0;

	// 검색한 변수를 잠시 저장하기 위한 변수
	$saved_variable_name = "";

	// 한 라인씩 검색하기 위한 foreach문
	foreach($file as $kk => $vv){

		// 잠시 저장한 변수에 값이 있을 경우
		if($saved_variable_name != ""){
			// 세미콜론이(;)이 없을 경우
			if(!preg_match('/;/', $file[$kk])){
				// 다음 라인
				continue;
			} else {
				// 세미콜론이(;)을 발견

				// 다음라인의 줄을 맞추어 주기 위한여 공백을 가져옴
				preg_match('/^\s*/', $file[$kk], $space_matches);

				// 다음라인에 시큐어코딩을 적용
				$file[$kk] = $file[$kk] . $space_matches[0] . $saved_variable_name . " = " . SECURE_VALIDATOR_USE_FUNCTION . "(" . $saved_variable_name . ");" . EOL;

				// 잠시저장한 값을 초기화
				$saved_variable_name = "";
			}
		}

		// 첫번째의 <%을 검색
		if($first_declare_jsp == -1 && preg_match('/<%(?!@|\!|=|-)/', $vv)){
			$first_declare_jsp = $kk;
		}

		// mpackage.share.SecureValidator 또는 mpackage.share.* 를 검색
		if(preg_match(DECLARE_IMPORT_PATTERN, $vv)){
			$is_declare_import_secu = true;
		}

		// 가장 나중에 선언 한 <%@를 검색
		if(preg_match('/<%@/', $vv)){
			$last_declare_jspAt = $kk;
		}

		// 변수 명과 request.getParameter, getRequestURI, getQueryString를 검색
		if (preg_match('/([가-힣a-zA-Z0-9\_]+)\s*\=\s*.*request\.(getParameter\(\s*\"(.*)\"\s*\)|getRequestURI\(\s*\)|getQueryString\(\s*\))/', $vv, $matches)) {

			// secu.makeSecureChar(..) 가 이미 적용되어 있다면 continue함
			if(preg_match('/'. SECURE_VALIDATOR_USE_FUNCTION .'/', $vv)) continue;

			// 검색된 변수 명
			$variable_name = $matches[1];

			// comment(//)일 경우 continue함
			if(preg_match('/^\s*\/\/.*?' . $variable_name . '/', $file[$kk])) continue;

			// request.getParameter(..)==null?... 형태 => secu.makeSecureChar(request.getParameter(..)==null?...); 변경
			$r1 = preg_replace('/(request\.getParameter\(\s*\".*\"\s*\))(\s*[!=]=\s*null\s*\?\s*)(\1\s*:\s*.+|.+\s*:\s*\1)(.*;)/', REPLACEMENT, $vv);

			// request.getParameter(..)==null?... 경우가 아님
			if($file[$kk] == $r1) {

				// <%=request.getParameter(..)%> => <%=secu.makeSecureChar(request.getParameter(..))%> 변경
				$r2 = preg_replace('/(<%=)(request\.getParameter\(\s*\".*\"\s*\))(\s*%>)/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $vv);

				// <%=request.getParameter(..)%> 경우가 아님
				if($file[$kk] == $r2) {				

					// ;(세미콜론)이 없을 경우
					if(!preg_match('/;/', $file[$kk])){
						// 변수를 잠시 저장
						$saved_variable_name = $variable_name;
						continue;
					}

					// 다음라인의 줄을 맞추어 주기 위한여 공백을 가져옴
					preg_match('/^\s*/', $file[$kk], $space_matches);

					// {변수명} = request.getParameter(..);의 다음 라인에
					// {변수명} = secu.makeSecureChar({변수명}); 을 추가
					$file[$kk] = $file[$kk] . $space_matches[0] . $variable_name . " = " . SECURE_VALIDATOR_USE_FUNCTION . "(" . $variable_name . ");" . EOL;
				} else {
					// $r2를 적용
					$file[$kk] = $r2;
				}
			} else {
				// $r1를 적용
				$file[$kk] = $r1;
			}

			// 시큐어 코딩이 필요한 파일임을 나타내기 위하여 플래그 값 true로 변경
			$is_need_declare_secu = true;
		}
		//WIN to UNIX (\r\n -> \n)
		$file[$kk] = preg_replace("/\r\n/", "\n", $file[$kk]);
	}

	// SECURE_VALIDATOR를 임포트 함
	if($is_need_declare_secu && !$is_declare_import_secu){
		$file[0] = IMPORT_SECURE_VALIDATOR . EOL . $file[0];
	}

	// 시큐어 코딩이 필요한 파일
	if($is_need_declare_secu){

		// 첫번째 <% 가 있는 라인
		if($first_declare_jsp != -1) {
			$pos = strrpos($file[$first_declare_jsp], "<%") + 2;
			$front_str = substr($file[$first_declare_jsp], 0, $pos);
			$rear_str = substr($file[$first_declare_jsp], $pos);

			// 첫번째 <% 가 있는 라인의 다음 줄에 SECURE_VALIDATOR를 선언 함.
			$file[$first_declare_jsp] = $front_str . EOL . "\t" . DECLARE_SECURE_VALIDATOR . EOL;
			if(strlen(trim($rear_str)) > 1){
				$file[$first_declare_jsp] .= $rear_str;
			}
		// 시큐어 코딩이 필요한 파일이지만 <%가 없는 경우
		} else {
			$file[$last_declare_jspAt] = $file[$last_declare_jspAt] . "<% " . DECLARE_SECURE_VALIDATOR . " %>" . EOL;
		}
		// 시큐어 코딩이 적용된 소스코드를 저장
		$result->files = $file;
	}
	return $result;
}
/**
 * 파라미터로 받은 폴더를 만들기 위한 함수
 * @param string $path 경로
 */
function create_folder($path){
	$dir = BASE_PATH . $path;
	if(!is_dir($dir)){
		@mkdir($dir, 0777, true);
	}
}
/**
 * 파라미터로 받은 값을 파일로 내보내기 위한 함수
 * @param object <br>
 * object->create_file (파일의 경로)
 * object->files (파일의 내용)
 */
function flush_output($output){
	$fp = fopen($output->create_file, 'w');
	foreach($output->files as $v){
		@fwrite($fp, $v);
	}
	@fclose($fp);
}

// backup모드인지 아닌지 저장하기 위한 플래그 변수
$is_backup_mode = true;

// 파라미터 값이 하나도 없는 경우 에러를 냄
if ($_SERVER["argc"] == 1) {
	echo "에러 : 대상폴더가 없습니다." . EOL;
	echo "example : resources\php.exe addSecu.php C:\ui" . EOL;
	exit(1);
}

// 두번째의 파라미터 값이 1인 경우 output 모드
// 두번째의 파라미터 값이 1이 아닌 경우 backup 모드
if (isset($_SERVER["argv"][2]) && $_SERVER["argv"][2] == "1"){
	define("BASE_PATH", "output");
	echo "대상폴더의 파일에 대하여 시큐어 코딩을 적용하지 않으며 " . BASE_PATH . "밑에 시큐어 코딩을 적용한 코드를 저장합니다." . EOL;
	$is_backup_mode = false;
} else {
	define("BASE_PATH", "backup");
	echo "대상폴더의 파일에 대하여 시큐어 코딩을 적용하고 " . BASE_PATH . "밑에 변경전의 파일을 백업합니다." . EOL;
}

// 첫번째 파라미터 값을 저장
$target_dir = $_SERVER["argv"][1];

// 윈도우의 역슬러쉬를 슬러쉬 값으로 변경
$target_dir = str_replace("\\", "/", $target_dir);

// 대상 파일을 배열값으로 가져옴
$target_files = dir_recursive($target_dir);

// 대상 파일을 사용하기 쉽게 pathinfo값을 넣어서 가져옴
$pathinfo_files = change_pathinfo($target_files, $target_dir);

// BASE_PATH 폴더가 없는 경우 생성함
if(!is_dir(BASE_PATH)){
	@mkdir(BASE_PATH, 0777);
}

// 대상 파일의 갯수를 가져옴
$count = count($pathinfo_files);

foreach($pathinfo_files as $k => $v){
	// 대상 파일의 풀 경로
	$base = $v["base"];
	
	// 만들어야 할 폴더를 정보를 가져와 없으면 생성 함
	$create_folder = $v["create_folder"];
	create_folder($create_folder);
	
	// 시큐어 코딩을 적용한 결과 값을 가져옴
	$output = add_secu_request_getParameter($base);

	// 결과 값이 없을 경우 continue 함
	if(!isset($output->files)) continue;

	// 만들어야 할 파일을 가져옴
	$create_file = $v["create_file"];

	// 모드에 따라서 처리를 달리 함
	if($is_backup_mode){
		//file backup
		@copy($base, BASE_PATH . $create_file);
		$output->create_file = $base;
	} else {
		$output->create_file = BASE_PATH . $create_file;
	}

	// 파일을 내보냄
	flush_output($output);

	// 진행된 퍼센트를 계산
	$percent = floor((intval($k)/$count)*100);

	// 완료된 파일명 (진행된 퍼센트)를 출력
	echo $output->create_file . " done. ($percent% 진행)". EOL;
}

// 대상폴더를 탐색기에서 열기
exec("start $target_dir");

// BASE_PATH를 탐색기에서 열기
exec("start " . BASE_PATH);

?>