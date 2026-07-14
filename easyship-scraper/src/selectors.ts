export const SELECTORS = {
  // Login Page
  loginEmailInput: 'input[type="email"], input[name="email"], [placeholder*="Email"]',
  loginPasswordInput: 'input[type="password"], input[name="password"], [placeholder*="Password"]',
  loginSubmitButton: 'button[type="submit"], button:has-text("Login"), button:has-text("Sign In")',

  // Shipments List Page
  shipmentsTable: '[role="table"]',
  shipmentRow: '[role="table"] div[role="row"]',
  shipmentLinkInRow: '[role="cell"] a:has-text("ESUS"), td a:has-text("ESUS"), a:has-text("ESUS")',
  
  // Pagination
  nextPageButton: 'button:has(svg path[d*="M6.653 15.607"]), button[aria-label="Next page"], button:has-text("Next"), .pagination-next',
  prevPageButton: 'button:has(svg path[d*="M13.346 4.393"]), button[aria-label="Previous page"], button:has-text("Previous"), .pagination-prev',
  currentPageIndicator: '.pagination-active, [class*="active"] button, .current-page',

  // Shipment Details Sidebar / Page
  detailsContainer: '.modal:has-text("Shipment ID:"), .modal-content:has-text("Shipment ID:")',
  closeDetailsButton: '.modal .close, button.close, button[aria-label="Close"], [class*="close"]',

  // Specific detail label/value container selectors
  detailItem: '.detail-item, .info-row, tr, div',
  detailLabel: '.label, .title, td:first-child, span:first-child',
  detailValue: '.value, .content, td:last-child, span:last-child',

  // Fallback direct selectors for fields
  fields: {
    vendorStore: 'text=/Store|Vendor|Shop/i',
    date: 'text=/Created At|Date/i',
    trackingNo: 'text=/Tracking Number|Tracking No/i',
    trackingNo2: 'text=/Alternative Tracking|Secondary Tracking/i',
    refNo1: 'text=/Reference 1|Ref No 1/i',
    refNo2: 'text=/Reference 2|Ref No 2/i',
    receiver: 'text=/Receiver|Recipient|Ship To/i',
    upsCharge: 'text=/UPS Charge|Courier Charge/i',
    deliveryDate: 'text=/Delivery Date|Estimated Delivery/i',
    shippingFrom: 'text=/Sender|Ship From|Shipping From/i',
    weight: 'text=/Weight/i',
    dimension: 'text=/Dimensions|Dimension/i',
    freightChargeFromCmr: 'text=/Freight Charge|CMR/i',
  }
};
