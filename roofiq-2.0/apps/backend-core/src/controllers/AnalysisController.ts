import { Response } from 'express';
import { AuthenticatedRequest } from '../middleware';
import { AnalysisService } from '../services/AnalysisService';
import { PropertyService } from '../services/PropertyService';

export class AnalysisController {
  private analysisService: AnalysisService;
  private propertyService: PropertyService;

  constructor() {
    this.analysisService = new AnalysisService();
    this.propertyService = new PropertyService();
  }

  analyze = async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    try {
      const { id } = req.params; // property id
      const property = await this.propertyService.getProperty(id);
      if (!property) {
        res.status(404).json({ success: false, message: 'Property not found' });
        return;
      }
      
      const result = await this.analysisService.queueAnalysis(
        property.id,
        Number(property.latitude),
        Number(property.longitude),
        req.userId
      );
      res.status(202).json(result);
    } catch (error: any) {
      res.status(500).json({ success: false, error: error.message });
    }
  };

  updateMeasurements = async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    try {
      const { analysisId } = req.params;
      const { roofAreaSqft, pitchDeg } = req.body;
      const updated = await this.analysisService.editMeasurement(
        analysisId,
        Number(roofAreaSqft),
        Number(pitchDeg),
        req.userId
      );
      res.status(200).json(updated);
    } catch (error: any) {
      res.status(500).json({ success: false, error: error.message });
    }
  };
}

export const analysisController = new AnalysisController();
