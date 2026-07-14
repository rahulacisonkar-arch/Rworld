import { Point3D } from './roof-plane';

export class HipCalculator {
  calculateHipLength(points: Point3D[][]): number {
    // Calculates total linear feet of hip lines (outer sloping joints)
    let totalLength = 0;
    for (const segment of points) {
      if (segment.length >= 2) {
        const p1 = segment[0];
        const p2 = segment[1];
        // Sloping check
        if (Math.abs(p1.z - p2.z) >= 0.5) {
          totalLength += Math.sqrt(
            Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2) + Math.pow(p2.z - p1.z, 2)
          );
        }
      }
    }
    return totalLength;
  }
}
