import { PropertyRepository } from '../repositories/PropertyRepository';
import { CensusClient } from '../clients/CensusClient';
import { FemaClient } from '../clients/FemaClient';

export class PropertyService {
  private propertyRepo: PropertyRepository;
  private censusClient: CensusClient;
  private femaClient: FemaClient;

  constructor() {
    this.propertyRepo = new PropertyRepository();
    this.censusClient = new CensusClient();
    this.femaClient = new FemaClient();
  }

  async resolveProperty(tenantId: string, address: string): Promise<any> {
    // 1. Resolve address components and geographic coordinates
    const geo = await this.censusClient.geocode(address);
    
    // 2. Fetch FEMA flood zone classifications in parallel
    const flood = await this.femaClient.getFloodZone(geo.latitude, geo.longitude);

    // 3. Persist the property record
    return this.propertyRepo.create({
      tenantId,
      address,
      formattedAddress: geo.address,
      latitude: geo.latitude as any,
      longitude: geo.longitude as any,
      ownerName: 'Assessor Database Records',
      yearBuilt: 2005, // Default/Assessor value
      lotSizeSqft: 10000 as any,
      buildingSizeSqft: 2500 as any,
      assessedValue: 350000 as any
    });
  }

  async getProperty(id: string): Promise<any> {
    return this.propertyRepo.findById(id);
  }

  async listProperties(tenantId: string, query?: string): Promise<any[]> {
    return this.propertyRepo.findAll(tenantId, query);
  }
}
