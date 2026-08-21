@echo off
REM Double-click this to start the resume-parsing sidecar on port 8001.
REM Leave the window open while you use the app; close it (or Ctrl+C) to stop.
title crm-pdf-parser sidecar (port 8001)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sidecar.ps1" %*
if errorlevel 1 pause
