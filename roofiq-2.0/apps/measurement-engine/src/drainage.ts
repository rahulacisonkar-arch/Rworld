export interface DrainageRequirement {
  recommendedGutterSizeInches: number;
  downspoutCountNeeded: number;
}

export class DrainageCalculator {
  calculateRequirements(roofAreaSqft: number, rainfallIntensityInHr: number = 4.0): DrainageRequirement {
    // Calculates gutter sizing parameters
    const runOffVolume = roofAreaSqft * (rainfallIntensityInHr / 12);
    const recommendedGutterSizeInches = roofAreaSqft > 5000 ? 6 : 5;
    const downspoutCountNeeded = Math.max(1, Math.ceil(roofAreaSqft / 1000));
    
    return {
      recommendedGutterSizeInches,
      downspoutCountNeeded
    };
  }
}
