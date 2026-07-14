# API Reference Guide

RoofIQ AI Core exposes v1 JSON endpoints under `/api/v1` namespace.

---

## 1. Property Management

### List Properties
* **Method**: `GET`
* **Path**: `/api/v1/property`
* **Response**:
  ```json
  [
    {
      "id": "7fbe45a1-432a-4311-9a74-d4b99812e9b0",
      "address": "123 Main St, New York, NY",
      "latitude": 40.7128,
      "longitude": -74.0060,
      "createdAt": "2026-06-25T15:00:00.000Z"
    }
  ]
  ```

### Create Property
* **Method**: `POST`
* **Path**: `/api/v1/property`
* **Payload**:
  ```json
  {
    "address": "123 Main St, New York, NY"
  }
  ```

---

## 2. Analysis & Overrides

### Queue Analysis
* **Method**: `POST`
* **Path**: `/api/v1/analysis/:propertyId`
* **Response**:
  ```json
  {
    "success": true,
    "analysisId": "8a3e7a1b-cd40-410c-99a3-d51a2318c4d2",
    "status": "Queued"
  }
  ```

### Edit Measurements
* **Method**: `PUT`
* **Path**: `/api/v1/analysis/:analysisId/measurements`
* **Payload**:
  ```json
  {
    "roofAreaSqft": 2450,
    "pitchDeg": 18.5
  }
  ```
