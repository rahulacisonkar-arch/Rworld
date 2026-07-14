export interface FeatureFlagConfig {
  aiEnabled: boolean;
  solarEnabled: boolean;
  ocrEnabled: boolean;
  permitsEnabled: boolean;
  weatherEnabled: boolean;
  crmEnabled: boolean;
  procurementEnabled: boolean;
}

export class FeatureFlagManager {
  private defaultFlags: FeatureFlagConfig;
  private tenantOverrides: Map<string, Partial<FeatureFlagConfig>> = new Map();

  constructor() {
    // Default deployment settings loaded from environment configurations
    this.defaultFlags = {
      aiEnabled: process.env.FEATURE_AI_ENABLED !== 'false',
      solarEnabled: process.env.FEATURE_SOLAR_ENABLED !== 'false',
      ocrEnabled: process.env.FEATURE_OCR_ENABLED !== 'false',
      permitsEnabled: process.env.FEATURE_PERMITS_ENABLED !== 'false',
      weatherEnabled: process.env.FEATURE_WEATHER_ENABLED !== 'false',
      crmEnabled: process.env.FEATURE_CRM_ENABLED === 'true', // Disabled by default
      procurementEnabled: process.env.FEATURE_PROCUREMENT_ENABLED !== 'false'
    };

    // Example mock tenant overrides setup
    this.tenantOverrides.set('00000000-0000-0000-0000-000000000000', {
      crmEnabled: true // Enable CRM specifically for this tenant
    });
  }

  isFeatureEnabled(feature: keyof FeatureFlagConfig, tenantId?: string): boolean {
    if (tenantId && this.tenantOverrides.has(tenantId)) {
      const overrides = this.tenantOverrides.get(tenantId)!;
      if (overrides[feature] !== undefined) {
        return overrides[feature]!;
      }
    }
    return this.defaultFlags[feature];
  }
}

export const featureFlags = new FeatureFlagManager();
