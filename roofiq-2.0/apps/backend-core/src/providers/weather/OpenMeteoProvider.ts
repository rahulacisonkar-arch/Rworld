import { WeatherProvider } from './WeatherProvider';
import { WeatherData, StormEvent } from '../../interfaces/IWeatherAdapter';
import { OpenMeteoAdapter } from '../../adapters/OpenMeteoAdapter';

export class OpenMeteoProvider implements WeatherProvider {
  private adapter: OpenMeteoAdapter;

  constructor() {
    this.adapter = new OpenMeteoAdapter();
  }

  async fetchForecast(lat: number, lng: number): Promise<WeatherData> {
    return this.adapter.getForecast(lat, lng);
  }

  async fetchStormHistory(lat: number, lng: number): Promise<StormEvent[]> {
    return this.adapter.getStormEvents(lat, lng, 50);
  }
}
