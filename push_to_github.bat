@echo off
echo Clearing GitHub repository and pushing fresh code...
echo.

REM Navigate to project directory
cd /d "C:\Users\mrkri\Desktop\Hackpro"

REM Remove existing git folder if it exists
if exist ".git" (
    echo Removing existing git configuration...
    rmdir /s /q .git
)

REM Initialize fresh git repository
echo Initializing fresh git repository...
git init

REM Set default branch to main
git branch -M main

REM Add remote repository
git remote add origin https://github.com/mrkrisshu/Unidoo.git

REM Create empty commit to establish main branch
git commit --allow-empty -m "Initial commit"

REM Push empty commit to clear repository
git push -f origin main

REM Add all current files
echo Adding all project files...
git add .

REM Commit all files
git commit -m "Add complete Manufacturing Management System - PHP/MySQL ERP solution"

REM Push to replace all existing files
echo Pushing fresh code to GitHub...
git push -f origin main

echo.
echo Repository cleared and fresh code pushed successfully!
echo Check your GitHub repository at: https://github.com/mrkrisshu/Unidoo
pause