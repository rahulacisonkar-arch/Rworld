import { WeatherData, StormEvent } from '../../interfaces/IWeatherAdapter';

export interface WeatherProvider {
  fetchForecast(lat: number, lng: number): Promise<WeatherData>;
  fetchStormHistory(lat: number, lng: number): Promise<StormEvent[]>;
}
