import axios from 'axios';

export interface GeocodedAddress {
  address: string;
  latitude: number;
  longitude: number;
  countyName?: string;
  stateCode?: string;
}

export class CensusClient {
  async geocode(address: string): Promise<GeocodedAddress> {
    try {
      const encodedAddress = encodeURIComponent(address);
      const url = `https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?address=${encodedAddress}&benchmark=Public_AR_Current&format=json`;
      const response = await axios.get(url, { timeout: 8000 });
      
      if (response.data && response.data.result && response.data.result.addressMatches.length > 0) {
        const match = response.data.result.addressMatches[0];
        return {
          address: match.matchedAddress,
          latitude: match.coordinates.y,
          longitude: match.coordinates.x,
          countyName: match.addressComponents?.county || 'Unknown County',
          stateCode: match.addressComponents?.state || 'US'
        };
      }
      throw new Error('Address not found in Census registry');
    } catch (error) {
      console.error('Census geocoding error:', error);
      throw error;
    }
  }
}
