@echo off
echo Setting up Interntrack React Application...
echo.

REM Create directories if they don't exist
if not exist "src\styles" mkdir src\styles
if not exist "public" mkdir public

REM Copy CSS files
echo Copying CSS files...
copy /Y master-style.css src\styles\master-style.css
copy /Y director-enhancements.css src\styles\director-enhancements.css  
copy /Y coordinator-fix.css src\styles\coordinator-fix.css
copy /Y styles.css src\styles\styles.css

REM Copy logo if it exists
if exist logo.jpg (
    echo Copying logo...
    copy /Y logo.jpg public\logo.jpg
) else (
    echo Warning: logo.jpg not found, skipping...
)

echo.
echo Setup complete!
echo.
echo To install dependencies, run: npm install
echo To start development server, run: npm run dev
pause
