import { SocrataProvider } from '../../../apps/backend-core/src/providers/permits/SocrataProvider';

describe('PermitProvider API Contract Test', () => {
  let provider: SocrataProvider;

  beforeAll(() => {
    provider = new SocrataProvider();
  });

  it('should query municipal logs and match permit columns', async () => {
    const list = await provider.lookupPermits("123 Main St", 40.7128, -74.0060);
    expect(list).toBeDefined();
    expect(list.length).toBeGreaterThan(0);
    expect(list[0]).toHaveProperty('permitNumber');
    expect(list[0]).toHaveProperty('contractor');
  });
});
