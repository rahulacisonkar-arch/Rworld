# PostGIS Georeferenced Footprints

RoofIQ AI aligns pixel segmentation coordinate grids to geographic coordinates using EPSG:4326 projections.

---

## 1. Pixel to Coordinates Conversion

1. **Camera Parameter Resolution**: Reads camera altitude, focal length, and compass heading from drone metadata or satellite imagery.
2. **Ground Projection Math**: Projects pixel coordinates `(X, Y)` relative to centroid latitude/longitude using a flat plane elevation assumption.
3. **PostGIS MultiPolygon Insertion**: Serializes coordinates into Well-Known Text (WKT) format:
   ```sql
   INSERT INTO roof_analyses ("property_id", "roofGeometry") 
   VALUES ($1, ST_GeomFromText('MULTIPOLYGON(((lng1 lat1, lng2 lat2, ...)))', 4326));
   ```

---

## 2. Area and Pitch Projections

- **ST_Area(geography)**: Calculates the physical ground area (square meters) of the boundary projection polygon.
- **Slope Multiplier**: Combines ST_Area with the cosine pitch angle calculated in the measurement engine to find the true sloping surface area.
