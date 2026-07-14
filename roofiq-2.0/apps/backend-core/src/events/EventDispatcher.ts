import { EventEmitter } from 'events';
import { weatherQueue, solarQueue, ocrQueue, permitQueue, samQueue, reportQueue } from '../queues/QueueManager';

class RoofIQEventDispatcher extends EventEmitter {
  constructor() {
    super();
    this.setupListeners();
  }

  private setupListeners() {
    // 1. When Roof Analysis is complete, trigger weather fetching
    this.on('ROOF_ANALYSIS_COMPLETE', async (data: { propertyId: string; latitude: number; longitude: number; imageUrl?: string }) => {
      console.log(`[Event] ROOF_ANALYSIS_COMPLETE triggered for Property: ${data.propertyId}`);
      
      // Dispatch Weather Job
      await weatherQueue.add('fetch-weather', {
        propertyId: data.propertyId,
        latitude: data.latitude,
        longitude: data.longitude
      });
      this.emit('WEATHER_TRIGGERED', data);
    });

    // 2. Weather trigger propagates to Solar Calculations
    this.on('WEATHER_TRIGGERED', async (data) => {
      console.log(`[Event] WEATHER_TRIGGERED propagating to solar recalculations`);
      await solarQueue.add('calc-yield', {
        propertyId: data.propertyId,
        latitude: data.latitude,
        longitude: data.longitude
      });
      this.emit('SOLAR_TRIGGERED', data);
    });

    // 3. Solar yield leads to OCR documentation parse
    this.on('SOLAR_TRIGGERED', async (data) => {
      console.log(`[Event] SOLAR_TRIGGERED propagating to OCR blueprints scan`);
      await ocrQueue.add('parse-shingles', {
        propertyId: data.propertyId,
        documentUrl: `/static/uploads/${data.propertyId}/blueprint.png`
      });
      this.emit('OCR_TRIGGERED', data);
    });

    // 4. OCR scan triggers building Permit registry checking
    this.on('OCR_TRIGGERED', async (data) => {
      console.log(`[Event] OCR_TRIGGERED propagating to permit lookup`);
      await permitQueue.add('check-permits', {
        propertyId: data.propertyId
      });
      this.emit('PERMIT_TRIGGERED', data);
    });

    // 5. Permit check triggers final AI estimation pipelines
    this.on('PERMIT_TRIGGERED', async (data) => {
      console.log(`[Event] PERMIT_TRIGGERED propagating to AI model segmenter`);
      await samQueue.add('run-sam', {
        propertyId: data.propertyId,
        imageUrl: data.imageUrl || `/static/maps/${data.propertyId}.jpg`,
        bbox: [0, 0, 512, 512]
      });
      this.emit('AI_TRIGGERED', data);
    });

    // 6. AI segmentation outputs trigger proposal PDF report generations
    this.on('AI_TRIGGERED', async (data) => {
      console.log(`[Event] AI_TRIGGERED propagating to report builder`);
      await reportQueue.add('generate-pdf-reports', {
        propertyId: data.propertyId
      });
      this.emit('REPORT_GENERATION_QUEUED', data);
    });
  }
}

export const eventDispatcher = new RoofIQEventDispatcher();
export type EventType = 
  | 'ROOF_ANALYSIS_COMPLETE'
  | 'WEATHER_TRIGGERED'
  | 'SOLAR_TRIGGERED'
  | 'OCR_TRIGGERED'
  | 'PERMIT_TRIGGERED'
  | 'AI_TRIGGERED'
  | 'REPORT_GENERATION_QUEUED';
