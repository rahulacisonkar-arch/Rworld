import { OpenMeteoProvider } from '../../../apps/backend-core/src/providers/weather/OpenMeteoProvider';

describe('WeatherProvider API Contract Test', () => {
  let provider: OpenMeteoProvider;

  beforeAll(() => {
    provider = new OpenMeteoProvider();
  });

  it('should match contract formats for forecast retrieval', async () => {
    const lat = 40.7128;
    const lng = -74.0060;
    
    const data = await provider.fetchForecast(lat, lng);
    
    // Contract Schema Validation
    expect(data).toHaveProperty('temperature');
    expect(data).toHaveProperty('windSpeed');
    expect(data).toHaveProperty('windDirection');
    expect(data).toHaveProperty('condition');
    expect(typeof data.temperature).toBe('number');
  });

  it('should fallback to safe default on invalid coordinates/timeouts', async () => {
    const data = await provider.fetchForecast(999, 999); // Invalid coordinates
    
    // Recovery Check
    expect(data).toBeDefined();
    expect(data.condition).toBe('Clear'); // Fallback default
    expect(data.temperature).toBe(20.0);
  });
});
