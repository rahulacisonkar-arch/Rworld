import { prisma } from '../config/prisma';
import { Permit } from '../types';

export class PermitRepository {
  async create(data: Omit<Permit, 'id'>): Promise<any> {
    return prisma.permit.create({
      data: {
        propertyId: data.propertyId,
        permitNumber: data.permitNumber,
        issueDate: data.issueDate,
        status: data.status,
        description: data.description,
        contractorName: data.contractorName
      }
    });
  }

  async findByPropertyId(propertyId: string): Promise<any[]> {
    return prisma.permit.findMany({
      where: { propertyId }
    });
  }
}
