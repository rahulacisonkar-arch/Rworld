import axios from 'axios';

export interface FemaFloodZone {
  floodZone: string;
  isSpecialFloodHazardArea: boolean;
  mapPanelNumber?: string;
}

export class FemaClient {
  async getFloodZone(lat: number, lng: number): Promise<FemaFloodZone> {
    try {
      // Query USGS / FEMA ArcGIS REST Services
      const url = `https://hazards.fema.gov/gis/nfhl/rest/services/public/NFHL/MapServer/28/query?geometry=${lng},${lat}&geometryType=esriGeometryPoint&spatialRel=esriSpatialRelIntersects&outFields=FLD_ZONE,PANEL_TYP&f=json`;
      const response = await axios.get(url, { timeout: 5000 });
      
      if (response.data && response.data.features && response.data.features.length > 0) {
        const attributes = response.data.features[0].attributes;
        const floodZone = attributes.FLD_ZONE || 'X';
        const isSpecialFloodHazardArea = !['X', 'C', 'B'].includes(floodZone);
        return {
          floodZone,
          isSpecialFloodHazardArea,
          mapPanelNumber: attributes.PANEL_TYP
        };
      }
      return { floodZone: 'X', isSpecialFloodHazardArea: false };
    } catch (error) {
      console.warn('FEMA API check failed, defaulting to low-risk Zone X:', error);
      return { floodZone: 'X', isSpecialFloodHazardArea: false };
    }
  }
}
