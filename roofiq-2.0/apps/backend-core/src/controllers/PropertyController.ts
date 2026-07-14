import { Response } from 'express';
import { AuthenticatedRequest } from '../middleware';
import { PropertyService } from '../services/PropertyService';
import { CreatePropertySchema } from '../validators';

export class PropertyController {
  private propertyService: PropertyService;

  constructor() {
    this.propertyService = new PropertyService();
  }

  list = async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    try {
      const tenantId = req.tenantId || '';
      const query = req.query.query as string;
      const list = await this.propertyService.listProperties(tenantId, query);
      res.status(200).json(list);
    } catch (error: any) {
      res.status(500).json({ success: false, error: error.message });
    }
  };

  create = async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    try {
      const tenantId = req.tenantId || '';
      const payload = CreatePropertySchema.parse({ ...req.body, tenantId });
      const property = await this.propertyService.resolveProperty(tenantId, payload.address);
      res.status(201).json(property);
    } catch (error: any) {
      res.status(400).json({ success: false, error: error.errors || error.message });
    }
  };

  get = async (req: AuthenticatedRequest, res: Response): Promise<void> => {
    try {
      const { id } = req.params;
      const property = await this.propertyService.getProperty(id);
      if (!property) {
        res.status(404).json({ success: false, message: 'Property not found' });
        return;
      }
      res.status(200).json(property);
    } catch (error: any) {
      res.status(500).json({ success: false, error: error.message });
    }
  };
}
export const propertyController = new PropertyController();
