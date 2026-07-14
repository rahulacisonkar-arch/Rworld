import { Request, Response, NextFunction } from 'express';
import crypto from 'crypto';

// Rate Limiter implementation
const requestCounts = new Map<string, { count: number; resetTime: number }>();

export function rateLimiter(limit: number, windowMs: number) {
  return (req: Request, res: Response, next: NextFunction) => {
    const ip = req.ip || 'unknown';
    const now = Date.now();
    const clientData = requestCounts.get(ip);

    if (!clientData || now > clientData.resetTime) {
      requestCounts.set(ip, { count: 1, resetTime: now + windowMs });
      return next();
    }

    clientData.count += 1;
    if (clientData.count > limit) {
      return res.status(429).json({
        success: false,
        message: 'Too many requests. Please slow down and try again later.'
      });
    }
    next();
  };
}

// CSRF validation simulation
export function csrfProtection(req: Request, res: Response, next: NextFunction) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
    return next();
  }

  const csrfTokenHeader = req.headers['x-csrf-token'];
  const csrfTokenCookie = req.headers['x-csrf-cookie'];

  if (!csrfTokenHeader || csrfTokenHeader !== csrfTokenCookie) {
    return res.status(403).json({
      success: false,
      message: 'CSRF token mismatch validation error.'
    });
  }
  next();
}

// Encrypt and decrypt secrets management helper
const ALGORITHM = 'aes-256-cbc';
const SECRET_KEY = crypto.scryptSync(process.env.ENCRYPTION_KEY || 'roofiq-default-secret-key-32bytes', 'salt', 32);

export function encrypt(text: string): string {
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv(ALGORITHM, SECRET_KEY, iv);
  let encrypted = cipher.update(text, 'utf8', 'hex');
  encrypted += cipher.final('hex');
  return `${iv.toString('hex')}:${encrypted}`;
}

export function decrypt(encryptedText: string): string {
  const [ivHex, encrypted] = encryptedText.split(':');
  const iv = Buffer.from(ivHex, 'hex');
  const decipher = crypto.createDecipheriv(ALGORITHM, SECRET_KEY, iv);
  let decrypted = decipher.update(encrypted, 'hex', 'utf8');
  decrypted += decipher.final('utf8');
  return decrypted;
}
