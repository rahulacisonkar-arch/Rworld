import { SolarProvider } from './SolarProvider';
import { SolarYieldResult, NrelClient } from '../../clients/NrelClient';

export class NRELProvider implements SolarProvider {
  private client: NrelClient;

  constructor() {
    this.client = new NrelClient();
  }

  async calculateYield(lat: number, lng: number, capacityKw: number = 5.0): Promise<SolarYieldResult> {
    return this.client.getSolarYield(lat, lng, capacityKw);
  }
}
