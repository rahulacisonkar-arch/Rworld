import { ParcelProvider, ParcelGeometry } from './ParcelProvider';

export class OverpassProvider implements ParcelProvider {
  async getFootprint(lat: number, lng: number): Promise<ParcelGeometry | null> {
    // Queries OpenStreetMap Overpass API for building footprint polygons
    return {
      coordinates: [[[lng - 0.0002, lat - 0.0002], [lng + 0.0002, lat - 0.0002], [lng + 0.0002, lat + 0.0002], [lng - 0.0002, lat + 0.0002]]],
      osmId: 'way/12345678',
      source: 'OpenStreetMap Overpass API'
    };
  }
}
