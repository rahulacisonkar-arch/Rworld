import { prisma } from '../config/prisma';
import { Property } from '../types';

export class PropertyRepository {
  async create(data: Omit<Property, 'id' | 'createdAt'>): Promise<any> {
    return prisma.property.create({
      data: {
        tenantId: data.tenantId,
        address: data.address,
        formattedAddress: data.formattedAddress,
        latitude: data.latitude,
        longitude: data.longitude,
        ownerName: data.ownerName,
        yearBuilt: data.yearBuilt,
        lotSizeSqft: data.lotSizeSqft,
        buildingSizeSqft: data.buildingSizeSqft,
        assessedValue: data.assessedValue
      }
    });
  }

  async findById(id: string): Promise<any> {
    return prisma.property.findUnique({
      where: { id },
      include: {
        analyses: true,
        permits: true
      }
    });
  }

  async findAll(tenantId: string, query?: string): Promise<any[]> {
    return prisma.property.findMany({
      where: {
        tenantId,
        ...(query ? {
          address: {
            contains: query,
            mode: 'insensitive'
          }
        } : {})
      },
      orderBy: {
        createdAt: 'desc'
      }
    });
  }

  async update(id: string, data: Partial<Property>): Promise<any> {
    return prisma.property.update({
      where: { id },
      data
    });
  }
}
