import { Request, Response, NextFunction } from 'express';
import { auditQueue } from '../queues/QueueManager';

export interface AuthenticatedRequest extends Request {
  tenantId?: string;
  userId?: string;
}

export function authMiddleware(req: AuthenticatedRequest, res: Response, next: NextFunction) {
  // Better Auth token headers validation simulation
  const tenantId = req.headers['x-tenant-id'] || '00000000-0000-0000-0000-000000000000';
  const userId = req.headers['x-user-id'] || '11111111-1111-1111-1111-111111111111';

  req.tenantId = tenantId as string;
  req.userId = userId as string;
  next();
}

export async function logAuditEvent(eventType: string, details: any, userId?: string) {
  try {
    await auditQueue.add('log-audit', {
      eventType, // User Login, Measurement Edited, Proposal Generated, Permit Retrieved, AI Used, PDF Exported
      userId: userId || 'system',
      details,
      timestamp: new Date()
    });
    console.log(`[Audit Logged] ${eventType}`);
  } catch (error) {
    console.error('Failed to log audit event:', error);
  }
}

export function errorHandler(err: any, req: Request, res: Response, next: NextFunction) {
  console.error('[Error Handler]', err);
  res.status(err.status || 500).json({
    success: false,
    message: err.message || 'Internal Server Error'
  });
}
