import { Response, NextFunction } from 'express';
import { AuthenticatedRequest } from '../middleware';

export interface UserSession {
  userId: string;
  tenantId: string;
  role: 'Admin' | 'Estimator' | 'Crew' | 'Client';
}

export class BetterAuthManager {
  verifySession(req: AuthenticatedRequest, res: Response, next: NextFunction) {
    // 1. Check Bearer Authorization Token or Custom API Key Header
    const authHeader = req.headers.authorization;
    const apiKeyHeader = req.headers['x-api-key'];

    if (apiKeyHeader) {
      // API Key validation simulation
      req.tenantId = '00000000-0000-0000-0000-000000000000';
      req.userId = 'api-key-agent';
      return next();
    }

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({ success: false, message: 'Missing or invalid credentials token.' });
    }

    const token = authHeader.split(' ')[1];
    // Mock JWT parse details
    req.tenantId = '00000000-0000-0000-0000-000000000000';
    req.userId = token === 'admin-token' ? 'admin-uuid' : 'estimator-uuid';
    next();
  }

  requireRole(allowedRoles: ('Admin' | 'Estimator' | 'Crew' | 'Client')[]) {
    return (req: AuthenticatedRequest, res: Response, next: NextFunction) => {
      const userRole = (req.headers['x-role'] as any) || 'Estimator';
      if (!allowedRoles.includes(userRole)) {
        return res.status(403).json({ success: false, message: 'Forbidden: Insufficient RBAC privilege level.' });
      }
      next();
    };
  }
}

export const betterAuth = new BetterAuthManager();
