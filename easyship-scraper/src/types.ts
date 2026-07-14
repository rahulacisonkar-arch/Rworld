export interface ShipmentData {
  vendorsStores: string;
  date: string;
  trackingNo: string;
  trackingNo2: string;
  refNo1: string;
  refNo2: string;
  receiver: string;
  upsCharge: string;
  deliveryDate: string;
  shippingFrom: string;
  weight: string;
  dimension: string;
  freightChargeFromCmr: string;
  receiverCity?: string;
  senderCity?: string;
}

export interface ProgressState {
  currentPage: number;
  currentShipmentIndex: number;
  lastTrackingNumber: string;
  totalProcessed: number;
  totalFailed: number;
  timestamp: string;
}
