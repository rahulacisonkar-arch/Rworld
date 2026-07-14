// Global TypeScript Type Definitions for RoofIQ AI 2.0
export interface Tenant {
  id: string;
  name: string;
  subdomain: string;
  createdAt: Date;
}

export interface User {
  id: string;
  tenantId: string;
  email: string;
  role: 'Admin' | 'Estimator' | 'Crew' | 'Client';
  createdAt: Date;
}

export interface Property {
  id: string;
  tenantId: string;
  address: string;
  formattedAddress?: string;
  latitude: number;
  longitude: number;
  ownerName?: string;
  yearBuilt?: number;
  lotSizeSqft?: number;
  buildingSizeSqft?: number;
  assessedValue?: number;
  createdAt: Date;
}

export interface RoofAnalysis {
  id: string;
  propertyId: string;
  analyzedById?: string;
  roofAreaSqft?: number;
  pitchDeg?: number;
  conditionScore?: number;
  floodZone?: string;
  elevationFt?: number;
  peakWindSpeed?: number;
  solarYieldKwhYr?: number;
  aiRawPayload?: any;
  createdAt: Date;
}

export interface Permit {
  id: string;
  propertyId: string;
  permitNumber: string;
  issueDate?: Date;
  status: string;
  description?: string;
  contractorName?: string;
}

export interface Takeoff {
  id: string;
  analysisId: string;
  materialName: string;
  category: string;
  quantity: number;
  unit: string;
  unitCost: number;
  totalCost: number;
}
