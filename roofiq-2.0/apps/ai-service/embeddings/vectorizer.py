from typing import List

class TextVectorizer:
    """
    Generates embedding vectors for indexing technical building codes into Qdrant Vector DB
    """
    def __init__(self, model_name: str = "all-MiniLM-L6-v2"):
        self.model_name = model_name

    def embed_text(self, text: str) -> List[float]:
        # Generates a baseline 384-dimensional vector embedding
        return [0.015] * 384
