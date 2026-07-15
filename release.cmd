@echo off
setlocal EnableExtensions EnableDelayedExpansion

rem Genera release ZIP del plugin (build + compress in release/)
rem Uso:
rem   release.cmd              - bump PATCH + pacchetto
rem   release.cmd patch        - bump PATCH + pacchetto
rem   release.cmd minor        - bump MINOR + pacchetto
rem   release.cmd major        - bump MAJOR + pacchetto
rem   release.cmd package      - solo pacchetto (nessun bump versione)
rem   release.cmd build        - solo build in build/ (nessuno zip)

cd /d "%~dp0"

set "MODE=%~1"
if "%MODE%"=="" set "MODE=patch"

if /i "%MODE%"=="help" goto :usage
if /i "%MODE%"=="/?" goto :usage
if /i "%MODE%"=="-h" goto :usage

where pnpm >nul 2>&1
if errorlevel 1 (
	echo [ERRORE] pnpm non trovato nel PATH.
	echo Installa Node.js e abilita pnpm: corepack enable ^&^& corepack prepare pnpm@10.10.0 --activate
	exit /b 1
)

if not exist "node_modules\" (
	echo Installazione dipendenze...
	call pnpm install
	if errorlevel 1 exit /b 1
)

if /i "%MODE%"=="build" (
	echo Build plugin in build/ ...
	call pnpm run build
	goto :done
)

if /i "%MODE%"=="package" (
	echo Creazione ZIP senza bump versione ...
	call pnpm run package
	goto :done
)

if /i not "%MODE%"=="patch" if /i not "%MODE%"=="minor" if /i not "%MODE%"=="major" (
	echo [ERRORE] Argomento non valido: %MODE%
	goto :usage
)

echo Release %MODE%: bump versione + build + ZIP ...
call pnpm run release:%MODE%
if errorlevel 1 exit /b 1

:done
if errorlevel 1 (
	echo [ERRORE] Release fallita.
	exit /b 1
)

for /f "usebackq delims=" %%V in (`node -p "require('./package.json').version"`) do set "VER=%%V"

echo.
echo OK - versione %VER%
if exist "release\" (
	echo ZIP in release\:
	dir /b "release\isigestsyncapi-*.zip" 2>nul
) else (
	echo Cartella release\ non trovata.
)

exit /b 0

:usage
echo.
echo ISIGest Sync API - release
echo.
echo   release.cmd [patch^|minor^|major^|package^|build]
echo.
echo   patch    default - incrementa PATCH e crea release\isigestsyncapi-X.Y.Z.zip
echo   minor    incrementa MINOR e crea ZIP
echo   major    incrementa MAJOR e crea ZIP
echo   package  crea ZIP senza modificare la versione
echo   build    solo grunt build in build/
echo.
exit /b 1
