import { AiServiceClient } from '../../../apps/backend-core/src/clients/AiServiceClient';

describe('OCR Service Integration Test', () => {
  let aiClient: AiServiceClient;

  beforeAll(() => {
    aiClient = new AiServiceClient();
  });

  it('should run OCR scan on blueprint and retrieve structural shingle counts', async () => {
    const text = await aiClient.performOcr('http://minio:9000/roofiq-bucket/uploads/blueprint-123.pdf');
    expect(text).toBeDefined();
    expect(text).toContain('squares');
  });
});
