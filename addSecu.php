<?php
// 상수선언
define("SECURE_VALIDATOR_CLASS", "AntiXss");
define("SECURE_VALIDATOR_VARIABLE", "ax");
define("SECURE_VALIDATOR_FUNCTION", "clean");
define("SECURE_VALIDATOR_USE_FUNCTION", SECURE_VALIDATOR_VARIABLE . "." .SECURE_VALIDATOR_FUNCTION);
define("DECLARE_SECURE_VALIDATOR", SECURE_VALIDATOR_CLASS . " " . SECURE_VALIDATOR_VARIABLE . " = new " . SECURE_VALIDATOR_CLASS . "();");
define("DECLARE_IMPORT_PATTERN", "/(mpackage\.share\." . SECURE_VALIDATOR_CLASS . "|mpackage\.share\.\*)/");
define("IMPORT_SECURE_VALIDATOR", "<%@page import=\"mpackage.share." . SECURE_VALIDATOR_CLASS . "\"%>");
define("REPLACEMENT", SECURE_VALIDATOR_USE_FUNCTION . "($1$2$3)$4");

/**
 * 첫번째 인자에서 두번째 인자로 끝나는면 참을 돌려주는 함수
 * @param string $str 찾을 대상의 문자
 * @param string $sub 찾을 문자
 * @return boolean 
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
    $start_dir = str_replace("\\", "/", $start_dir);

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
 * 파라미터로 받은 경로를 바탕으로 파일의 내용을 읽여 
 * request.getParameter, getRequestURI, getQueryString를 검색
 * 검색된 변수명을 담아 배열형태로 반환한다.
 * @param array $file 파일을 읽어들일 경로
 * @return array  [varName1, varName2 ...]
 */

function find_varName_getParameter($file){

    // 결과를 담을 배열 변수 선언
    $result = array();

    // 파일을 배열 형태로 읽어 들인다.
    $file = @file($file);

    // 한 라인씩 검색하기 위한 foreach문
    foreach($file as $k => $v){
    
        // 변수 명과 request.getParameter, getRequestURI, getQueryString를 검색
        if (preg_match('/([가-힣a-zA-Z0-9\_]+)\s*\=\s*.*request\.(getParameter\(\s*\"(.*)\"\s*\)|getRequestURI\(\s*\)|getQueryString\(\s*\))/', $v, $matches)) {
            // 검색된 변수 을 배열에 담는다.
            array_push($result, $matches[1]);
        }
    }
    return $result;
}

/**
 * 파라미터로 받은 경로를 바탕으로 파일의 내용을 읽어들여 시큐어 코딩에 필요한 내용을 적용 하기 위한 함수
 * @param array $file 파일을 읽어들일 경로
 * @param array $varNames 검색할 변수명들
 * @return object (obj->files : 결과값이 들어있다.)
 */
