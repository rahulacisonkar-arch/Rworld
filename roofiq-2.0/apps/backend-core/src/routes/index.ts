import { Router, Request, Response } from 'express';
import { propertyController } from '../controllers/PropertyController';
import { analysisController } from '../controllers/AnalysisController';
import { reportController } from '../controllers/ReportController';
import { prisma } from '../database/PrismaConnection';

const router = Router();

// Standard Orchestration Health Checks
router.get('/health', (req: Request, res: Response) => {
  res.status(200).json({ status: 'healthy', timestamp: new Date() });
});

router.get('/ready', async (req: Request, res: Response) => {
  try {
    // Check database connection responsiveness
    await prisma.$queryRaw`SELECT 1`;
    res.status(200).json({ status: 'ready', database: 'connected' });
  } catch (error: any) {
    res.status(503).json({ status: 'not ready', error: error.message });
  }
});

router.get('/live', (req: Request, res: Response) => {
  res.status(200).json({ status: 'live' });
});

router.get('/version', (req: Request, res: Response) => {
  res.status(200).json({ version: '2.0.0', service: 'roofiq-core-backend' });
});

// 1. Property Router Endpoints (/api/v1/property)
router.get('/property', propertyController.list);
router.post('/property', propertyController.create);
router.get('/property/:id', propertyController.get);

// 2. Analysis Router Endpoints (/api/v1/analysis)
router.post('/analysis/:id', analysisController.analyze);
router.put('/analysis/:analysisId/measurements', analysisController.updateMeasurements);

// 3. Report Router Endpoints (/api/v1/report)
router.post('/report/:analysisId', reportController.generate);

export default router;
