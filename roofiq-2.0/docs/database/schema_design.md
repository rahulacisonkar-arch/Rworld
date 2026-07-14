# Database Schema Design

RoofIQ AI uses PostgreSQL with the PostGIS extension for boundary polygon modeling.

---

## 1. Relational Map

- **`Tenant`**: Multi-tenant isolation anchor.
- **`User`**: Linked to Tenant with roles (`Admin`, `Estimator`, `Crew`, `Client`).
- **`Property`**: Matches addresses to geocoordinates. Stores 2D spatial geometries (`Unsupported("geometry(Geometry, 4326)")`).
- **`RoofAnalysis`**: Linked to Property. Stores 3D polygon plane geometries (`Unsupported("geometry(MultiPolygon, 4326)")`) and solar PVWatts estimates.
- **`Permit`**: Chronological log of city inspections and roofing records.
- **`Takeoff`**: Bill of Materials (BOM) breakdown list matching items to shingle counts.

---

## 2. Spatial Indexing

To optimize geographical queries, spatial bounding box indexes are created on geometries using GIST (Generalized Search Tree):
```sql
CREATE INDEX idx_properties_geometry ON properties USING GIST (geometry);
CREATE INDEX idx_roof_analyses_geometry ON roof_analyses USING GIST (roofGeometry);
```
This enables near-instant intersections check for parcel footprints.
