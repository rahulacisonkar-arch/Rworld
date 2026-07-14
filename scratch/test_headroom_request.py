import os
import httpx
import json

print("=========================================")
print(" Testing Headroom Proxy connection")
print("=========================================")

# Set API Key directly in python runtime
os.environ["OPENROUTER_API_KEY"] = os.getenv("OPENROUTER_API_KEY", "your_openrouter_api_key_here")
os.environ["ANTHROPIC_API_KEY"] = "sk-ant-dummy"

url = "http://127.0.0.1:8787/v1/chat/completions"
headers = {
    "Content-Type": "application/json",
    "Authorization": "Bearer sk-ant-dummy" # Headroom expects bearer token (ignored)
}

payload = {
    "model": "openai/gpt-4o-mini",
    "messages": [
        {"role": "user", "content": "Say hello in one word."}
    ]
}

try:
    print(f"Sending POST request to {url}...")
    response = httpx.post(url, headers=headers, json=payload, timeout=20.0)
    print(f"Status Code: {response.status_code}")
    print("Response Headers:")
    for k, v in response.headers.items():
        print(f"  {k}: {v}")
    print("\nResponse Body:")
    print(response.text)
except Exception as e:
    print(f"Request failed: {e}")
