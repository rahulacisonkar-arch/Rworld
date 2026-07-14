import { Point3D } from './roof-plane';

export class EaveCalculator {
  calculateEaveLength(points: Point3D[][]): number {
    // Calculates total linear feet of lower eaves (drip edges)
    let totalLength = 0;
    for (const segment of points) {
      if (segment.length >= 2) {
        const p1 = segment[0];
        const p2 = segment[1];
        // Eaves are typically level (Z is same) and at the lowest bound
        if (Math.abs(p1.z - p2.z) < 0.1) {
          totalLength += Math.sqrt(
            Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2)
          );
        }
      }
    }
    return totalLength;
  }
}
