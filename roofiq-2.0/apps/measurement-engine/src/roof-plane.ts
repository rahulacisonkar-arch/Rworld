export interface Point3D {
  x: number;
  y: number;
  z: number;
}

export class RoofPlaneCalculator {
  calculatePlaneArea(vertices: Point3D[]): number {
    // Calculates surface area of a 3D polygon plane using vector cross products
    if (vertices.length < 3) return 0;
    
    let totalX = 0;
    let totalY = 0;
    let totalZ = 0;
    
    for (let i = 0; i < vertices.length; i++) {
      const p1 = vertices[i];
      const p2 = vertices[(i + 1) % vertices.length];
      
      totalX += (p1.y - p2.y) * (p1.z + p2.z);
      totalY += (p1.z - p2.z) * (p1.x + p2.x);
      totalZ += (p1.x - p2.x) * (p1.y + p2.y);
    }
    
    const magnitude = Math.sqrt(totalX * totalX + totalY * totalY + totalZ * totalZ);
    return magnitude / 2.0;
  }
}
