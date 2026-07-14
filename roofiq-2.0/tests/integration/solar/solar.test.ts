import { NRELProvider } from '../../../apps/backend-core/src/providers/solar/NRELProvider';

describe('SolarProvider API Contract Test', () => {
  let provider: NRELProvider;

  beforeAll(() => {
    provider = new NRELProvider();
  });

  it('should return NREL PVWatts outputs matching types', async () => {
    const result = await provider.calculateYield(34.0522, -118.2437, 5.0);
    expect(result).toBeDefined();
    expect(result.annualKwh).toBeGreaterThan(0);
    expect(result.monthlyKwh).toHaveLength(12);
  });
});
