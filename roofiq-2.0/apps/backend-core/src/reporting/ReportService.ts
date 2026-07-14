import { auditLogger } from '../audit/AuditLogger';

export interface GeneratedReport {
  reportId: string;
  type: 'Proposal' | 'Measurement' | 'Permit' | 'Solar' | 'Inspection';
  version: 'v1' | 'v2' | 'v3';
  minioKey: string;
  url: string;
}

export class ReportService {
  async generateReport(
    analysisId: string, 
    type: 'Proposal' | 'Measurement' | 'Permit' | 'Solar' | 'Inspection',
    tenantId: string,
    userId: string,
    version: 'v1' | 'v2' | 'v3' = 'v1' // Object Versioning parameter support
  ): Promise<GeneratedReport> {
    const reportId = Math.random().toString(36).substring(7);
    
    // Construct versioned MinIO storage key path
    // Format: reports/[v1|v2|v3]/[type]-[analysisId]-[reportId].pdf
    let minioKey = '';
    if (type === 'Proposal') {
      minioKey = `exports/${version}/proposal-${analysisId}-${reportId}.pdf`;
    } else {
      // Versioned reports folder structure: reports/v1/, reports/v2/, reports/v3/
      minioKey = `reports/${version}/${type.toLowerCase()}-${analysisId}-${reportId}.pdf`;
    }

    const reportUrl = `http://minio:9000/roofiq-bucket/${minioKey}`;

    // Audit trace
    await auditLogger.log({
      eventType: 'PDF Exported',
      userId,
      tenantId,
      details: { analysisId, type, version, minioKey, reportId }
    });

    return {
      reportId,
      type,
      version,
      minioKey,
      url: reportUrl
    };
  }
}

export const reportService = new ReportService();
