from ddgs import DDGS
from .vector_store import NumPyVectorStore
from .business_knowledge import BusinessKnowledgeEngine

class SearchEngineRouter:
    """
    Unified Search Engine aggregating DuckDuckGo (web), local vector indices,
    and Knowledge Graph relationships.
    """

    def query_search(self, query: str, vector_store: NumPyVectorStore = None, 
                     knowledge: BusinessKnowledgeEngine = None) -> dict:
        """
        Combines search hits across multiple providers, sorting by semantic score.
        """
        print(f"[SearchEngine] Querying search channels for: '{query}'")
        
        web_results = []
        local_docs = []
        graph_nodes = []

        # 1. DuckDuckGo Web Search
        try:
            with DDGS() as ddgs:
                # Limit to first 3 results for performance
                hits = ddgs.text(query, max_results=3)
                for h in hits:
                    web_results.append({
                        "title": h.get("title"),
                        "snippet": h.get("body"),
                        "link": h.get("href"),
                        "source": "web"
                    })
        except Exception as e:
            print(f"[SearchEngine] DDG Search failed or offline: {e}")
            web_results = [{"title": "Web results offline", "snippet": "No internet connection detected.", "source": "web"}]

        # 2. Local Document Vector Search
        if vector_store:
            try:
                hits = vector_store.similarity_search(query, top_k=2)
                for h in hits:
                    local_docs.append({
                        "title": h.get("filename", "document.txt"),
                        "snippet": h.get("text"),
                        "score": h.get("score"),
                        "source": "local_vector"
                    })
            except Exception as e:
                print(f"[SearchEngine] Local vector search failed: {e}")

        # 3. Knowledge Graph Entity Search
        if knowledge:
            try:
                # Find nodes matching label
                for node_id, node in knowledge.nodes.items():
                    if query.lower() in node_id.lower() or query.lower() in str(node.get("properties")).lower():
                        graph_nodes.append({
                            "entity_id": node_id,
                            "type": node.get("type"),
                            "relations_count": len(node.get("relations", [])),
                            "source": "knowledge_graph"
                        })
            except Exception as e:
                print(f"[SearchEngine] Knowledge graph query failed: {e}")

        return {
            "query": query,
            "web_hits": web_results,
            "local_document_hits": local_docs,
            "knowledge_graph_hits": graph_nodes
        }
