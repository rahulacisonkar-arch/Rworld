"""
Simple try of the agent.

@dev You need to add OPENAI_API_KEY to your environment variables.
"""

import asyncio
import os

from dotenv import load_dotenv

from browser_use import Agent, ChatOpenAI

load_dotenv()

# All the models are type safe from OpenAI in case you need a list of supported models
llm = ChatOpenAI(
	model=os.getenv('OPENAI_MODEL_NAME', 'openai/gpt-4o-mini'),
	base_url=os.getenv('OPENAI_BASE_URL', 'http://127.0.0.1:8787/v1'),
	api_key=os.getenv('OPENAI_API_KEY', 'sk-ant-dummy'),
)
agent = Agent(
	task='Find the number of stars of the browser-use repo',
	llm=llm,
	use_vision=False,
)


async def main():
	await agent.run(max_steps=10)


asyncio.run(main())
