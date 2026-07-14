export class WasteFactorCalculator {
  calculateWasteSquares(baseAreaSqft: number, complexity: 'Simple' | 'Moderate' | 'Complex'): number {
    // Standard roofing waste factor estimation
    const wasteRates = {
      Simple: 0.10, // 10%
      Moderate: 0.15, // 15%
      Complex: 0.20 // 20%
    };
    const rate = wasteRates[complexity] || 0.15;
    return (baseAreaSqft * (1 + rate)) / 100;
  }
}
