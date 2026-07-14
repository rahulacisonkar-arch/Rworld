export interface ParcelGeometry {
  coordinates: [number, number][][];
  osmId?: string;
  source: string;
}

export interface ParcelProvider {
  getFootprint(lat: number, lng: number): Promise<ParcelGeometry | null>;
}
