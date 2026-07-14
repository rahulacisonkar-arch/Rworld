import { prisma } from '../config/prisma';
import { RoofAnalysis } from '../types';

export class AnalysisRepository {
  async create(data: Omit<RoofAnalysis, 'id' | 'createdAt'>): Promise<any> {
    return prisma.roofAnalysis.create({
      data: {
        propertyId: data.propertyId,
        analyzedById: data.analyzedById,
        roofAreaSqft: data.roofAreaSqft,
        pitchDeg: data.pitchDeg,
        conditionScore: data.conditionScore,
        floodZone: data.floodZone,
        elevationFt: data.elevationFt,
        peakWindSpeed: data.peakWindSpeed,
        solarYieldKwhYr: data.solarYieldKwhYr,
        aiRawPayload: data.aiRawPayload
      }
    });
  }

  async findById(id: string): Promise<any> {
    return prisma.roofAnalysis.findUnique({
      where: { id },
      include: {
        property: true,
        takeoffs: true
      }
    });
  }

  async update(id: string, data: Partial<RoofAnalysis>): Promise<any> {
    return prisma.roofAnalysis.update({
      where: { id },
      data
    });
  }

  async createTakeoffs(analysisId: string, items: any[]): Promise<any> {
    return prisma.$transaction(
      items.map(item => prisma.takeoff.create({
        data: {
          analysisId,
          materialName: item.materialName,
          category: item.category,
          quantity: item.quantity,
          unit: item.unit,
          unitCost: item.unitCost,
          totalCost: item.totalCost
        }
      }))
    );
  }
}
