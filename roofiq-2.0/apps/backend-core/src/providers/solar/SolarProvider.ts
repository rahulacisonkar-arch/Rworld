import { SolarYieldResult } from '../../clients/NrelClient';

export interface SolarProvider {
  calculateYield(lat: number, lng: number, capacityKw?: number): Promise<SolarYieldResult>;
}
