export interface PermitRecord {
  permitNumber: string;
  issueDate: Date;
  status: string;
  description: string;
  contractor: string;
}

export interface PermitProvider {
  lookupPermits(address: string, lat: number, lng: number): Promise<PermitRecord[]>;
}
