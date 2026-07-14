import { Response } from 'express';
import { AuthenticatedRequest } from '../middleware';
import { reportService } from '../reporting/ReportService';

export class ReportController {
  generate = async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    try {
      const { analysisId } = req.params;
      const { type } = req.body; // 'Proposal', 'Measurement', 'Permit', 'Solar', 'Inspection'
      const tenantId = req.tenantId || '00000000-0000-0000-0000-000000000000';
      const userId = req.userId || 'system';

      const result = await reportService.generateReport(
        analysisId,
        type || 'Proposal',
        tenantId,
        userId
      );
      res.status(201).json(result);
    } catch (error: any) {
      res.status(500).json({ success: false, error: error.message });
    }
  };
}

export const reportController = new ReportController();
