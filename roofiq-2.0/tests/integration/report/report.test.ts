import { reportService } from '../../../apps/backend-core/src/reporting/ReportService';

describe('Report Service Integration Test', () => {
  it('should compile proposal and format versioned keys', async () => {
    const report = await reportService.generateReport(
      'analysis-123',
      'Proposal',
      'tenant-1',
      'user-1',
      'v2'
    );
    expect(report.version).toBe('v2');
    expect(report.minioKey).toContain('exports/v2/proposal-');
    expect(report.url).toContain('proposal-');
  });
});
