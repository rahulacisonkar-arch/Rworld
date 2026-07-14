# Interchangeable Plugin Hooks

The platform provides a plugin-centric model to add CRM synchronization, custom supplier databases, and regional permit catalogs without editing core microservices.

---

## 1. Plugin Lifecycle Contract

Every plugin must implement the interface:
```typescript
export interface RoofIQPlugin {
  metadata: PluginMetadata;
  initialize(): Promise<void>;
  shutdown(): Promise<void>;
  health(): Promise<{ status: 'healthy' | 'unhealthy' }>;
  execute(input: any): Promise<any>;
}
```

---

## 2. Interchangeable Directories

Our structure maps specialized plugins into subfolders:
- `weather/`: Integrates local NOAA or WeatherFlow stations.
- `permit/`: Standardizes municipal record queries.
- `solar/`: Recalculates cost savings using local utility tables.
- `gis/`: Pulls additional georeferenced footprint boundaries.
- `ocr/`: Connects advanced layout parsers.
- `procurement/`: Connects local wholesaler ERPs.
- `inspection/`: Integrates flight routes calculations for drone vendors.
- `crm/`: Pushes lead opportunities into Salesforce or HubSpot.
- `custom/`: Staging hooks playground.
