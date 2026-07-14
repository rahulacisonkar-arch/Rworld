import { AnalysisRepository } from '../repositories/AnalysisRepository';
import { eventDispatcher } from '../events/EventDispatcher';
import { logAuditEvent } from '../middleware';

export class AnalysisService {
  private analysisRepo: AnalysisRepository;

  constructor() {
    this.analysisRepo = new AnalysisRepository();
  }

  async queueAnalysis(propertyId: string, latitude: number, longitude: number, analyzedById?: string): Promise<any> {
    // 1. Create a placeholder/initial analysis record
    const analysis = await this.analysisRepo.create({
      propertyId,
      analyzedById,
      roofAreaSqft: 0 as any,
      pitchDeg: 0 as any,
      conditionScore: 10,
      floodZone: 'X',
      elevationFt: 0 as any,
      peakWindSpeed: 0 as any,
      solarYieldKwhYr: 0 as any,
      aiRawPayload: {}
    });

    // 2. Dispatch events that trigger weather, solar, OCR, permit, and AI in sequence
    eventDispatcher.emit('ROOF_ANALYSIS_COMPLETE', {
      propertyId,
      latitude,
      longitude,
      imageUrl: `/static/maps/${propertyId}.jpg`
    });

    // 3. Log Audit Event
    await logAuditEvent('AI Used', { propertyId, analysisId: analysis.id }, analyzedById);

    return {
      success: true,
      analysisId: analysis.id,
      status: 'Queued'
    };
  }

  async getAnalysis(id: string): Promise<any> {
    return this.analysisRepo.findById(id);
  }

  async editMeasurement(id: string, newAreaSqft: number, pitch: number, userId?: string): Promise<any> {
    const updated = await this.analysisRepo.update(id, {
      roofAreaSqft: newAreaSqft as any,
      pitchDeg: pitch as any
    });

    await logAuditEvent('Measurement Edited', { analysisId: id, newAreaSqft, pitch }, userId);
    return updated;
  }
}
