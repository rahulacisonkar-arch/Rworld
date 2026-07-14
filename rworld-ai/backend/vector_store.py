import os
import numpy as np
import pickle
import urllib.request
import json
from sqlalchemy.orm import Session
from .models import DocumentEmbedding

class ChromaDBVectorStore:
    """
    Persistent ChromaDB vector store with automatic SQLite/NumPy fallback.
    """
    def __init__(self, db_session: Session, ollama_url: str = "http://localhost:11434"):
        self.db = db_session
        self.ollama_url = ollama_url
        self.use_chroma = False
        try:
            import chromadb
            # Initialize persistent client in backend/chroma_data
            db_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "chroma_data")
            self.client = chromadb.PersistentClient(path=db_dir)
            self.collection = self.client.get_or_create_collection("mworld_intelligence")
            self.use_chroma = True
            print("[ChromaDB] Persistent collection 'mworld_intelligence' initialized.")
        except Exception as e:
            print(f"[ChromaDB] Falling back to SQLite/NumPy vector store. Reason: {e}")

    def _get_embedding(self, text: str) -> list:
        """
        Retrieves embedding vector from Ollama API. Fallbacks to a deterministic
        pseudo-random text embedding vector if Ollama is offline.
        """
        payload = {
            "model": "all-minilm",
            "prompt": text
        }
        req_data = json.dumps(payload).encode('utf-8')
        
        try:
            req = urllib.request.Request(
                f"{self.ollama_url}/api/embeddings",
                data=req_data,
                headers={"Content-Type": "application/json"},
                method="POST"
            )
            with urllib.request.urlopen(req, timeout=3) as res:
                res_data = json.loads(res.read().decode('utf-8'))
                vector = res_data.get("embedding")
                if vector:
                    return [float(x) for x in vector]
        except Exception:
            # Deterministic vector fallback
            seed = sum(ord(c) for c in text) % 1000
            rng = np.random.default_rng(seed)
            vector = rng.standard_normal(384)
            norm = np.linalg.norm(vector)
            if norm > 0:
                vector = vector / norm
            return [float(x) for x in vector]

    def add_document(self, text: str, filename: str = None, chunk_index: int = 0):
        """
        Stores the document vector in ChromaDB if available, otherwise commits to SQLite.
        """
        vector = self._get_embedding(text)
        
        if self.use_chroma:
            try:
                doc_id = f"{filename or 'doc'}_{chunk_index}_{seed_hash(text)}"
                self.collection.add(
                    embeddings=[vector],
                    documents=[text],
                    metadatas=[{"filename": filename or "unknown", "chunk_index": chunk_index}],
                    ids=[doc_id]
                )
                print(f"[ChromaDB] Added chunk {chunk_index} for document {filename}")
                return doc_id
            except Exception as e:
                print(f"[ChromaDB Write Error] {e}. Falling back to SQLite.")

        # SQLite/NumPy fallback
        blob = pickle.dumps(np.array(vector, dtype=np.float32))
        doc = DocumentEmbedding(
            filename=filename,
            chunk_index=chunk_index,
            text_content=text,
            embedding_blob=blob
        )
        self.db.add(doc)
        self.db.commit()
        return doc.id

    def similarity_search(self, query: str, top_k: int = 3):
        """
        Searches matching documents in ChromaDB, or SQLite as a fallback.
        """
        vector = self._get_embedding(query)
        
        if self.use_chroma:
            try:
                results = self.collection.query(
                    query_embeddings=[vector],
                    n_results=top_k
                )
                formatted = []
                if results and "documents" in results and results["documents"]:
                    docs = results["documents"][0]
                    ids = results["ids"][0]
                    metadatas = results["metadatas"][0]
                    # ChromaDB distance values are L2 distances, we mock cosine-like score
                    distances = results.get("distances", [[0.0]*len(ids)])[0]
                    for idx, doc_text in enumerate(docs):
                        formatted.append({
                            "id": ids[idx],
                            "filename": metadatas[idx].get("filename", "unknown"),
                            "text": doc_text,
                            "score": float(1.0 / (1.0 + distances[idx]))
                        })
                return formatted
            except Exception as e:
                print(f"[ChromaDB Search Error] {e}. Falling back to SQLite.")

        # SQLite fallback
        query_vector = np.array(vector, dtype=np.float32)
        docs = self.db.query(DocumentEmbedding).all()
        if not docs:
            return []

        results = []
        for doc in docs:
            doc_vector = pickle.loads(doc.embedding_blob)
            dot_product = np.dot(query_vector, doc_vector)
            norm_q = np.linalg.norm(query_vector)
            norm_d = np.linalg.norm(doc_vector)
            similarity = 0.0
            if norm_q > 0 and norm_d > 0:
                similarity = float(dot_product / (norm_q * norm_d))

            results.append({
                "id": doc.id,
                "filename": doc.filename,
                "text": doc.text_content,
                "score": similarity
            })
        results.sort(key=lambda x: x["score"], reverse=True)
        return results[:top_k]

def seed_hash(text: str) -> int:
    return sum(ord(c) for c in text) % 100000

# Maintain backward compatibility alias
NumPyVectorStore = ChromaDBVectorStore
