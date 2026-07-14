export interface WeatherData {
  temperature: number;
  windSpeed: number;
  windDirection: number;
  relativeHumidity: number;
  precipitation: number;
  condition: string;
}

export interface StormEvent {
  date: Date;
  eventType: string;
  magnitude?: string;
  source: string;
}

export interface IWeatherAdapter {
  getForecast(lat: number, lng: number): Promise<WeatherData>;
  getHistoricalWeather(lat: number, lng: number, startDate: string, endDate: string): Promise<WeatherData[]>;
  getStormEvents(lat: number, lng: number, radiusKm: number): Promise<StormEvent[]>;
}
