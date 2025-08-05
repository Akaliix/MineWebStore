@echo off
echo Building MineWebStore Plugin...
echo.

REM Clean and build the project
echo Cleaning previous builds and building the new one...
mvn clean package

pause
