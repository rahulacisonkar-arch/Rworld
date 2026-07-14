export class PitchCalculator {
  getPitchFromAngle(degrees: number): string {
    // Converts slope angle into construction rise/run (e.g. 4/12)
    const radians = (degrees * Math.PI) / 180;
    const rise = Math.round(Math.tan(radians) * 12);
    return `${rise}/12`;
  }

  getSlopeMultiplier(degrees: number): number {
    const radians = (degrees * Math.PI) / 180;
    return 1.0 / Math.cos(radians);
  }
}
