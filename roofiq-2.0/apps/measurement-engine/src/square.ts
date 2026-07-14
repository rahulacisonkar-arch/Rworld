export class SquareConverter {
  sqftToSquares(sqft: number): number {
    return sqft / 100;
  }

  squaresToSqft(squares: number): number {
    return squares * 100;
  }
}
