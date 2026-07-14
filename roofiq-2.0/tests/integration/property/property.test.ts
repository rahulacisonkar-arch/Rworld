import { PropertyService } from '../../../apps/backend-core/src/services/PropertyService';

describe('Property Integration Test', () => {
  let propertyService: PropertyService;

  beforeAll(() => {
    propertyService = new PropertyService();
  });

  it('should geocode address and store property in DB', async () => {
    // 1. Resolve mock or live address via U.S. Census API
    const address = "1600 Pennsylvania Ave NW, Washington, DC";
    const property = await propertyService.resolveProperty("tenant-1", address);

    // 2. Validate saved fields
    expect(property).toBeDefined();
    expect(property.address).toBe(address);
    expect(property.latitude).toBeDefined();
    expect(property.longitude).toBeDefined();
  });
});
