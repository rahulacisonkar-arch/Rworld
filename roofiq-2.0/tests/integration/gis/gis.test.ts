import { OverpassProvider } from '../../../apps/backend-core/src/providers/gis/OverpassProvider';

describe('ParcelProvider API Contract Test', () => {
  let provider: OverpassProvider;

  beforeAll(() => {
    provider = new OverpassProvider();
  });

  it('should retrieve georeferenced footprint polygons', async () => {
    const footprint = await provider.getFootprint(40.7128, -74.0060);
    expect(footprint).toBeDefined();
    expect(footprint?.coordinates[0].length).toBeGreaterThan(3);
    expect(footprint?.source).toBe('OpenStreetMap Overpass API');
  });
});
