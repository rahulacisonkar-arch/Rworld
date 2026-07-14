# Deep Learning Models (SAM 2 & YOLOv11)

The AI estimation pipeline blends computer vision segmenters with rotated box detection networks.

---

## 1. Segment Anything 2 (SAM 2)

- **Purpose**: Generates high-definition polygon borders mapping individual roof facets and pitch orientations.
- **Trigger**: Fired by the workflow orchestrator passing bounding coordinates.
- **Output**: Multi-polygon geometry coordinates simplified via Ramer-Douglas-Peucker (RDP) algorithm to prune excessive coordinate points.

---

## 2. YOLOv11-OBB (Oriented Bounding Boxes)

- **Purpose**: Rotated target detection locating roof ridge peaks, valleys, hips, chimneys, solar panels, and skylight obstructions.
- **Rotated Angle Parameters**: Bounding boxes store rotated angles `[x, y, width, height, theta]` to accurately trace valley runs and ridges orientations.
