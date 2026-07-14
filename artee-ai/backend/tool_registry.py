import json

class ToolRegistry:
    """
    Decoupled tool registry enabling dynamic registration, discovery,
    and invocation of AI agent action interfaces.
    """

    def __init__(self):
        # Maps tool name string -> execution callable callback
        self.registry = {}

    def register_tool(self, name: str, callback) -> None:
        """
        Locks a callback function to the registry.
        """
        self.registry[name] = callback
        print(f"[ToolRegistry] Tool '{name}' registered successfully.")

    def execute_tool(self, name: str, input_json: str, task=None) -> dict:
        """
        Invokes a registered tool dynamically.
        """
        if name not in self.registry:
            return {"error": f"Tool '{name}' is not registered in the Tool Registry."}

        try:
            args = json.loads(input_json)
        except Exception:
            args = {}

        try:
            callback = self.registry[name]
            # Execute callback function with args payload
            result = callback(args, task) if task else callback(args)
            return result
        except Exception as e:
            return {"error": f"Tool '{name}' execution failed: {str(e)}"}

    def list_tools(self) -> list:
        return list(self.registry.keys())
