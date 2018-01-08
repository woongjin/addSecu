@echo off
echo SecureValidator 보안필터 적용를 적용하고 싶은 폴더를 입력 해 주세요.
set INPUT=
set /P INPUT=폴더명 : %=%

cls
resources\php.exe addSecu.php %INPUT%