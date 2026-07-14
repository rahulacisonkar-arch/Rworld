class BusinessKnowledgeEngine:
    """
    Enterprise Brain Graph tracking entities and their relational mappings:
    (Customer, Order, Invoice, Payment, Project, Supplier, Email, Document, Utility Bill, Inventory, PO, Employee).
    """

    def __init__(self):
        # Graph stored as: entity_id -> {type, properties, relations: set((target_id, relation_type))}
        self.nodes = {}

    def add_entity(self, entity_id: str, entity_type: str, properties: dict = None) -> None:
        """
        Registers an entity node in the business brain graph.
        """
        self.nodes[entity_id] = {
            "type": entity_type,
            "properties": properties or {},
            "relations": set()
        }

    def add_relationship(self, source_id: str, target_id: str, relation_type: str) -> bool:
        """
        Binds a directed relationship link between source and target nodes.
        """
        if source_id in self.nodes and target_id in self.nodes:
            self.nodes[source_id]["relations"].add((target_id, relation_type))
            return True
        return False

    def get_entity_relations(self, entity_id: str) -> list:
        """
        Returns all relationship links extending from this node.
        """
        if entity_id not in self.nodes:
            return []
        
        results = []
        for target_id, rel in self.nodes[entity_id]["relations"]:
            target_node = self.nodes.get(target_id, {})
            results.append({
                "source": entity_id,
                "target": target_id,
                "target_type": target_node.get("type", "unknown"),
                "relation": rel
            })
        return results

    def find_path(self, start_id: str, target_id: str, max_depth: int = 3) -> list:
        """
        Performs a Breadth-First-Search (BFS) path search between two entities.
        """
        if start_id not in self.nodes or target_id not in self.nodes:
            return []

        queue = [[(start_id, "start")]]
        visited = {start_id}

        while queue:
            path = queue.pop(0)
            node_id, _ = path[-1]

            if node_id == target_id:
                return path

            for neighbor, rel in self.nodes[node_id]["relations"]:
                if neighbor not in visited:
                    visited.add(neighbor)
                    new_path = list(path)
                    new_path.append((neighbor, rel))
                    queue.append(new_path)
                    
        return []