function add_secu_request_getParameter($file, $varNames){
    // 파일을 배열 형태로 읽어 들인다.
    $file = @file($file);

    // 결과값을 담을 변수
    $result = new stdClass();

    // SECURE_VALIDATOR_CLASS가 선언되어 있는지 담을 변수
    $is_declared_class = false;

    // 시큐어 코딩이 필요한지 저장하기 위한 플래그 값
    $is_need_declare_secu = false;

    // out.print(ln)형태를 지울 필요가 있는지 담을 변수
    $is_need_remove_out_print = false;
    
    // mpackage.share.SecureValidator 또는 mpackage.share.* 가 선언되어 있지를 저장하기 위한 플래그 값
    $is_declared_import_secu = false;

    // 첫번째의 <%가 몇번째 라인에 있는지 저장하기 위한 변수
    $first_declare_jsp = -1;

    // 가장 나중에 선언 한 <%@가 몇번째 라인에 있짖 저장하기 위한 변수
    $last_declare_jspAt = 0;

    // 검색한 변수를 잠시 저장하기 위한 변수
    $saved_variable_name = "";

    // 읽어드린 파일의 라인 수를 담은 변수
    $count = count($file);

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
                $file[$kk] = $file[$kk] . $space_matches[0] . $saved_variable_name . " = " . SECURE_VALIDATOR_USE_FUNCTION . "(" . $saved_variable_name . ");" . PHP_EOL;

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
            $is_declared_import_secu = true;
        }

        // mpackage.share.SecureValidator 또는 mpackage.share.* 를 검색
        if(strpos($vv, DECLARE_SECURE_VALIDATOR)){
            $is_declared_class = true;
        }

        // 가장 나중에 선언 한 <%@를 검색
        if(preg_match('/<%@/', $vv)){
            $last_declare_jspAt = $kk;
        }

        // 변수 명과 request.getParameter, getRequestURI, getQueryString를 검색
        if (preg_match('/([가-힣a-zA-Z0-9\_]+)\s*\=\s*.*request\.(getParameter\(\s*\"(.*)\"\s*\)|getRequestURI\(\s*\)|getQueryString\(\s*\))/', $vv, $matches)) {
            // 검색된 변수 명
            $variable_name = $matches[1];

            // 검색된 변수를 매개로 out.print형태 / <%=%>형태를 찾아 시큐어 함수를 적용한다.
            for ($j=$kk; $j<$count; $j++) {
                
                // 이미 시큐어코딩이 적용되었다면 다음 줄로
                if(strpos($file[$j], SECURE_VALIDATOR_USE_FUNCTION . '(' . $variable_name . ')')) continue;

                // 주석(//) System.out.print 형태의 경우 삭제
                if(preg_match('/(\/\/)*\s*System\.out\.print(ln)\(.*' . $variable_name . '.*\)\s*;/', $file[$j])) {
                    $file[$j] = preg_replace('/(\/\/)*\s*System\.out\.print(ln)\(.*' . $variable_name . '.*\)\s*;/', '', $file[$j]);
                    continue;
                }

                // <%=${variable_name}%> 형태 검색
                if(preg_match('/\<\%\s*=\s*' . $variable_name . '\s*\%\>/', $file[$j])) {
                    $file[$j] = preg_replace('/(\<\%\s*=\s*)(' . $variable_name . ')(\s*\%\>)/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
                }

                // out.print(${variable_name}) 형태
                if(preg_match('/out.print\s*[^;]*' . $variable_name . '[)+;\s]+/', $file[$j])) {

                    // 주석(//) out.print(ln) 형태의 경우 삭제
                    if(preg_match('/(\/\/)\s*out\.print(ln)*\s*\(.*' . $variable_name . '.*\)\s*;/', $file[$j])) {
                        $file[$j] = preg_replace('/(\/\/)\s*out\.print(ln)*\s*\(.*' . $variable_name . '.*\)\s*;/', '', $file[$j]);
                        continue;
                    }

                    // out.print(${variable_name}) 형태에서 +(플러스)가 있는 경우
                    if(strpos($file[$j], '+')){
                        
                        /*
                         * out.print(${variable_name1} + "sothing" +
                         * ${variable_name2}); 줄을 바꾸어 출력하는 형태 대응
                         */
                        for ($jj=$j; $jj<$count; $jj++) {
                            
                            // out.print(${variable_name1} + ${variable_name2});
                            // 같은 라인에 다른 변수가 사용될 수 있는 형태 대응
                            foreach($varNames as $vn){
                                // + ${variable_name} 형태를 검색
                                if(preg_match('/\s*\+\s*' . $vn .'/', $file[$jj])) {
                                    // + ${variable_name}의 형태를  SECURE_VALIDATOR_USE_FUNCTION(${variable_name})로 변환
                                    $file[$jj] = preg_replace('/(\s*\+\s*)(' . $vn .')/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)', $file[$jj]);
                                } else {
                                    // ${variable_name} +의 형태를 SECURE_VALIDATOR_USE_FUNCTION(${variable_name}) +로 변환
                                    $file[$jj] = preg_replace('/(\(\s*)(' . $vn .')(\s*\+)/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$jj]);
                                }
                            }
                            // 주석(//)일 경우 다음 라인으로
                            if(preg_match('/^\s*\/\//', $file[$jj])) continue;
                            
                            // ;가 있다면 for을 나감
                            if(preg_match('/\)\s*;/', $file[$jj])) break;
                        }
                    } else {
                        $is_need_remove_out_print = true;
                        $file[$j] = preg_replace('/(out.print(?:ln)*\(\s*[^"]*)(' . $variable_name .')([^"]*\s*\))/', '$1' . SECURE_VALIDATOR_USE_FUNCTION . '($2)$3', $file[$j]);
                    }
                }
            }
            // 시큐어 코딩이 필요한 파일임을 나타내기 위하여 플래그 값 true로 변경
            $is_need_declare_secu = true;
        }
    }

    // 
    foreach($file as $kk => $vv){
        // 위에서 SECURE_VALIDATOR_USE_FUNCTION이 적용되었다면 
        if(strpos($file[$kk], SECURE_VALIDATOR_USE_FUNCTION)){
            // out.print(ln)을 <%= %>로 변환
            $file[$kk] = preg_replace('/(?:out\.print(?:ln)*\s*\()(.+)([\+|\)])(\s*;)/', '%><%=$1%><%', $file[$kk]);
        }

        // <%=request.getParameter("${variable_name}")%> 형태
        if(preg_match('/(\<\%=\s*request\.(getParameter|getQueryString)\s*\(|print\(.*\+*\s+request\.(getParameter|getQueryString)\s*\+*|print\(request\.(getParameter|getQueryString))/', $file[$kk])) {
            // 시큐어 코딩이 필요한 파일임을 나타내기 위하여 플래그 값 true로 변경
            $is_need_declare_secu = true;
            // <%=SECURE_VALIDATOR_USE_FUNCTION(request.getParameter("${variable_name}"))%> 형태로 변경
            $file[$kk] = preg_replace('/(request\.)(getParameter\(\s*\"(.*)\"\s*\)|getRequestURI\(\s*\"(.*)\"\s*\)|getQueryString\(\s*\"(.*)\"\s*\))/', SECURE_VALIDATOR_USE_FUNCTION . '($1$2)', $file[$kk]);
        }
    }

    // SECURE_VALIDATOR를 임포트 함
    if($is_need_declare_secu && !$is_declared_import_secu){
        $file[0] = IMPORT_SECURE_VALIDATOR . PHP_EOL . $file[0];
    }

    // 시큐어 코딩이 필요한 파일
    if($is_need_declare_secu){

        // DECLARE_SECURE_VALIDATOR 가 선언되어 있지 않으면 추가
        if(!$is_declared_class){
            // 첫번째 <% 가 있는 라인
            if($first_declare_jsp != -1) {
                $pos = strrpos($file[$first_declare_jsp], "<%") + 2;
                $front_str = substr($file[$first_declare_jsp], 0, $pos);
                $rear_str = substr($file[$first_declare_jsp], $pos);

                // 첫번째 <% 가 있는 라인의 다음 줄에 SECURE_VALIDATOR를 선언 함.
                $file[$first_declare_jsp] = $front_str . PHP_EOL . "\t" . DECLARE_SECURE_VALIDATOR . PHP_EOL;
                if(strlen(trim($rear_str)) > 1){
                    $file[$first_declare_jsp] .= $rear_str;
                }
            // 시큐어 코딩이 필요한 파일이지만 <%가 없는 경우
            } else {
                $file[$last_declare_jspAt] = $file[$last_declare_jspAt] . "<% " . DECLARE_SECURE_VALIDATOR . " %>" . PHP_EOL;
            }
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


/**
 * 이 곳에 부터 main
 */

// backup모드인지 아닌지 저장하기 위한 플래그 변수
$is_backup_mode = true;

// 파라미터 값이 하나도 없는 경우 에러를 냄
if ($_SERVER["argc"] == 1) {
    echo "에러 : 대상폴더가 없습니다." . PHP_EOL;
    echo "example : resources\php.exe addSecu.php C:\ui" . PHP_EOL;
    exit(1);
}

// 두번째의 파라미터 값이 1인 경우 output 모드
// 두번째의 파라미터 값이 1이 아닌 경우 backup 모드
if (isset($_SERVER["argv"][2]) && $_SERVER["argv"][2] == "1"){
    define("BASE_PATH", "output");
    echo "대상폴더의 파일에 대하여 시큐어 코딩을 적용하지 않으며 " . BASE_PATH . "밑에 시큐어 코딩을 적용한 코드를 저장합니다." . PHP_EOL;
    $is_backup_mode = false;
} else {
    define("BASE_PATH", "backup");
    echo "대상폴더의 파일에 대하여 시큐어 코딩을 적용하고 " . BASE_PATH . "밑에 변경전의 파일을 백업합니다." . PHP_EOL;
}

// 첫번째 파라미터 값을 저장
$target_dir = $_SERVER["argv"][1];

// 윈도우의 역슬러쉬를 슬러쉬 값으로 변경
$target_dir = str_replace("\\", "/", $target_dir);

// 대상 파일을 배열값으로 가져옴
$target_files = dir_recursive($target_dir);

// 대상 파일이 0일 경우, 아무것도 없는 경우 끝냄.
if($target_files == 0){
    echo 'There is no file. Please Check the folder.';
    exit(1);
}

// 파라미터 값이 폴더가 아니고 파일일 경우의 처리
if(!$target_files){
    // 파일 경로를 배열에 값을 넣음
    $target_files[] = $target_dir;

    // 파일명을 제외한 값만 추출해서 $target_dir에 넣음
    $explode_slash_target_dir = explode("/", $target_dir);
    $target_file = array_pop($explode_slash_target_dir);
    $target_dir = implode("/", $explode_slash_target_dir);
}

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
    
    // 변수명 검색
    $varNames = find_varName_getParameter($base);

    // 시큐어 코딩을 적용한 결과 값을 가져옴
    $output = add_secu_request_getParameter($base, $varNames);

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
    $percent = round((intval($k)/$count)*100, 2);

    // 완료된 파일명 (진행된 퍼센트)를 출력
    echo $output->create_file . " ($percent%)". PHP_EOL;
}

// 대상폴더를 탐색기에서 열기
exec("start $target_dir");

// BASE_PATH를 탐색기에서 열기
exec("start " . BASE_PATH);

?>