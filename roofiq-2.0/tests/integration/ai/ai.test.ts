import { AiServiceClient } from '../../../apps/backend-core/src/clients/AiServiceClient';

describe('AI Service Integration Test', () => {
  let aiClient: AiServiceClient;

  beforeAll(() => {
    aiClient = new AiServiceClient();
  });

  it('should run image segmentation pipeline (SAM/YOLO)', async () => {
    const result = await aiClient.runImageInference('http://minio:9000/roofiq-bucket/images/roof-123.jpg', [0,0,512,512]);
    expect(result.success).toBe(true);
    expect(result.facets.length).toBeGreaterThan(0);
    expect(result.detections.length).toBeGreaterThan(0);
  });
});
