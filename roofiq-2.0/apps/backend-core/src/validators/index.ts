import { z } from 'zod';

export const CreatePropertySchema = z.object({
  tenantId: z.string().uuid({ message: 'Valid Tenant ID is required' }),
  address: z.string().min(5, { message: 'Address must be at least 5 characters long' })
});

export const RunAnalysisSchema = z.object({
  imageUrl: z.string().url().optional(),
  bbox: z.array(z.number()).length(4).optional()
});
