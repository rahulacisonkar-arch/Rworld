@echo off
title Headroom Optimization Proxy
color 0B
echo ========================================================
echo          Starting Headroom Context Compression Proxy
echo ========================================================
echo.
echo This proxy sits between your agent and OpenRouter, compressing
echo prompt contexts, RAG chunks, and history to reduce token billing.
echo.

set OPENROUTER_API_KEY=your_openrouter_api_key_here
set ANTHROPIC_API_KEY=sk-ant-dummy

if "%OPENROUTER_API_KEY%"=="" (
    echo WARNING: OPENROUTER_API_KEY is not defined. The proxy might fail to authenticate.
    echo.
)

echo Starting Headroom Proxy server on http://localhost:8787/v1 ...
"C:\Users\Artee Admin\Desktop\browser-use-main\.venv\Scripts\headroom.exe" proxy --backend openrouter
pause
